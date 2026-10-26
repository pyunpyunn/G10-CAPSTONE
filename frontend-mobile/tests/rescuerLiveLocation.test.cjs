const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const ts=require('typescript');
const flush=()=>new Promise(setImmediate);
function fixture() {
 const storage=new Map([['resqperation_mobile_token','session-a']]);
 const calls=[],tasks={},timers=new Set();let foreground,appListener,background=false,removed=0,currentFix;
 const fix=(latitude=10.29, timestamp=Date.now(),accuracy=5)=>({timestamp,coords:{latitude,longitude:123.88,accuracy,heading:null,speed:null,altitude:null,altitudeAccuracy:null}});
 currentFix=fix(10.29,Date.now()-20000);
 const location={Accuracy:{High:4,Highest:6},async requestForegroundPermissionsAsync(){return {granted:true};},async requestBackgroundPermissionsAsync(){return {granted:true};},
  async getCurrentPositionAsync(){return currentFix;},async watchPositionAsync(options,callback){calls.push(['watch',options]);foreground=callback;return {remove(){removed++;}};},
  async hasStartedLocationUpdatesAsync(){return background;},async stopLocationUpdatesAsync(){background=false;calls.push(['stop-background']);},async startLocationUpdatesAsync(name,options){background=true;calls.push(['background',options]);}};
 const app={currentState:'active',addEventListener(_,callback){appListener=callback;return {remove(){appListener=null;}};}};
 const exports={};
 const mocks={'expo-location':location,'expo-task-manager':{defineTask(name,callback){tasks[name]=callback;}},'react-native':{AppState:app,Platform:{OS:'android'}},
 '@/api/client':{api:{async post(url,payload,config){calls.push(['post',url,payload,config]);return {};}}},
 './secureStorage':{async getStoredItem(key){return storage.get(key)||null;},async setStoredItem(key,value){storage.set(key,value);},async deleteStoredItem(key){storage.delete(key);}}};
 vm.runInNewContext(ts.transpileModule(fs.readFileSync(path.join(__dirname,'../utils/rescuerLiveLocation.ts'),'utf8'),{compilerOptions:{module:ts.ModuleKind.CommonJS,target:ts.ScriptTarget.ES2022}}).outputText,
 {exports,AbortController,setInterval(fn){timers.add(fn);return fn;},clearInterval(fn){timers.delete(fn);},require(name){if(!(name in mocks))throw Error(name);return mocks[name];}});
 return {runtime:exports,calls,fix,storage,timers,location,background:()=>background,removed:()=>removed,foreground:fix=>foreground(fix),setCurrentFix:fix=>{currentFix=fix;},
 async sendBackground(fix){await tasks['resq-active-rescuer-live-location']({data:{locations:[fix]}});},state(value){app.currentState=value;appListener?.(value);}};
}
test('active dispatch uses a paced high-accuracy GPS watch and background updates without starting a trip',async()=>{
 const f=fixture();await f.runtime.configureRescuerTracking(71);await flush();
 const watch=f.calls.find(x=>x[0]==='watch')[1],background=f.calls.find(x=>x[0]==='background')[1];
 assert.equal(watch.accuracy,4);assert.equal(watch.timeInterval,5000);assert.equal(watch.distanceInterval,5);
 assert.equal(background.timeInterval,10000);assert.equal(background.distanceInterval,10);assert.equal(background.pausesUpdatesAutomatically,false);
 assert.equal(f.calls.find(x=>x[0]==='post')[1],'/rescuer/assignments/71/location');assert.ok(f.background());
 await f.runtime.stopRescuerTracking();
});
test('screen-locked fixes update the shared mobile position and post the same GPS to HQ',async()=>{
 const f=fixture(),seen=[];f.runtime.subscribeRescuerPosition(fix=>seen.push(fix));
 f.setCurrentFix(f.fix(10.29,Date.now()-20000));await f.runtime.configureRescuerTracking(71);await flush();f.state('background');
 const current=f.fix(10.295,Date.now()+1);await f.sendBackground(current);await flush();
 assert.equal(seen.at(-1).coords.latitude,10.295);
 const post=f.calls.filter(x=>x[0]==='post').at(-1);assert.equal(post[2].latitude,10.295);assert.equal(post[2].recorded_at,new Date(current.timestamp).toISOString());
 await f.runtime.stopRescuerTracking();
});
test('GPS uploads are suppressed until movement and elapsed-time thresholds are met',async()=>{
 const f=fixture();await f.runtime.configureRescuerTracking(71);await flush();
 const initialCount=f.calls.filter(x=>x[0]==='post').length;
 f.foreground(f.fix(10.29001,Date.now(),5));await flush();
 assert.equal(f.calls.filter(x=>x[0]==='post').length,initialCount,'small GPS jitter is not uploaded');
 f.foreground(f.fix(10.2902,Date.now(),5));await flush();
 assert.equal(f.calls.filter(x=>x[0]==='post').length,initialCount+1,'a meaningful move is uploaded after the minimum interval');
 assert.equal(f.calls.filter(x=>x[0]==='post').at(-1)[2].refresh_route,false,'route recalculation is separately throttled');
 await f.runtime.stopRescuerTracking();
});
test('completion/logout stops tracking and prevents late background uploads',async()=>{
 const f=fixture();await f.runtime.configureRescuerTracking(71);await flush();await f.runtime.stopRescuerTracking();
 const count=f.calls.filter(x=>x[0]==='post').length;
 await f.sendBackground(f.fix(10.3));assert.equal(f.calls.filter(x=>x[0]==='post').length,count);assert.equal(f.background(),false);assert.equal(f.timers.size,0);
});
test('a previous accounts background callback cannot post using a new session',async()=>{
 const f=fixture();await f.runtime.configureRescuerTracking(71);await flush();const count=f.calls.filter(x=>x[0]==='post').length;
 f.storage.set('resqperation_mobile_token','session-b');await f.sendBackground(f.fix(10.3));assert.equal(f.calls.filter(x=>x[0]==='post').length,count);
 await f.runtime.stopRescuerTracking();
});
test('stale fixes are rejected while a fresh approximate fix still updates the visible position',async()=>{
 const f=fixture(),seen=[];f.runtime.subscribeRescuerPosition(fix=>seen.push(fix));await f.runtime.configureRescuerTracking(71);await flush();
 const count=seen.length;f.foreground(f.fix(11,Date.now()-60000));assert.equal(seen.length,count);
 f.foreground(f.fix(10.296,Date.now()+1,150));await flush();assert.equal(seen.at(-1).coords.latitude,10.296);
 await f.runtime.stopRescuerTracking();
});
