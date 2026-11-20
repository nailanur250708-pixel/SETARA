/* Pembayaran: format input uang (logika utama ada di kasir/pembayaran.php). */
window.setaraUang = function (input) {
  input.addEventListener('input', function () {
    var n = parseInt(String(this.value).replace(/\D/g, ''), 10) || 0;
    this.value = n.toLocaleString('id-ID');
  });
};
