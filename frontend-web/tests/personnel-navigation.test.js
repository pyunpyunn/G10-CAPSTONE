import test from 'node:test'
import assert from 'node:assert/strict'
import { personnelDestination, searchResultDestination } from '../src/utils/personnelNavigation.js'

test('rescuer results open the base accounts page with the requested profile selected', () => {
  const destination=searchResultDestination({type:'responders',href:'/rescuers',responder_id:2024035543})
  assert.equal(destination.to,'/rescuers')
  assert.equal(destination.state.selectedProfileId,'2024035543')
  assert.deepEqual(personnelDestination(2024035542),{to:'/rescuers',state:{selectedProfileId:'2024035542'}})
})
test('older cached search results also open the base accounts page', () => {
  assert.deepEqual(searchResultDestination({type:'responders',href:'/rescuers/view?id=2024035543'}),personnelDestination('2024035543'))
})
test('other search results keep their own destination', () => {
  assert.deepEqual(searchResultDestination({type:'households',href:'/households/HH-1'}),{to:'/households/HH-1'})
})
