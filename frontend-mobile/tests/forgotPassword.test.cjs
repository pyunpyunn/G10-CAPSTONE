const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const ts = require('typescript');

function setup(send) {
  const values = [];
  let cursor = 0;
  const hook = (initial) => {
    const index = cursor++;
    if (!(index in values)) values[index] = initial;
    return [values[index], (next) => { values[index] = typeof next === 'function' ? next(values[index]) : next; }];
  };
  const exports = {};
  const source = fs.readFileSync(path.join(__dirname, '../app/forgot-password.tsx'), 'utf8');
  vm.runInNewContext(ts.transpileModule(source, {
    compilerOptions: { module: ts.ModuleKind.CommonJS, jsx: ts.JsxEmit.ReactJSX },
  }).outputText, {
    exports,
    require(name) {
      if (name === 'react') return { useState: hook, useRef: (value) => hook({ current: value })[0], useEffect() {} };
      if (name === 'react/jsx-runtime') return { jsx: (type, props) => ({ type, props }), jsxs: (type, props) => ({ type, props }) };
      if (name === 'react-native') return { Platform: { OS: 'android' }, StyleSheet: { create: (styles) => styles },
        ...Object.fromEntries(['ActivityIndicator', 'KeyboardAvoidingView', 'Pressable', 'ScrollView', 'Text', 'TextInput', 'View'].map(name => [name, name])) };
      if (name === 'expo-router') return { useRouter: () => ({}), useLocalSearchParams: () => ({}) };
      if (name === 'react-native-safe-area-context') return { SafeAreaView: 'SafeAreaView' };
      if (name === '@expo/vector-icons/Ionicons') return { default: 'Icon' };
      if (name === '@/constants/resqTheme') return { palette: {}, spacing: {} };
      if (name === '@/api/auth') return { lookupHouseholdRecoveryContact: async () => ({ masked_contact_number: '*********12' }), sendHouseholdRecoveryCode: send };
      throw Error(name);
    },
  });
  let nodes;
  function render() {
    cursor = 0;
    nodes = [];
    function walk(node) {
      if (Array.isArray(node)) return node.forEach(walk);
      if (!node || typeof node !== 'object') return;
      nodes.push(node);
      walk(node.props?.children);
    }
    walk(exports.default());
    return nodes;
  }
  const find = (type) => nodes.find(node => node.type === type);
  async function ready() {
    render();
    find('TextInput').props.onChangeText('HH-OTP-001');
    render();
    find('TextInput').props.onBlur();
    await new Promise(setImmediate);
    render();
    nodes.filter(node => node.type === 'TextInput')[1].props.onChangeText('09171234512');
    render();
  }
  return { ready, render, button: () => find('Pressable') };
}

test('one tap sends without phone blur and repeated taps do not duplicate the request', async () => {
  let complete;
  let calls = 0;
  const screen = setup((login, number) => {
    calls++;
    assert.equal(login, 'HH-OTP-001');
    assert.equal(number, '09171234512');
    return new Promise(resolve => { complete = resolve; });
  });
  await screen.ready();
  const button = screen.button();
  assert.equal(button.props.disabled, false);
  button.props.onPress();
  button.props.onPress();
  assert.equal(calls, 1);
  screen.render();
  assert.equal(screen.button().props.disabled, true);
  complete({ delivery_channel: 'sms_gateway' });
  await new Promise(setImmediate);
  const nodes = screen.render();
  assert.ok(nodes.some(node => node.props?.children === 'Resend in 1:00'));
});

test('confirmed gateway failure allows retry without a five-minute lock', async () => {
  const screen = setup(async () => { throw { response: { status: 503, data: { message: 'Gateway unavailable' } } }; });
  await screen.ready();
  screen.button().props.onPress();
  await new Promise(setImmediate);
  screen.render();
  assert.equal(screen.button().props.disabled, false);
});
