(function(){
  function send(el,type){
    if(!window.NfiniteMonetization||!el)return;
    var data=new URLSearchParams();
    data.set('action','nfinite_monetization_event');
    data.set('nonce',NfiniteMonetization.nonce);
    data.set('event_type',type);
    ['campaign','placement','creator','content'].forEach(function(k){
      data.set(k+'_id',el.dataset['nfinite'+k.charAt(0).toUpperCase()+k.slice(1)]||0);
    });
    data.set('creative_key',el.dataset.nfiniteCreative||'default');
    fetch(NfiniteMonetization.ajaxUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:data.toString(),keepalive:true}).catch(function(){});
  }

  function markViewable(el){
    if(el.dataset.nfiniteImpressionSent)return;
    el.dataset.nfiniteImpressionSent='1';
    send(el,'impression');
  }

  function observe(el){
    if(!('IntersectionObserver' in window)){markViewable(el);return;}
    var timer=null;
    var observer=new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        if(entry.isIntersecting&&entry.intersectionRatio>=0.5){
          if(!timer)timer=setTimeout(function(){markViewable(el);observer.disconnect();timer=null;},1000);
        }else if(timer){clearTimeout(timer);timer=null;}
      });
    },{threshold:[0,0.5,1]});
    observer.observe(el);
  }

  function bind(root){
    (root||document).querySelectorAll('.nfinite-monetization-placement:not([data-nfinite-bound])').forEach(function(el){
      el.dataset.nfiniteBound='1';
      observe(el);
      el.querySelectorAll('.nfinite-campaign-link').forEach(function(a){
        a.addEventListener('click',function(){send(el,'click');});
      });
    });
  }

  document.addEventListener('DOMContentLoaded',function(){bind(document);});
  document.addEventListener('wpnfinite:navigation-complete',function(){bind(document);});
})();
