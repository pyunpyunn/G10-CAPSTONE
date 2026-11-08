type ActiveWebRecording = {
  stream: MediaStream;
  audioCtx: AudioContext;
  sourceNode: MediaStreamAudioSourceNode;
  processorNode: ScriptProcessorNode;
  silentGainNode: GainNode;
  chunks: Float32Array[];
  sampleRate: number;
};

let activeRecording: ActiveWebRecording | null = null;
let playbackAudioCtx: AudioContext | null = null;
let currentSourceNode: AudioBufferSourceNode | null = null;
let currentHtmlAudio: HTMLAudioElement | null = null;

function getAudioContextClass(): typeof AudioContext | null {
  if (typeof window === 'undefined') {
    return null;
  }
  return (window as any).AudioContext || (window as any).webkitAudioContext || null;
}

export async function startWebVoiceRecording(): Promise<void> {
  await cancelWebVoiceRecording();

  if (typeof navigator === 'undefined' || !navigator.mediaDevices?.getUserMedia) {
    throw new Error('Microphone recording is not supported in this browser.');
  }

  const stream = await navigator.mediaDevices.getUserMedia({
    audio: {
      echoCancellation: true,
      noiseSuppression: true,
      autoGainControl: true,
    },
  });

  const AudioCtx = getAudioContextClass();
  if (!AudioCtx) {
    stream.getTracks().forEach((track) => track.stop());
    throw new Error('Web Audio API is not supported in this browser.');
  }

  const audioCtx = new AudioCtx();
  if (audioCtx.state === 'suspended') {
    await audioCtx.resume();
  }

  const sourceNode = audioCtx.createMediaStreamSource(stream);
  const processorNode = audioCtx.createScriptProcessor(2048, 1, 1);
  const silentGainNode = audioCtx.createGain();
  // Keep non-zero so Chromium's audio graph never culls ScriptProcessorNode callbacks
  silentGainNode.gain.value = 0.0001;

  const chunks: Float32Array[] = [];

  processorNode.onaudioprocess = (event: AudioProcessingEvent) => {
    const input = event.inputBuffer.getChannelData(0);
    chunks.push(new Float32Array(input));
  };

  sourceNode.connect(processorNode);
  processorNode.connect(silentGainNode);
  silentGainNode.connect(audioCtx.destination);

  activeRecording = {
    stream,
    audioCtx,
    sourceNode,
    processorNode,
    silentGainNode,
    chunks,
    sampleRate: audioCtx.sampleRate || 44100,
  };
}

export async function cancelWebVoiceRecording(): Promise<void> {
  if (!activeRecording) {
    return;
  }

  const rec = activeRecording;
  activeRecording = null;

  try {
    rec.processorNode.onaudioprocess = null;
    rec.sourceNode.disconnect();
    rec.processorNode.disconnect();
    rec.silentGainNode.disconnect();
  } catch {
    // Ignore disconnect errors
  }

  try {
    rec.stream.getTracks().forEach((track) => track.stop());
  } catch {
    // Ignore track stop errors
  }

  try {
    if (rec.audioCtx.state !== 'closed') {
      await rec.audioCtx.close();
    }
  } catch {
    // Ignore close errors
  }
}

function createPttChirp(sampleRate: number, rising: boolean): Float32Array {
  const durationSec = 0.08;
  const length = Math.floor(sampleRate * durationSec);
  const out = new Float32Array(length);
  for (let i = 0; i < length; i += 1) {
    const t = i / sampleRate;
    const freq = rising ? (i < length / 2 ? 1046.5 : 1396.9) : (i < length / 2 ? 1396.9 : 1046.5);
    const env = Math.sin((Math.PI * i) / length);
    out[i] = Math.sin(2 * Math.PI * freq * t) * 0.28 * env;
  }
  return out;
}

export async function stopWebVoiceRecording(): Promise<{ uri: string; hasVoice: boolean }> {
  if (!activeRecording) {
    throw new Error('No active voice recording.');
  }

  const { chunks, sampleRate } = activeRecording;
  await cancelWebVoiceRecording();

  const totalLength = chunks.reduce((sum, chunk) => sum + chunk.length, 0);
  const merged = new Float32Array(Math.max(totalLength, Math.floor(sampleRate * 0.5)));
  let offset = 0;
  for (const chunk of chunks) {
    merged.set(chunk, offset);
    offset += chunk.length;
  }

  // Remove DC offset and find peak amplitude
  let sum = 0;
  for (let i = 0; i < totalLength; i += 1) {
    sum += merged[i];
  }
  const mean = totalLength > 0 ? sum / totalLength : 0;

  let maxAbs = 0;
  for (let i = 0; i < totalLength; i += 1) {
    const centered = merged[i] - mean;
    merged[i] = centered;
    const abs = Math.abs(centered);
    if (abs > maxAbs) {
      maxAbs = abs;
    }
  }

  const hasVoice = maxAbs > 0.001;
  const gain = hasVoice && maxAbs < 0.9 ? Math.min(0.9 / maxAbs, 30) : 1;

  for (let i = 0; i < totalLength; i += 1) {
    const amplified = merged[i] * gain;
    merged[i] = Math.max(-1, Math.min(1, amplified));
  }

  const startChirp = createPttChirp(sampleRate, true);
  const endChirp = createPttChirp(sampleRate, false);
  const finalSamples = new Float32Array(startChirp.length + merged.length + endChirp.length);
  finalSamples.set(startChirp, 0);
  finalSamples.set(merged, startChirp.length);
  finalSamples.set(endChirp, startChirp.length + merged.length);

  const wavBlob = encodeWavBlob(finalSamples, sampleRate);
  const uri = URL.createObjectURL(wavBlob);

  return { uri, hasVoice };
}

