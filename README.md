# Ruang Baca

Aplikasi sirkulasi perpustakaan untuk mengelola koleksi buku, anggota, peminjaman, dan pengembalian. Stok diperbarui oleh server saat transaksi disimpan; browser bukan sumber kebenaran stok.

## Menjalankan Lokal

Prasyarat: PHP 8.3+, Composer, Node.js 22.12+, npm, ekstensi PDO MySQL, dan PDO SQLite untuk test suite. Jalankan dari folder `backend`.

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
npm.cmd install
npm.cmd run build
php artisan serve
```

Buka `http://127.0.0.1:8000`. Akun seed lokal:

| Peran | Email | Kata sandi |
| --- | --- | --- |
| Kepala Perpustakaan | `kepala@perpus.local` | `password` |
| Staf Pustakawan | `staf@perpus.local` | `password` |

Akun dan kata sandi tersebut hanya untuk demo lokal. Ganti kredensial sebelum penggunaan di lingkungan bersama.

Konfigurasi `.env.example` menggunakan MySQL lokal dengan database `uts_web2` (Laragon default: user `root`, password kosong). Atur `DB_HOST`, `DB_PORT`, `DB_USERNAME`, dan `DB_PASSWORD` sesuai lingkungan, lalu jalankan migrasi. SQLite tetap tersedia dengan mengubah `DB_CONNECTION=sqlite` dan mengatur `DB_DATABASE` ke path file SQLite. `SESSION_DRIVER=database` menyimpan sesi login pada tabel `sessions`.

## Struktur Dan Alur Data

- `resources/js/App.vue` dan `resources/css/app.css`: UI Vue untuk ringkasan, buku, anggota, dan peminjaman. Vite membundel aset dari `resources/js/app.js` dan `vite.config.js`.
- `resources/views/app.blade.php`: shell HTML Laravel, memuat bundle Vite dan token CSRF.
- `routes/web.php`: halaman shell serta endpoint JSON `/api/*` dengan middleware sesi web dan `auth`.
- `app/Http/Controllers/Api`: login/logout, CRUD buku dan anggota, operasi transaksi, dan pemeriksaan role.
- `app/Models`: relasi Eloquent `Book`, `Member`, `Loan`, dan `User`.
- `database/migrations`: skema pengguna, buku, anggota, dan transaksi; `database/seeders/DatabaseSeeder.php` menyediakan akun demo serta contoh data.
- `tests/Feature/LibraryWorkflowTest.php`: validasi bisnis dan hak akses menggunakan SQLite sementara.

Alur data: Vue mengirim `fetch` dengan cookie sesi dan token CSRF ke endpoint Laravel. Controller memvalidasi input, memeriksa role dan stok, lalu menyimpan perubahan melalui Eloquent. Peminjaman mengurangi stok dengan satu pembaruan bersyarat (`stock > 0`) di dalam transaksi database sebelum mencatat pinjaman; kegagalan membatalkan seluruh transaksi. Pengembalian mengunci catatan pinjaman, mengubah status/tanggal, dan menambah stok satu kali. JSON dikirim kembali ke Vue untuk memuat ulang daftar.

## Modul Dan Hak Akses

| Aksi | Kepala Perpustakaan | Staf Pustakawan |
| --- | --- | --- |
| Lihat, tambah, ubah buku dan anggota | Diizinkan | Diizinkan |
| Hapus buku dan anggota | Diizinkan | Dilarang |
| Lihat dan buat peminjaman | Diizinkan | Diizinkan |
| Catat pengembalian | Diizinkan | Diizinkan |
| Hapus riwayat transaksi yang sudah kembali | Diizinkan | Dilarang |

Buku atau anggota yang memiliki riwayat transaksi tidak dapat dihapus. Transaksi aktif harus dikembalikan sebelum riwayatnya bisa dihapus.

## Pengujian

Jalankan `php artisan test --compact`. Feature test memeriksa login staf, tambah/ubah buku dan anggota, penolakan ISBN duplikat, penolakan peminjaman saat stok kosong (HTTP 422 dengan pesan `Gagal: Stok buku tidak tersedia.`), pengurangan stok, penambahan stok dan tanggal kembali, pengembalian berulang tanpa penambahan stok ganda, serta larangan hapus bagi staf.

## Batasan

- Autentikasi memakai sesi Laravel lokal, bukan OAuth atau API token untuk aplikasi pihak ketiga.
- Belum ada tanggal jatuh tempo, denda keterlambatan, notifikasi, impor, atau ekspor.
- Akun seed berbagi kata sandi demo; buat akun individual dan kebijakan kata sandi sebelum dipakai bersama.
- Hosting publik tidak disertakan; aplikasi dapat dijalankan dan diuji secara lokal.
