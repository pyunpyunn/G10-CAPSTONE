import { Platform } from 'react-native';
import * as FileSystem from 'expo-file-system/legacy';
import * as MediaLibrary from 'expo-media-library';

export async function saveQrImage(base64: string, filename: string): Promise<string> {
  // SDK 54 requires legacy write permission below Android 13, not gallery read access.
  if (Platform.OS === 'ios' || Number(Platform.Version) < 33) {
    const permission = await MediaLibrary.requestPermissionsAsync(true);
    if (!permission.granted) throw new Error('Allow photo saving to download your QR to the gallery.');
  }
  if (!FileSystem.cacheDirectory) throw new Error('Temporary image storage is unavailable.');
  const uri = `${FileSystem.cacheDirectory}${filename}`;
  try {
    await FileSystem.writeAsStringAsync(uri, base64, { encoding: FileSystem.EncodingType.Base64 });
    await MediaLibrary.saveToLibraryAsync(uri);
    return 'Your QR image was saved to your photo gallery.';
  } finally {
    await FileSystem.deleteAsync(uri, { idempotent: true }).catch(() => {});
  }
}
