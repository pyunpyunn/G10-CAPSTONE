import test from 'node:test'
import assert from 'node:assert/strict'
import { buildDispatchAreaGroups } from '../src/utils/dispatchHelpers.js'

test('dispatch households follow registered IDs when address names conflict', () => {
  const priorities = [
    { sitio_id: 1, sitio: 'Sitio A', puroks: [{ purok_id: 10, purok_name: 'Purok 1' }] },
    { sitio_id: 2, sitio: 'Sitio B', puroks: [{ purok_id: 20, purok_name: 'Purok 1' }] },
  ]
  const areas = [{ id: 'purok:20', sitio_id: 2, purok_id: 20, area_name: 'Purok 1, Sitio A', households: [{ household_id: 'household-B' }] }]
  const groups = buildDispatchAreaGroups(priorities, areas)
  assert.deepEqual(groups[0].cards[0].households, [])
  assert.deepEqual(groups[1].cards[0].households.map(h => h.household_id), ['household-B'])
})

test('unregistered address text cannot populate a registered purok dispatch', () => {
  const groups = buildDispatchAreaGroups(
    [{ sitio_id: 1, sitio: 'Sitio A', puroks: [{ purok_id: 10, purok_name: 'Purok 1' }] }],
    [{ id: 'unregistered', area_name: 'Purok 1, Sitio A', households: [{ household_id: 'unlinked' }] }],
  )
  assert.deepEqual(groups[0].cards[0].households, [])
})
