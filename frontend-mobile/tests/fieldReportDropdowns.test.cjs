const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const ts = require('typescript');

async function screen() {
  const state = [], effects = [], submitted = [];
  let cursor = 0, initial = true;
  const options = [
    { key:'unknown', label:'Unchecked' }, { key:'safe', label:'Safe' },
    { key:'injured', label:'Injured', counts_as_injury:true },
    { key:'deceased', label:'Deceased', counts_as_death:true },
  ];
  const data = { household:{household_id:'HH-1',household_name:'Test household'},
    member_status_options:options, property_damage_options:[{key:'db_level',label:'Database damage level'}],
    members:[{member_id:'M1',name:'Member one',member_status:{key:'unknown'}},{member_id:'M2',name:'Member two',member_status:{key:'safe'}}] };
  const exports = {};
  const code = ts.transpileModule(fs.readFileSync(path.join(__dirname,'../components/rescuer/FieldReportScreen.tsx'),'utf8'),{compilerOptions:{module:ts.ModuleKind.CommonJS,jsx:ts.JsxEmit.ReactJSX}}).outputText;
  vm.runInNewContext(code,{exports,require(name) {
    if(name==='react/jsx-runtime')return require(name);
    if(name==='react')return {useEffect(fn){if(initial)effects.push(fn);},useRef:()=>({current:'test-key'}),useState(value){const index=cursor++;if(!(index in state))state[index]=value;return [state[index],value=>{state[index]=typeof value==='function'?value(state[index]):value;}];}};
    if(name==='react-native')return {Alert:{alert(){}},Text:'Text',View:'View',TextInput:'TextInput',StyleSheet:{create:x=>x}};
    if(name==='@/api/rescuer')return {getAssignedHousehold:async()=>data};
    if(name==='@/constants/resqTheme')return {palette:{},radius:{},spacing:{}};
    if(name==='./ReportSelect')return {ReportSelect:'ReportSelect'};
    if(name==='./RescuerUI')return Object.fromEntries(['ActionButton','SectionHeader','StatTile'].map(x=>[x,x]));
    throw Error('Unexpected import '+name);
  }});
  const render=()=>{cursor=0;return exports.FieldReportScreen({reports:[],assignmentId:7,householdId:'HH-1',onSubmitReport:async payload=>submitted.push(payload)});};
  render(); initial=false; effects.forEach(fn=>fn()); await new Promise(setImmediate);
  return {render,submitted};
}
function nodes(node) {
  if(Array.isArray(node))return node.flatMap(nodes);
  return node?.props?[node,...nodes(node.props.children)]:[];
}

test('member dropdowns preserve database statuses including Unchecked and use database damage options',async()=>{
  const app=await screen();const all=nodes(app.render());const selects=all.filter(x=>x.type==='ReportSelect');
  assert.equal(selects[0].props.value,'unknown');assert.equal(selects[1].props.value,'safe');
  assert.equal(selects[2].props.options[0].key,'db_level');
  assert.ok(!all.some(x=>x.type==='TextInput'&&['Deaths','Injured'].includes(x.props.placeholder)));
});
test('deaths and injuries change automatically with member selections',async()=>{
  const app=await screen();let all=nodes(app.render());let selects=all.filter(x=>x.type==='ReportSelect');
  selects[0].props.onChange('deceased');selects[1].props.onChange('injured');
  all=nodes(app.render());assert.equal(all.find(x=>x.type==='StatTile'&&x.props.label==='Deaths').props.value,1);
  assert.equal(all.find(x=>x.type==='StatTile'&&x.props.label==='Injured').props.value,1);
});
test('submission sends selected statuses and calculated counts without an incident type',async()=>{
  const app=await screen();let all=nodes(app.render());const selects=all.filter(x=>x.type==='ReportSelect');
  selects[0].props.onChange('injured');selects[2].props.onChange('db_level');
  all=nodes(app.render());await all.find(x=>x.type==='ActionButton'&&x.props.label==='Submit report').props.onPress();
  assert.equal(app.submitted.length,1);assert.equal(app.submitted[0].injured_count,1);assert.equal(app.submitted[0].death_count,0);
  assert.equal(app.submitted[0].property_damage,'db_level');assert.equal(app.submitted[0].incident_type,undefined);
});
