# WP Health Cockpit

Dashboard audit teknikal 360-darjah untuk WordPress anda.

WP Health Cockpit adalah alat diagnostik semua-dalam-satu yang direka untuk agensi, freelancer, dan pemilik laman web yang serius tentang prestasi dan keselamatan. Ia mengumpulkan metrik kesihatan teknikal yang kritikal dari keseluruhan stack anda—dari konfigurasi server hingga ke keselamatan aplikasi—dan memaparkannya dalam satu papan pemuka yang kemas dan boleh diambil tindakan.

## Ciri-ciri Utama 🚀
Plugin ini menyediakan imbasan komprehensif merentasi enam (6) lapisan utama laman web anda:

### 1. Imbasan Keselamatan Asas 🛡️
Mengesan konfigurasi tidak selamat dan amalan terbaik yang sering terlepas pandang.

Memeriksa awalan database yang tidak selamat (wp_).

Mengesahkan kunci keselamatan dalam wp-config.php telah ditetapkan.

Memberi amaran jika suntingan fail dari dashboard dibenarkan.

Mengesan pendedahan nama pengguna melalui REST API.

Menyemak kewujudan nama pengguna "admin".

Mengesahkan integriti folder plugin untuk mengesan fail 'hantu'.

### 2. Analisis Kitaran Hayat Plugin 🔄
Menilai kesihatan dan risiko ekosistem plugin anda.

Menyenaraikan semua plugin dengan status kemas kini tersedia.

Menghubungi API WordPress.org untuk mengenal pasti plugin terbiar (tidak dikemas kini lebih dari 1-2 tahun).

Mengira plugin tidak aktif yang patut dibuang.

### 3. Analisis Muka Depan (Frontend) ⏱️
Mengaudit prestasi dari perspektif pengguna.

Audit URL Dinamik untuk mana-mana halaman di laman web anda.

Mengukur masa respons server (TTFB Belakang).

Menganalisis bilangan aset statik (CSS & JS) dan saiz HTML.

### 4. Analisis Dalaman WordPress ⚙️
Menyelam ke dalam konfigurasi teras WordPress.

Mengaudit tetapan penting dalam wp-config.php seperti WP_DEBUG, DISABLE_WP_CRON, dan had memori.

Mengesahkan status Object Cache Kekal (Redis/Memcached).

Menyemak konfigurasi Revisi Pos untuk mengelakkan database bloat.

### 5. Analisis Konfigurasi PHP 💻
Memastikan enjin PHP anda ditala untuk prestasi dan keselamatan.

Mengesahkan tetapan php.ini kritikal seperti memory_limit, max_execution_time, dan max_input_vars.

Memastikan OPcache aktif dan dikonfigurasi dengan betul.

Menyemak kehadiran PHP extensions penting seperti Imagick dan Sodium.

### 6. Analisis Pangkalan Data (Database) 🗃️
Menyiasat kesihatan dan kecekapan pangkalan data anda.

Memastikan jadual teras menggunakan enjin InnoDB moden dan charset utf8mb4.

Menganalisis 'bloat' dengan menyenaraikan jadual terbesar dan saiz data autoload.

Memberi cadangan pintar untuk saiz innodb_buffer_pool_size berdasarkan RAM server (jika dimasukkan).



