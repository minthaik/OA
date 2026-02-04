(function(){
  var cfg=window.ordelixAnalyticsCfg||{};
  if(!cfg.endpoint) return;

  function getCookie(name){
    try{
      var m=document.cookie.match(new RegExp('(?:^|; )'+name.replace(/[-.$?*|{}()\[\]\\\/\+^]/g,'\\$&')+'=([^;]*)'));
      return m?decodeURIComponent(m[1]||''):'';
    }catch(e){ return ''; }
  }
  function setCookie(name,val,days){
    try{
      var d=new Date(); d.setTime(d.getTime()+(days||365)*24*60*60*1000);
      document.cookie=name+'='+encodeURIComponent(val)+'; Path=/; SameSite=Lax; Expires='+d.toUTCString();
    }catch(e){}
  }
  function hasConsent(){
    var oc = cfg.optoutCookie || 'oa_optout';
    if(getCookie(oc)==='1') return false;
    var mode = cfg.consentMode || 'off';
    if(mode==='off') return true;
    // CMP mode: site sets window.ordelixAnalyticsConsent=true/false
    if(mode==='cmp') return (window.ordelixAnalyticsConsent===true);
    // require cookie
    var cc = cfg.consentCookie || 'oa_consent';
    return (getCookie(cc)==='1' || window.ordelixAnalyticsConsent===true);
  }
  window.ordelixAnalyticsSetConsent = function(on){
    window.ordelixAnalyticsConsent = !!on;
    var cc = cfg.consentCookie || 'oa_consent';
    var oc = cfg.optoutCookie || 'oa_optout';
    if(on){ setCookie(oc,'0',365); setCookie(cc,'1',365); }
    else { setCookie(cc,'0',365); setCookie(oc,'1',365); }
  };

  function normPath(href){
    try{ var u=new URL(href, location.origin); return cfg.stripQuery?u.pathname:(u.pathname+u.search); }
    catch(e){ return cfg.stripQuery?String(href||'/').split('?')[0]:String(href||'/'); }
  }
  function deviceClass(){
    var w=Math.max(document.documentElement.clientWidth||0, window.innerWidth||0);
    if(w<=768) return 'mobile';
    if(w<=1024) return 'tablet';
    return 'desktop';
  }
  function utmFromUrl(){
    var sp=new URLSearchParams(location.search);
    var s=sp.get('utm_source')||'', m=sp.get('utm_medium')||'', c=sp.get('utm_campaign')||'';
    var t=sp.get('utm_term')||'', co=sp.get('utm_content')||'';
    if(!(s||m||c||t||co)) return null;
    return {s:s,m:m,c:c,t:t,co:co};
  }
  function nowTs(){ return Math.floor(Date.now()/1000); }
  function parseStoredUtm(raw){
    try{
      if(!raw) return null;
      var parsed=JSON.parse(raw);
      if(!parsed || typeof parsed!=='object') return null;
      if(!parsed.v || typeof parsed.v!=='object') return null;
      if(!(parsed.v.s||parsed.v.m||parsed.v.c||parsed.v.t||parsed.v.co)) return null;
      return parsed;
    }catch(e){ return null; }
  }
  function isExpired(ts, days){
    var t=parseInt(ts,10) || 0;
    if(t<=0) return true;
    return (nowTs()-t) > (days*24*60*60);
  }
  function readStoredUtmByKey(localKey, cookieKey){
    var days=Math.max(1, parseInt(cfg.utmAttributionDays,10) || 30);
    var local='';
    try{
      local=window.localStorage ? window.localStorage.getItem(localKey) : '';
    }catch(e){ local=''; }
    var parsed=parseStoredUtm(local);
    if(!parsed){
      parsed=parseStoredUtm(getCookie(cookieKey));
    }
    if(!parsed || isExpired(parsed.ts, days)) return null;
    return parsed.v;
  }
  function storeUtmByKey(val, localKey, cookieKey){
    if(!val) return;
    var payload=JSON.stringify({v:val,ts:nowTs()});
    var days=Math.max(1, parseInt(cfg.utmAttributionDays,10) || 30);
    try{
      if(window.localStorage) window.localStorage.setItem(localKey, payload);
    }catch(e){}
    setCookie(cookieKey, payload, days);
  }
  var attrMode=(cfg.attributionMode==='last_touch') ? 'last_touch' : 'first_touch';
  var firstTouchUtm=readStoredUtmByKey('oa_utm_first_touch','oa_utm_ft');
  var lastTouchUtm=readStoredUtmByKey('oa_utm_last_touch','oa_utm_lt');
  function applyAttributionFromUrl(){
    var fromUrl=utmFromUrl();
    if(!fromUrl) return;
    if(attrMode==='last_touch'){
      lastTouchUtm=fromUrl;
      storeUtmByKey(lastTouchUtm,'oa_utm_last_touch','oa_utm_lt');
      if(!firstTouchUtm){
        firstTouchUtm=fromUrl;
        storeUtmByKey(firstTouchUtm,'oa_utm_first_touch','oa_utm_ft');
      }
      return;
    }
    if(!firstTouchUtm){
      firstTouchUtm=fromUrl;
      storeUtmByKey(firstTouchUtm,'oa_utm_first_touch','oa_utm_ft');
    }
    if(!lastTouchUtm){
      lastTouchUtm=fromUrl;
      storeUtmByKey(lastTouchUtm,'oa_utm_last_touch','oa_utm_lt');
    }
  }
  function utm(){
    applyAttributionFromUrl();
    if(attrMode==='last_touch') return lastTouchUtm || firstTouchUtm || null;
    return firstTouchUtm || lastTouchUtm || null;
  }
  function send(payload){
    if(!hasConsent()) return;
    try{
      var body=JSON.stringify(payload);
      if(navigator.sendBeacon){
        navigator.sendBeacon(cfg.endpoint, new Blob([body],{type:'application/json'}));
        return;
      }
      fetch(cfg.endpoint,{method:'POST',headers:{'Content-Type':'application/json'},body:body,keepalive:true,credentials:'omit'}).catch(function(){});
    }catch(e){}
  }
  function pageview(){
    send({t:'pv',p:normPath(location.href),r:document.referrer||'',d:deviceClass(),utm:utm()||undefined});
  }
  function ev(name, meta, value){
    send({t:'ev',p:normPath(location.href),r:document.referrer||'',d:deviceClass(),utm:utm()||undefined,e:{n:String(name||'custom'),k:String(meta||'')},v:value||0});
  }
  window.ordelixTrack=function(name, props){ props=props||{}; ev(name, props.meta||'', props.value||0); };

  function initAuto(){
    if(!cfg.autoEvents) return;
    document.addEventListener('click', function(e){
      var a=e.target && e.target.closest ? e.target.closest('a') : null;
      if(!a) return;
      var href=a.getAttribute('href')||'';
      if(!href) return;

      if(cfg.autoTel && href.indexOf('tel:')===0){ ev('tel_click','tel='+href.substring(4),0); return; }
      if(cfg.autoMailto && href.indexOf('mailto:')===0){ ev('mailto_click','mailto='+href.substring(7),0); return; }

      if(cfg.autoDownloads){
        var lower=href.toLowerCase();
        if(lower.match(/\.(pdf|zip|docx?|xlsx?|pptx?|csv|mp3|mp4|mov|avi)(\?|#|$)/)){ ev('download','file='+href,0); return; }
      }

      if(cfg.autoOutbound){
        try{ var u=new URL(href, location.href); if(u.host && u.host!==location.host){ ev('outbound','to='+u.host,0); } }catch(err){}
      }
    }, {passive:true});

    if(cfg.autoForms){
      document.addEventListener('submit', function(e){
        var f=e.target; if(!f||!f.tagName) return;
        var id=f.getAttribute('id')||''; var name=f.getAttribute('name')||'';
        var meta=id?('form='+id):(name?('form='+name):'form=unknown');
        ev('form_submit', meta, 0);
      }, {passive:true});
    }
  }

  pageview();
  initAuto();
})();
