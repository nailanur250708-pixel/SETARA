/* Laporan: bantuan render grafik pendapatan (logika utama ada di laporan/index.php). */
window.setaraGrafikLaporan = function (canvasId, labels, values, warnaMuda, warnaTua) {
  if (!window.Chart || !document.getElementById(canvasId)) return null;
  return new Chart(document.getElementById(canvasId), {
    type: 'bar',
    data: { labels: labels, datasets: [{ data: values, backgroundColor: values.map(function (_, i) { return i === values.length - 1 ? (warnaTua || '#5C3317') : (warnaMuda || '#D9B98E'); }), borderRadius: 6, barPercentage: .55 }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { return 'Rp' + Number(c.raw).toLocaleString('id-ID'); } } } }, scales: { y: { beginAtZero: true }, x: { grid: { display: false } } } }
  });
};
