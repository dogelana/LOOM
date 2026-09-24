// @loom-file release=0.12.08 revision=3 policy=package-priority
(() => {
  'use strict';
  const makeId=(prefix)=>`${prefix}_${crypto?.randomUUID?.() || (Date.now().toString(36)+Math.random().toString(36).slice(2))}`;
  const safeGet=(store,key)=>{try{return store.getItem(key)}catch{return null}};
  const safeSet=(store,key,value)=>{try{store.setItem(key,value)}catch{}};
  const profileKey=clientId=>`loom:user-profile:${clientId}`;
  const installationKey='loom:installation-id',activeClientKey='loom:active-client-id';
  function storedProfile(clientId){try{return JSON.parse(safeGet(localStorage,profileKey(clientId))||'{}')||{}}catch{return {}}}
  function setPrivilege(clientId,privilege){
    const clean=String(privilege||'User')==='Admin'?'Admin':'User';
    if(!clientId)return clean;
    const current=storedProfile(clientId);const next={...current,privilege:clean,updatedAt:new Date().toISOString()};safeSet(localStorage,profileKey(clientId),JSON.stringify(next));
    return clean;
  }
  function setUserId(clientId,userId){
    if(!clientId)return null;
    const clean=userId?String(userId).replace(/[^a-zA-Z0-9_.-]/g,'').slice(0,96):null;
    const current=storedProfile(clientId);const next={...current,userId:clean,updatedAt:new Date().toISOString()};safeSet(localStorage,profileKey(clientId),JSON.stringify(next));
    return clean;
  }
  function setUserLabel(clientId,label){
    const clean=String(label??'').replace(/[\u0000-\u001F\u007F]/g,'').replace(/\s+/g,' ').trim().slice(0,40);
    if(!clientId||!clean)return null;
    const current=storedProfile(clientId);const next={...current,username:clean,updatedAt:new Date().toISOString()};safeSet(localStorage,profileKey(clientId),JSON.stringify(next));
    return clean;
  }
  function get(project='project'){
    const clientKey='loom:client-id';
    let installationId=safeGet(localStorage,installationKey);if(!installationId){installationId=makeId('install').replace(/-/g,'');safeSet(localStorage,installationKey,installationId);}
    let clientId=safeGet(localStorage,activeClientKey)||safeGet(localStorage,clientKey);
    if(!clientId){clientId=makeId('client');safeSet(localStorage,clientKey,clientId);safeSet(localStorage,activeClientKey,clientId);}
    const sessionKey=`loom:${project}:session-id`;
    let sessionId=safeGet(sessionStorage,sessionKey);
    if(!sessionId){sessionId=makeId('session');safeSet(sessionStorage,sessionKey,sessionId);}
    const saved=storedProfile(clientId);
    const userId=(window.LOOM_USER_ID ?? saved.userId ?? null);
    const explicit=(window.LOOM_USER_LABEL ?? null);
    const short=clientId.replace(/^client_/,'').replace(/-/g,'').slice(0,8).toUpperCase();
    return {
      installationId,clientId, sessionId, userId, userLabel:explicit || saved.username || `Anonymous ${short}`,
      profileId:saved.profileId||null, privilege:saved.privilege==='Admin'?'Admin':'User',
      meta:{
        language:navigator.language || null,
        languages:Array.from(navigator.languages||[]),
        timezone:Intl.DateTimeFormat().resolvedOptions().timeZone || null,
        userAgent:navigator.userAgent || null,
        platform:navigator.platform || null,
        viewport:{width:innerWidth,height:innerHeight,devicePixelRatio:devicePixelRatio||1},
        page:location.pathname
      }
    };
  }
  function setActiveClient(clientId){const clean=String(clientId||'').replace(/[^a-zA-Z0-9_.-]/g,'').slice(0,96);if(!clean||!clean.startsWith('client_'))return null;safeSet(localStorage,activeClientKey,clean);safeSet(localStorage,'loom:client-id',clean);return clean}
  function getInstallationId(){let id=safeGet(localStorage,installationKey);if(!id){id=makeId('install').replace(/-/g,'');safeSet(localStorage,installationKey,id)}return id}
  window.LoomIdentity=Object.freeze({get,setUserLabel,setUserId,setPrivilege,storedProfile,setActiveClient,getInstallationId});
})();
