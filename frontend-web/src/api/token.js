const tokenKey = 'resqperation_web_token'

export function getToken() {
  return localStorage.getItem(tokenKey) || sessionStorage.getItem(tokenKey)
}

export function saveToken(token, remember = false) {
  localStorage.removeItem(tokenKey)
  sessionStorage.removeItem(tokenKey)
  ;(remember ? localStorage : sessionStorage).setItem(tokenKey, token)
}

export function clearToken() {
  localStorage.removeItem(tokenKey)
  sessionStorage.removeItem(tokenKey)
}
