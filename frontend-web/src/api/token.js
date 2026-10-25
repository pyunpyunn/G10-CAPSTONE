import { resetApiState } from '../state/apiStore.js'
const tokenKey = 'resqperation_web_token'

export function getToken() {
  return localStorage.getItem(tokenKey)
}

export function saveToken(token) {
  resetApiState()
  localStorage.setItem(tokenKey, token)
}

export function clearToken() {
  resetApiState()
  localStorage.removeItem(tokenKey)
}
