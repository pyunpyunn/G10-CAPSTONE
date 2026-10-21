type HouseholdQr = { household_id?: string | number | null } | null | undefined;

// Encode the actual household ID, never a device UUID, display name or placeholder.
export function householdQrValue(qr: HouseholdQr, profileHouseholdId: unknown): string | null {
  if (typeof profileHouseholdId !== 'string' && typeof profileHouseholdId !== 'number') return null;
  const householdId = String(profileHouseholdId).trim();
  if (!householdId || qr?.household_id == null) return null;
  return String(qr.household_id).trim() === householdId ? householdId : null;
}
