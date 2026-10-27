export function normalizeApiServerUrl(value: string, requireHttps: boolean) {
  const parsed = new URL(value.trim());
  const secureProtocol = parsed.protocol === 'https:';
  const developmentProtocol = !requireHttps && parsed.protocol === 'http:';

  if ((!secureProtocol && !developmentProtocol) || parsed.username || parsed.password || parsed.search || parsed.hash) {
    throw new Error(requireHttps
      ? 'Enter an HTTPS server URL without passwords or query parameters.'
      : 'Enter a server URL beginning with http:// or https://, without passwords or query parameters.');
  }

  let path = parsed.pathname.replace(/\/+$/, '');
  if (!path) path = '/api/v1';
  if (!path.endsWith('/api/v1')) throw new Error('The server address must end with /api/v1.');
  return `${parsed.origin}${path}`;
}
