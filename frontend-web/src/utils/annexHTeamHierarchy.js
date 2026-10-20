export const ANNEX_H_SUBCOMMITTEES = [
  {
    key: 'mitigation_prevention',
    title: 'Mitigation and Prevention Sub-committee',
    shortTitle: 'Mitigation & Prevention',
    roleKeys: ['mitigation_prevention'],
    teamGroupKeys: ['research_planning'],
  },
  {
    key: 'preparedness',
    title: 'Preparedness Sub-committee',
    shortTitle: 'Preparedness',
    roleKeys: ['preparedness'],
    teamGroupKeys: ['preparedness'],
  },
  {
    key: 'response',
    title: 'Response Sub-committee',
    shortTitle: 'Response',
    roleKeys: ['response'],
    teamGroupKeys: ['operations_warning'],
  },
  {
    key: 'recovery_rehabilitation',
    title: 'Recovery and Rehabilitation Sub-committee',
    shortTitle: 'Recovery & Rehabilitation',
    roleKeys: ['recovery_rehabilitation'],
    teamGroupKeys: [],
  },
]

export const ANNEX_H_TEAM_GROUPS = [
  {
    key: 'operations_warning',
    title: 'Operations & Warning Teams',
    description: 'Field response teams associated with the Response Sub-committee.',
    teamCodes: ['SRR', 'ECM', 'HFAPS', 'BHERT', 'SST', 'FMT', 'RDT', 'TRT', 'CLT'],
    units: [
      { code: 'SRR', name: 'Search, Rescue and Retrieval Team' },
      { code: 'ECM', name: 'Evacuation and Camp Management Team' },
      { code: 'HFAPS', name: 'Health, First Aid and Psychosocial Support Team' },
      { code: 'BHERT', name: 'Barangay Health Emergency Response Team', parentCode: 'HFAPS' },
      { code: 'SST', name: 'Security and Safety Team' },
      { code: 'FMT', name: 'Fire Management Team' },
      { code: 'RDT', name: 'Relief Distribution Team' },
      { code: 'TRT', name: 'Transportation Team' },
      { code: 'CLT', name: 'Clearing Operations Team' },
    ],
    committeeKey: 'response',
  },
  {
    key: 'research_planning',
    title: 'Research & Planning Teams',
    description: 'Assessment and planning teams associated with Mitigation and Prevention.',
    teamCodes: ['RDANA', 'PDANA'],
    units: [
      { code: 'RDANA', name: 'Rapid Damage Assessment and Needs Analysis Team' },
      { code: 'PDANA', name: 'Post-Disaster Assessment and Needs Analysis Team' },
    ],
    committeeKey: 'mitigation_prevention',
  },
  {
    key: 'preparedness',
    title: 'Preparedness Teams',
    description: 'Teams assigned directly to the Preparedness Sub-committee.',
    teamCodes: [],
    units: [],
    committeeKey: 'preparedness',
  },
]

export const ANNEX_H_DESK_FUNCTIONS = {
  mitigation_prevention: [{ code: 'RPT', name: 'Research and Planning Team' }],
  response: [{ code: 'CWT', name: 'Communication and Warning Team' }],
  recovery_rehabilitation: [
    { code: 'LHT', name: 'Livelihood Team' },
    { code: 'ISH', name: 'Infrastructure and Shelter Team' },
  ],
}

export const ANNEX_H_ROLE_LABELS = {
  chair: 'BDRRMC Chairperson',
  operations_admin: 'Operations/Admin (technical only)',
  mitigation_prevention: 'Mitigation and Prevention',
  preparedness: 'Preparedness',
  response: 'Response',
  recovery_rehabilitation: 'Recovery and Rehabilitation',
}

export const ANNEX_H_COMMITTEE_OPTIONS = ANNEX_H_SUBCOMMITTEES.map(committee => ({
  key: committee.key,
  label: committee.title,
}))

