export function assertApiDestination(requestUrl, apiBaseUrl, pageUrl, production) {
  const trusted = new URL(apiBaseUrl, pageUrl)
  const target = new URL(requestUrl, pageUrl)
  if (!['http:', 'https:'].includes(target.protocol)
    || target.username || target.password || target.origin !== trusted.origin) {
    throw new Error('Blocked a request outside the configured API server.')
  }
  if (production && target.protocol !== 'https:') {
    throw new Error('HTTPS is required for production API requests.')
  }
}
