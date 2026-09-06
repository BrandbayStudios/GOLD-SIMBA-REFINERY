(function(){

  var GSR = {};
  GSR.ready = true; // same-origin API — always available, nothing to configure

  var TOKEN_KEY = 'gsr_admin_token';

  GSR.getToken = function(){
    try { return sessionStorage.getItem(TOKEN_KEY); } catch(e){ return null; }
  };
  GSR.setToken = function(token){
    try { sessionStorage.setItem(TOKEN_KEY, token); } catch(e){}
  };
  GSR.clearToken = function(){
    try { sessionStorage.removeItem(TOKEN_KEY); } catch(e){}
  };

  // Thin fetch() wrapper: JSON in, JSON out, throws a real Error (with the
  // server's message) on any non-2xx response so callers can just use
  // .then()/.catch() the way the old Firestore calls did. Automatically
  // attaches the staff bearer token to /api/admin/* requests.
  GSR.api = function(path, options){
    options = options || {};
    var headers = Object.assign({}, options.headers || {});
    if(options.body && !headers['Content-Type']){
      headers['Content-Type'] = 'application/json';
    }
    if(path.indexOf('/api/admin/') === 0){
      var token = GSR.getToken();
      if(token) headers['Authorization'] = 'Bearer ' + token;
    }
    return fetch(path, Object.assign({}, options, { headers: headers }))
      .then(function(res){
        return res.json().catch(function(){ return {}; }).then(function(json){
          if(!res.ok){
            var msg = (json && json.error) ? json.error : ('Request failed (' + res.status + ').');
            var err = new Error(msg);
            err.status = res.status;
            throw err;
          }
          return json;
        });
      });
  };

  GSR.computeBalance = function(fee, paid){
    var due = Number(fee) || 0;
    var p = Number(paid) || 0;
    return Math.round((due - p) * 100) / 100;
  };

  window.GSR = GSR;

})();

(function(){

  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function initParticles(canvas, count, opts){
    if(!canvas) return;
    var ctx = canvas.getContext('2d');
    var section = canvas.parentElement;
    var particles = [];
    var w = 0, h = 0, dpr = Math.min(window.devicePixelRatio || 1, 2);

    function resize(){
      w = section.clientWidth; h = section.clientHeight;
      canvas.width = w * dpr; canvas.height = h * dpr;
      canvas.style.width = w + 'px'; canvas.style.height = h + 'px';
      ctx.setTransform(dpr,0,0,dpr,0,0);
    }

    function spawn(){
      var gold = Math.random() > 0.45;
      return {
        x: Math.random() * w,
        y: Math.random() * h,
        r: 0.6 + Math.random() * (opts.size || 1.8),
        sp: 0.12 + Math.random() * (opts.speed || 0.35),
        sway: Math.random() * Math.PI * 2,
        swayAmp: 6 + Math.random() * 18,
        alpha: 0.25 + Math.random() * 0.55,
        color: gold ? '212,165,55' : '248,235,190'
      };
    }

    resize();
    for(var i=0;i<count;i++) particles.push(spawn());

    function draw(){
      ctx.clearRect(0,0,w,h);
      for(var i=0;i<particles.length;i++){
        var p = particles[i];
        var x = p.x + Math.sin(p.sway) * p.swayAmp * 0.3;
        ctx.beginPath();
        ctx.arc(x, p.y, p.r, 0, Math.PI*2);
        ctx.fillStyle = 'rgba(' + p.color + ',' + p.alpha + ')';
        ctx.shadowColor = 'rgba(' + p.color + ',0.9)';
        ctx.shadowBlur = p.r * 2.5;
        ctx.fill();
      }
    }

    if(reduce){
      draw();
      window.addEventListener('resize', function(){ resize(); draw(); });
      return;
    }

    function tick(){
      for(var i=0;i<particles.length;i++){
        var p = particles[i];
        p.y -= p.sp;
        p.sway += 0.01;
        if(p.y < -10){ p.y = h + 10; p.x = Math.random() * w; }
      }
      draw();
      requestAnimationFrame(tick);
    }
    window.addEventListener('resize', resize);
    requestAnimationFrame(tick);
  }

  initParticles(document.getElementById('particles-hero'), 70, {size:2.2, speed:0.4});
  initParticles(document.getElementById('particles-contact'), 40, {size:1.8, speed:0.3});
})();

(function(){
// Mobile nav drawer
  var menuOpen = document.getElementById('menuOpen');
  var menuClose = document.getElementById('menuClose');
  var mobileNav = document.getElementById('mobileNav');
  var navBackdrop = document.getElementById('navBackdrop');

  function openNav(){
    mobileNav.classList.add('open');
    navBackdrop.classList.add('open');
    document.body.classList.add('nav-open');
    menuOpen.setAttribute('aria-expanded', 'true');
  }
  function closeNav(){
    mobileNav.classList.remove('open');
    navBackdrop.classList.remove('open');
    document.body.classList.remove('nav-open');
    menuOpen.setAttribute('aria-expanded', 'false');
  }
  if(menuOpen){
    menuOpen.addEventListener('click', openNav);
    menuClose.addEventListener('click', closeNav);
    navBackdrop.addEventListener('click', closeNav);
    mobileNav.querySelectorAll('a').forEach(function(a){
      a.addEventListener('click', closeNav);
    });
    document.addEventListener('keydown', function(e){
      if(e.key === 'Escape') closeNav();
    });
  }
})();
