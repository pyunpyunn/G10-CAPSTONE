import { useRef, useState } from 'react';
import { Alert } from 'react-native';
import * as Location from 'expo-location';
import { previewHouseholdSetupLocation } from '@/api/household';
import { confirmHouseholdGps } from '@/utils/confirmHouseholdGps';

export type SetupDetails = {
  is_apartment: boolean | null;
  house_number: string;
  building_name: string;
  floor_number: string;
  unit_number: string;
  room_number: string;
  landmark: string;
};

export function useHouseholdSetup({ overview, deviceUuid, onComplete, photoUri = '' }: {
  overview: any; deviceUuid: string; onComplete: (payload: any) => Promise<void>; photoUri?: string;
}) {
  const registered = overview.setup?.address;
  const [address, setAddress] = useState<any>(registered || null);
  const [pin, setPin] = useState<{ latitude: number; longitude: number; accuracy_m?: number | null } | null>(null);
  const [details, setDetails] = useState<SetupDetails>({
    is_apartment: registered?.is_apartment == null ? null : Boolean(registered.is_apartment),
    house_number: registered?.house_number || '', building_name: registered?.building_name || '',
    floor_number: registered?.floor_number || '', unit_number: registered?.unit_number || '',
    room_number: registered?.room_number || '', landmark: registered?.landmark || '',
  });
  const [findingAddress, setFindingAddress] = useState(false);
  const [saving, setSaving] = useState(false);
  const [resolved, setResolved] = useState(false);
  const [geotagMessage, setGeotagMessage] = useState('Choose your household location. It will be saved when setup is complete.');
  const version = useRef(0);
  const submitting = useRef(false);
  const device = { device_uuid: deviceUuid, device_name: 'Household mobile', platform: 'expo' };

  function beginPinMove() {
    version.current++;
    setResolved(false);
    setFindingAddress(true);
    setGeotagMessage('Release the pin to check the registered address.');
  }

  async function applyPinnedCoordinate(coordinate: NonNullable<typeof pin>) {
    const current = ++version.current;
    setPin(coordinate);
    setResolved(false);
    setFindingAddress(true);
    setGeotagMessage('Checking the household address in the database...');
    try {
      const result = await previewHouseholdSetupLocation(coordinate.latitude, coordinate.longitude);
      if (current !== version.current) return;
      if (result.reused_geotag) {
        setGeotagMessage('Using the location already saved for your household.');
        await onComplete(device);
        return;
      }
      setAddress(result.address);
      setResolved(true);
      setGeotagMessage('Registered sitio and purok loaded. Complete the address details below.');
    } catch (error: any) {
      if (current === version.current) setGeotagMessage(error?.response?.data?.message || 'Could not check the address. Choose the location again to retry.');
    } finally {
      if (current === version.current) setFindingAddress(false);
    }
  }

  async function requestGpsLocation() {
    if (!await confirmHouseholdGps()) return;
    const current = ++version.current;
    setFindingAddress(true);
    setResolved(false);
    try {
      const permission = await Location.requestForegroundPermissionsAsync();
      if (permission.status !== 'granted') throw new Error('Allow GPS access or choose the household location on the map.');
      const location = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.High });
      if (current === version.current) await applyPinnedCoordinate({ ...location.coords, accuracy_m: location.coords.accuracy });
    } catch (error: any) {
      if (current === version.current) setGeotagMessage(error.message || 'GPS is unavailable. Choose your location on the map.');
    } finally {
      if (current === version.current) setFindingAddress(false);
    }
  }

  async function handleSave() {
    if (findingAddress || submitting.current) return;
    if (!pin || !resolved || !address) return Alert.alert('Location required', 'Choose a location with a registered sitio and purok before continuing.');
    if (details.is_apartment === null) return Alert.alert('Address type', 'Select whether you live in an apartment.');
    if (details.is_apartment && !details.unit_number.trim()) return Alert.alert('Unit required', 'Enter your apartment unit number.');
    submitting.current = true;
    setSaving(true);
    try {
      await onComplete({
        ...device, ...pin, ...details, photo_uri: photoUri || null,
        house_number: details.house_number.trim(), unit_number: details.is_apartment ? details.unit_number.trim() : null,
      });
    } finally {
      submitting.current = false;
      setSaving(false);
    }
  }

  return { pin, address, details, setDetails, saving, findingAddress, geotagMessage, beginPinMove, applyPinnedCoordinate, requestGpsLocation, handleSave };
}
