type Dispatch = { assignment_id: string | number; status: string; household_ids?: (string | number)[]; household_id?: string | number };
type CachedRoute = { profile: string; status: string; household_id?: string | number };

export function selectNavigationAssignment<T extends Dispatch>(dispatches: T[], preferredId?: string | number) {
  return preferredId ? dispatches.find((item) => String(item.assignment_id) === String(preferredId))
    : dispatches.find((item) => !['completed', 'cancelled'].includes(item.status));
}

export function selectAssignedRoute<T extends CachedRoute>(routes: T[], dispatch: Dispatch | undefined, profile: string): T | null {
  if (!dispatch) return null;
  const ids = (dispatch.household_ids || [dispatch.household_id]).filter(Boolean).map(String);
  return routes.find((route) => route.profile === profile && route.status === 'ready' && ids.includes(String(route.household_id))) || null;
}
