import { useEffect, useMemo, useRef, useState } from 'react';
import { ActivityIndicator, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as FileSystem from 'expo-file-system/legacy';
import {
  RecordingPresets,
  requestRecordingPermissionsAsync,
  setAudioModeAsync,
  useAudioPlayer,
  useAudioRecorder,
} from 'expo-audio';
import { storageUrl } from '@/api/client';
import {
  getRadioFeed,
  startRadioTransmission,
  stopRadioTransmission,
  uploadRadioClip,
} from '@/api/rescuer';
import { palette, radius, shadow, spacing } from '@/constants/resqTheme';
import { SectionHeader, StatusBadge } from './RescuerUI';

type RadioProps = {
  overview: any;
};

type RadioLog = {
  id: number | string;
  type: string;
  type_label: string;
  transmission_id?: string | null;
  duration_seconds: number;
  responder_id?: number | string | null;
  responder_name: string;
  responder_code?: string | null;
  team_name: string;
  audio_path?: string | null;
  audio_url?: string | null;
  message: string;
  timestamp: string;
  raw_timestamp?: string;
};

type TeamMember = {
  responder_id: number | string;
  responder_code?: string | null;
  full_name: string;
  initials: string;
  team_name?: string | null;
  is_self?: boolean;
  is_transmitting?: boolean;
};

type ActiveTransmission = {
  transmission_id: string;
  responder_id?: number | string | null;
  responder_name: string;
  team_name: string;
  is_self: boolean;
  last_seen_at: string;
};

export function RadioCommunicationScreen({ overview }: RadioProps) {
  const recorder = useAudioRecorder(RecordingPresets.HIGH_QUALITY);
  const player = useAudioPlayer(null);
  const [isRecording, setIsRecording] = useState(false);
  const [recordSeconds, setRecordSeconds] = useState(0);
  const [logs, setLogs] = useState<RadioLog[]>([]);
  const [teamMembers, setTeamMembers] = useState<TeamMember[]>([]);
  const [activeTransmission, setActiveTransmission] = useState<ActiveTransmission | null>(null);
  const [currentTransmissionId, setCurrentTransmissionId] = useState('');
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(false);
  const [isLoadingLogs, setIsLoadingLogs] = useState(true);
  const [isActionLoading, setIsActionLoading] = useState(false);
  const [nowPlayingId, setNowPlayingId] = useState<string | number | null>(null);
  const [playedVersion, setPlayedVersion] = useState(0);
  const [error, setError] = useState('');
  const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const secondsRef = useRef(0);
  const playedLogIdsRef = useRef<Set<string | number>>(new Set());

  const profile = overview?.profile || {};
  const responder = profile.responder || {};
  const activeAssignment = useMemo(
    () =>
      (overview?.assignments || []).find((assignment: any) =>
        ['dispatched', 'accepted', 'en_route', 'on_scene'].includes(assignment.status_key)
      ),
    [overview?.assignments]
  );
  const activeAssignmentId = Number(activeAssignment?.assignment_id);

  const membersWithQueues = teamMemberQueues(
    teamMembers,
    logs,
    responder,
    activeTransmission,
    playedLogIdsRef.current,
    playedVersion
  );

  useEffect(() => {
    setAudioModeAsync({
      playsInSilentMode: true,
      allowsRecording: false,
      shouldPlayInBackground: false,
      shouldRouteThroughEarpiece: false,
      interruptionMode: 'doNotMix',
    }).catch(() => undefined);
  }, []);

  useEffect(() => {
    loadRadioFeed(1, true);

    const feedTimer = setInterval(() => {
      loadRadioFeed(1, true, true);
    }, 4000);

    return () => {
      clearInterval(feedTimer);
      clearRecordTimer();
    };
  }, []);

  async function loadRadioFeed(nextPage = 1, replace = true, silent = false) {
    if (!silent && replace) {
      setIsLoadingLogs(true);
    }

    try {
      const data = await getRadioFeed({
        channel: 'team',
        page: nextPage,
        per_page: 20,
      });
      const nextLogs = data?.logs?.data || [];

      setLogs((current) => (replace ? nextLogs : [...current, ...nextLogs]));
      setTeamMembers(data?.team_members || []);
      setActiveTransmission(data?.active_transmission || null);
      setPage(data?.logs?.current_page || nextPage);
      setHasMore(Boolean(data?.logs?.has_more));
      setError('');
    } catch (radioError: any) {
      setError(radioError?.response?.data?.message || 'Radio feed cannot be loaded.');
    } finally {
      if (!silent && replace) {
        setIsLoadingLogs(false);
      }
    }
  }

  async function playMemberNextClip(member: TeamMember) {
    const memberId = String(member.responder_id);
    const nextClip = logs
      .filter((log) =>
        log.type === 'ptt_audio'
        && String(log.responder_id) === memberId
        && !playedLogIdsRef.current.has(log.id)
      )
      .slice()
      .reverse()[0];

    if (!nextClip) {
      return;
    }

    await playClip(nextClip);
  }

  async function playClip(log: RadioLog) {
    const remoteUrl = log.audio_path ? storageUrl(log.audio_path) : log.audio_url;

    if (!remoteUrl) {
      return;
    }

    try {
      setError('');
      setNowPlayingId(log.id);
      await setAudioModeAsync({
        playsInSilentMode: true,
        allowsRecording: false,
        shouldPlayInBackground: false,
        shouldRouteThroughEarpiece: false,
        interruptionMode: 'doNotMix',
      });
      const localUri = await cachedAudioUri(log, remoteUrl);
      player.replace({ uri: localUri });
      player.play();
      playedLogIdsRef.current.add(log.id);
      setPlayedVersion((current) => current + 1);
    } catch {
      setError('Voice message cannot be played. Check Wi-Fi and audio format.');
      setNowPlayingId(null);
    }
  }

  async function cachedAudioUri(log: RadioLog, remoteUrl: string) {
    if (!FileSystem.cacheDirectory) {
      return remoteUrl;
    }

    const extension = audioExtension(log, remoteUrl);
    const fileUri = `${FileSystem.cacheDirectory}resq-radio-${log.id}.${extension}`;
    const fileInfo = await FileSystem.getInfoAsync(fileUri);

    if (fileInfo.exists) {
      return fileUri;
    }

    const downloaded = await FileSystem.downloadAsync(remoteUrl, fileUri);

    if (downloaded.status < 200 || downloaded.status >= 300) {
      throw new Error('Audio download failed.');
    }

    return downloaded.uri;
  }

  function audioExtension(log: RadioLog, remoteUrl: string) {
    const source = log.audio_path || remoteUrl;
    const extension = source.split('?')[0].split('.').pop()?.toLowerCase() || 'm4a';
    const supported = ['m4a', 'mp4', 'aac', 'mp3', 'wav'];

    return supported.includes(extension) ? extension : 'm4a';
  }

  function clearRecordTimer() {
    if (timerRef.current) {
      clearInterval(timerRef.current);
      timerRef.current = null;
    }
  }

  function startLocalTimer() {
    secondsRef.current = 0;
    setRecordSeconds(0);
    timerRef.current = setInterval(() => {
      secondsRef.current += 1;
      setRecordSeconds(secondsRef.current);
    }, 1000);
  }

  async function startRecording() {
    if (isRecording || isActionLoading) {
      return;
    }

    setIsActionLoading(true);

    try {
      const permission = await requestRecordingPermissionsAsync();

      if (!permission.granted) {
        setError('Microphone permission is required for team radio.');
        return;
      }

      await setAudioModeAsync({
        playsInSilentMode: true,
        allowsRecording: true,
        shouldPlayInBackground: false,
        shouldRouteThroughEarpiece: false,
        interruptionMode: 'doNotMix',
      });
      await recorder.prepareToRecordAsync();
      recorder.record();

      const data = await startRadioTransmission({
        channel: 'team',
        assignment_id: Number.isFinite(activeAssignmentId) ? activeAssignmentId : null,
      });

      setCurrentTransmissionId(data?.transmission_id || '');
      setActiveTransmission(data?.active_transmission || null);
      setIsRecording(true);
      startLocalTimer();
      setError('');
    } catch (radioError: any) {
      setError(radioError?.response?.data?.message || 'Voice recording cannot start.');
    } finally {
      setIsActionLoading(false);
    }
  }

  async function stopRecordingAndSend() {
    if (!isRecording || isActionLoading) {
      return;
    }

    setIsActionLoading(true);
    clearRecordTimer();

    const duration = secondsRef.current;
    const transmissionId = currentTransmissionId;
    secondsRef.current = 0;
    setRecordSeconds(0);
    setIsRecording(false);
    setCurrentTransmissionId('');

    try {
      await recorder.stop();
      const uri = recorder.uri;

      if (!uri) {
        throw new Error('No voice file was recorded.');
      }

      await uploadRadioClip({
        channel: 'team',
        assignment_id: Number.isFinite(activeAssignmentId) ? activeAssignmentId : null,
        duration_seconds: duration,
        uri,
        name: 'resqperation-ptt.m4a',
        type: 'audio/mp4',
      });

      if (transmissionId) {
        await stopRadioTransmission({
          channel: 'team',
          transmission_id: transmissionId,
          duration_seconds: duration,
        });
      }

      await setAudioModeAsync({
        playsInSilentMode: true,
        allowsRecording: false,
        shouldPlayInBackground: false,
        shouldRouteThroughEarpiece: false,
        interruptionMode: 'doNotMix',
      });
      await loadRadioFeed(1, true, true);
      setError('');
    } catch (radioError: any) {
      setError(radioError?.response?.data?.message || radioError?.message || 'Voice message could not be sent.');
    } finally {
      setIsActionLoading(false);
    }
  }

  async function toggleRecording() {
    if (isRecording) {
      await stopRecordingAndSend();
    } else {
      await startRecording();
    }
  }

  return (
    <View style={styles.stack}>
      <View style={styles.card}>
        <SectionHeader
          title="Team radio"
          action={<StatusBadge label={isRecording ? 'Recording' : 'Ready'} tone={isRecording ? 'urgent' : 'safe'} />}
        />

        {isLoadingLogs ? (
          <View style={styles.loadingRow}>
            <ActivityIndicator color={palette.navActive} />
            <Text style={styles.emptyText}>Loading...</Text>
          </View>
        ) : (
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.memberRow}>
            {membersWithQueues.map((member: TeamMember & { queued_count?: number }) => {
              const hasQueue = Number(member.queued_count || 0) > 0;
              const isLive = Boolean(member.is_transmitting);

              return (
                <Pressable
                  key={String(member.responder_id)}
                  style={styles.memberItem}
                  onPress={() => playMemberNextClip(member)}
                >
                  <View style={[styles.avatarRing, isLive && styles.avatarRingLive]}>
                    <View style={[styles.memberAvatar, hasQueue && styles.memberAvatarQueued]}>
                      <Text style={styles.memberInitials}>{member.initials || initials(member.full_name)}</Text>
                    </View>
                    {hasQueue ? (
                      <View style={styles.countBadge}>
                        <Text style={styles.countText}>{member.queued_count}</Text>
                      </View>
                    ) : null}
                    {isLive ? (
                      <View style={styles.liveBadge}>
                        <Ionicons name="call" size={11} color="#fff" />
                      </View>
                    ) : null}
                  </View>
                  <Text numberOfLines={1} style={styles.memberName}>
                    {member.is_self ? 'You' : firstName(member.full_name)}
                  </Text>
                </Pressable>
              );
            })}
          </ScrollView>
        )}
      </View>

      {activeTransmission ? (
        <View style={styles.activeFloat}>
          <View style={styles.activeAvatar}>
            <Text style={styles.activeAvatarText}>{initials(activeTransmission.responder_name)}</Text>
          </View>
          <View style={styles.activeCopy}>
            <Text style={styles.activeTitle}>
              {activeTransmission.is_self ? 'You are transmitting' : `${activeTransmission.responder_name} is transmitting`}
            </Text>
            <Text style={styles.activeMeta}>{activeTransmission.team_name}</Text>
          </View>
          <Ionicons name="volume-high-outline" size={20} color={palette.navActive} />
        </View>
      ) : null}

      <View style={[styles.transmitCard, isRecording && styles.transmitCardActive]}>
        <Pressable
          style={[styles.talkButton, isRecording && styles.talkButtonActive]}
          onPress={toggleRecording}
          disabled={isActionLoading && !isRecording}
        >
          {isActionLoading && !isRecording ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Ionicons name={isRecording ? 'stop' : 'call'} size={42} color="#fff" />
          )}
        </Pressable>
        <Text style={styles.talkText}>
          {isRecording ? `Tap again to send (${recordSeconds}s)` : 'Tap to talk'}
        </Text>
        <Text style={styles.talkMeta}>
          {activeAssignment?.assigned_area || activeAssignment?.household_id || 'Team channel'}
        </Text>
      </View>

      <View style={styles.card}>
        <SectionHeader title="Voice logs" />
        {error ? <Text style={styles.errorText}>{error}</Text> : null}
        {logs.length === 0 ? (
          <Text style={styles.emptyText}>No voice messages yet.</Text>
        ) : (
          logs
            .filter((log) => log.type === 'ptt_audio')
            .map((log) => (
              <View key={log.id} style={styles.logItem}>
                <View style={styles.voiceDot} />
                <View style={styles.logCopy}>
                  <Text style={styles.logMessage}>{log.responder_name || 'Responder'}</Text>
                  <Text style={styles.logMeta}>{log.timestamp}</Text>
                </View>
                <Pressable style={styles.playButton} onPress={() => playClip(log)}>
                  <Ionicons name={nowPlayingId === log.id ? 'volume-high' : 'play'} size={15} color={palette.navActive} />
                </Pressable>
              </View>
            ))
        )}

        {hasMore ? (
          <Pressable style={styles.moreButton} onPress={() => loadRadioFeed(page + 1, false)} disabled={isLoadingLogs}>
            <Text style={styles.moreText}>View more</Text>
          </Pressable>
        ) : null}
      </View>
    </View>
  );
}

