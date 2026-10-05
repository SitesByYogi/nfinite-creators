(function(){
  'use strict';
  function post(data){
    var body=new URLSearchParams(data);
    return fetch(NfiniteNotifications.ajaxUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()}).then(function(r){return r.json();});
  }
  function updateCount(count){
    document.querySelectorAll('[data-nfinite-unread-count]').forEach(function(el){el.textContent=count;el.hidden=!count;});
  }
  function mark(el){
    var row=el.closest('[data-notification-id]'); if(!row)return;
    post({action:'nfinite_notification_read',nonce:NfiniteNotifications.nonce,notification_id:row.dataset.notificationId}).then(function(res){if(res&&res.success){row.classList.remove('is-unread');var btn=row.querySelector('[data-nfinite-notification-read]');if(btn)btn.remove();updateCount(res.data.unread);}});
  }
  document.addEventListener('click',function(e){
    var read=e.target.closest('[data-nfinite-notification-read]'); if(read){e.preventDefault();mark(read);return;}
    var open=e.target.closest('[data-nfinite-notification-open]'); if(open){var row=open.closest('[data-notification-id]');if(row&&row.classList.contains('is-unread')){navigator.sendBeacon&&navigator.sendBeacon(NfiniteNotifications.ajaxUrl,new URLSearchParams({action:'nfinite_notification_read',nonce:NfiniteNotifications.nonce,notification_id:row.dataset.notificationId}));}return;}
    var all=e.target.closest('[data-nfinite-read-all]'); if(all){e.preventDefault();post({action:'nfinite_notifications_read_all',nonce:NfiniteNotifications.nonce}).then(function(res){if(res&&res.success){document.querySelectorAll('.nfinite-notification.is-unread').forEach(function(row){row.classList.remove('is-unread');var b=row.querySelector('[data-nfinite-notification-read]');if(b)b.remove();});updateCount(0);all.remove();}});}
  });
})();
