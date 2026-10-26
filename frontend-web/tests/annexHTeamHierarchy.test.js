import assert from 'node:assert/strict'
import test from 'node:test'
import {
  ANNEX_H_DESK_FUNCTIONS,
  ANNEX_H_TEAM_GROUPS,
  committeeKeyForTeam,
  roleAccountsForCommittee,
  teamGroupKey,
  teamsForCommittee,
} from '../src/utils/annexHTeamHierarchy.js'

test('team acronyms map to their Annex H committee and function group', () => {
  assert.equal(committeeKeyForTeam({ team_code: 'SRR', team_type: 'Rescue' }), 'response')
  assert.equal(teamGroupKey({ team_code: 'SRR', team_type: 'Rescue' }), 'operations_warning')
  assert.equal(committeeKeyForTeam({ team_code: 'RDANA', team_type: 'Assessment' }), 'mitigation_prevention')
  assert.equal(teamGroupKey({ team_code: 'RDANA', team_type: 'Assessment' }), 'research_planning')
  assert.equal(committeeKeyForTeam({ team_code: 'LHT', team_type: 'Livelihood' }), 'recovery_rehabilitation')
})

test('legacy team codes classify and display using the Annex H acronyms', () => {
  assert.equal(committeeKeyForTeam({ team_code: 'SAR', team_type: 'SAR' }), 'response')
  assert.equal(teamGroupKey({ team_code: 'MED', team_type: 'Medical' }), 'operations_warning')
  assert.equal(teamGroupKey({ team_code: 'DANA', team_type: 'Damage Assessment' }), 'research_planning')
})

test('explicit committee assignment overrides acronym defaults', () => {
  const team = { team_code: 'SRR', team_type: 'Recovery and Rehabilitation Sub-committee' }
  assert.equal(committeeKeyForTeam(team), 'recovery_rehabilitation')
  assert.equal(teamGroupKey(team), 'recovery_rehabilitation')
})

test('unknown teams stay unclassified until assigned', () => {
  assert.equal(committeeKeyForTeam({ team_code: 'NEW1', team_type: 'Rescue' }), 'unassigned')
  assert.equal(committeeKeyForTeam({ team_code: 'NEW1', team_type: 'Preparedness Sub-committee' }), 'preparedness')
})

test('Annex H unit catalog includes every configured field and planning team', () => {
  const codes = ANNEX_H_TEAM_GROUPS.flatMap(group => group.units.map(unit => unit.code))
  assert.deepEqual(codes.sort(), [
    'BHERT', 'CLT', 'ECM', 'FMT', 'HFAPS', 'PDANA', 'RDANA', 'RDT', 'SST', 'SRR', 'TRT',
  ].sort())
})

test('Annex H desk functions are visible but excluded from mobile team catalog', () => {
  const deskCodes = Object.values(ANNEX_H_DESK_FUNCTIONS).flatMap(units => units.map(unit => unit.code))
  const mobileCodes = ANNEX_H_TEAM_GROUPS.flatMap(group => group.units.map(unit => unit.code))
  assert.deepEqual(deskCodes.sort(), ['CWT', 'ISH', 'LHT', 'RPT'])
  assert.equal(deskCodes.some(code => mobileCodes.includes(code)), false)
  assert.equal(committeeKeyForTeam({ team_code: 'COM', team_type: 'Communication' }), 'unassigned')
})

test('committee filters distinguish web role accounts from team members', () => {
  const teams = [
    { team_code: 'SRR', team_type: 'Rescue' },
    { team_code: 'LHT', team_type: 'Livelihood' },
  ]
  const accounts = [
    { role_key: 'response', full_name: 'Responder' },
    { role_key: 'operations_admin', full_name: 'Technical admin' },
  ]

  assert.equal(teamsForCommittee(teams, 'response').length, 1)
  assert.deepEqual(roleAccountsForCommittee(accounts, ['response']).map(account => account.full_name), ['Responder'])
})
