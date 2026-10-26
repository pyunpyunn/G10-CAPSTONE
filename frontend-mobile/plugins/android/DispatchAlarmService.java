package __PACKAGE__;

import android.app.*;
import android.content.Intent;
import android.media.*;
import android.os.*;
import androidx.core.app.NotificationCompat;

public class DispatchAlarmService extends Service {
    private final Handler handler = new Handler(Looper.getMainLooper());
    private MediaPlayer player;
    private PowerManager.WakeLock wakeLock;
    private int cycles, soundMs, pauseMs;

    @Override public IBinder onBind(Intent intent) { return null; }

    @Override public int onStartCommand(Intent intent, int flags, int startId) {
        if (intent == null || "STOP".equals(intent.getAction())) { stopSelf(); return START_NOT_STICKY; }
        String id = intent.getStringExtra("alarm_id");
        if (id == null || id.isEmpty()) { stopSelf(); return START_NOT_STICKY; }
        // Persist a bounded deduplication window so retries cannot restart the siren.
        android.content.SharedPreferences prefs = getSharedPreferences("dispatch_alarm", MODE_PRIVATE);
        String seen = "seen_" + id;
        if (System.currentTimeMillis() - prefs.getLong(seen, 0) < 86400000L) {
            if (wakeLock == null) stopSelf();
            return START_NOT_STICKY;
        }
        String channel = "dispatch_alarm_playback";
        NotificationManager manager = getSystemService(NotificationManager.class);
        if (Build.VERSION.SDK_INT >= 26) {
            NotificationChannel nc = new NotificationChannel(channel, "Dispatch alarm", NotificationManager.IMPORTANCE_LOW);
            nc.setSound(null, null); manager.createNotificationChannel(nc);
        }
        Intent stop = new Intent(this, DispatchAlarmService.class).setAction("STOP");
        PendingIntent stopAction = PendingIntent.getService(this, 1, stop, PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE);
        Intent launch = getPackageManager().getLaunchIntentForPackage(getPackageName());
        NotificationCompat.Builder notification = new NotificationCompat.Builder(this, channel)
            .setSmallIcon(android.R.drawable.ic_dialog_alert).setContentTitle("New rescue dispatch")
            .setContentText("Open your assignment or stop the alarm.").setOngoing(true)
            .addAction(android.R.drawable.ic_media_pause, "Stop alarm", stopAction);
        if (launch != null) notification.setContentIntent(PendingIntent.getActivity(this, 2, launch, PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE));
        startForeground(4102, notification.build());
        prefs.edit().putLong(seen, System.currentTimeMillis()).apply();
        handler.removeCallbacksAndMessages(null); releasePlayer();
        cycles = Math.max(3, Math.min(4, intent.getIntExtra("cycles", 4)));
        soundMs = 60000;
        pauseMs = Math.max(10, Math.min(15, intent.getIntExtra("pause_seconds", 15))) * 1000;
        if (wakeLock != null && wakeLock.isHeld()) wakeLock.release();
        wakeLock = getSystemService(PowerManager.class).newWakeLock(PowerManager.PARTIAL_WAKE_LOCK, getPackageName() + ":dispatchAlarm");
        wakeLock.acquire(300000L);
        blast();
        return START_NOT_STICKY;
    }

    private void blast() {
        try {
            player = new MediaPlayer();
            player.setAudioAttributes(new AudioAttributes.Builder().setUsage(AudioAttributes.USAGE_ALARM).setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION).build());
            android.net.Uri sound = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_ALARM);
            if (sound == null) sound = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION);
            player.setDataSource(this, sound); player.setLooping(true); player.prepare(); player.start();
        } catch (Exception error) { releasePlayer(); stopSelf(); return; }
        handler.postDelayed(() -> {
            releasePlayer();
            if (--cycles > 0) handler.postDelayed(this::blast, pauseMs);
            else stopSelf();
        }, soundMs);
    }

    private void releasePlayer() { if (player != null) { player.release(); player = null; } }
    @Override public void onDestroy() {
        handler.removeCallbacksAndMessages(null); releasePlayer();
        if (wakeLock != null && wakeLock.isHeld()) wakeLock.release();
        stopForeground(true); super.onDestroy();
    }
}
