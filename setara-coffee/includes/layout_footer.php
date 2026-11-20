<?php
/** Penutup layout owner. */
function layout_footer(): void
{
    ?>
    </div><!-- /.konten-dalam -->
  </main>
</div><!-- /.app-shell -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (window.lucide) lucide.createIcons();
  var h = document.getElementById('hamburger');
  var s = document.getElementById('sidebar');
  var o = document.getElementById('overlay');
  if (h && s && o) {
    h.addEventListener('click', function(){ s.classList.toggle('buka'); o.classList.toggle('tampil'); });
    o.addEventListener('click', function(){ s.classList.remove('buka'); o.classList.remove('tampil'); });
  }
  document.querySelectorAll('.flash-close').forEach(function(b){
    b.addEventListener('click', function(){ b.parentElement.remove(); });
  });
  var tema=document.getElementById('themeToggle');
  if(tema) tema.addEventListener('click',function(){
    var s=document.documentElement.getAttribute('data-theme')==='dark'?'dark':'light';
    var n=s==='dark'?'light':'dark';
    document.documentElement.setAttribute('data-theme',n);
    try{localStorage.setItem('setara_theme',n);}catch(e){}
    tema.setAttribute('aria-label',n==='dark'?'Ganti ke mode terang':'Ganti ke mode gelap');
  });
});
</script>
</body>
</html>
<?php
}
