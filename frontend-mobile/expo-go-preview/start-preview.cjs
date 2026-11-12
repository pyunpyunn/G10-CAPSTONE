const path = require('path');
const { spawn } = require('child_process');

const expoCli = path.join(__dirname, 'node_modules', 'expo', 'bin', 'cli');
const appRoot = path.join(__dirname, 'app');
const child = spawn(
  process.execPath,
  [expoCli, ...process.argv.slice(2)],
  {
    cwd: __dirname,
    env: { ...process.env, EXPO_ROUTER_APP_ROOT: appRoot },
    stdio: 'inherit',
  }
);

child.on('error', (error) => {
  console.error('Failed to start the Expo Go preview:', error);
  process.exitCode = 1;
});

child.on('exit', (code, signal) => {
  if (signal) {
    process.kill(process.pid, signal);
    return;
  }
  process.exitCode = code ?? 1;
});
