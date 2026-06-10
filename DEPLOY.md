# Panduan Deploy — Product Research Agent

> Panduan langkah-demi-langkah (Bahasa Indonesia) untuk menjalankan & membagikan
> aplikasi ini. Ditujukan untuk pemula. Bagian utama: **jalur GRATIS (lokal +
> Cloudflare Tunnel)**. Opsi online 24/7 ada di bagian akhir.

## Hal paling penting yang harus dipahami

Aplikasi ini punya **2 bagian yang harus jalan bersamaan**:

1. **Web** — halaman yang dibuka di browser (form, progress, laporan).
2. **Queue worker** — proses latar belakang yang *benar-benar menjalankan riset*
   (Gemini → Serper → Jina → laporan). Perintahnya: `php artisan queue:work`.

> ⚠️ **Tanpa worker, riset akan diam di status "Menunggu antrian" selamanya.**
> Inilah alasan hosting cloud gratis sulit: worker harus hidup terus-menerus.

---

## ✅ Yang sudah saya siapkan otomatis (tidak perlu Anda kerjakan)

- Kode sudah **siap deploy**: `trustProxies` aktif (HTTPS terdeteksi benar di
  balik tunnel/proxy), pesan error ramah saat API key kosong.
- `composer demo` — satu perintah menyalakan **web + worker** (untuk demo publik).
- `.env.production.example` — template variabel env produksi (tinggal disalin & diisi).
- Seluruh test hijau & Pint bersih.

## ✍️ Yang perlu Anda kerjakan manual

Semua perintah di bawah dijalankan di terminal (PowerShell) Windows Anda.

---

## A. Jalankan & pakai di laptop sendiri (GRATIS selamanya)

Cara paling mudah dan tanpa risiko biaya. Online hanya saat laptop nyala.

### Sekali saja (kalau belum)
```powershell
composer install
npm install
```
Pastikan `.env` sudah terisi: DB Supabase, `GEMINI_API_KEY`, `SERPER_API_KEY`
(punya Anda sudah terisi & teruji ✅).

### Setiap mau memakai
```powershell
composer dev
```
Perintah ini menyalakan **web + worker + assets** sekaligus.
**Biarkan terminal ini terbuka** — jika ditutup, worker mati dan riset berhenti.

Lalu buka di browser: **http://localhost:8000**
→ Daftar akun → menu **"Riset Baru"** → tulis kebutuhan → tunggu progress →
laporan 8 bagian muncul.

> Karena Laravel Herd aktif, web juga bisa diakses di
> `https://product-research-agent.test`. Tapi worker tetap harus jalan —
> `composer dev` sudah mencakupnya, jadi cukup pakai `composer dev`.

---

## B. Bagikan link publik GRATIS (Cloudflare Tunnel)

Memberi URL `https://...trycloudflare.com` yang bisa dibuka siapa saja.
**Tanpa kartu kredit.** Online hanya selama laptop + tunnel menyala.

### 1. Install cloudflared (sekali saja)
```powershell
winget install --id Cloudflare.cloudflared
```
Tutup lalu buka lagi terminal setelah instalasi.

### 2. Build assets (penting untuk tunnel)
```powershell
npm run build
```
> Wajib `build`, bukan `npm run dev`. Assets mode "dev" (Vite) berjalan di port
> lain dan tidak ikut lewat tunnel, sehingga tampilan bisa rusak.

### 3. Terminal 1 — nyalakan web + worker
```powershell
composer demo
```

### 4. Terminal 2 — buka tunnel
```powershell
cloudflared tunnel --url http://localhost:8000
```
Salin URL yang muncul, misal `https://random-words.trycloudflare.com`.

### 5. Samakan APP_URL dengan URL tunnel
Edit `.env`:
```
APP_URL=https://random-words.trycloudflare.com
```
Lalu:
```powershell
php artisan optimize:clear
```
Buka URL publiknya → bagikan. Selesai. 🎉

> Catatan: tunnel cepat (quick tunnel) memberi URL **acak baru setiap kali**
> dijalankan. Untuk URL tetap, buat akun Cloudflare gratis + "named tunnel"
> (lebih lanjut — minta saya pandu jika perlu).

---

## C. (Opsional) Online 24/7 GRATIS — Oracle Cloud "Always Free"

Server gratis **selamanya** (app online terus walau laptop mati). Lebih advanced.
Butuh kartu kredit untuk verifikasi pendaftaran, tapi paket **Always Free tidak
menagih** selama Anda memakai resource Always Free.

