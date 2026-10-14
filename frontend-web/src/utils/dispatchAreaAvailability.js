const activeStatuses = new Set(['dispatched', 'accepted', 'en_route', 'on_scene', 'onscene', 'returning', 'deployed'])
const normalizeArea = value => String(value || '').trim().replace(/\s+/g, ' ').toLowerCase()
const statusKey = assignment => String(assignment.status?.key || assignment.status || '').replaceAll('-', '_')

/** Separate whole sitios as soon as any area/household has an active assignment. */
export function splitDispatchAreaAvailability(groups, workspace = {}) {
  const completeFeed = workspace.active_dispatches
  const assignments = Array.isArray(completeFeed)
    ? completeFeed.filter(item => !statusKey(item) || activeStatuses.has(statusKey(item)))
    : (workspace.dispatches?.data || []).filter(item => activeStatuses.has(statusKey(item)))
  const assignedAreas = new Set(assignments.map(item => normalizeArea(item.assigned_area)).filter(Boolean))
  const assignedHouseholds = new Set(assignments.flatMap(item => [item.household_id, ...(item.household_ids || [])]).filter(id => id != null).map(String))
  // Team-only and individual assignments can also be represented in these current-event records.
  for (const team of workspace.teams || []) {
    if (team.active_assignment_id && team.assigned_area) assignedAreas.add(normalizeArea(team.assigned_area))
  }
  for (const responder of workspace.responders || []) {
    if (responder.active_assignment_id && responder.active_assigned_area) assignedAreas.add(normalizeArea(responder.active_assigned_area))
  }

  const available = [], assigned = []
  for (const group of groups) {
    const hasAssignment = assignedAreas.has(normalizeArea(group.name)) || (group.cards || []).some(card =>
      assignedAreas.has(normalizeArea(card.area_name)) || (card.households || []).some(household =>
        household.active_assignment_id != null || household.is_available_for_dispatch === false || assignedHouseholds.has(String(household.household_id)),
      ),
    )
    ;(hasAssignment ? assigned : available).push(group)
  }
  return { available: available.map((group, index) => ({ ...group, rank: index + 1 })), assigned }
}
