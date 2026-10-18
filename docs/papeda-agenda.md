# Perintah Agenda Papeda

Kirim `agenda` melalui WhatsApp untuk menerima daftar agenda pribadi dari
Kalender Terpadu Papeda. Periode mencakup hari ini sampai enam hari sesudahnya
(tujuh tanggal kalender), berdasarkan zona waktu Asia/Jayapura (WIT).
Rapat, agenda pimpinan, virtual meeting, cuti, kegiatan ZI, serta penugasan
mengikuti data dan aturan akses Kalender Terpadu dengan cakupan `mine`.
Agenda kosong dibalas dengan keterangan tidak ada agenda. Daftar panjang dibagi
menjadi beberapa pesan, tanpa membuat atau memakai token login.

## Deployment

Unggah perubahan chatbot:

- `app/Http/Controllers/FonnteWebhookController.php`
- `app/Services/PapedaAgendaService.php`

Unggah perubahan Papeda (`htdocs/super3`):

- `routes/api.php`
- `app/Http/Controllers/ChatbotAgendaController.php`
- `app/Http/Middleware/EnsureChatbotApiKey.php`

Jalankan `php artisan optimize:clear` di kedua aplikasi sesudah deployment.
Tidak ada migrasi atau query SQL baru. Tidak perlu variabel env baru.
Pastikan `INTERNAL_API_KEY` pada chatbot sama dengan
`CHATBOT_GATEWAY_INTERNAL_API_KEY` pada Papeda, seperti integrasi auth login.
Nilai `applications.base_url` untuk kode `papeda` harus menunjuk domain Papeda
yang dapat dijangkau server chatbot.

Endpoint Papeda adalah `POST /api/chatbot/agenda`. Chatbot mengirim header
`X-INTERNAL-API-KEY` dan `app_user_id` dari mapping aktif milik nomor pengirim.
Identitas pengguna tidak diambil dari teks pesan. Endpoint menolak kunci salah,
akun tidak ditemukan, akun nonaktif, atau akun tanpa akses Kalender Terpadu.
Cakupan dan periode selalu ditentukan server Papeda.

## Pemeriksaan

Setelah kedua aplikasi diunggah, kirim `agenda` dari nomor yang sudah terdaftar
dan memiliki mapping Papeda aktif. Bandingkan dengan Kalender Terpadu pengguna
tersebut pada cakupan pribadi dan periode yang sama. Pengujian otomatis memakai
database SQLite sementara serta HTTP dan Fonnte tiruan, tanpa mengirim pesan
WhatsApp atau mengambil data dari hosting.