function initials(name: string) {
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('') || 'R';
}

function firstName(name: string) {
  return name.split(' ').filter(Boolean)[0] || 'Responder';
}

function teamMemberQueues(
  teamMembers: TeamMember[],
  logs: RadioLog[],
  responder: any,
  activeTransmission: ActiveTransmission | null,
  playedLogIds: Set<string | number>,
  playedVersion: number
) {
  void playedVersion;

  const memberMap = new Map<string, TeamMember>();

  teamMembers.forEach((member) => {
    memberMap.set(String(member.responder_id), member);
  });

  logs.forEach((log) => {
    if (!log.responder_id || memberMap.has(String(log.responder_id))) {
      return;
    }

    memberMap.set(String(log.responder_id), {
      responder_id: log.responder_id,
      responder_code: log.responder_code,
      full_name: log.responder_name || 'Responder',
      initials: initials(log.responder_name || 'Responder'),
      team_name: log.team_name,
    });
  });

  if (responder?.responder_id && !memberMap.has(String(responder.responder_id))) {
    memberMap.set(String(responder.responder_id), {
      responder_id: responder.responder_id,
      responder_code: responder.responder_code,
      full_name: responder.full_name || 'Responder',
      initials: initials(responder.full_name || 'Responder'),
      team_name: responder.team_name,
      is_self: true,
    });
  }

  return Array.from(memberMap.values()).map((member) => {
    const memberId = String(member.responder_id);
    const queuedCount = logs.filter((log) =>
      log.type === 'ptt_audio'
      && String(log.responder_id) === memberId
      && !playedLogIds.has(log.id)
    ).length;
    const isTransmitting = activeTransmission
      && String(activeTransmission.responder_id || '') === memberId;

    return {
      ...member,
      queued_count: queuedCount,
      is_transmitting: Boolean(isTransmitting),
    };
  });
}

