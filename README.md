# ⚡ CuciKilat — Kelompok 6

Website laundry untuk Tugas Besar 1 Cloud Computing, studi kasus **K06 — CuciKilat**. Dibuat pakai **Laravel 13, PHP 8.4, MySQL, dan Blade**. Tema tampilan retro kuning, dengan logo petir dan nota laundry.

Bagian ini mencakup aplikasi/backend. **Docker, Jenkins, Docker Hub, webhook, dan deploy belum dikonfigurasi di repo ini.** Anggota Container dan DevOps bisa melanjutkan dari aplikasi yang sudah ada.

## Anggota

- Alifia Mareta Cahyani — 102062400086
- Ihtada Haqi Prasetyo — 102062400060
- Ryan Alfin Saputra — 102062400072 — Backend / Tech Lead
- Sulthoni Prima Rafasya — 102062400110

Peran anggota lain disesuaikan lagi dengan pembagian kelompok. Setiap anggota tetap harus punya commit sendiri dan mengerjakan perubahan lewat PR.

## Fitur yang sudah dibuat

| Fitur | Lokasi |
| --- | --- |
| Halaman awal dan daftar harga | `/` |
| Login petugas | `/login` |
| Daftar, pencarian, dan filter order | `/admin` |
| Buat order dengan layanan dan berat/jumlah | `/admin/orders/create` |
| Detail, edit, pembayaran, dan pembaruan status | `/admin/orders/{id}` |
| CRUD layanan reguler, express, dan satuan | `/admin/services` |
| Pelacakan publik dengan kode order | `/lacak` |
| Laporan pendapatan harian | `/admin/report` |
| Status aplikasi dan koneksi database | `/health` |

Semua halaman admin wajib login. Tidak ada pendaftaran petugas publik. Form memakai CSRF protection; login dan pelacakan diberi pembatasan request. Pelacakan publik tidak menampilkan nama, nomor telepon, atau catatan pribadi pelanggan.

## Aturan bisnis

- Kiloan: berat tagihan dibulatkan **ke atas setiap 0,5 kg**, minimal **2 kg**. Contoh: 1,2 kg → 2 kg; 2,1 kg → 2,5 kg; 2,501 kg → 3 kg.
- Reguler: `berat tagihan × harga dasar/kg`, estimasi selesai **3 hari** dari waktu order dibuat.
- Express: `berat tagihan × harga dasar/kg × 1,5`, estimasi selesai **1 hari**. Isi harga dasar express, jangan tambahkan 50% secara manual.
- Order berstatus **DIAMBIL tidak dapat diubah**, termasuk detail, status, dan pembayaran. Pemeriksaan dilakukan di server dalam transaksi dengan row lock.
- Harga dihitung ulang di server. Nilai `total` dari browser tidak dipakai. Pecahan rupiah dibulatkan ke atas.
- Tarif disalin ke order saat transaksi agar perubahan/arsip layanan tidak mengubah nota sebelumnya.

Keputusan tambahan yang tidak dirinci PDF:

- Satuan dihitung per item, tanpa minimum kiloan. Untuk layanan sepatu, satu item berarti satu pasang. Estimasi satuan 3 hari.
- Status berjalan berurutan: `DITERIMA → DICUCI → DIKERINGKAN → DISETRIKA → SIAP_DIAMBIL → DIAMBIL`.
- Pembayaran harus dicatat sebelum order diambil. Rincian order yang sudah dibayar juga dikunci supaya laporan pendapatan tidak berubah setelah pembayaran.
- Pendapatan dihitung dari **tanggal pembayaran yang dicatat**, bukan tanggal order dibuat. Semua tanggal operasional memakai **Asia/Jakarta / WIB**.
- Tombol hapus layanan mengarsipkan layanan (soft delete), sehingga order lama tetap bisa dibuka.

## 1. Siapkan alatnya

- PHP **8.4** dengan ekstensi PDO MySQL, BCMath, Mbstring, XML/DOM, Ctype, Fileinfo, OpenSSL, Tokenizer, Session, dan cURL.
- Composer 2.
- MySQL 8.x yang sedang berjalan.
- Git.

**Node/npm tidak dibutuhkan untuk tampilan saat ini.** CSS dan JavaScript berada langsung di `public/`. Font memakai Google Fonts dengan fallback lokal, jadi halaman tetap berfungsi tanpa koneksi internet.

Cek PHP yang dipakai terminal:

```bash
php -v
php -m
composer -V
```

Pastikan PHP terminal juga PHP 8.4, bukan versi lama dari instalasi lain.

## 2. Clone repo pertama kali

```bash
git clone https://github.com/byryanalfin/cucikilat-cc-k06.git
cd cucikilat-cc-k06
composer install
cp .env.example .env
```

Repo private: login GitHub dan pastikan sudah diundang sebagai collaborator. Jika menggunakan GitHub CLI, jalankan `gh auth login` terlebih dahulu.

