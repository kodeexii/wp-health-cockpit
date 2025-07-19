# WP Health Cockpit
Dashboard audit teknikal atas-permintaan untuk WordPress anda.

WP Health Cockpit adalah sebuah alat diagnostik ringan yang direka untuk agensi, freelancer, dan pemilik laman web yang serius tentang prestasi. Ia mengumpulkan semua metrik kesihatan teknikal yang kritikal—dari konfigurasi server hinggalah ke tetapan aplikasi—dan memaparkannya dalam satu papan pemuka yang mudah dibaca. Dapatkan gambaran 360-darjah tentang kekuatan dan kelemahan laman web anda dengan satu klik.

## Ciri-ciri Utama 🚀
Plugin ini mengimbas dan melaporkan empat lapisan utama laman web anda:

### 1. Analisis Muka Depan (Frontend)
Audit URL Dinamik: Masukkan mana-mana URL dari laman web anda untuk menjalankan audit frontend secara atas-permintaan tanpa reload halaman (menggunakan AJAX).

Masa Respons Server (TTFB): Mengukur kelajuan tindak balas server untuk permintaan halaman.

Analisis Aset Statik: Mengira bilangan fail CSS & JS yang dimuatkan dalam HTML awal untuk mengesan bloat.

SEO Asas: Memeriksa amalan terbaik seperti penggunaan tag <h1> tunggal dan kehadiran teks alt pada imej.

### 2. Analisis Dalaman WordPress
Konfigurasi Teras: Mengaudit tetapan penting dalam wp-config.php seperti WP_DEBUG, DISABLE_WP_CRON, WP_POST_REVISIONS, dan had memori (WP_MEMORY_LIMIT).

Kitaran Hayat Plugin: Menyenaraikan semua plugin, memberi amaran jika ada kemas kini tersedia, dan mengesan plugin yang mungkin terbiar (tidak dikemas kini lebih dari 1-2 tahun).

Status Object Cache: Memeriksa jika object cache luaran (seperti Redis/Memcached) aktif, yang sangat kritikal untuk laman dinamik.

### 3. Analisis Konfigurasi PHP
Pemeriksaan Menyeluruh: Mengesahkan tetapan php.ini penting seperti memory_limit, max_execution_time, max_input_vars, dan had muat naik fail.

Talaan OPcache: Memastikan 'turbocharger' PHP diaktifkan dan dikonfigurasi dengan memori yang mencukupi.

Pemeriksaan PHP Extensions: Mengesahkan kehadiran extension kritikal seperti Imagick, cURL, dan Sodium.

Pengukuhan Keselamatan: Memberi amaran jika tetapan seperti expose_php atau display_errors diaktifkan pada laman produksi.

### 4. Analisis Pangkalan Data (Database)
Kesihatan Jadual: Memastikan jadual-jadual teras menggunakan enjin InnoDB moden dan set aksara utf8mb4.

Analisis 'Bloat': Mengenal pasti 5 jadual terbesar dan mengukur saiz data autoload dalam wp_options yang sering menjadi punca kelambatan.

Konfigurasi Server DB: Menganalisis tetapan prestasi utama seperti innodb_buffer_pool_size dan memberikan cadangan pintar berdasarkan saiz database dan RAM server (jika dimasukkan).

## Ciri Interaktif
Borang Konfigurasi: Masukkan jumlah RAM server anda untuk mendapatkan cadangan konfigurasi database yang lebih tepat dan dikira khas untuk anda.

