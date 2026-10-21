package __PACKAGE__;

import android.content.Intent;
import android.os.Build;
import android.util.Log;
import androidx.annotation.Keep;
import com.onesignal.notifications.INotificationReceivedEvent;
import com.onesignal.notifications.INotificationServiceExtension;
import org.json.JSONObject;

@Keep
public class DispatchNotificationExtension implements INotificationServiceExtension {
    @Override public void onNotificationReceived(INotificationReceivedEvent event) {
        JSONObject data = event.getNotification().getAdditionalData();
        if (data == null || !"rescue_dispatch".equals(data.optString("type"))) return;
        JSONObject alarm = data.optJSONObject("alarm");
        if (alarm == null) return;
        Intent intent = new Intent(event.getContext(), DispatchAlarmService.class);
        intent.putExtra("alarm_id", data.optString("alarm_id", data.optString("assignment_id")));
        intent.putExtra("sound_seconds", alarm.optInt("sound_seconds", 60));
        intent.putExtra("pause_seconds", alarm.optInt("pause_seconds", 15));
        intent.putExtra("cycles", alarm.optInt("cycles", 4));
        try {
            if (Build.VERSION.SDK_INT >= 26) event.getContext().startForegroundService(intent);
            else event.getContext().startService(intent);
        } catch (RuntimeException error) {
            // Android may deny background service starts. Keep the ordinary push visible.
            Log.w("DispatchAlarm", "Alarm service start denied; standard notification remains available", error);
        }
    }
}
