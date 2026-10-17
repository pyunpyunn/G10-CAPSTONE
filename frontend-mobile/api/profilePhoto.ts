import { Platform } from 'react-native';
import { api } from './client';

type ProfilePhotoAsset = {
  uri: string;
  fileName?: string | null;
  mimeType?: string | null;
  file?: File;
};

export async function uploadProfilePhoto(asset: ProfilePhotoAsset) {
  const formData = new FormData();
  const name = asset.fileName || 'profile-photo.jpg';
  const type = asset.mimeType || (name.toLowerCase().endsWith('.png') ? 'image/png' : 'image/jpeg');

  if (Platform.OS === 'web' && asset.file) {
    formData.append('photo', asset.file, name);
  } else {
    formData.append('photo', { uri: asset.uri, name, type } as any);
  }

  const response = await api.post('/profile/photo', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });

  return response.data.data;
}

export async function removeProfilePhoto() {
  const response = await api.delete('/profile/photo');
  return response.data.data;
}