function encodeWavBlob(samples: Float32Array, sampleRate: number): Blob {
  const numChannels = 1;
  const bytesPerSample = 2;
  const blockAlign = numChannels * bytesPerSample;
  const byteRate = sampleRate * blockAlign;
  const dataSize = samples.length * bytesPerSample;
  const buffer = new ArrayBuffer(44 + dataSize);
  const view = new DataView(buffer);

  writeAscii(view, 0, 'RIFF');
  view.setUint32(4, 36 + dataSize, true);
  writeAscii(view, 8, 'WAVE');
  writeAscii(view, 12, 'fmt ');
  view.setUint32(16, 16, true);
  view.setUint16(20, 1, true);
  view.setUint16(22, numChannels, true);
  view.setUint32(24, sampleRate, true);
  view.setUint32(28, byteRate, true);
  view.setUint16(32, blockAlign, true);
  view.setUint16(34, 16, true);
  writeAscii(view, 36, 'data');
  view.setUint32(40, dataSize, true);

  let pos = 44;
  for (let i = 0; i < samples.length; i += 1) {
    const clamped = Math.max(-1, Math.min(1, samples[i]));
    const int16 = clamped < 0 ? clamped * 0x8000 : clamped * 0x7fff;
    view.setInt16(pos, int16, true);
    pos += 2;
  }

  return new Blob([buffer], { type: 'audio/wav' });
}

function writeAscii(view: DataView, offset: number, text: string) {
  for (let i = 0; i < text.length; i += 1) {
    view.setUint8(offset + i, text.charCodeAt(i));
  }
}

export function stopWebVoicePlayback(): boolean {
  let stopped = false;

  if (currentSourceNode) {
    try {
      currentSourceNode.onended = null;
      currentSourceNode.stop();
      currentSourceNode.disconnect();
    } catch {
      // Ignore stop errors
    }
    currentSourceNode = null;
    stopped = true;
  }

  if (currentHtmlAudio) {
    try {
      currentHtmlAudio.onended = null;
      currentHtmlAudio.onerror = null;
      currentHtmlAudio.pause();
    } catch {
      // Ignore pause errors
    }
    currentHtmlAudio = null;
    stopped = true;
  }

  return stopped;
}

export async function playWebVoiceUrl(
  url: string,
  onEnded: () => void
): Promise<{ isSilentClip: boolean }> {
  stopWebVoicePlayback();

  const AudioCtx = getAudioContextClass();
  if (!AudioCtx) {
    throw new Error('Web Audio API is not supported.');
  }

  if (!playbackAudioCtx || playbackAudioCtx.state === 'closed') {
    playbackAudioCtx = new AudioCtx();
  }

  if (playbackAudioCtx.state === 'suspended') {
    await playbackAudioCtx.resume();
  }

  const response = await fetch(url, { cache: 'no-store' });
  if (!response.ok) {
    throw new Error(`Audio fetch failed (${response.status})`);
  }

  const arrayBuffer = await response.arrayBuffer();
  const decodedBuffer = await playbackAudioCtx.decodeAudioData(arrayBuffer.slice(0));

  let maxAbs = 0;
  for (let ch = 0; ch < decodedBuffer.numberOfChannels; ch += 1) {
    const channelData = decodedBuffer.getChannelData(ch);
    for (let i = 0; i < channelData.length; i += 1) {
      const abs = Math.abs(channelData[i]);
      if (abs > maxAbs) {
        maxAbs = abs;
      }
    }
  }

  const isSilentClip = maxAbs < 0.0008;

  if (isSilentClip) {
    const sampleRate = playbackAudioCtx.sampleRate;
    const durationSec = 0.45;
    const toneBuffer = playbackAudioCtx.createBuffer(1, Math.floor(sampleRate * durationSec), sampleRate);
    const data = toneBuffer.getChannelData(0);
    for (let i = 0; i < data.length; i += 1) {
      const t = i / sampleRate;
      const freq = t < 0.22 ? 880 : 1174.66;
      const env = Math.sin((Math.PI * (t % 0.225)) / 0.225);
      data[i] = Math.sin(2 * Math.PI * freq * t) * 0.45 * env;
    }

    const toneSource = playbackAudioCtx.createBufferSource();
    toneSource.buffer = toneBuffer;
    toneSource.connect(playbackAudioCtx.destination);
    currentSourceNode = toneSource;
    toneSource.onended = () => {
      if (currentSourceNode === toneSource) {
        currentSourceNode = null;
      }
      onEnded();
    };
    toneSource.start(0);
    return { isSilentClip: true };
  }

  const sourceNode = playbackAudioCtx.createBufferSource();
  sourceNode.buffer = decodedBuffer;

  const gainNode = playbackAudioCtx.createGain();
  gainNode.gain.value = maxAbs < 0.9 ? Math.min(0.95 / maxAbs, 20) : 1.0;

  sourceNode.connect(gainNode);
  gainNode.connect(playbackAudioCtx.destination);

  currentSourceNode = sourceNode;
  sourceNode.onended = () => {
    if (currentSourceNode === sourceNode) {
      currentSourceNode = null;
    }
    onEnded();
  };

  sourceNode.start(0);
  return { isSilentClip: false };
}
