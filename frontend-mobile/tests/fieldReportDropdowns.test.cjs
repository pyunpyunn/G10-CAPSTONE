const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const ts = require('typescript');

async function screen({ reports = [], saved = null } = {}) {
  const state = [], effects = [], submitted = [], corrected = [];
  let cursor = 0, initial = true;
  const options = [
    { key:'unknown', label:'Unchecked' }, { key:'safe', label:'Safe' },
    { key:'injured', label:'Injured', counts_as_injury:true },
    { key:'deceased', label:'Deceased', counts_as_death:true },
  ];
  const data = { household:{household_id:'HH-1',household_name:'Test household'},
    member_status_options:options, property_damage_options:[{key:'db_level',label:'Database damage level'}],
    saved_counts:saved?.counts, saved_property_damage:saved?.damage, saved_notes:saved?.notes || '',
    saved_report_id:saved?.id, members:saved?.members || [
      {member_id:'M1',name:'Member one',member_status:{key:'unknown'}},
      {member_id:'M2',name:'Member two',member_status:{key:'safe'}},
    ] };
  const exports = {};
  const code = ts.transpileModule(fs.readFileSync(path.join(__dirname,'../components/rescuer/FieldReportScreen.tsx'),'utf8'),{compilerOptions:{module:ts.ModuleKind.CommonJS,jsx:ts.JsxEmit.ReactJSX}}).outputText;
  vm.runInNewContext(code,{exports,require(name) {
    if(name==='react/jsx-runtime')return require(name);
    if(name==='react')return {useEffect(fn){if(initial)effects.push(fn);},useRef:()=>({current:'test-key'}),useState(value){const index=cursor++;if(!(index in state))state[index]=value;return [state[index],value=>{state[index]=typeof value==='function'?value(state[index]):value;}];}};
    if(name==='react-native')return {Alert:{alert(){}},Text:'Text',View:'View',TextInput:'TextInput',Pressable:'Pressable',StyleSheet:{create:x=>x}};
    if(name==='expo-crypto')return {randomUUID:()=>`key-${Math.random()}`};
    if(name==='@/api/rescuer')return {getAssignedHousehold:async()=>data};
    if(name==='@/constants/resqTheme')return {palette:{},radius:{},spacing:{xs:4,sm:8,md:12,lg:16}};
    if(name==='./ReportSelect')return {ReportSelect:'ReportSelect'};
    if(name==='./RescuerUI')return Object.fromEntries(['ActionButton','SectionHeader'].map(x=>[x,x]));
    throw Error('Unexpected import '+name);
  }});
  const render=()=>{cursor=0;return exports.FieldReportScreen({reports,assignmentId:7,householdId:'HH-1',
    onSubmitReport:async payload=>submitted.push(payload),onCorrectReport:async (reportId,payload)=>corrected.push({reportId,payload})});};
  render(); initial=false; effects.forEach(fn=>fn()); await new Promise(setImmediate);
  return {render,submitted,corrected};
}
function nodes(node) {
  if(Array.isArray(node))return node.flatMap(nodes);
  return node?.props?[node,...nodes(node.props.children),...nodes(node.props.action)]:[];
}
function countValue(all, label) {
  return all.find(node => node.type === 'Text' && node.props.accessibilityLabel === `${label} count`).props.children;
}

test('member dropdowns preserve database statuses including Unchecked and use database damage options',async()=>{
  const app=await screen();const all=nodes(app.render());const selects=all.filter(x=>x.type==='ReportSelect');
  assert.equal(selects[0].props.value,'unknown');assert.equal(selects[1].props.value,'safe');
  assert.equal(selects[2].props.options[0].key,'db_level');
  assert.ok(selects.every(select => select.props.disabled === false));
  assert.ok(!all.some(x=>x.type==='TextInput'&&['Deaths','Injured'].includes(x.props.placeholder)));
});
test('deaths and injuries change automatically with member selections',async()=>{
  const app=await screen();let all=nodes(app.render());let selects=all.filter(x=>x.type==='ReportSelect');
  selects[0].props.onChange('deceased');selects[1].props.onChange('injured');
  all=nodes(app.render());assert.equal(countValue(all,'Deaths'),1);assert.equal(countValue(all,'Injured'),1);
});
test('submission sends selected statuses and calculated counts without an incident type',async()=>{
  const app=await screen();let all=nodes(app.render());const selects=all.filter(x=>x.type==='ReportSelect');
  selects[0].props.onChange('injured');selects[2].props.onChange('db_level');
  all=nodes(app.render());await all.find(x=>x.type==='ActionButton'&&x.props.label==='Submit report').props.onPress();
  assert.equal(app.submitted.length,1);assert.equal(app.submitted[0].injured_count,1);assert.equal(app.submitted[0].death_count,0);
  assert.equal(app.submitted[0].property_damage,'db_level');assert.equal(app.submitted[0].incident_type,undefined);
});