const styles = StyleSheet.create({
  stack: {
    gap: spacing.md,
  },
  card: {
    gap: spacing.md,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.lg,
    padding: spacing.lg,
    backgroundColor: palette.card,
  },
  memberRow: {
    gap: spacing.md,
    paddingVertical: 2,
  },
  memberItem: {
    width: 70,
    alignItems: 'center',
    gap: 7,
  },
  avatarRing: {
    width: 58,
    height: 58,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 2,
    borderColor: 'transparent',
    borderRadius: 29,
  },
  avatarRingLive: {
    borderColor: palette.unsafe,
    backgroundColor: '#f5e8e8',
  },
  memberAvatar: {
    width: 48,
    height: 48,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 24,
    backgroundColor: palette.navActive,
  },
  memberAvatarQueued: {
    opacity: 0.45,
  },
  memberInitials: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '900',
  },
  countBadge: {
    position: 'absolute',
    top: -1,
    right: -1,
    minWidth: 22,
    height: 22,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 2,
    borderColor: palette.card,
    borderRadius: 11,
    backgroundColor: palette.unsafe,
  },
  countText: {
    color: '#fff',
    fontSize: 11,
    fontWeight: '900',
  },
  liveBadge: {
    position: 'absolute',
    bottom: 1,
    right: 1,
    width: 20,
    height: 20,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 2,
    borderColor: palette.card,
    borderRadius: 10,
    backgroundColor: palette.unsafe,
  },
  memberName: {
    width: 64,
    color: palette.text,
    fontSize: 11,
    fontWeight: '900',
    textAlign: 'center',
  },
  loadingRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
  },
  activeFloat: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    borderWidth: 1,
    borderColor: '#d19090',
    borderRadius: radius.lg,
    padding: spacing.md,
    backgroundColor: '#f5e8e8',
    ...shadow,
  },
  activeAvatar: {
    width: 40,
    height: 40,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 20,
    backgroundColor: palette.unsafe,
  },
  activeAvatarText: {
    color: '#fff',
    fontSize: 13,
    fontWeight: '900',
  },
  activeCopy: {
    flex: 1,
  },
  activeTitle: {
    color: palette.nav,
    fontSize: 14,
    fontWeight: '900',
  },
  activeMeta: {
    marginTop: 2,
    color: palette.textSoft,
    fontSize: 12,
    fontWeight: '800',
  },
  transmitCard: {
    alignItems: 'center',
    gap: spacing.sm,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.lg,
    padding: spacing.xl,
    backgroundColor: palette.nav,
    ...shadow,
  },
  transmitCardActive: {
    borderColor: palette.unsafe,
  },
  talkButton: {
    width: 96,
    height: 96,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 48,
    backgroundColor: palette.navActive,
  },
  talkButtonActive: {
    backgroundColor: palette.unsafe,
  },
  talkText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '900',
  },
  talkMeta: {
    color: '#b7c8dc',
    fontSize: 12,
    fontWeight: '800',
  },
  emptyText: {
    color: palette.textSoft,
    fontSize: 13,
    fontWeight: '800',
  },
  errorText: {
    color: palette.unsafe,
    fontSize: 12,
    fontWeight: '800',
  },
  logItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    borderTopWidth: 1,
    borderTopColor: palette.border,
    paddingTop: spacing.md,
  },
  voiceDot: {
    width: 10,
    height: 10,
    borderRadius: 5,
    backgroundColor: palette.navActive,
  },
  logCopy: {
    flex: 1,
  },
  logMessage: {
    color: palette.text,
    fontSize: 13,
    fontWeight: '900',
  },
  logMeta: {
    marginTop: 3,
    color: palette.textSoft,
    fontSize: 11,
    fontWeight: '800',
  },
  playButton: {
    width: 34,
    height: 34,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: 17,
    backgroundColor: palette.card,
  },
  moreButton: {
    alignItems: 'center',
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.pill,
    paddingVertical: 10,
    backgroundColor: palette.card,
  },
  moreText: {
    color: palette.nav,
    fontSize: 12,
    fontWeight: '900',
  },
});
