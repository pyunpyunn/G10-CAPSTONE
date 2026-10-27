// Temporary, isolated emulator verification. Removed before the deliverable build.
import { useEffect, useState } from 'react';
import { Alert, Pressable, ScrollView, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { RescueMapScreen } from '@/components/rescuer/RescueMapScreen';
import { HouseholdQrModal } from '@/components/household/HouseholdModals';
import { api } from '@/api/client';
import { rememberGps } from '@/utils/savedGps';

const centers = [{ evacuation_center_id: 'EMU-A', name: 'Emulator center A', latitude: 10.292, longitude: 123.882, status: 'active' },
  { evacuation_center_id: 'EMU-B', name: 'Emulator center B', latitude: 10.295, longitude: 123.885, status: 'active' }];
const assignments: any[] = [];
export default function NativeCheck() {
  const [qr, setQr] = useState(false);
  const [ticks, setTicks] = useState(0);
  const [requests, setRequests] = useState(0);
  useEffect(() => {
    const previous = api.post;
    api.post = (async (url: string, payload: any) => {
      if (url !== '/rescuer/evacuation-centers/EMU-A/route') throw new Error('Emulator fixture refuses real API writes.');
      if (payload.latitude !== 10.29 || payload.longitude !== 123.88) throw new Error('Saved GPS was not used.');
      setRequests(value => value + 1);
      return { data: { data: { coordinates: [{ latitude: 10.29, longitude: 123.88 }, { latitude: 10.291, longitude: 123.881 }, { latitude: 10.292, longitude: 123.882 }], distance_m: 300, duration_s: 60 } } };
    }) as typeof api.post;
    const timer = setInterval(() => setTicks(value => value + 1), 1500);
    return () => { clearInterval(timer); api.post = previous; };
  }, []);
  return <SafeAreaView style={{ flex: 1, backgroundColor: '#eff3f6' }}>
    <Text>Android native verification · refresh {ticks} · saved-GPS requests {requests}</Text>
    <View style={{ flexDirection: 'row', gap: 12, padding: 8 }}>
      <Pressable accessibilityRole="button" accessibilityLabel="Seed saved GPS" style={{ padding: 12, backgroundColor: '#ddd' }} onPress={() => {
        void rememberGps({ timestamp: Date.now() - 3600000, coords: { latitude: 10.29, longitude: 123.88, accuracy: 5, altitude: null, altitudeAccuracy: null, heading: null, speed: null } })
          .then(() => Alert.alert('Saved GPS seeded', 'Test position is one hour old. Device GPS can remain disabled.'));
      }}><Text>Seed saved GPS</Text></Pressable>
      <Pressable accessibilityRole="button" accessibilityLabel="Show QR test" style={{ padding: 12, backgroundColor: '#ddd' }} onPress={() => setQr(true)}><Text>Show QR test</Text></Pressable>
    </View>
    <ScrollView removeClippedSubviews={false} contentContainerStyle={{ padding: 16 }}>
      <RescueMapScreen assignments={assignments} activeAssignment={null} evacuationCenters={centers} />
      <View style={{ height: 400 }}><Text>Scroll away and back; the map must remain visible.</Text></View>
    </ScrollView>
    <HouseholdQrModal visible={qr} qr={{ household_id: 'EMU-SMOKE', household_name: 'Emulator QR verification' }} householdId="EMU-SMOKE" onClose={() => setQr(false)} />
  </SafeAreaView>;
}
