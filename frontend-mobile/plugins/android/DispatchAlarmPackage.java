package __PACKAGE__;

import android.content.Context;
import androidx.annotation.NonNull;
import com.facebook.react.ReactPackage;
import com.facebook.react.bridge.*;
import com.facebook.react.uimanager.ViewManager;
import java.util.Collections;
import java.util.List;

public class DispatchAlarmPackage implements ReactPackage {
    @Override public List<NativeModule> createNativeModules(ReactApplicationContext context) {
        return Collections.singletonList(new AlarmModule(context));
    }
    @Override public List<ViewManager> createViewManagers(ReactApplicationContext context) {
        return Collections.emptyList();
    }

    public static class AlarmModule extends ReactContextBaseJavaModule {
        AlarmModule(ReactApplicationContext context) { super(context); }
        @NonNull @Override public String getName() { return "DispatchAlarm"; }

        @ReactMethod public void acknowledge(String assignmentId, Promise promise) {
            try {
                Context context = getReactApplicationContext();
                DispatchAlarmService.acknowledge(context, assignmentId);
                promise.resolve(null);
            } catch (RuntimeException error) {
                promise.reject("dispatch_alarm_stop", "Could not stop the dispatch alarm", error);
            }
        }
    }
}
