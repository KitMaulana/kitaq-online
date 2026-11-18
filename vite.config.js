import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

// Tiga entry: admin (Livewire+Alpine), ujian siswa (vanilla, CSP ketat), halaman siswa non-ujian.
// Semua aset dibangun di laptop (npm run build) lalu diunggah — tanpa Node di server, tanpa CDN.
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.js', 'resources/js/exam/exam-app.js', 'resources/js/student/student.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    build: {
        chunkSizeWarningLimit: 900,
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