const subcommitteeKeys = new Set(ANNEX_H_SUBCOMMITTEES.map(committee => committee.key))
const LEGACY_TEAM_CODES = {
  SAR: 'SRR',
  MED: 'HFAPS',
  EVC: 'ECM',
  LOG: 'RDT',
  FIR: 'FMT',
  DANA: 'RDANA',
  SEC: 'SST',
}
const ANNEX_H_TEAM_NAMES = {
  SRR: 'Search, Rescue and Retrieval Team',
  ECM: 'Evacuation and Camp Management Team',
  HFAPS: 'Health, First Aid and Psychosocial Support Team',
  BHERT: 'Barangay Health Emergency Response Team',
  SST: 'Security and Safety Team',
  FMT: 'Fire Management Team',
  RDT: 'Relief Distribution Team',
  TRT: 'Transportation Team',
  CLT: 'Clearing Operations Team',
  CWT: 'Communication and Warning Team',
  RDANA: 'Rapid Damage Assessment and Needs Analysis Team',
  PDANA: 'Post-Disaster Assessment and Needs Analysis Team',
  RPT: 'Research and Planning Team',
  LHT: 'Livelihood Team',
  ISH: 'Infrastructure and Shelter Team',
}

export function annexHTeamCode(team) {
  const code = String(team?.team_code || '').trim().toUpperCase()
  return LEGACY_TEAM_CODES[code] || code
}

export function annexHTeamCodeLabel(team) {
  const rawCode = String(team?.team_code || '').trim().toUpperCase()
  if (rawCode === 'LOG') return 'RDT / TRT (legacy LOG)'
  const code = annexHTeamCode(team)
  return rawCode && rawCode !== code ? `${code} (legacy ${rawCode})` : code
}

export function annexHTeamName(team) {
  if (String(team?.team_code || '').trim().toUpperCase() === 'LOG') {
    return 'Relief Distribution / Transportation Team (legacy combined unit)'
  }
  const code = annexHTeamCode(team)
  return ANNEX_H_TEAM_NAMES[code] || team?.team_name || 'Unnamed team'
}

export function committeeKeyForTeam(team) {
  const code = annexHTeamCode(team)
  const type = String(team?.team_type || '').trim().toLowerCase()

  if (code === 'HQCC' || ['hq', 'hqcc', 'headquarters'].includes(type)) return 'leadership'

  const explicitCommittee = ({
    mitigation_prevention: 'mitigation_prevention',
    'mitigation and prevention': 'mitigation_prevention',
    'mitigation and prevention sub-committee': 'mitigation_prevention',
    'mitigation & prevention': 'mitigation_prevention',
    'mitigation & prevention sub-committee': 'mitigation_prevention',
    preparedness: 'preparedness',
    'preparedness sub-committee': 'preparedness',
    response: 'response',
    'response sub-committee': 'response',
    recovery_rehabilitation: 'recovery_rehabilitation',
    'recovery and rehabilitation': 'recovery_rehabilitation',
    'recovery and rehabilitation sub-committee': 'recovery_rehabilitation',
  })[type]
  if (subcommitteeKeys.has(explicitCommittee)) return explicitCommittee

  return ANNEX_H_TEAM_GROUPS.find(group => group.teamCodes.includes(code))?.committeeKey || 'unassigned'
}

export function teamGroupKey(team) {
  const code = String(team?.team_code || '').trim().toUpperCase()
  const committeeKey = committeeKeyForTeam(team)
  const groupByCommittee = {
    mitigation_prevention: 'research_planning',
    preparedness: 'preparedness',
    response: 'operations_warning',
  }

  return groupByCommittee[committeeKey]
    || ANNEX_H_TEAM_GROUPS.find(group => group.teamCodes.includes(code))?.key
    || groupByCommittee[committeeKey]
    || 'unassigned'
}

export function teamsForCommittee(teams, committeeKey) {
  return teams.filter(team => committeeKeyForTeam(team) === committeeKey)
}

export function roleAccountsForCommittee(responders, roleKeys) {
  const keys = new Set(roleKeys)
  return responders.filter(responder => keys.has(responder.role_key))
}
