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
| Struk cetak/simpan PDF dan QR pelacakan | `/admin/orders/{id}/receipt` |
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

- PHP **8.4** dengan ekstensi GD (QR struk), PDO MySQL, BCMath, Mbstring, XML/DOM, Ctype, Fileinfo, OpenSSL, Tokenizer, Session, dan cURL.
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

`DB_PORT` mengikuti MySQL di laptop masing-masing. Pengaturan FlyEnv di laptop Ryan sekarang memakai **3306**, sama seperti port MySQL standar. Gunakan akun database masing-masing; password lokal tidak dibagikan lewat Git.

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

### Membuka lewat FlyEnv + VS Code

Di laptop Ryan, situs sudah terdaftar sebagai **http://cucikilat.test**. Nginx, PHP-FPM 8.4, dan MySQL 8.4 dikelola FlyEnv, jadi **tidak perlu `php artisan serve` atau `composer local`** untuk membuka domain ini.

1. Buka FlyEnv dan nyalakan **Nginx**, **PHP-FPM 8.4**, serta **MySQL**.
2. Buka **Site → PHP Projects**. Untuk laptop teman, tambahkan situs sendiri:
   - Hostname: `cucikilat.test`.
   - Document Root: folder **public/** di dalam hasil clone proyek.
   - PHP Version: **8.4**.
   - Port: **80**.
   - Nginx URL Rewrite: template **laravel**.
3. Sesuaikan `.env`: `APP_URL=http://cucikilat.test`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, dan akun MySQL milikmu.
4. Jalankan `php artisan optimize:clear` dari terminal proyek setelah mengganti konfigurasi.
5. Buka folder proyek di VS Code. Di laptop Ryan: `/Users/ryanalfin/Sites/CC/cucikilat`.
6. Buka **http://cucikilat.test**; login di **http://cucikilat.test/login**.

Contoh membuka proyek lewat terminal:

```bash
code /Users/ryanalfin/Sites/CC/cucikilat
```

Terminal VS Code dapat dipakai untuk `php artisan migrate:status`, `php artisan db:seed`, `composer test`, dan `composer lint`. Server website tetap dijalankan oleh FlyEnv, sehingga terminal tidak perlu dibiarkan menjalankan server PHP.

Data contoh serta akun lokal Ryan dipindahkan ke database CuciKilat di MySQL FlyEnv. Salinan database sebelum perpindahan disimpan hanya di `storage/app/private/` dan tidak masuk Git. Database aplikasi lain tidak diubah.

Jika ingin memakai port 8000 sebagai alternatif, nyalakan MySQL FlyEnv lebih dulu lalu jalankan `composer local`. Perintah ini sekarang hanya menyalakan server PHP dan memakai koneksi database dari `.env`.

### Isian login otomatis untuk demo lokal

Di laptop Ryan, email dan password akun demo sudah terisi otomatis di halaman login; tinggal klik **Masuk ke meja order**. Nilainya disimpan hanya di `.env` lokal, tidak di repository.

Untuk mengaktifkan di laptop sendiri, tambahkan `LOCAL_DEMO_LOGIN=true`, `LOCAL_DEMO_EMAIL` dan `LOCAL_DEMO_PASSWORD` di `.env` dengan akun petugas yang sudah dibuat. Jalankan `php artisan optimize:clear` setelah mengubahnya. Fitur ini hanya mengisi form ketika `APP_ENV=local` dan pengunjung datang dari loopback laptop. Mode production dan pengunjung dari luar laptop tetap menerima form kosong. Menonaktifkan: `LOCAL_DEMO_LOGIN=false`.

### Windows: Laragon atau XAMPP + artisan serve

Cara praktis untuk teman kelompok: jalankan **MySQL lewat Laragon/XAMPP**, lalu jalankan Laravel lewat terminal VS Code. Apache boleh tetap mati bila memakai artisan serve.

1. Pastikan `php -v` di terminal VS Code menunjukkan **PHP 8.4**. Aktifkan ekstensi **GD**, **pdo_mysql**, **bcmath**, dan ekstensi Laravel lainnya di php.ini yang dipakai PHP terminal. Cek `php --ini` dan `php -m`.
2. Nyalakan MySQL dari Laragon/XAMPP. Sesuaikan DB_PORT, DB_USERNAME, dan DB_PASSWORD di .env milikmu. Jangan menganggap password MySQL semua laptop sama.
3. Ikuti langkah clone, composer install, .env, database, key, migrate --seed, dan pembuatan akun petugas di atas.
4. Dari folder proyek di terminal VS Code:

```powershell
php artisan optimize:clear
php artisan serve --host=127.0.0.1 --port=8000
```

5. Buka **http://127.0.0.1:8000** dan biarkan terminal tetap berjalan. Untuk berhenti: `Ctrl+C`.
6. Jika mengubah kode PHP/Blade/CSS, muat ulang browser. Setelah pull dependency baru jalankan `composer install`; setelah pull migrasi baru jalankan `php artisan migrate`.

Jika Laragon/XAMPP memakai virtual host dengan Document Root ke **public/**, artisan serve tidak wajib. Jangan menjalankan `composer local` yang memakai shell Bash sebagai langkah utama Windows; gunakan perintah artisan di atas.

## 7. Cara memakai aplikasi

1. Login petugas, buka **Layanan** jika perlu mengubah tarif.
2. Buka **Order → Terima order**. Isi pelanggan, telepon, layanan, dan berat atau jumlah item.
3. Simpan, lalu klik **Cetak struk / QR** pada halaman detail order.
4. Struk menampilkan nama, kontak, layanan, berat aktual/tagihan atau jumlah item, tarif dasar, subtotal, tambahan express 50%, total, pembayaran, estimasi selesai, kode order, dan **QR/tautan langsung ke pelacakan**.
5. Klik **Cetak / simpan PDF** untuk mencetak ke kertas 80 mm/A4 atau memilih **Save as PDF**. Matikan header/footer browser. Printer fisik belum diuji; sesuaikan ukuran kertas di dialog printer. Browser dalam aplikasi tertentu tidak menampilkan dialog cetak; jika itu terjadi, buka alamat struk di Chrome/Edge/Brave/Safari.
6. Berikan struk atau file PDF ke pelanggan. Mereka cukup scan QR, tanpa mencatat kode atau login. Tombol **Salin tautan** juga tersedia; pada browser HTTP yang membatasi clipboard, isian akan dipilih agar bisa disalin dengan Ctrl+C/Cmd+C.
7. Majukan status sesuai proses laundry. Ketika uang diterima, tekan **Catat sudah dibayar** dan konfirmasikan. Tombol ini hanya mencatat pembayaran, bukan payment gateway.
8. Setelah cucian siap dan lunas, konfirmasikan penyerahan ke **DIAMBIL**. Cocokkan identitas pelanggan oleh petugas; QR hanya untuk melacak, bukan bukti identitas. Order kemudian terkunci.
9. Buka **Laporan** dan pilih tanggal pembayaran untuk melihat pemasukan hari itu.

### QR, alamat web, dan privasi

QR dibuat oleh aplikasi dengan `endroid/qr-code`, tanpa mengirimkan kode pelanggan ke API QR pihak ketiga. QR hanya berisi tautan pelacakan, bukan nomor telepon, nama, atau catatan. Halaman struk berisi detail pribadi dan hanya bisa dibuka petugas yang login; respons diberi `Cache-Control: private, no-store`. Pelacakan publik tetap tidak menampilkan data kontak.

Alamat QR dibentuk dari **APP_URL di .env**, bukan Host header pengunjung:

- FlyEnv Ryan: `APP_URL=http://cucikilat.test`.
- Laragon/XAMPP + artisan serve: `APP_URL=http://127.0.0.1:8000`.
- Server untuk pelanggan: `APP_URL=https://domain-yang-bisa-diakses-pelanggan`.

**Domain .test, localhost, dan 127.0.0.1 tidak dapat dibuka dari HP pelanggan.** QR lokal berguna untuk demo laptop. Untuk pemakaian pelanggan, deploy ke alamat yang dapat diakses, atur APP_URL, jalankan `php artisan optimize:clear`, dan cetak ulang struk. QR yang sudah tercetak tetap menyimpan alamat lama.

### Pengamanan order tambahan

- Form order memiliki UUID submission key dengan indeks unik database. Kirim ulang form yang sama akan kembali ke order yang sama, bukan membuat tagihan/order baru. Tidak perlu mengisi field ini secara manual.
- Layanan yang diarsipkan tidak bisa dipilih untuk order baru; nota lama tetap bisa dicetak.
- Ada konfirmasi sebelum pembayaran dan pengambilan agar tombol tidak mudah terpencet tanpa sengaja.
- Nilai uang dan berat tetap dihitung di server; struk memakai snapshot tarif order, bukan harga layanan terbaru.

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

Saat ini ada **42 kasus test**, termasuk minimum 2 kg, batas pembulatan 0,5 kg, surcharge express, estimasi reguler/express, harga satuan, penolakan berat nol, penguncian order DIAMBIL, urutan status, pembayaran, laporan harian, layanan, login, pelacakan tanpa data pribadi, escaping HTML, dan respons health 200/503.

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

## Pencocokan dengan PDF tugas K06

Acuan: Panduan Tugas Besar 1, halaman 3 (aplikasi) dan halaman 7 (K06 CuciKilat).

| Ketentuan | Implementasi / bukti |
| --- | --- |
| CRUD layanan reguler, express, satuan | Halaman Layanan, tambah/edit/arsip; test perubahan tarif dan arsip. |
| Buat order dengan berat dan layanan | Form order, perhitungan server, dan nota; layanan satuan memakai jumlah item sebagai keputusan tambahan. |
| Pelacakan status dengan kode order | /lacak, riwayat status, QR dan tautan langsung dari struk. |
| Laporan pendapatan harian | /admin/report berdasarkan pembayaran yang dicatat, tanggal WIB. |
| Pembulatan ke atas per 0,5 kg, minimum 2 kg | Kasus batas 0,2; 2; 2,001; 2,5; 2,501 kg diuji. |
| Express +50%, estimasi 1 hari; reguler 3 hari | Perhitungan dan tanggal selesai diuji, rincian dicetak di struk. |
| Order sudah diambil tidak dapat diubah | Semua endpoint mutasi menolak DIAMBIL; nota tetap dapat dibaca/cetak ulang. |
| Database, migrasi, seeder contoh | MySQL, migration layanan/order, seeder layanan yang aman dijalankan ulang. |
| GET /health: HTTP 200 dan status database | Respons connected; HTTP 503 ketika database gagal, keduanya diuji. |
| Minimal 5 test, minimal 2 aturan bisnis | 42 test saat fitur struk ditambahkan; lihat composer test untuk jumlah terbaru. |
| Tantangan Lint sebelum Test di container Jenkins | composer lint tersedia; Dockerfile/Jenkinsfile dan bukti merah–hijau belum dibuat. |

Struk, QR, salin tautan, konfirmasi transaksi, dan pencegahan order ganda adalah fitur tambahan untuk memudahkan penggunaan. Ini tidak menggantikan bukti container, pipeline, kolaborasi, laporan PDF, dan demo yang tetap perlu diselesaikan kelompok.