test('casualty counter cards keep deaths, injured, and missing on one non-wrapping row', async () => {
  const app = await screen();
  const all = nodes(app.render());
  const counts = all.find(node => node.type === 'View' && node.props.style?.flexWrap === 'nowrap');
  assert.ok(counts);
  assert.equal(counts.props.children.length, 3);
  assert.deepEqual(Array.from(counts.props.children, tile => nodes(tile).find(node => node.type === 'Text' && ['Deaths','Injured','Missing'].includes(node.props.children)).props.children),
    ['Deaths','Injured','Missing']);
});

test('all three quantity cards have working plus/minus controls and submit their quantities', async () => {
  const app = await screen();
  let all = nodes(app.render());
  const press = label => {
    const button = nodes(app.render()).find(node => node.type === 'Pressable' && node.props.accessibilityLabel === label);
    assert.ok(button, label);
    assert.equal(button.props.disabled, false);
    button.props.onPress();
  };
  const zero = all.find(node => node.type === 'Pressable' && node.props.accessibilityLabel === 'Decrease deaths');
  assert.equal(zero.props.disabled, true);
  for (const label of ['deaths', 'injured', 'missing']) {
    press(`Increase ${label}`);
    press(`Increase ${label}`);
    press(`Decrease ${label}`);
    all = nodes(app.render());
    assert.equal(countValue(all,label[0].toUpperCase()+label.slice(1)),1);
  }
  all = nodes(app.render());
  all.filter(node => node.type === 'ReportSelect')[2].props.onChange('db_level');
  await nodes(app.render()).find(node => node.type === 'ActionButton' && node.props.label === 'Submit report').props.onPress();
  assert.equal(app.submitted[0].death_count, 1);
  assert.equal(app.submitted[0].injured_count, 1);
  assert.equal(app.submitted[0].missing_count, 1);
  const saved = nodes(app.render());
  assert.ok(saved.filter(node => node.type === 'ReportSelect').every(node => node.props.disabled));
});

test('a saved report can be edited and submitted as a correction', async () => {
  const app = await screen({
    reports:[{assignment_id:7,household_id:'HH-1'}],
    saved:{id:42,damage:'db_level',notes:'Original note',
      counts:{death_count:0,injured_count:1,missing_count:0},
      members:[{member_id:'M1',name:'Member one',member_status:{key:'unknown'}},
        {member_id:'M2',name:'Member two',member_status:{key:'injured'}}]},
  });
  let all = nodes(app.render());
  assert.equal(all.find(node=>node.type==='TextInput'&&node.props.accessibilityLabel==='Notes for HQ').props.value,'Original note');
  assert.ok(all.filter(node=>node.type==='ReportSelect').every(node=>node.props.disabled));
  all.find(node=>node.type==='ActionButton'&&node.props.label==='Edit saved report').props.onPress();
  all=nodes(app.render());
  assert.ok(all.filter(node=>node.type==='ReportSelect').every(node=>!node.props.disabled));
  all.filter(node=>node.type==='ReportSelect')[0].props.onChange('deceased');
  nodes(app.render()).find(node=>node.type==='Pressable'&&node.props.accessibilityLabel==='Increase deaths').props.onPress();
  await nodes(app.render()).find(node=>node.type==='ActionButton'&&node.props.label==='Save correction').props.onPress();
  assert.equal(app.corrected.length,1);
  assert.equal(app.corrected[0].reportId,42);
  assert.equal(app.corrected[0].payload.death_count,1);
  assert.equal(app.corrected[0].payload.injured_count,1);
});
