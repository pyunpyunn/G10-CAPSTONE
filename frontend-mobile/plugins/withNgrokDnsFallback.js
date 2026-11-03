const fs = require('fs');
const path = require('path');
const { withDangerousMod } = require('@expo/config-plugins');

module.exports = function withNgrokDnsFallback(config) {
  const packageName = config.android.package;

  return withDangerousMod(config, ['android', (mod) => {
    const javaRoot = path.join(mod.modRequest.platformProjectRoot, 'app/src/main/java', ...packageName.split('.'));
    fs.mkdirSync(javaRoot, { recursive: true });

    const dnsSource = fs.readFileSync(path.join(__dirname, 'android', 'NgrokDnsFallback.kt'), 'utf8');
    fs.writeFileSync(
      path.join(javaRoot, 'NgrokDnsFallback.kt'),
      dnsSource.replaceAll('__PACKAGE__', packageName),
    );

    const applicationPath = path.join(javaRoot, 'MainApplication.kt');
    let applicationSource = fs.readFileSync(applicationPath, 'utf8');
    if (!applicationSource.includes('OkHttpClientProvider.setOkHttpClientFactory')) {
      if (!applicationSource.includes('    loadReactNative(this)')) {
        throw new Error('Could not install the Ngrok DNS fallback in MainApplication.kt.');
      }

      applicationSource = applicationSource.replace(
        /^package ([^\r\n]+)\r?\n/,
        '$&\nimport com.facebook.react.modules.network.OkHttpClientProvider\n',
      );
      applicationSource = applicationSource.replace(
        '    loadReactNative(this)',
        [
          '    OkHttpClientProvider.setOkHttpClientFactory {',
          '      OkHttpClientProvider.createClientBuilder()',
          '        .dns(NgrokDnsFallback())',
          '        .build()',
          '    }',
          '    loadReactNative(this)',
        ].join('\n'),
      );
      fs.writeFileSync(applicationPath, applicationSource);
    }

    return mod;
  }]);
};
