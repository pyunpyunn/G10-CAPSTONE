const { withAndroidManifest, withDangerousMod } = require('@expo/config-plugins');
const fs = require('fs');
const path = require('path');

module.exports = function withDispatchAlarm(config) {
  const pkg = config.android.package;
  config = withAndroidManifest(config, (mod) => {
    const manifest = mod.modResults.manifest;
    manifest['uses-permission'] ||= [];
    for (const name of ['android.permission.FOREGROUND_SERVICE', 'android.permission.FOREGROUND_SERVICE_MEDIA_PLAYBACK', 'android.permission.WAKE_LOCK']) {
      if (!manifest['uses-permission'].some((item) => item.$['android:name'] === name)) manifest['uses-permission'].push({ $: { 'android:name': name } });
    }
    const app = manifest.application[0];
    app['meta-data'] ||= [];
    app['meta-data'] = app['meta-data'].filter((item) => item.$['android:name'] !== 'com.onesignal.NotificationServiceExtension');
    app['meta-data'].push({ $: { 'android:name': 'com.onesignal.NotificationServiceExtension', 'android:value': `${pkg}.DispatchNotificationExtension` } });
    app.service ||= [];
    app.service = app.service.filter((item) => item.$['android:name'] !== `${pkg}.DispatchAlarmService`);
    app.service.push({ $: { 'android:name': `${pkg}.DispatchAlarmService`, 'android:exported': 'false', 'android:foregroundServiceType': 'mediaPlayback' } });
    return mod;
  });
  return withDangerousMod(config, ['android', (mod) => {
    const target = path.join(mod.modRequest.platformProjectRoot, 'app/src/main/java', ...pkg.split('.'));
    fs.mkdirSync(target, { recursive: true });
    for (const name of ['DispatchNotificationExtension', 'DispatchAlarmService']) {
      const source = fs.readFileSync(path.join(__dirname, 'android', `${name}.java`), 'utf8');
      fs.writeFileSync(path.join(target, `${name}.java`), source.replaceAll('__PACKAGE__', pkg));
    }
    return mod;
  }]);
};
