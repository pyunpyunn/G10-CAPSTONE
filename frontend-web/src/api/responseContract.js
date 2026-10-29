export function assertJsonApiResponse(response) {
  const type = response.config?.responseType
  if (type && type !== 'json') return response
  if ([204, 205].includes(response.status) || response.config?.method?.toLowerCase() === 'head') return response
  if (response.data !== null && typeof response.data === 'object') return response

  const error = new Error('The server returned an unexpected response. Records could not be verified. Please refresh or contact the administrator.')
  error.code = 'INVALID_API_RESPONSE'
  error.friendlyMessage = error.message
  throw error
}
