(function () {
    'use strict';
    const cfg = window.NfiniteVideoEngagement || {};
    const state = { active: null, timer: null, lastWall: 0, position: 0, duration: 0, playing: false };
    const storage = window.localStorage;
    function uuid() { if (window.crypto && crypto.randomUUID) return crypto.randomUUID(); return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => { const r=Math.random()*16|0,v=c==='x'?r:(r&3|8); return v.toString(16); }); }
    function id(key) { let v=''; try { v=storage.getItem(key)||''; if(!v){v=uuid();storage.setItem(key,v);} } catch(e){v=uuid();} return v; }
    const visitorId=id('nfinite_video_visitor_id'); let sessionId=id('nfinite_video_session_id');
    function provider(url){ try{const h=new URL(url,location.href).hostname.replace(/^www\./,''); if(h.includes('youtube')||h==='youtu.be')return'youtube'; if(h.includes('vimeo'))return'vimeo';}catch(e){} return'unknown'; }
    function surface(trigger){ if(trigger && trigger.dataset.videoSurface) return trigger.dataset.videoSurface; if(document.body.classList.contains('page-tv'))return'tv'; if(document.querySelector('.nfinite-tv'))return'tv'; if(document.querySelector('.nfinite-episode-single'))return'episode'; return'other'; }
    function send(type, delta){ const a=state.active; if(!a||!a.creatorId||!a.objectId)return; const body=new URLSearchParams({action:cfg.action||'nfinite_video_engagement_event',event_type:type,creator_id:a.creatorId,object_type:a.objectType,object_id:a.objectId,visitor_id:visitorId,session_id:sessionId,playback_id:a.playbackId,duration_ms:Math.max(0,Math.round(state.duration*1000)),position_ms:Math.max(0,Math.round(state.position*1000)),played_delta_ms:Math.max(0,Math.round(delta||0)),surface:a.surface,provider:a.provider,media_title:a.title||''}); fetch(cfg.endpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:body.toString(),keepalive:type==='video_end'}).catch(()=>{}); }
    function startTimer(){ stopTimer(); state.lastWall=Date.now(); state.timer=setInterval(()=>{ if(!state.active||!state.playing)return; const now=Date.now(),delta=Math.min(cfg.maxPulseMs||15000,Math.max(0,now-state.lastWall)); state.lastWall=now; send('video_progress',delta); },cfg.progressIntervalMs||10000); }
    function stopTimer(){ if(state.timer){clearInterval(state.timer);state.timer=null;} }
    function begin(detail){ if(state.active) send('video_end',0); const trigger=detail.trigger||null; const creatorId=parseInt(trigger&&trigger.dataset.videoCreatorId||detail.creatorId||0,10); const objectId=parseInt(trigger&&trigger.dataset.videoObjectId||detail.objectId||0,10); const objectType=(trigger&&trigger.dataset.videoObjectType)||detail.objectType||''; if(!creatorId||!objectId||!objectType){state.active=null;return;} state.active={creatorId,objectId,objectType,playbackId:uuid(),surface:surface(trigger),provider:provider(detail.url||''),title:(trigger&&trigger.dataset.videoTitle)||detail.title||''}; state.position=0;state.duration=0;state.playing=true;state.lastWall=Date.now(); send('video_start',0); startTimer(); }
    document.addEventListener('nfinite:video-open',e=>begin(e.detail||{}));
    document.addEventListener('nfinite:video-close',()=>{ if(state.active)send('video_end',0); stopTimer();state.active=null;state.playing=false; });
    document.addEventListener('nfinite:video-pause',()=>{ state.playing=false; });
    document.addEventListener('nfinite:video-playing',()=>{ state.playing=true;state.lastWall=Date.now();startTimer(); });
    document.addEventListener('nfinite:video-time',e=>{ const d=e.detail||{}; if(Number.isFinite(d.position))state.position=d.position; if(Number.isFinite(d.duration))state.duration=d.duration; if(typeof d.playing==='boolean')state.playing=d.playing; });
    window.addEventListener('pagehide',()=>{ if(state.active)send('video_end',0); });
})();
