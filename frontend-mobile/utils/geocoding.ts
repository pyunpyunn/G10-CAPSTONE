import * as Location from 'expo-location';

export type ReverseGeocodeResult = {
  label: string;
  street: string;
  barangay: string;
  city: string;
  province: string;
};

export async function reverseGeocodeAddress(latitude: number, longitude: number): Promise<ReverseGeocodeResult> {
  const expoAddress = await reverseWithExpo(latitude, longitude);

  if (expoAddress.label) {
    return expoAddress;
  }

  return reverseWithOpenStreetMap(latitude, longitude);
}

async function reverseWithExpo(latitude: number, longitude: number): Promise<ReverseGeocodeResult> {
  try {
    const [address] = await Location.reverseGeocodeAsync({ latitude, longitude });

    if (!address) {
      return emptyAddress();
    }

    const street = [address.streetNumber, address.street].filter(Boolean).join(' ') || address.name || '';
    const barangay = address.district || '';
    const city = address.city || address.subregion || '';
    const province = address.region || '';

    return {
      label: formatLabel([address.name, street, barangay, city, province]),
      street,
      barangay,
      city,
      province,
    };
  } catch {
    return emptyAddress();
  }
}

async function reverseWithOpenStreetMap(latitude: number, longitude: number): Promise<ReverseGeocodeResult> {
  try {
    const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${latitude}&lon=${longitude}&zoom=18&addressdetails=1`;
    const response = await fetch(url, {
      headers: {
        Accept: 'application/json',
        'User-Agent': 'RESQPERATION-Capstone/1.0',
      },
    });

    if (!response.ok) {
      return fallbackPinnedAddress(latitude, longitude);
    }

    const data = await response.json();
    const address = data?.address || {};
    const street = [address.house_number, address.road || address.street].filter(Boolean).join(' ');
    const barangay = address.suburb || address.village || address.neighbourhood || address.quarter || address.hamlet || '';
    const city = address.city || address.town || address.municipality || address.county || '';
    const province = address.state || address.region || '';

    return {
      label: data?.display_name || formatLabel([street, barangay, city, province]),
      street,
      barangay,
      city,
      province,
    };
  } catch {
    return fallbackPinnedAddress(latitude, longitude);
  }
}

function formatLabel(parts: Array<string | null | undefined>) {
  return parts.filter(Boolean).join(', ');
}

function emptyAddress(): ReverseGeocodeResult {
  return {
    label: '',
    street: '',
    barangay: '',
    city: '',
    province: '',
  };
}

function fallbackPinnedAddress(latitude: number, longitude: number): ReverseGeocodeResult {
  return {
    ...emptyAddress(),
    label: `Pinned location (${latitude.toFixed(6)}, ${longitude.toFixed(6)})`,
  };
}
