/* POS: format Angka input & bantuan stepper (logika utama ada di kasir/index.php). */
window.setaraFormatAngka = function (input) {
  input.addEventListener('input', function () {
    var n = parseInt(String(this.value).replace(/\D/g, ''), 10) || 0;
    this.value = n.toLocaleString('id-ID');
  });
};
