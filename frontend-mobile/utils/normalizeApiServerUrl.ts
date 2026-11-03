export function normalizeApiServerUrl(value: string, requireHttps: boolean, allowPrivateHttp = false) {
  const parsed = new URL(value.trim());
  const secureProtocol = parsed.protocol === 'https:';
  const developmentProtocol = !requireHttps && parsed.protocol === 'http:';
  const privateNetworkProtocol = allowPrivateHttp && parsed.protocol === 'http:' && isPrivateIpv4(parsed.hostname);

  if ((!secureProtocol && !developmentProtocol && !privateNetworkProtocol)
    || parsed.username || parsed.password || parsed.search || parsed.hash) {
    throw new Error(requireHttps
      ? 'Enter an HTTPS server URL or a private IPv4 HTTP address without passwords or query parameters.'
      : 'Enter a server URL beginning with http:// or https://, without passwords or query parameters.');
  }

  let path = parsed.pathname.replace(/\/+$/, '');
  if (!path) path = '/api/v1';
  if (!path.endsWith('/api/v1')) throw new Error('The server address must end with /api/v1.');
  return `${parsed.origin}${path}`;
}

export function isPrivateIpv4(hostname: string) {
  const parts = hostname.split('.').map(Number);
  if (parts.length !== 4 || parts.some((part) => !Number.isInteger(part) || part < 0 || part > 255)) return false;
  return parts[0] === 10
    || parts[0] === 192 && parts[1] === 168
    || parts[0] === 172 && parts[1] >= 16 && parts[1] <= 31
    || parts[0] === 100 && parts[1] >= 64 && parts[1] <= 127;
}
