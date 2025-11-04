# 🎞️ Frame Of Us

**Frame Of Us** adalah web album foto dengan nuansa _pixel aesthetic_, dibuat untuk menyimpan dan menampilkan momen-momen berharga dalam bentuk galeri interaktif.  
Dilengkapi dengan **dashboard admin**, sistem **multi-album carousel**, serta dukungan **musik latar** yang sinkron antar halaman.

![Preview Screenshot](preview.png)

---

## ✨ Fitur Utama

### 🖼️ Galeri Publik
- Tampilan grid interaktif dengan animasi AOS.
- Setiap album memiliki slider (carousel) yang bisa di-swipe seperti di TikTok.
- Mendukung **fullscreen viewer** (`post.php`) dengan tombol share dan download.
- Musik latar yang tetap **berlanjut** meski berpindah halaman.
- Penghitung total pengunjung dan _online visitors_ real-time.

### 🛠️ Dashboard Admin
- Upload banyak foto sekaligus (drag & drop reordering).
- Edit album (judul, deskripsi, tambah, hapus, atau ganti foto).
- Reorder foto dalam album via drag & drop (mobile friendly dengan SortableJS).
- Kelola musik latar aktif (`music.php`).
- Statistik album & foto secara keseluruhan.

### 🎧 Musik Latar Sinkron
- Pemutaran musik disimpan via `sessionStorage`, sehingga saat berpindah halaman musik tidak berhenti.
- Admin bisa mengganti file MP3 aktif dari panel.

---

## 🧰 Teknologi yang Digunakan

| Teknologi | Kegunaan |
|------------|-----------|
| **PHP (Native)** | Backend & routing sederhana |
| **MySQL / MariaDB** | Database album, foto, dan pengunjung |
| **Tailwind CSS + DaisyUI** | Styling modern dan responsif |
| **Swiper.js** | Carousel foto & fullscreen viewer |
| **SortableJS** | Drag & drop reordering di mobile |
| **SweetAlert2** | Popup notifikasi dan konfirmasi |
| **AOS.js** | Animasi masuk (fade, slide) |
| **Typed.js** | Efek teks dinamis di halaman utama |

---

## ⚙️ Instalasi Lokal

1. Clone repository ini:
   ```bash
   git clone https://github.com/newbiema/Frame-Of-Us.git
   cd Frame-Of-Us
2. Import file SQL ke database MySQL kamu (misal frame_of_us):
   frames_gallery.sql
4. Edit file db.php agar sesuai dengan konfigurasi lokal:
   $conn = new mysqli("localhost", "root", "", "frame_of_us");
6. Pastikan folder berikut dapat ditulis (writable):
   /uploads
   /music

8. Jalankan di localhost (misal Laragon):
   http://localhost/Frame-Of-Us/


### 🔐 Default Login Admin
| Username | Password                                      |
| -------- | --------------------------------------------- |
| `admin`  | `admin123` *(ubah di database setelah login)* |


### 🚀 Deployment

Project ini sudah diuji di hosting gratis seperti InfinityFree
Pastikan:

- PHP ≥ 8.0

- Ekstensi mysqli dan fileinfo aktif

- Folder uploads/ & music/ memiliki izin tulis (CHMOD 755 atau 777 jika perlu)

### 🧑‍💻 Kontributor

Evan Jamaq – Front-End Developer & Creator

"What was once a moment, now a memory."

- GitHub: @newbiema

- Instagram: @n4ve.666


### 📜 Lisensi

Proyek ini menggunakan MIT License — silakan digunakan, dimodifikasi, dan dikembangkan dengan tetap mencantumkan kredit.

