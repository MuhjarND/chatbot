# Chatbot Gateway — Dokumentasi Teknis

## 1. Arsitektur

```
┌─────────────┐     ┌──────────────────┐     ┌───────────────────┐
│  WhatsApp    │────▶│  Fonnte API      │────▶│  Laravel Gateway  │
│  User/Pegawai│◀────│  (Webhook)       │◀────│  bot.pta-papuabarat│
└─────────────┘     └──────────────────┘     └────────┬──────────┘
                                                       │
                                              ┌────────▼──────────┐
                                              │  Database MySQL   │
                                              │  - employees      │
                                              │  - applications   │
                                              │  - employee_app   │
                                              │    _accounts      │
                                              │  - login_tokens   │
                                              │  - access_logs    │
                                              └────────┬──────────┘
                                                       │
                                              ┌────────▼──────────┐
                                              │  Aplikasi Internal│
                                              │  (11 subdomain)   │
                                              │  /autologin?token= │
                                              └───────────────────┘
```

**Komponen Utama:**
- **Fonnte Webhook** — Menerima pesan WhatsApp masuk
- **Magic Login Service** — Membuat & memvalidasi token login
- **Internal API** — Endpoint untuk aplikasi internal memvalidasi token

---

## 2. Alur Chatbot

1. Pegawai mengirim pesan WhatsApp ke bot (`081247947246`)
2. Fonnte meneruskan pesan ke `POST /api/webhook/fonnte`
3. Gateway menormalisasi nomor pengirim
4. Cek apakah nomor terdaftar di tabel `employees`
   - ❌ Tidak terdaftar → balas "nomor belum terdaftar"
   - ❌ Tidak aktif → balas "akun tidak aktif"
5. Parsing pesan:
   - `menu/halo/hi/start` → tampilkan daftar aplikasi pegawai
   - `angka (1,2,3...)` → buat magic link untuk aplikasi terpilih
   - Lainnya → balas "ketik menu"
6. Magic link dikirim ke WhatsApp pegawai
7. Pegawai klik link → diarahkan ke `/autologin?token=xxx`
8. Aplikasi internal memanggil `POST /api/magic-login/validate` untuk validasi

---

## 3. Daftar Endpoint

| Method | URL | Middleware | Deskripsi |
|--------|-----|------------|-----------|
| POST | `/api/webhook/fonnte` | throttle:60,1 | Webhook dari Fonnte |
| POST | `/api/magic-login/validate` | throttle:60,1, internal.api.key | Validasi token oleh app internal |

---

## 4. Cara Isi `.env`

```bash
# Copy template
cp .env.example .env

# Generate app key
php artisan key:generate
```

**Variabel yang WAJIB diisi manual:**

| Variable | Deskripsi |
|----------|-----------|
| `FONNTE_TOKEN` | Token API dari dashboard Fonnte |
| `INTERNAL_API_KEY` | API key untuk autentikasi antar-aplikasi |
| `DB_DATABASE` | Nama database MySQL |
| `DB_USERNAME` | Username database |
| `DB_PASSWORD` | Password database |

**⚠️ JANGAN commit file `.env` ke repository!**

---

## 5. Cara Membuat INTERNAL_API_KEY

Jalankan command berikut di server:

```bash
php -r "echo bin2hex(random_bytes(32)).PHP_EOL;"
```

Salin output ke `.env`:

```
INTERNAL_API_KEY=<hasil_output>
```

Gunakan key yang sama di semua aplikasi internal yang perlu memvalidasi token.

---

## 6. Cara Migrate dan Seed

```bash
# Jalankan migrasi
php artisan migrate

# Seed data aplikasi (11 aplikasi)
php artisan db:seed

# Atau seed spesifik
php artisan db:seed --class=ApplicationsTableSeeder
```

---

## 7. Cara Tambah Pegawai

Via Tinker:

```bash
php artisan tinker
```

```php
App\Employee::create([
    'name'             => 'Budi Santoso',
    'nip'              => '199001012020011001',
    'email'            => 'budi@pta-papuabarat.go.id',
    'whatsapp_number'  => '6281247947246',
    'role'             => 'pegawai',
    'is_active'        => true,
]);
```

Atau langsung via SQL:

```sql
INSERT INTO employees (name, nip, email, whatsapp_number, role, is_active, created_at, updated_at)
VALUES ('Budi Santoso', '199001012020011001', 'budi@pta-papuabarat.go.id', '6281247947246', 'pegawai', 1, NOW(), NOW());
```

**Format nomor:** Gunakan format `62xxx` (tanpa `+`, tanpa `0` di depan).

---

## 8. Cara Mapping `employee_app_accounts`

