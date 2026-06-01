const tokenKey = 'resqperation_web_token'

export function getToken() {
  return localStorage.getItem(tokenKey)
}

export function saveToken(token) {
  localStorage.setItem(tokenKey, token)
}

export function clearToken() {
  localStorage.removeItem(tokenKey)
}
