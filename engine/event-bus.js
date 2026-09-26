// @loom-file release=0.12.08 revision=3 policy=package-priority
(() => {
  'use strict';
  class LoomEventBus {
    constructor(project, identity=null) {
      this.project=project; this.identity=identity;
      this.channelName=`loom:${project}:events`;
      this.channel='BroadcastChannel' in window ? new BroadcastChannel(this.channelName) : null;
      this.listeners=new Set(); this.storageKey=`${this.channelName}:last`;
      if(this.channel)this.channel.onmessage=e=>this._deliver(e.data);
      addEventListener('storage',e=>{if(e.key===this.storageKey&&e.newValue){try{this._deliver(JSON.parse(e.newValue))}catch{}}});
    }
    on(fn){this.listeners.add(fn);return()=>this.listeners.delete(fn)}
    emit(event){
      const i=this.identity||{};
      const payload={
        id:crypto?.randomUUID?.()||`${Date.now()}_${Math.random().toString(36).slice(2)}`,
        project:this.project,installationId:i.installationId||null,clientId:i.clientId||null,sessionId:i.sessionId||null,userId:i.userId||null,userLabel:i.userLabel||null,
        clientTimestamp:new Date().toISOString(),...event
      };
      this.channel?.postMessage(payload);try{localStorage.setItem(this.storageKey,JSON.stringify(payload))}catch{}
      this._deliver(payload);return payload;
    }
    _deliver(payload){for(const fn of this.listeners){try{fn(payload)}catch(err){console.error(err)}}}
    close(){this.channel?.close();this.listeners.clear()}
  }
  window.LoomEventBus=LoomEventBus;
  window.PegboardEventBus=LoomEventBus;
})();
