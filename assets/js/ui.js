/**
 * assets/js/ui.js
 * ------------------------------------------------------------------
 * Perilaku bersama SEMUA halaman:
 *   1. Tombol terang / gelap  (#themeToggle) -> simpan di localStorage
 *   2. Jam live bahasa Indonesia (.clock-text / #clockText)
 *
 * Halaman lain cukup me-require partial head.php + memanggil file ini.
 * Event "themechange" di-dispatch pada <document> setiap kali tema
 * berganti, dipakai komponen lain (mis. chart di halaman hasil).
 */
(function () {
    'use strict';

    var root = document.documentElement;

    /* ---------------------------------------------------------- tema */
    function isDark() {
        return root.classList.contains('dark');
    }

    function readStoredTheme() {
        try {
            return localStorage.getItem('theme');
        } catch (e) {
            return null;
        }
    }

    function storeTheme(value) {
        try {
            localStorage.setItem('theme', value);
        } catch (e) {}
    }

    function paintToggle(btn) {
        if (!btn) return;
        var dark = isDark();
        btn.setAttribute('aria-pressed', dark ? 'true' : 'false');
        var sun = btn.querySelector('.icon-sun');
        var moon = btn.querySelector('.icon-moon');
        if (sun) sun.classList.toggle('hidden', dark);
        if (moon) moon.classList.toggle('hidden', !dark);
    }

    function paintAllToggles() {
        var list = document.querySelectorAll('#themeToggle');
        for (var i = 0; i < list.length; i++) paintToggle(list[i]);
    }

    function setTheme(dark) {
        root.classList.toggle('dark', !!dark);
        storeTheme(dark ? 'dark' : 'light');
        paintAllToggles();
        document.dispatchEvent(new CustomEvent('themechange', { detail: { dark: !!dark } }));
    }

    function initTheme() {
        var btn = document.getElementById('themeToggle');
        if (btn) {
            paintToggle(btn);
            btn.addEventListener('click', function () {
                setTheme(!isDark());
            });
        }

        /* Sinkronkan preferensi sistem bila pengguna belum memilih. */
        if (!readStoredTheme() && window.matchMedia) {
            var mq = window.matchMedia('(prefers-color-scheme: dark)');
            if (mq.matches) setTheme(true);
            if (mq.addEventListener) {
                mq.addEventListener('change', function (e) {
                    if (!readStoredTheme()) setTheme(e.matches);
                });
            }
        }
    }

    /* ----------------------------------------------------------- jam */
    var HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    var BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    function pad(n) {
        return n < 10 ? '0' + n : '' + n;
    }

    function initClock() {
        var el = document.getElementById('clockText');
        if (!el) return;

        var tick = function () {
            var d = new Date();
            el.textContent = HARI[d.getDay()] + ', ' + pad(d.getDate()) + ' ' + BULAN[d.getMonth()]
                + ' ' + d.getFullYear() + ' \u2022 ' + pad(d.getHours()) + ':'
                + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
        };

        tick();
        setInterval(tick, 1000);
    }

    function start() {
        initTheme();
        initClock();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }

    window.ThemeUI = {
        isDark: isDark,
        setTheme: setTheme,
        apply: paintAllToggles
    };
})();
