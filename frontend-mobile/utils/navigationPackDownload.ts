import { File, Paths } from 'expo-file-system';
import { fetch } from 'expo/fetch';
import * as Crypto from 'expo-crypto';
import * as Network from 'expo-network';
import { OfflineManager, type OfflinePackStatus } from '@maplibre/maplibre-react-native';
import { api } from '@/api/client';
import { navigationMetadata } from './navigationStore';

const chunkBytes = 1024 * 1024;
let downloading: Promise<void> | null = null;

export function importedPackStatus(version: string): OfflinePackStatus {
  return { id: version, state: 'complete', percentage: 100, completedResourceCount: 1,
    requiredResourceCount: 1, completedTileCount: 0, completedResourceSize: 0, completedTileSize: 0 };
}

export async function downloadNativeNavigationPack(manifest: any, onProgress: (status: OfflinePackStatus) => void) {
  if (downloading) return downloading;
  downloading = (async () => {
    if (!/^[A-Za-z0-9._-]{1,64}$/.test(manifest.version) || !/^[a-f0-9]{64}$/i.test(manifest.checksum)
      || !Number.isInteger(manifest.size_bytes) || manifest.size_bytes <= 0 || manifest.size_bytes > 40_000_000) {
      throw new Error('The native pack manifest is invalid or exceeds 40 MB.');
    }
    const identity = `${manifest.version}:${manifest.checksum}`;
    if (await navigationMetadata('imported_pack') === identity) { onProgress(importedPackStatus(manifest.version)); return; }
    // Keep partial bytes across application restarts. A changed checksum gets a
    // new filename so stale bytes cannot be appended to a revised artifact.
    const file = new File(Paths.document, `resq-pack-${manifest.version}-${manifest.checksum}.db`);
    if (!file.exists) file.create();
    let offset = file.size;
    if (offset > manifest.size_bytes) { file.delete(); file.create(); offset = 0; }
    const refreshed = await api.get(`/mobile/packs/${encodeURIComponent(manifest.version)}`);
    const fresh = refreshed.data.data;
    if (fresh.checksum !== manifest.checksum || fresh.size_bytes !== manifest.size_bytes) throw new Error('Pack version changed. Refresh assigned routes first.');
    while (offset < manifest.size_bytes) {
      if ((await Network.getNetworkStateAsync()).type !== Network.NetworkStateType.WIFI) throw new Error('Download paused. Connect to Wi-Fi and resume.');
      const end = Math.min(offset + chunkBytes, manifest.size_bytes) - 1;
      const abort = new AbortController();
      const timeout = setTimeout(() => abort.abort(), 30000);
      let response: Awaited<ReturnType<typeof fetch>>;
      let downloaded: ArrayBuffer;
      try {
        response = await fetch(fresh.download_url, { signal: abort.signal, headers: { Range: `bytes=${offset}-${end}`, 'Accept-Encoding': 'identity' } });
        downloaded = await response.arrayBuffer();
      } finally { clearTimeout(timeout); }
      if (response.status !== 206 || response.headers.get('content-range') !== `bytes ${offset}-${end}/${manifest.size_bytes}`) {
        throw new Error('The pack server must support byte-range downloads.');
      }
      const bytes = new Uint8Array(downloaded);
      if (bytes.length !== end - offset + 1) throw new Error('Pack download was interrupted. Resume on Wi-Fi.');
      const handle = file.open();
      try { handle.offset = offset; handle.writeBytes(bytes); } finally { handle.close(); }
      offset += bytes.length;
      onProgress({ ...importedPackStatus(manifest.version), state: 'active', percentage: offset / manifest.size_bytes * 100,
        completedResourceSize: offset });
    }
    const digest = await Crypto.digest(Crypto.CryptoDigestAlgorithm.SHA256, await file.bytes());
    const checksum = Array.from(new Uint8Array(digest), (byte) => byte.toString(16).padStart(2, '0')).join('');
    if (checksum !== manifest.checksum.toLowerCase()) { file.delete(); throw new Error('Map checksum verification failed. Download again.'); }
    await OfflineManager.mergeOfflineRegions(file.uri.replace(/^file:\/\//, ''));
    await navigationMetadata('imported_pack', identity);
    file.delete();
    onProgress({ ...importedPackStatus(manifest.version), completedResourceSize: manifest.size_bytes });
  })().finally(() => { downloading = null; });
  return downloading;
}
