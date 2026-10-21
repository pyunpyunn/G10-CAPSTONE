import { notifyDisasterBroadcast } from './mobileReadCache';

export function handleForegroundNotification(event: {
  getNotification(): { display(): void; additionalData?: { type?: string } };
}) {
  const notification = event.getNotification();
  // The native bridge defers foreground display to this handler.
  // Display first so a dashboard listener cannot suppress the Android alert.
  notification.display();
  if (['disaster_broadcast', 'rescuer_broadcast', 'rescue_dispatch', 'rescue_dispatch_change'].includes(notification.additionalData?.type || '')) {
    notifyDisasterBroadcast();
  }
}
