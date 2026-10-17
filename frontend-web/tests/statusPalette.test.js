import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { statusColor, statusTone, toneColors } from '../src/utils/statusPalette.js'
import { weatherNumber, weatherDay } from '../src/utils/weatherDisplay.js'

test('all status aliases use the dashboard semantic colors', () => {
  for (const key of ['safe', 'safe-at-home', 'safe_at_home', 'completed', 'available']) assert.equal(statusColor(key), toneColors.green)
  for (const key of ['evacuated', 'relocated', 'dispatched', 'en-route', 'en_route']) assert.equal(statusColor(key), toneColors.blue)
  for (const key of ['unsafe', 'needs-help', 'needs_assistance', 'injured', 'missing']) assert.equal(statusColor(key), toneColors.red)
  assert.equal(statusColor('unchecked'), toneColors.gray)
  assert.equal(statusTone('on-scene'), 'purple')
  assert.equal(statusTone('returning'), 'amber')
  assert.equal(statusColor('unknown-status'), toneColors.gray)
})

test('API and mobile palettes agree with dashboard color tokens', () => {
  const foundation = readFileSync(new URL('../src/styles/foundation.css', import.meta.url), 'utf8')
  const php = readFileSync(new URL('../../backend-laravel/config/status_palette.php', import.meta.url), 'utf8')
  const mobile = readFileSync(new URL('../../frontend-mobile/constants/resqTheme.ts', import.meta.url), 'utf8')
  for (const hex of Object.values(toneColors)) {
    assert.ok(foundation.includes(hex), `Missing dashboard token ${hex}`)
    assert.ok(php.includes(hex), `API palette differs for ${hex}`)
    assert.ok(mobile.includes(hex), `Mobile palette differs for ${hex}`)
  }
})

test('weather distinguishes missing measurements from genuine zero values', () => {
  assert.equal(weatherNumber(0, ' mm'), '0 mm')
  assert.equal(weatherNumber(null, ' mm'), '—')
  assert.equal(weatherNumber(undefined, '%'), '—')
  assert.equal(weatherNumber('invalid', '°'), '—')
  assert.equal(weatherDay('2026-10-15T00:00:00+08:00', new Date('2026-10-14T18:00:00Z')), 'Today')
})
