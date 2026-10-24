// Use the same per-rescuer assignment and planned route as the dashboard/map.
// A missing device-bound offline session must not hide an authenticated dispatch.
export function assignmentNavigationSnapshot(overview: any, bootstrap: any = {}) {
  const assignments = overview.assignments || [];
  const dispatches = assignments.map((item: any) => ({ ...item,
    status: item.status_key, household_ids: item.route_notes?.pending_household_ids ?? item.route_notes?.assigned_household_ids ?? (item.household_id ? [item.household_id] : []),
  }));
  const routes = assignments.flatMap((item: any) => {
    if (['completed', 'cancelled'].includes(item.status_key)) return [];
    if ((item.route?.household_id && String(item.route.household_id) !== String(item.household_id)) || !item.household_id || (item.route?.coordinates?.length || 0) < 2) return [];
    return [{ ...item.route, assignment_id: item.assignment_id,
      team_id: overview.profile?.responder?.team_id || bootstrap.team?.team_id || 0,
      household_id: item.household_id, profile: 'auto', status: 'ready',
      distance_m: Number(item.route.distance_km || 0) * 1000,
      duration_s: Number(item.route.duration_min || 0) * 60,
    }];
  });
  const ids = new Set(dispatches.flatMap((item: any) => item.household_ids.map(String)));
  return { ...bootstrap, full_snapshot: true, dispatches,
    team: bootstrap.team || overview.profile?.responder,
    households: assignments.filter((item: any) => item.household_id).map((item: any) => ({
      household_id: item.household_id, household_name: item.destination_label || item.assigned_area || item.household_id,
    })),
    routes: [...routes, ...(bootstrap.routes || []).filter((route: any) => ids.has(String(route.household_id))
      && !routes.some((ready: any) => String(ready.household_id) === String(route.household_id) && ready.profile === route.profile))],
    status_options: bootstrap.status_options || overview.status_options || [],
    evacuation_centers: bootstrap.evacuation_centers || [],
  };
}
