// Tombol ganti mode (gelap/terang)
(function(){
  var root = document.documentElement;
  var KEY = 'setara_theme';
  function read(){ try{ return localStorage.getItem(KEY); }catch(e){ return null; } }
  function write(v){ try{ localStorage.setItem(KEY, v); }catch(e){} }
  function detect(){
    var t = read();
    if (t === 'dark' || t === 'light') return t;
    return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
  }
  root.setAttribute('data-theme', detect());
  var btn = document.getElementById('themeToggle');
  if (btn) btn.addEventListener('click', function(){
    root.setAttribute('data-theme', root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
    write(root.getAttribute('data-theme'));
  });
})();

// Hamburger menu
(function () {
  var btn = document.getElementById('burger');
  var nav = document.getElementById('navlinks');
  if (!btn || !nav) return;

  function toggle(open) {
    nav.classList.toggle('open', open);
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    btn.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
  }

  btn.addEventListener('click', function () {
    toggle(btn.getAttribute('aria-expanded') !== 'true');
  });
  nav.addEventListener('click', function (e) {
    if (e.target.tagName === 'A') toggle(false);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') toggle(false);
  });
})();
