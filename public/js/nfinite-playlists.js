(function () {
  'use strict';
  function post(data) {
    data = data || {};
    data.nonce = (window.NfinitePlaylists || {}).nonce || '';
    return fetch((window.NfinitePlaylists || {}).ajaxUrl || '/wp-admin/admin-ajax.php', {
      method: 'POST', credentials: 'same-origin', headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
      body: new URLSearchParams(data).toString()
    }).then(function(r){ return r.json(); });
  }
  function bind(root) {
    root = root || document;
    root.querySelectorAll('[data-playlist-create]').forEach(function(form){
      if(form.dataset.bound==='1') return; form.dataset.bound='1';
      form.addEventListener('submit', function(e){
        e.preventDefault(); var fd=new FormData(form), msg=form.querySelector('[data-playlist-message]');
        if(msg) msg.textContent='Creating…';
        post({action:'nfinite_playlist_create',title:fd.get('title')||'',kind:fd.get('kind')||'music',visibility:fd.get('visibility')||'public'}).then(function(res){
          if(!res.success) throw new Error((res.data&&res.data.message)||'Unable to create playlist.');
          window.location.href=res.data.url;
        }).catch(function(err){if(msg)msg.textContent=err.message;});
      });
    });
    root.querySelectorAll('[data-nfinite-add-to-playlist]').forEach(function(btn){
      if(btn.dataset.bound==='1')return;btn.dataset.bound='1';
      btn.addEventListener('click',function(){var wrap=btn.closest('.nfinite-add-to-playlist'), select=wrap&&wrap.querySelector('[data-playlist-select]'), msg=wrap&&wrap.querySelector('[data-playlist-add-message]'), pid=select&&select.value;if(!pid)return;btn.disabled=true;post({action:'nfinite_playlist_add_item',playlist_id:pid,item_id:btn.dataset.itemId||''}).then(function(res){if(msg)msg.textContent=res.success?'Added':'Could not add';}).catch(function(){if(msg)msg.textContent='Could not add';}).finally(function(){btn.disabled=false;});});
    });
  }
  document.addEventListener('DOMContentLoaded',function(){bind(document);});
  document.addEventListener('wpnfinite:navigation-complete',function(){bind(document);});
  window.NfinitePlaylistUI={bind:bind,request:post};
}());