Untuk Windows PowerShell, `cp` juga tersedia sebagai alias `Copy-Item`.

## 3. Buat database dan isi .env

Buat database di MySQL/HeidiSQL/phpMyAdmin atau MySQL CLI:

```sql
CREATE DATABASE cucikilat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE cucikilat_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Gunakan akun MySQL lokal milikmu yang diberi akses ke kedua database itu. Lalu sesuaikan `.env`:

```dotenv
APP_NAME=CuciKilat
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cucikilat
DB_USERNAME=akun_mysql_lokalmu
DB_PASSWORD=password_mysql_lokalmu
```

`DB_PORT` mengikuti MySQL di laptop masing-masing. Di laptop Ryan, instance khusus proyek memakai **13316**. Teman kelompok biasanya memakai **3306**; tidak perlu meniru port laptop Ryan.

**Jangan push `.env`, `.env.testing`, password, atau data MySQL.** File tersebut sudah di-ignore. Di server deploy nanti, set `APP_ENV=production` dan `APP_DEBUG=false`.

## 4. Key, migrasi, dan seeder

```bash
php artisan key:generate
php artisan migrate --seed
```

Seeder mengisi tiga layanan contoh:

| Layanan | Tarif dasar | Ketentuan |
| --- | --- | --- |
| Cuci Reguler | Rp7.000/kg | 3 hari |
| Cuci Express | Rp7.000/kg | +50%, 1 hari |
| Sepatu Satuan | Rp30.000/item | Satu item = satu pasang |

`php artisan db:seed` bisa dijalankan ulang. Layanan yang sudah ada tidak diduplikasi, dan harga yang sudah diedit tidak ditimpa.

Untuk **menghapus seluruh tabel dan mengulang data di database latihan**, jalankan ini hanya jika datanya memang boleh hilang:

```bash
php artisan migrate:fresh --seed
```

Jangan gunakan `migrate:fresh` pada database berisi order yang ingin disimpan.

## 5. Buat akun petugas

```bash
php artisan cucikilat:admin nama@contoh.test
```

Perintah akan meminta password minimal 10 karakter secara tersembunyi. Tidak ada password petugas bawaan di source code. Tidak ada halaman registrasi publik; semua akun dibuat lewat perintah ini.

Jika lupa password akun lokal, gunakan mekanisme reset administratif Laravel atau buat akun petugas lain dengan email berbeda. Hindari mengedit password menjadi teks biasa di database.

## 6. Buka website

Pastikan MySQL sudah berjalan, lalu:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Buka **http://127.0.0.1:8000**. Masuk petugas di **http://127.0.0.1:8000/login** dengan akun yang dibuat pada langkah 5. Biarkan terminal server tetap terbuka; `Ctrl+C` untuk berhenti.

Khusus **laptop Ryan**, data MySQL lokal sudah disiapkan di `storage/app/private/mysql` dan dapat dimulai bersama web dengan:

```bash
cd /Users/ryanalfin/Sites/CC/cucikilat
composer local
```

`composer local` memakai MySQL FlyEnv yang sudah terpasang, port 13316, dan hanya membuka server ke loopback laptop. Teman yang clone repo menggunakan MySQL masing-masing dan `php artisan serve`, bukan menyalin folder data MySQL Ryan.

## 7. Cara memakai aplikasi

1. Login petugas, buka **Layanan** jika perlu mengubah tarif.
2. Buka **Order → Terima order**. Isi pelanggan, telepon, layanan, dan berat atau jumlah item.
3. Simpan, lalu bagikan **kode order** atau tautan lacak di nota ke pelanggan.
4. Majukan status satu tahap sesuai proses laundry.
5. Setelah uang diterima, tekan **Catat sudah dibayar**. Tombol ini hanya mencatat pembayaran; tidak terhubung ke payment gateway.
6. Setelah siap dan lunas, majukan status ke **DIAMBIL**. Order kemudian terkunci.
7. Buka **Laporan** dan pilih tanggal pembayaran untuk melihat pemasukan hari itu.

## 8. Pull update dari teman

Simpan pekerjaanmu melalui commit di branch sendiri terlebih dahulu. Jangan pull dengan perubahan yang belum disimpan jika bisa menimbulkan konflik.

```bash
git switch main
git pull --ff-only origin main
composer install
php artisan migrate --seed
php artisan optimize:clear
```

`composer install` mengikuti versi di `composer.lock`; jangan menggantinya dengan `composer update` setiap pull. Jangan membuat ulang `APP_KEY` pada proyek yang sudah berjalan.

## 9. Kerja lewat branch dan PR

Jangan push fitur langsung ke main.

```bash
git switch main
git pull --ff-only origin main
git switch -c feature/nama-fitur
# edit file lalu jalankan lint dan test
git add path/file-yang-diubah
git commit -m "Jelaskan perubahanmu"
git push -u origin feature/nama-fitur
```

Buka Pull Request ke `main`, minta teman review, lalu merge. Setelah merge, anggota lain melakukan pull lagi. Setiap anggota memakai akun Git dan commit sendiri; jangan mengganti author commit orang lain.

## 10. Test otomatis

Test menggunakan database **cucikilat_test** yang terpisah dari data aplikasi. Buat `.env.testing` dari contoh dan sesuaikan akun serta port MySQL:

```bash
cp .env.testing.example .env.testing
php artisan key:generate --env=testing
php artisan config:clear
composer test
```

Pastikan `DB_DATABASE=cucikilat_test`. Test memakai `RefreshDatabase` dan mengelola ulang tabel pada database testing. Jangan arahkan database testing ke data aplikasi.

Saat ini ada **27 kasus test**, termasuk minimum 2 kg, batas pembulatan 0,5 kg, surcharge express, estimasi reguler/express, harga satuan, penolakan berat nol, penguncian order DIAMBIL, urutan status, pembayaran, laporan harian, layanan, login, pelacakan tanpa data pribadi, escaping HTML, dan respons health 200/503.

Untuk laporan JUnit bila diperlukan tim QA:

```bash
php artisan test --log-junit storage/logs/junit.xml
```

## 11. Lint untuk tantangan K06

```bash
composer lint
```

Perintah ini menjalankan `pint --test`: **exit code bukan nol jika gaya kode salah**, sehingga bisa dijadikan quality gate. Untuk memperbaiki format:

```bash
vendor/bin/pint
```

Di Windows bisa gunakan `php vendor/bin/pint`.

Tim DevOps perlu menambahkan **stage Lint sebelum Test** dan menjalankan lint **di dalam container**. Repo ini belum berisi Dockerfile/Jenkinsfile, jadi `composer lint` lokal belum menjadi bukti penyelesaian tantangan pipeline. Bukti tugas tetap perlu satu build gagal karena lint lalu hijau setelah diperbaiki, serta uji gagal–pulih karena test aturan bisnis.

## 12. Health check dan integrasi container

```bash
curl -i http://127.0.0.1:8000/health
```

Saat database tersambung: HTTP 200, `{"status":"ok","database":"connected"}`. Saat database gagal: HTTP 503, `{"status":"error","database":"disconnected"}`. Endpoint tidak membutuhkan login atau sesi database.

Untuk teman Container/DevOps:

- Document root Laravel adalah **public/**, bukan root repo.
- Aplikasi butuh PHP 8.4 dan ekstensi pada langkah 1.
- Sediakan write permission untuk `storage/` dan `bootstrap/cache/`.
- Di Compose, `DB_HOST` harus nama service database, misalnya `db`; bukan `127.0.0.1`.
- Migrasi: `php artisan migrate --force`. Seeder: `php artisan db:seed --force` jika memang dibutuhkan.
- Sediakan `APP_KEY` yang tetap dan secret dari environment/Jenkins Credentials.
- Database testing harus terpisah dari aplikasi. Jalankan test dengan dev dependencies terpasang.
- Tahapan untuk K06: **Checkout → Lint → Test → Build Image → Push Image → Deploy → Smoke Test**.
- Smoke Test harus memeriksa `/health` dan gagal jika HTTP bukan 200.
- Compose perlu app + database, network backend internal, volume database, healthcheck, dan image hasil pipeline dari Docker Hub, sesuai panduan tugas.

## Struktur file utama

```text
app/Http/Controllers/    Alur login, layanan, order, laporan, pelacakan
app/Models/             Service, LaundryOrder, User
app/Services/           Perhitungan harga order
app/Console/Commands/   Pembuatan akun petugas
routes/web.php          Endpoint website dan health
resources/views/        Halaman Blade
public/                 CSS, JavaScript, logo petir
config/app.php          Zona waktu operasional
database/migrations/   Struktur database
database/seeders/      Data layanan contoh
tests/Feature/         Test fitur dan aturan bisnis
```

## Kalau error

| Masalah | Coba ini |
| --- | --- |
| `could not find driver` | Aktifkan ekstensi `pdo_mysql` pada PHP terminal. |
| `Connection refused` | Nyalakan MySQL, cek host dan port `.env`. |
| `Access denied` | Cek user, password, dan hak akses database. |
| `Unknown database` | Buat database sesuai nama di `.env`. |
| `No application encryption key` | Saat setup pertama: `php artisan key:generate`. |
| Tabel belum ada | `php artisan migrate --seed`. |
| Konfigurasi lama masih terbaca | `php artisan optimize:clear`. |
| Port 8000 sudah dipakai | Jalankan `php artisan serve --port=8001`, buka port 8001. |
| `419 Page Expired` | Muat ulang form; pastikan cookie dan sesi aktif. |
| Belum bisa login | Buat akun lewat `php artisan cucikilat:admin email@contoh.test`. |
| Clone repo ditolak | Pastikan pemilik repo sudah menambahkan akun GitHub kamu sebagai collaborator. |
