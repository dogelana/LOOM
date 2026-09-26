// @loom-file release=0.15.62 revision=1 policy=package-priority
(() => {
  'use strict';
  const SQL_NO_ZONE=/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?)$/;
  function parse(value){
    if(value instanceof Date)return Number.isNaN(value.getTime())?null:value;
    if(value===null||value===undefined||value==='')return null;
    if(typeof value==='number'){const d=new Date(value);return Number.isNaN(d.getTime())?null:d;}
    let s=String(value).trim();if(!s)return null;
    const m=s.match(SQL_NO_ZONE);if(m)s=`${m[1]}T${m[2]}Z`;
    const d=new Date(s);return Number.isNaN(d.getTime())?null:d;
  }
  function absolute(value){const d=parse(value);if(!d)return value?String(value):'—';try{return new Intl.DateTimeFormat(undefined,{year:'numeric',month:'short',day:'numeric',hour:'numeric',minute:'2-digit',timeZoneName:'short'}).format(d)}catch{return d.toLocaleString()}}
  function relative(value){const d=parse(value);if(!d)return '—';const sec=(d.getTime()-Date.now())/1000,abs=Math.abs(sec);let unit='second',div=1;if(abs>=31557600){unit='year';div=31557600}else if(abs>=2629800){unit='month';div=2629800}else if(abs>=604800){unit='week';div=604800}else if(abs>=86400){unit='day';div=86400}else if(abs>=3600){unit='hour';div=3600}else if(abs>=60){unit='minute';div=60}try{return new Intl.RelativeTimeFormat(undefined,{numeric:'auto'}).format(Math.round(sec/div),unit)}catch{return absolute(d)}}
  function format(value){const d=parse(value);if(!d)return value?String(value):'—';return `${relative(d)} · ${absolute(d)}`}
  function timeZone(){try{return Intl.DateTimeFormat().resolvedOptions().timeZone||'local time'}catch{return 'local time'}}
  window.LoomTime=Object.freeze({parse,absolute,relative,format,timeZone});
})();
