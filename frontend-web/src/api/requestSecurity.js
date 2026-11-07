export function assertApiDestination(requestUrl, apiBaseUrl, pageUrl, production) {
  const trusted = new URL(apiBaseUrl, pageUrl)
  const target = new URL(requestUrl, pageUrl)
  if (!['http:', 'https:'].includes(target.protocol)
    || target.username || target.password || target.origin !== trusted.origin) {
    throw new Error('Blocked a request outside the configured API server.')
  }
  const isLocalOrPrivate = ['localhost', '127.0.0.1', '0.0.0.0', '::1', '[::1]'].includes(target.hostname)
    || /^10\.\d+\.\d+\.\d+$/.test(target.hostname)
    || /^192\.168\.\d+\.\d+$/.test(target.hostname)
    || /^172\.(1[6-9]|2\d|3[0-1])\.\d+\.\d+$/.test(target.hostname)
  if (production && !isLocalOrPrivate && target.protocol !== 'https:') {
    throw new Error('HTTPS is required for production API requests.')
  }
}
