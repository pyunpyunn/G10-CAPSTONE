type Dispatch = { assignment_id: string | number; status: string; household_ids?: (string | number)[]; household_id?: string | number };
type CachedRoute = { assignment_id?: string | number; profile: string; status: string; household_id?: string | number };

export function selectNavigationAssignment<T extends Dispatch>(dispatches: T[], preferredId?: string | number) {
  return preferredId ? dispatches.find((item) => String(item.assignment_id) === String(preferredId) && !['completed', 'cancelled'].includes(item.status))
    : dispatches.find((item) => !['completed', 'cancelled'].includes(item.status));
}

export function selectAssignedRoute<T extends CachedRoute>(routes: T[], dispatch: Dispatch | undefined, profile: string): T | null {
  if (!dispatch || ['completed', 'cancelled'].includes(dispatch.status)) return null;
  const ids = (dispatch.household_id ? [dispatch.household_id] : dispatch.household_ids || []).filter(Boolean).map(String);
  return routes.find((route) => route.profile === profile && route.status === 'ready' && (!route.assignment_id || String(route.assignment_id) === String(dispatch.assignment_id)) && ids.includes(String(route.household_id))) || null;
}
