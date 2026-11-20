/* Dashboard: formatter angka untuk Chart.js (dipakai bila diperlukan). */
window.setaraRupiahSingkat = function (n) {
  if (n >= 1000000) return (n / 1000000).toFixed(1).replace('.', ',') + ' jt';
  if (n >= 1000) return Math.round(n / 1000) + ' rb';
  return String(n);
};
