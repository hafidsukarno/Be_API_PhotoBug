# Project Handoff: PhotoBug Backend API

## Status Terkini (Per 14 Mei 2026)
Sistem backend (Laravel 11) sudah berhasil diintegrasikan dengan model AI YOLOv11 (FastAPI Python) untuk deteksi hama padi, serta terhubung dengan Bot Telegram untuk memberikan notifikasi *real-time* kepada penyuluh.

## Fitur & Perbaikan Utama yang Sudah Diselesaikan

### 1. Integrasi AI YOLOv11 (Python FastAPI)
- **Lokasi Kode**: `app/Http/Controllers/Petani/DetectionController.php`
- **Pencapaian**:
  - Berhasil mengirim gambar petani dari Laravel ke endpoint FastAPI `http://127.0.0.1:9000/predict`.
  - Memperbaiki penangkapan respons JSON dari bot Python (memakai struktur `['data']` dan key `class_name`).
  - Menangani error pemformatan teks dengan mengubah *underscore* (`_`) menjadi *spasi* (contoh: `wereng_coklat` -> `wereng coklat`) agar payload aman saat dikirim via Telegram Markdown.

### 2. Notifikasi Webhook Telegram
- **Lokasi Kode**: `app/Http/Controllers/TelegramWebhookController.php` & `DetectionController.php`
- **Pencapaian**:
  - Webhook berhasil di-set menggunakan URL ngrok.
  - Bot otomatis menyimpan `chat_id` grup (tempat bot di-invite) ke dalam storage lokal `storage/app/telegram_group_id.txt`.
  - Format pesan sukses dikirim menggunakan Markdown, meliputi: Nama Petani, Desa, Nama Penyuluh PJ, Waktu (WIB), Catatan Petani, Hasil AI, dan Rekomendasi Awal.
  - Foto hama yang di-upload petani berhasil dikirim sebagai `photo` / lampiran Telegram bersama caption.

### 3. Pengaturan Global & Keamanan Data
- **Timezone**: Mengubah zona waktu di `config/app.php` dari `UTC` menjadi `Asia/Jakarta` agar deteksi jam lapor di DB dan Telegram sesuai porsi WIB.
- **Exception & Error Handling (JSON)**: `bootstrap/app.php` sudah disetel untuk memaksa respons JSON (401, 404, 422) pada semua *routes* berawalan `api/*`. Sangat mempermudah *debugging* di Postman.
- **Admin Controllers (Penyuluh & Village)**: 
  - Mencegah *hard delete* (penghapusan) pada Penyuluh atau Desa jika data tersebut sudah memiliki relasi (contoh: desa tidak bisa dihapus jika sudah ada user/petani di dalamnya).
  - Khusus endpoint `update` Penyuluh, dikunci agar hanya melayani pembaruan (sync) penugasan wilayah (Village).

## Konfigurasi Penting Untuk Agent Selanjutya (Next AI / Dev)
1. **Jalankan Python FastAPI**: Pastikan `uvicorn app:app --port 9000` aktif untuk service AI saat mengetes fitur deteksi.
2. **Jalankan Webhook (Jika tes lokal)**: Forward port laravel (`php artisan serve`) dengan ngrok, dan set webhook API Telegram menggunakan URL ngrok terbaru. `https://api.telegram.org/bot<TOKEN>/setWebhook?url=<NGROK_URL>/api/telegram/webhook`
3. **Seeder Data**: Sistem penyuluh masih ditangani seeder dummy (Penyuluh 1, Penyuluh 2). Nama asli akan tampil otomatis jika diubah lewat interface Admin.

## Langkah Selanjutnya (Next Todos)
- **Aplikasi Front-End / Mobile**: Mulai integrasi endpoint `/api/detections` untuk aplikasi HP Petani.
- **Fitur Penyuluh**: Mematangkan endpoint untuk penyuluh agar bisa melihat daftar deteksi spesifik untuk desa mereka dan memberikan rekomendasi/validasi manual yang menimpa rekomendasi awal hasil AI.
- **Push Notification (FCM/OneSignal)**: Menambahkan push notification ke aplikasi, jika dibutuhkan sebagai pelengkap Telegram.

---
*Dibuat oleh GitHub Copilot sebagai checkpoint session.*
