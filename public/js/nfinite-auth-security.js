(function(){
  'use strict';
  var cfg = window.NfiniteAuthSecurity || {};
  if (!cfg.siteKey || !window.grecaptcha) return;
  function refresh(form){
    var field = form.querySelector('.nfinite-recaptcha-token');
    if (!field) return Promise.resolve();
    var action = field.getAttribute('data-action') || 'login';
    return new Promise(function(resolve){
      grecaptcha.ready(function(){
        grecaptcha.execute(cfg.siteKey, {action: action}).then(function(token){ field.value = token || ''; resolve(); }).catch(function(){ resolve(); });
      });
    });
  }
  document.querySelectorAll('.nfinite-auth-form').forEach(function(form){
    var busy = false;
    form.addEventListener('submit', function(e){
      var field = form.querySelector('.nfinite-recaptcha-token');
      if (!field || busy) return;
      e.preventDefault();
      busy = true;
      refresh(form).then(function(){ form.submit(); });
    });
    refresh(form);
  });
})();
