/**
 * Konfigurasi Tailwind CSS - Soft Skills Assessment
 * Dijalankan dari root proyek:
 *   npx tailwindcss@3.4.17 -c tailwind/tailwind.config.js ^
 *       -i tailwind/input.css -o assets/css/tailwind.css --minify
 *
 * preflight dimatikan, reset ditulis sendiri di @layer base (input.css)
 * supaya perilakunya bisa dikendalikan penuh.
 *
 * Warna semantik (bg/card/text/line/brand/...) diikat ke CSS variable
 * sehingga cukup mengubah :root dan .dark -> semua halaman ikut.
 */
module.exports = {
    content: [
        './peserta/**/*.php',
        './admin/**/*.php',
        './assets/partials/**/*.php',
        './assets/js/*.js',
        './index.php'
    ],
    darkMode: 'class',
    corePlugins: {
        preflight: false,
        container: false /* .container dipakai milik sendiri (input.css) */
    },
    theme: {
        extend: {
            colors: {
                bg: 'var(--c-bg)',
                card: 'var(--c-card)',
                surface: 'var(--c-surface)',
                line: 'var(--c-line)',
                ink: 'var(--c-text)',
                muted: 'var(--c-muted)',
                brand: {
                    DEFAULT: 'var(--c-brand)',
                    soft: 'var(--c-brand-soft)',
                    50: '#eef0ff',
                    100: '#e0e3ff',
                    200: '#c7cbff',
                    300: '#a5a9fb',
                    400: '#8283f4',
                    500: '#6b69e8',
                    600: '#5b5bd6',
                    700: '#4c4cbc',
                    800: '#3e3e99',
                    900: '#35377a'
                },
                violetx: {
                    DEFAULT: 'var(--c-brand2)',
                    soft: 'var(--c-brand2-soft)'
                },
                success: {
                    DEFAULT: 'var(--c-success)',
                    soft: 'var(--c-success-soft)'
                },
                danger: {
                    DEFAULT: 'var(--c-danger)',
                    soft: 'var(--c-danger-soft)'
                },
                warning: {
                    DEFAULT: 'var(--c-warning)',
                    soft: 'var(--c-warning-soft)'
                }
            },
            fontFamily: {
                sans: ['Inter', 'Segoe UI', 'Arial', 'sans-serif']
            },
            borderRadius: {
                card: '26px',
                panel: '18px'
            },
            boxShadow: {
                soft: '0 18px 50px rgba(40,40,80,.08)',
                lift: '0 10px 30px rgba(40,40,80,.06)',
                pop: '0 25px 70px rgba(40,40,80,.14)',
                brand: '0 10px 24px rgba(91,91,214,.28)'
            }
        }
    },
    plugins: []
};
