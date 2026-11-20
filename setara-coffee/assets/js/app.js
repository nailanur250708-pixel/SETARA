/* Utilitas umum Café Setara */
document.addEventListener('DOMContentLoaded', function () {
  // Tutup flash otomatis setelah 6 detik.
  setTimeout(function () {
    document.querySelectorAll('.flash').forEach(function (f) {
      f.style.transition = 'opacity .3s'; f.style.opacity = '0';
      setTimeout(function () { f.remove(); }, 300);
    });
  }, 6000);
  // Konfirmasi untuk form dengan data-konfirmasi.
  document.querySelectorAll('form[data-konfirmasi]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!confirm(f.dataset.konfirmasi)) e.preventDefault();
    });
  });
});
