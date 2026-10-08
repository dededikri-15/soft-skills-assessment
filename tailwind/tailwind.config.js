/**
 * Konfigurasi Tailwind CSS - Soft Skills Assessment
 * Dijalankan dari root proyek:
 *   npx tailwindcss@3.4.17 -c tailwind/tailwind.config.js ^
 *       -i tailwind/input.css -o assets/css/tailwind.css --minify
 *
 * preflight dimatikan supaya stylesheet lama (style.css) tidak berubah
 * perilakunya -> tidak ada error/tampilan rusak di halaman lain.
 */
module.exports = {
    content: [
        './peserta/**/*.php',
        './admin/**/*.php',
        './assets/js/*.js',
        './index.php'
    ],
    darkMode: 'class',
    corePlugins: {
        preflight: false
    },
    theme: {
        extend: {
            colors: {
                brand: {
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
                }
            },
            fontFamily: {
                sans: ['Inter', 'Segoe UI', 'Arial', 'sans-serif']
            },
            boxShadow: {
                card: '0 18px 50px rgba(40,40,80,.08)',
                'card-dark': '0 18px 50px rgba(0,0,0,.35)'
            }
        }
    },
    plugins: []
};
