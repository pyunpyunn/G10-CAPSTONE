import { OfflineManager, type OfflinePackStatus } from '@maplibre/maplibre-react-native';
import * as Network from 'expo-network';
import { getActivePack, navigationMetadata } from './navigationStore';
import { downloadNativeNavigationPack, importedPackStatus } from './navigationPackDownload';

let wifiListener: ReturnType<typeof Network.addNetworkStateListener> | null = null;

export async function prepareNavigationPack(onProgress: (status: OfflinePackStatus) => void, onError: (message: string) => void) {
  const manifest = await getActivePack();
  if (!manifest) throw new Error('HQ has not registered an offline map pack.');
  const network = await Network.getNetworkStateAsync();
  if (network.type !== Network.NetworkStateType.WIFI) throw new Error('Connect to Wi-Fi to download or resume the map.');
  if (manifest.pack_format === 'maplibre_db') return downloadNativeNavigationPack(manifest, onProgress);
  if (!Array.isArray(manifest.bbox) || manifest.bbox.length !== 4 || !manifest.bbox.every(Number.isFinite)) {
    throw new Error('The offline map boundary is invalid.');
  }
  OfflineManager.setProgressEventThrottle(1000);
  if (!wifiListener) wifiListener = Network.addNetworkStateListener((state) => {
    if (state.type !== Network.NetworkStateType.WIFI) {
      void OfflineManager.getPacks().then((items) => Promise.all(items.filter((item) => item.metadata.resqVersion).map((item) => item.pause())))
        .catch((error) => onError(error.message));
    }
  });
  const packs = await OfflineManager.getPacks();
  const existing = packs.find((pack) => pack.metadata.resqVersion === manifest.version);
  const progress = async (pack: typeof existing, status: OfflinePackStatus) => {
    onProgress(status);
    if (status.state === 'complete') {
      for (const old of packs) {
        if (old.metadata.resqVersion && old.metadata.resqVersion !== manifest.version) await OfflineManager.deletePack(old.id);
      }
    }
    if (status.completedResourceSize > 40_000_000 && status.state !== 'complete') {
      await pack?.pause(); onError('Map download exceeded 40 MB. Ask HQ to reduce the map extent.');
    }
  };
  if (existing) {
    await OfflineManager.addListener(existing.id, progress, (_pack, error) => onError(error.message));
    const status = await existing.status();
    onProgress(status);
    if (status.state !== 'complete') await existing.resume();
    return existing;
  }
  return OfflineManager.createPack({ mapStyle: manifest.style_url, bounds: manifest.bbox,
    minZoom: 12, maxZoom: 17, metadata: { resqVersion: manifest.version } }, progress,
  (_pack, error) => onError(error.message));
}

export async function navigationPackStatus() {
  const manifest = await getActivePack();
  if (manifest && await navigationMetadata('imported_pack') === `${manifest.version}:${manifest.checksum}`) return importedPackStatus(manifest.version);
  const pack = (await OfflineManager.getPacks()).find((item) => item.metadata.resqVersion === manifest?.version);
  return pack ? pack.status() : null;
}
