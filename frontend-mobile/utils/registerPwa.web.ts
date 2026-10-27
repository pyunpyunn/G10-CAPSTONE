export function registerPwa() {
  if (process.env.NODE_ENV !== 'production' || !('serviceWorker' in navigator) || !window.isSecureContext) return;
  void navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(error => {
    console.error('Offline app installation failed.', error);
  });
}
