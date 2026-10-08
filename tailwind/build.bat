@echo off
rem Build Tailwind CSS (jalankan dari root proyek)
cd /d "%~dp0.."
npx tailwindcss@3.4.17 -c tailwind/tailwind.config.js -i tailwind/input.css -o assets/css/tailwind.css --minify
