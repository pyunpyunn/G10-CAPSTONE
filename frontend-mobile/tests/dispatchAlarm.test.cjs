const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const test = require('node:test');
const ts = require('typescript');

function load(file, imports) {
  const exports = {};
  const code = ts.transpileModule(fs.readFileSync(path.join(__dirname, '..', file), 'utf8'), {
    compilerOptions: { module: ts.ModuleKind.CommonJS },
  }).outputText;
  vm.runInNewContext(code, { exports, console, require(name) {
    if (name in imports) return imports[name];
    throw new Error(`Unexpected import ${name}`);
  }});
  return exports;
}

test('accepted and later statuses stop the Android alarm for the matching assignment', async () => {
  const calls = [];
  const { stopAcceptedDispatchAlarm } = load('utils/dispatchAlarm.ts', {
    'react-native': { Platform: { OS: 'android' }, NativeModules: { DispatchAlarm: { acknowledge: async id => calls.push(id) } } },
  });
  await stopAcceptedDispatchAlarm(21, 'dispatched');
  assert.deepEqual(calls, []);
  for (const status of ['accepted', 'en_route', 'on_scene', 'completed', 'cancelled']) await stopAcceptedDispatchAlarm(21, status);
  assert.deepEqual(calls, Array(5).fill('21'));
});

test('web and Expo Go do not require the native alarm module', async () => {
  for (const OS of ['web', 'android']) {
    const { stopAcceptedDispatchAlarm } = load('utils/dispatchAlarm.ts', {
      'react-native': { Platform: { OS }, NativeModules: {} },
    });
    await stopAcceptedDispatchAlarm(21, 'accepted');
  }
});

test('acceptance stops the alarm only after the server confirms the update', async () => {
  const calls = [];
  let reject = false;
  const api = load('api/rescuer.ts', {
    'react-native': { Platform: { OS: 'android' } },
    './client': {},
    './reportSubmission': { submitReport: async () => {
      calls.push('save');
      if (reject) throw new Error('Network failed');
      return { data: { data: { status: 'accepted' } } };
    } },
    '@/utils/dispatchAlarm': { stopAcceptedDispatchAlarm: async (id, status) => calls.push([id, status]) },
  });
  await api.updateAssignmentStatus(21, 'accepted');
  assert.deepEqual(calls, ['save', [21, 'accepted']]);
  calls.length = 0;
  reject = true;
  await assert.rejects(api.updateAssignmentStatus(21, 'accepted'), /Network failed/);
  assert.deepEqual(calls, ['save']);
});

test('Expo config registers the alarm bridge once and includes its native source', () => {
  const pluginPath = path.join(__dirname, '../plugins/withDispatchAlarm.js');
  const module = { exports: {} };
  let main;
  let dangerous;
  vm.runInNewContext(fs.readFileSync(pluginPath, 'utf8'), {
    module, __dirname: path.dirname(pluginPath), require(name) {
      if (name === '@expo/config-plugins') return {
        withAndroidManifest: config => config,
        withMainApplication: (config, action) => { main = action; return config; },
        withDangerousMod: (config, [, action]) => { dangerous = action; return config; },
      };
      if (name === 'fs') return { mkdirSync() {}, readFileSync: fs.readFileSync, writeFileSync(file, text) {
        if (file.endsWith('DispatchAlarmPackage.java')) assert.ok(text.includes('package com.example;'));
      } };
      return require(name);
    },
  });
  module.exports({ android: { package: 'com.example' } });
  const mod = { modResults: { contents: 'PackageList(this).packages.apply {' } };
  main(mod); main(mod);
  assert.equal(mod.modResults.contents.split('add(DispatchAlarmPackage())').length - 1, 1);
  dangerous({ modRequest: { platformProjectRoot: '/android' } });
});