```php
App\EmployeeAppAccount::create([
    'employee_id'      => 1,        // ID pegawai
    'application_code' => 'wfh',    // Kode aplikasi
    'app_user_id'      => '15',     // ID user di aplikasi tujuan
    'is_active'        => true,
]);
```

Satu pegawai bisa memiliki banyak mapping ke berbagai aplikasi.

---

## 9. Cara Setting Webhook Fonnte

1. Login ke [dashboard Fonnte](https://md.fonnte.com)
2. Pilih device `081247947246`
3. Set webhook URL: `https://bot.pta-papuabarat.go.id/api/webhook/fonnte`
4. Method: POST
5. Simpan

---

## 10. Contoh cURL Test Webhook

```bash
curl -X POST https://bot.pta-papuabarat.go.id/api/webhook/fonnte \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "sender=6281247947246&message=menu"
```

Response yang diharapkan:

```json
{"status": true, "message": "Webhook processed"}
```

---

## 11. Contoh cURL Test Validasi Token

```bash
curl -X POST https://bot.pta-papuabarat.go.id/api/magic-login/validate \
  -H "Content-Type: application/json" \
  -H "X-INTERNAL-API-KEY: <your_internal_api_key>" \
  -d '{"token": "<raw_token>", "application_code": "wfh"}'
```

Response valid:

```json
{
  "valid": true,
  "employee_id": 1,
  "app_user_id": "15",
  "name": "Budi Santoso",
  "role": "pegawai",
  "application_code": "wfh"
}
```

Response invalid:

```json
{
  "valid": false,
  "message": "Link login tidak valid atau sudah kedaluwarsa."
}
```

---

## 12. Cara Integrasi Aplikasi Internal dengan `/autologin`

Setiap aplikasi internal harus membuat route `/autologin` yang:

### Langkah 1: Terima token dari URL

```php
// routes/web.php di aplikasi internal
Route::get('/autologin', 'AutoLoginController@handle');
```

### Langkah 2: Validasi token ke Gateway

```php
// AutoLoginController.php
public function handle(Request $request)
{
    $token = $request->query('token');
    if (!$token) {
        return redirect('/login')->with('error', 'Token tidak ditemukan.');
    }

    $response = Http::withHeaders([
        'X-INTERNAL-API-KEY' => config('services.chatbot_gateway.api_key'),
    ])->post('https://bot.pta-papuabarat.go.id/api/magic-login/validate', [
        'token'            => $token,
        'application_code' => 'wfh', // sesuaikan per aplikasi
    ]);

    $data = $response->json();

    if (!$data['valid']) {
        return redirect('/login')->with('error', $data['message']);
    }

    // Login user berdasarkan app_user_id
    $user = User::find($data['app_user_id']);
    if (!$user) {
        return redirect('/login')->with('error', 'User tidak ditemukan.');
    }

    Auth::login($user);
    return redirect('/dashboard');
}
```

### Langkah 3: Tambahkan config di aplikasi internal

```
# .env aplikasi internal
CHATBOT_GATEWAY_API_KEY=<sama_dengan_INTERNAL_API_KEY_di_gateway>
```

```php
// config/services.php
'chatbot_gateway' => [
    'api_key' => env('CHATBOT_GATEWAY_API_KEY'),
],
```

---

## 13. Checklist Keamanan

| Item | Status |
|------|--------|
| Token Fonnte hanya di `.env`, tidak di kode | ✅ |
| INTERNAL_API_KEY hanya di `.env` | ✅ |
| Raw token tidak pernah disimpan di database | ✅ (hanya SHA-256 hash) |
| Raw token tidak pernah di-log | ✅ |
| Token single-use (sekali pakai) | ✅ |
| Token punya TTL (default 5 menit) | ✅ |
| Rate limit: maks 5 link per 10 menit per pegawai | ✅ |
| API key comparison menggunakan `hash_equals` | ✅ (timing-safe) |
| Race condition dicegah dengan DB transaction + lockForUpdate | ✅ |
| `.env` dan `.env.example` tidak mengandung secret asli | ✅ |
| Webhook tidak di WordPress | ✅ (di Laravel Gateway) |
| IP dan User-Agent dicatat saat validasi | ✅ (audit trail) |
| Access log untuk semua aksi penting | ✅ |
| HTTPS enforced di semua URL | ✅ (via nginx/server config) |

### Rekomendasi Tambahan:
- Pastikan `.env` tidak bisa diakses publik (cek konfigurasi web server)
- Gunakan HTTPS di semua subdomain
- Rotasi INTERNAL_API_KEY secara berkala
- Monitor access_logs untuk aktivitas mencurigakan
- Batasi akses ke `/api/magic-login/validate` hanya dari IP server internal (opsional, via firewall)
