import test from 'node:test'
import assert from 'node:assert/strict'
import { assertApiDestination } from '../src/api/requestSecurity.js'

test('permits relative and absolute requests to the configured HTTPS API', () => {
  for (const url of ['/api/v1/auth/me', 'https://api.example.com/api/v1/auth/me']) {
    const base = url.startsWith('https:') ? 'https://api.example.com/api/v1' : '/api/v1'
    assert.doesNotThrow(() => assertApiDestination(url, base, 'https://app.example.com/', true))
  }
})

test('blocks credential exfiltration destinations and insecure production transport', () => {
  for (const url of ['https://evil.example/auth', '//evil.example/auth', 'https://user:pass@api.example.com/auth', 'http://api.example.com/auth', 'javascript:alert(1)']) {
    assert.throws(() => assertApiDestination(url, 'https://api.example.com/api/v1', 'https://app.example.com/', true))
  }
  assert.throws(() => assertApiDestination('/api/v1/auth', '/api/v1', 'http://app.example.com/', true))
  assert.doesNotThrow(() => assertApiDestination('/api/v1/auth', '/api/v1', 'http://localhost/', false))
  assert.doesNotThrow(() => assertApiDestination('/api/v1/auth', 'http://127.0.0.1:8000/api/v1', 'http://127.0.0.1:8000/', true))
  assert.doesNotThrow(() => assertApiDestination('/api/v1/auth', 'http://localhost:8000/api/v1', 'http://localhost:8000/', true))
})
