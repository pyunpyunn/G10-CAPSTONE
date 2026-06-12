import { useEffect, useMemo, useRef, useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import {
  getRadioFeed,
  heartbeatRadioTransmission,
  sendRadioSignal,
  startRadioTransmission,
  stopRadioTransmission,
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
  channel_label: string;
  transmission_id?: string | null;
  signal?: string | null;
  duration_seconds: number;
  responder_name: string;
  team_name: string;
  audio_status: string;
  message: string;
  timestamp: string;
};

type ActiveTransmission = {
  transmission_id: string;
  channel_label: string;
  responder_name: string;
  responder_code?: string | null;
  team_name: string;
  duration_seconds: number;
  is_self: boolean;
  audio_status: string;
  last_seen_at: string;
};

const radioChannels = [
  { key: 'team', label: 'Team', icon: 'people-outline' },
  { key: 'command', label: 'HQ Command', icon: 'radio-outline' },
  { key: 'event', label: 'Event', icon: 'warning-outline' },
] as const;

type RadioChannel = (typeof radioChannels)[number]['key'];

const quickSignals = ['Copy', 'Need backup', 'On-scene', 'Clear'];

export function RadioCommunicationScreen({ overview }: RadioProps) {
  const [activeChannel, setActiveChannel] = useState<RadioChannel>('team');
  const [isTalking, setIsTalking] = useState(false);
  const [talkSeconds, setTalkSeconds] = useState(0);
  const [logs, setLogs] = useState<RadioLog[]>([]);
  const [activeTransmission, setActiveTransmission] = useState<ActiveTransmission | null>(null);
  const [currentTransmissionId, setCurrentTransmissionId] = useState('');
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(false);
  const [isLoadingLogs, setIsLoadingLogs] = useState(true);
  const [isActionLoading, setIsActionLoading] = useState(false);
  const [error, setError] = useState('');
  const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const heartbeatRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const talkingRef = useRef(false);
  const secondsRef = useRef(0);

  const profile = overview?.profile || {};
  const responder = profile.responder || {};
  const activeEvent = overview?.active_event;
  const activeAssignment = useMemo(
    () =>
      (overview?.assignments || []).find((assignment: any) =>
        ['dispatched', 'accepted', 'en_route', 'on_scene'].includes(assignment.status_key)
      ),
    [overview?.assignments]
  );

  const selectedChannel = radioChannels.find((channel) => channel.key === activeChannel) || radioChannels[0];
  const activeAssignmentId = Number(activeAssignment?.assignment_id);

  useEffect(() => {
    loadRadioFeed(1, true);

    const feedTimer = setInterval(() => {
      loadRadioFeed(1, true, true);
    }, 5000);

    return () => {
      clearInterval(feedTimer);
      clearTalkTimers();
    };
  }, [activeChannel]);

  async function loadRadioFeed(nextPage = 1, replace = true, silent = false) {
    if (!silent && replace) {
      setIsLoadingLogs(true);
    }

    try {
      const data = await getRadioFeed({
        channel: activeChannel,
        page: nextPage,
        per_page: 5,
      });

      const nextLogs = data?.logs?.data || [];

      setLogs((current) => (replace ? nextLogs : [...current, ...nextLogs]));
      setActiveTransmission(data?.active_transmission || null);
      setPage(data?.logs?.current_page || nextPage);
      setHasMore(Boolean(data?.logs?.has_more));
      setError('');
    } catch (radioError: any) {
      setError(radioError?.response?.data?.message || 'Radio feed cannot be loaded right now.');
    } finally {
      if (!silent && replace) {
        setIsLoadingLogs(false);
      }
    }
  }

  function clearTalkTimers() {
    if (timerRef.current) {
      clearInterval(timerRef.current);
      timerRef.current = null;
    }

    if (heartbeatRef.current) {
      clearInterval(heartbeatRef.current);
      heartbeatRef.current = null;
    }
  }

  async function startTalking() {
    if (isTalking || isActionLoading) return;

    talkingRef.current = true;
    secondsRef.current = 0;
    setTalkSeconds(0);
    setIsTalking(true);
    setIsActionLoading(true);
    startLocalTimer();

    try {
      const data = await startRadioTransmission({
        channel: activeChannel,
        assignment_id: Number.isFinite(activeAssignmentId) ? activeAssignmentId : null,
      });
      const transmissionId = data?.transmission_id || '';

      setCurrentTransmissionId(transmissionId);
      setActiveTransmission(data?.active_transmission || null);
      startHeartbeat(transmissionId);
      setError('');
    } catch (radioError: any) {
      clearTalkTimers();
      talkingRef.current = false;
      setIsTalking(false);
      setCurrentTransmissionId('');
      setError(radioError?.response?.data?.message || 'PTT could not start.');
    } finally {
      setIsActionLoading(false);
    }
  }

  function startLocalTimer() {
    timerRef.current = setInterval(() => {
      secondsRef.current += 1;
      setTalkSeconds(secondsRef.current);
    }, 1000);
  }

  function startHeartbeat(transmissionId: string) {
    if (!transmissionId) return;

    heartbeatRef.current = setInterval(() => {
      heartbeatRadioTransmission({
        channel: activeChannel,
        transmission_id: transmissionId,
        duration_seconds: secondsRef.current,
      }).catch(() => {
        setError('Radio signal heartbeat was not saved.');
      });
    }, 5000);
  }

  async function stopTalking() {
    if (!talkingRef.current) return;

    clearTalkTimers();
    talkingRef.current = false;
    setIsTalking(false);

    const transmissionId = currentTransmissionId;
    const duration = secondsRef.current;

    secondsRef.current = 0;
    setTalkSeconds(0);
    setCurrentTransmissionId('');

    if (!transmissionId) {
      await loadRadioFeed(1, true, true);
      return;
    }

    setIsActionLoading(true);

    try {
      const data = await stopRadioTransmission({
        channel: activeChannel,
        transmission_id: transmissionId,
        duration_seconds: duration,
      });

      setActiveTransmission(data?.active_transmission || null);
      await loadRadioFeed(1, true, true);
      setError('');
    } catch (radioError: any) {
      setError(radioError?.response?.data?.message || 'PTT stop log could not be saved.');
    } finally {
      setIsActionLoading(false);
    }
  }

  async function sendSignal(signal: string) {
    if (isActionLoading) return;

    setIsActionLoading(true);

    try {
      const data = await sendRadioSignal({
        channel: activeChannel,
        signal,
      });

      setActiveTransmission(data?.active_transmission || null);
      await loadRadioFeed(1, true, true);
      setError('');
    } catch (radioError: any) {
      setError(radioError?.response?.data?.message || 'Radio signal could not be saved.');
    } finally {
      setIsActionLoading(false);
    }
  }

  function initials(name: string) {
    return name
      .split(' ')
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part[0]?.toUpperCase())
      .join('') || 'R';
  }

  return (
    <View style={styles.stack}>
      <View style={styles.card}>
        <SectionHeader
          title="Radio communication"
          action={<StatusBadge label={isTalking ? 'Transmitting' : 'Standby'} tone={isTalking ? 'urgent' : 'safe'} />}
        />

        <View style={styles.contextRow}>
          <View style={styles.contextItem}>
            <Text style={styles.contextLabel}>Team</Text>
            <Text style={styles.contextValue}>{responder.team_name || 'Unassigned'}</Text>
          </View>
          <View style={styles.contextItem}>
            <Text style={styles.contextLabel}>Event</Text>
            <Text style={styles.contextValue}>{activeEvent?.name || 'No active event'}</Text>
          </View>
        </View>

        <View style={styles.channelRow}>
          {radioChannels.map((channel) => {
            const isActive = channel.key === activeChannel;

            return (
              <Pressable
                key={channel.key}
                style={[styles.channelButton, isActive && styles.activeChannel]}
                onPress={() => setActiveChannel(channel.key)}
              >
                <Ionicons
                  name={channel.icon}
                  size={16}
                  color={isActive ? '#fff' : palette.navActive}
                />
                <Text style={[styles.channelText, isActive && styles.activeChannelText]}>{channel.label}</Text>
              </Pressable>
            );
          })}
        </View>
      </View>

      {activeTransmission && (
        <View style={styles.activeFloat}>
          <View style={styles.activeAvatar}>
            <Text style={styles.activeAvatarText}>{initials(activeTransmission.responder_name)}</Text>
          </View>
          <View style={styles.activeCopy}>
            <Text style={styles.activeTitle}>
              {activeTransmission.is_self ? 'You are transmitting' : `${activeTransmission.responder_name} is transmitting`}
            </Text>
            <Text style={styles.activeMeta}>
              {activeTransmission.channel_label} - {activeTransmission.team_name}
            </Text>
            <Text style={styles.audioPending}>Live audio server pending</Text>
          </View>
          <Ionicons name="volume-high-outline" size={20} color={palette.navActive} />
        </View>
      )}

      <View style={[styles.transmitCard, isTalking && styles.transmitCardActive]}>
        <Text style={styles.channelLabel}>{selectedChannel.label}</Text>
        <Pressable
          style={[styles.talkButton, isTalking && styles.talkButtonActive]}
          onPressIn={startTalking}
          onPressOut={stopTalking}
          disabled={isActionLoading && !isTalking}
        >
          {isActionLoading && !isTalking ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Ionicons name={isTalking ? 'mic' : 'mic-outline'} size={42} color="#fff" />
          )}
        </Pressable>
        <Text style={styles.talkText}>{isTalking ? `Transmitting ${talkSeconds}s` : 'Hold to talk'}</Text>
        <Text style={styles.talkMeta}>{activeAssignment?.assigned_area || activeAssignment?.household_id || 'No active assignment linked'}</Text>
      </View>

      <View style={styles.card}>
        <SectionHeader title="Quick signals" />
        <View style={styles.signalGrid}>
          {quickSignals.map((signal) => (
            <Pressable key={signal} style={styles.signalButton} onPress={() => sendSignal(signal)} disabled={isActionLoading}>
              <Text style={styles.signalText}>{signal}</Text>
            </Pressable>
          ))}
        </View>
      </View>

      <View style={styles.card}>
        <SectionHeader title="Recent radio log" />
        {error ? <Text style={styles.errorText}>{error}</Text> : null}
        {isLoadingLogs ? (
          <View style={styles.loadingRow}>
            <ActivityIndicator color={palette.navActive} />
            <Text style={styles.emptyText}>Loading...</Text>
          </View>
        ) : logs.length === 0 ? (
          <Text style={styles.emptyText}>No transmissions yet.</Text>
        ) : (
          logs.map((log) => (
            <View key={log.id} style={styles.logItem}>
              <View style={[styles.logDot, log.type !== 'quick_signal' && styles.sentDot]} />
              <View style={styles.logCopy}>
                <Text style={styles.logMessage}>{log.message}</Text>
                <Text style={styles.logMeta}>{log.channel_label} - {log.timestamp}</Text>
              </View>
            </View>
          ))
        )}

        {hasMore && (
          <Pressable style={styles.moreButton} onPress={() => loadRadioFeed(page + 1, false)} disabled={isLoadingLogs}>
            <Text style={styles.moreText}>View more</Text>
          </Pressable>
        )}
      </View>

      <View style={styles.note}>
        <Ionicons name="information-circle-outline" size={18} color={palette.textSoft} />
        <Text style={styles.noteText}>Current version saves PTT activity and signals. Real voice playback needs the later LiveKit/WebRTC setup.</Text>
      </View>
    </View>
  );
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
  contextRow: {
    flexDirection: 'row',
    gap: spacing.sm,
  },
  contextItem: {
    flex: 1,
    borderRadius: radius.md,
    padding: spacing.md,
    backgroundColor: palette.secondary,
  },
  contextLabel: {
    color: palette.textSoft,
    fontSize: 11,
    fontWeight: '800',
    textTransform: 'uppercase',
  },
  contextValue: {
    marginTop: 4,
    color: palette.text,
    fontSize: 13,
    fontWeight: '900',
  },
  channelRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.sm,
  },
  channelButton: {
    minHeight: 42,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 7,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.pill,
    paddingHorizontal: spacing.md,
    backgroundColor: palette.card,
  },
  activeChannel: {
    borderColor: palette.navActive,
    backgroundColor: palette.navActive,
  },
  channelText: {
    color: palette.nav,
    fontSize: 12,
    fontWeight: '900',
  },
  activeChannelText: {
    color: '#fff',
  },
  activeFloat: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    borderWidth: 1,
    borderColor: '#88b0d6',
    borderRadius: radius.lg,
    padding: spacing.md,
    backgroundColor: '#e7eff9',
    ...shadow,
  },
  activeAvatar: {
    width: 40,
    height: 40,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 20,
    backgroundColor: palette.navActive,
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
  audioPending: {
    marginTop: 2,
    color: palette.evacuated,
    fontSize: 11,
    fontWeight: '900',
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
    borderColor: '#d19090',
    backgroundColor: palette.unsafe,
  },
  channelLabel: {
    color: palette.navMuted,
    fontSize: 12,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  talkButton: {
    width: 118,
    height: 118,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 8,
    borderColor: '#ffffff33',
    borderRadius: 59,
    backgroundColor: palette.navActive,
  },
  talkButtonActive: {
    backgroundColor: '#b92828',
  },
  talkText: {
    color: '#fff',
    fontSize: 20,
    fontWeight: '900',
  },
  talkMeta: {
    color: palette.navMuted,
    fontSize: 12,
    fontWeight: '800',
    textAlign: 'center',
  },
  signalGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.sm,
  },
  signalButton: {
    flexGrow: 1,
    minHeight: 44,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    backgroundColor: palette.secondary,
  },
  signalText: {
    color: palette.nav,
    fontSize: 13,
    fontWeight: '900',
  },
  loadingRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
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
  logDot: {
    width: 10,
    height: 10,
    borderRadius: 5,
    backgroundColor: palette.textSoft,
  },
  sentDot: {
    backgroundColor: palette.safe,
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
    fontSize: 12,
    fontWeight: '700',
  },
  moreButton: {
    minHeight: 42,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: palette.borderStrong,
    borderRadius: radius.md,
    backgroundColor: palette.card,
  },
  moreText: {
    color: palette.nav,
    fontSize: 13,
    fontWeight: '900',
  },
  note: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    borderWidth: 1,
    borderColor: palette.border,
    borderRadius: radius.md,
    padding: spacing.md,
    backgroundColor: palette.secondary,
  },
  noteText: {
    flex: 1,
    color: palette.textSoft,
    fontSize: 12,
    fontWeight: '700',
  },
});