Garis besar (minta saya buatkan panduan detail bila memilih ini):
1. Daftar Oracle Cloud → buat VM **Always Free** (Ampere ARM atau AMD micro), OS Ubuntu.
2. Install: PHP 8.4 + ekstensi, Composer, Node.js, Nginx, Git.
3. `git clone` repo → `composer install --no-dev` → `npm ci && npm run build`.
4. Salin `.env.production.example` → `.env`, isi (DB Supabase + API key), `php artisan key:generate`.
5. `php artisan migrate --force` lalu `php artisan config:cache route:cache view:cache`.
6. Konfigurasi **Nginx** ke `public/` + sertifikat HTTPS gratis (Let's Encrypt/Certbot).
7. Buat **worker permanen** dengan `systemd` (atau Supervisor):
   `php artisan queue:work --tries=2 --timeout=120` agar otomatis hidup & restart.

---

## D. (Opsional) Tercepat tapi BERBAYAR — Railway

Paling sedikit setup (auto-deteksi Laravel), web + worker dari 1 repo. Namun
worker **tidak gratis** jangka panjang (~$5/bln setelah trial $5 habis). Pilih
hanya jika siap membayar nanti. Ringkas:
1. Push repo ke GitHub → "New Project" → "Deploy from GitHub" di Railway.
2. Tambah variabel env (lihat `.env.production.example`).
3. Buat **service kedua** dari repo yang sama, start command:
   `php artisan queue:work --tries=2 --timeout=120`.

---

## E. Checklist variabel env produksi (untuk jalur C/D)

Salin `.env.production.example` → `.env`, lalu isi:

| Variabel | Wajib | Catatan |
|---|---|---|
| `APP_KEY` | ✅ | hasil `php artisan key:generate` |
| `APP_ENV` / `APP_DEBUG` | ✅ | `production` / `false` |
| `APP_URL` | ✅ | domain publik Anda |
| `DB_*` (pgsql Supabase) | ✅ | migrate via port 5432; web boleh pooler 6543 |
| `GEMINI_API_KEY` | ✅ | kunci AI utama |
| `SERPER_API_KEY` | ✅ | kunci pencarian |
| `JINA_READER_URL` | ✅ | default `https://r.jina.ai` |
| `QUEUE_CONNECTION` | ✅ | `database` |
| `RESEARCH_AI_FALLBACK_PROVIDER` + `RESEARCH_AI_FALLBACK_MODEL` (+ `GROQ_API_KEY`) | opsional | jaring pengaman kuota Gemini; boleh berantai (mis. `groq,ollama`). Produksi: Groq (cloud gratis). Ollama hanya untuk mesin lokal. |

Perintah build produksi:
```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache route:cache view:cache
```
Dan **worker WAJIB**: `php artisan queue:work --tries=2 --timeout=120`.

---

## F. Kalau Anda menaruh project ini di Git/GitHub

```powershell
git init
git add .
git commit -m "Product Research Agent"
git branch -M main
git remote add origin https://github.com/<username>/<repo>.git
git push -u origin main
```
> ⚠️ `.env` **tidak ikut** ter-commit (sudah diabaikan di `.gitignore`) — aman,
> kunci rahasia Anda tidak bocor. Orang lain yang meng-clone cukup menyalin
> `.env.example` → `.env` lalu mengisi kuncinya sendiri (lihat `README.md`).

---

## G. Troubleshooting cepat

| Gejala | Penyebab & solusi |
|---|---|
| Riset diam di "Menunggu antrian" | Worker tidak jalan. Jalankan `composer dev` atau `php artisan queue:work`. |
| Error "SERPER_API_KEY/GEMINI_API_KEY belum diatur" | Isi key di `.env`, lalu `php artisan config:clear`. |
| Tampilan rusak saat dibuka via tunnel | `npm run build`, set `APP_URL` = URL tunnel, `php artisan optimize:clear`. |
| Riset gagal "rate limit"/"overloaded"/kuota Gemini | Aktifkan failover berantai: `RESEARCH_AI_FALLBACK_PROVIDER=groq,ollama` + `RESEARCH_AI_FALLBACK_MODEL=llama-3.3-70b-versatile,qwen2.5:3b` + `GROQ_API_KEY`. Groq = cloud gratis (cadangan 24/7 terbaik); Ollama = LLM lokal (perlu `localhost:11434` + `ollama pull`). Ekstraksi otomatis lewati Groq → Gemini/Ollama. |
| Halaman 500 di produksi | Cek `storage/logs/laravel.log`; pastikan `APP_KEY` terisi & `storage/` writable. |
