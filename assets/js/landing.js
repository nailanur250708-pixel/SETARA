/* ==========================================================================
   CAFÉ SETARA — script.js (vanilla JS)
   ========================================================================== */

/* --------------------------------------------------------------------------
   KONFIGURASI YANG MUDAH DIGANTI
   -------------------------------------------------------------------------- */

// Link Google Maps. Ganti di sini saja bila perlu.
const MAPS_URL = "https://maps.app.goo.gl/XXeuhrH975Q1KuLDA";
// Cadangan bila link pendek tidak berfungsi:
// const MAPS_URL = "https://www.google.com/maps/search/?api=1&query=Jl+Towerkanjovank+Tamalanrea+Indah+Makassar";

// Harga promo (mengikuti rancangan asli: harga coret lebih kecil dari harga promo).
// Koreksi nilai di sini bila diperlukan.
const harga_asli = 25000;
const harga_promo = 25480;

// Data menu contoh — tambah/ubah di sini saja, kartu dirender otomatis.
const MENU = [
  { nama: "Kopi Susu",    harga: 22000, kategori: "Kopi",       gambar: "assets/kopi-susu.jpg" },
  { nama: "Kopi Hitam",   harga: 18000, kategori: "Kopi",       gambar: "assets/kopi-hitam.jpg" },
  { nama: "Matcha Latte", harga: 25000, kategori: "Non-kopi",   gambar: "assets/matcha.jpg" },
  { nama: "Cokelat",      harga: 22000, kategori: "Non-kopi",   gambar: "assets/cokelat.jpg" },
  { nama: "Milky",        harga: 22000, kategori: "Non-kopi",   gambar: "assets/milky.jpg" },
];

const rupiah = (n) => "Rp" + n.toLocaleString("id-ID");

/* --------------------------------------------------------------------------
   1. Pasang link Google Maps ke kartu gambar hero
   -------------------------------------------------------------------------- */
const mapsLink = document.getElementById("mapsLink");
if (mapsLink) {
  mapsLink.href = MAPS_URL;
  mapsLink.setAttribute("aria-label", "Buka lokasi Café Setara di Google Maps");
}

/* --------------------------------------------------------------------------
   2. Render kartu menu dari array MENU
   -------------------------------------------------------------------------- */
const menuGrid = document.getElementById("menuGrid");
if (menuGrid) {
  menuGrid.innerHTML = MENU.map((m) => `
    <article class="card menu-card reveal">
      <img class="menu-card__img" src="${m.gambar}" alt="Ilustrasi ${m.nama} dari Café Setara" loading="lazy">
      <div class="menu-card__body">
        <div class="menu-card__head">
          <span class="menu-card__name">${m.nama}</span>
          <span class="menu-card__price">${rupiah(m.harga)}</span>
        </div>
        <div class="menu-card__cat">${m.kategori}</div>
      </div>
    </article>
  `).join("");
}

/* --------------------------------------------------------------------------
   3. Isi harga promo dari konstanta
   -------------------------------------------------------------------------- */
const elAsli = document.getElementById("hargaAsli");
const elPromo = document.getElementById("hargaPromo");
if (elAsli) elAsli.textContent = rupiah(harga_asli);
if (elPromo) elPromo.textContent = rupiah(harga_promo);

/* --------------------------------------------------------------------------
   4. Hamburger menu (mobile)
   -------------------------------------------------------------------------- */
const hamburger = document.getElementById("hamburger");
const nav = document.getElementById("mainNav");
if (hamburger && nav) {
  hamburger.addEventListener("click", () => {
    const buka = nav.classList.toggle("is-open");
    hamburger.setAttribute("aria-expanded", String(buka));
    hamburger.setAttribute("aria-label", buka ? "Tutup menu" : "Buka menu");
  });
  // Tutup saat link diklik
  nav.querySelectorAll("a").forEach((a) => a.addEventListener("click", () => {
    nav.classList.remove("is-open");
    hamburger.setAttribute("aria-expanded", "false");
  }));
}

/* --------------------------------------------------------------------------
   5. Reveal on scroll + stagger + highlight + garis alur
   -------------------------------------------------------------------------- */
const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

if ("IntersectionObserver" in window && !reduceMotion) {
  const revealObserver = new IntersectionObserver((entries, obs) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      const el = entry.target;

      // Stagger untuk kartu dalam satu grup (80ms per kartu)
      const grup = el.parentElement;
      if (grup && (grup.classList.contains("grid") || grup.classList.contains("flow__steps"))) {
        const saudara = [...grup.children];
        el.style.transitionDelay = `${saudara.indexOf(el) * 80}ms`;
      }

      el.classList.add("is-visible");
      obs.unobserve(el);
    });
  }, { threshold: 0.15, rootMargin: "0px 0px -40px 0px" });

  document.querySelectorAll(".reveal").forEach((el) => revealObserver.observe(el));

  // Highlight kata kunci saat terlihat
  const hlObserver = new IntersectionObserver((entries, obs) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add("is-on");
        obs.unobserve(entry.target);
      }
    });
  }, { threshold: 0.6 });
  document.querySelectorAll(".hl").forEach((el) => hlObserver.observe(el));

  // Garis alur tergambar kiri → kanan
  const flowFill = document.getElementById("flowFill");
  if (flowFill) {
    const flowObserver = new IntersectionObserver((entries, obs) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          flowFill.classList.add("is-on");
          obs.unobserve(entry.target);
        }
      });
    }, { threshold: 0.4 });
    flowObserver.observe(document.getElementById("flow"));
  }
} else {
  // Fallback tanpa observer / reduced motion: tampilkan semuanya
  document.querySelectorAll(".reveal").forEach((el) => el.classList.add("is-visible"));
  document.querySelectorAll(".hl").forEach((el) => el.classList.add("is-on"));
  const flowFill = document.getElementById("flowFill");
  if (flowFill) flowFill.classList.add("is-on");
}

/* --------------------------------------------------------------------------
   6. Accordion FAQ — pastikan hanya satu terbuka (opsional, rapi)
   -------------------------------------------------------------------------- */
document.querySelectorAll(".faq__item").forEach((item) => {
  item.addEventListener("toggle", () => {
    if (item.open) {
      document.querySelectorAll(".faq__item").forEach((lain) => {
        if (lain !== item) lain.open = false;
      });
    }
  });
});
