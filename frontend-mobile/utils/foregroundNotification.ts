import { notifyDisasterBroadcast } from './mobileReadCache';

export function handleForegroundNotification(event: {
  getNotification(): { display(): void; additionalData?: { type?: string } };
}) {
  const notification = event.getNotification();
  // The native bridge defers foreground display to this handler.
  // Display first so a dashboard listener cannot suppress the Android alert.
  notification.display();
  if (notification.additionalData?.type === 'disaster_broadcast') {
    notifyDisasterBroadcast();
  }
}
