=== WP Health Cockpit ===
Contributors: hadeeroslan, matgem
Tags: health, audit, performance, security, database, optimizer
Requires at least: 5.8
Tested up to: 6.6
Stable tag: 1.12.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Diagnostik teknikal 360-darjah dan alat optimasi on-the-fly untuk WordPress anda.

== Description ==

**WP Health Cockpit** adalah alat diagnostik dan optimasi semua-dalam-satu yang direka untuk agensi, freelancer, dan pemilik laman web yang serius tentang prestasi dan keselamatan. Ia bukan sekadar dashboard statik, tetapi pusat kawalan aktif untuk memantau dan membaiki isu teknikal laman web anda.

Plugin ini mengaudit tujuh (7) lapisan utama laman web anda (termasuk Multisite) dengan pendekatan **Context-Aware**, di mana cadangan diberikan berdasarkan jenis projek, spesifikasi server, dan anggaran trafik anda.

= Ciri-ciri Utama =
* **🏗️ MyISAM to InnoDB Converter (Baru!):** Tukar enjin jadual pangkalan data yang lama kepada enjin moden yang lebih pantas dan selamat.
* **🌐 Analisis Multisite:** Audit khusus untuk persekitaran Multisite termasuk saiz sitemeta, global tables, dan beban sub-site.
* **🛡️ Audit Keselamatan Asas:** Mengesan isu prefix database, salts, pendedahan REST API, dan integriti folder plugin.
* **🔄 Analisis Plugin:** Mengenal pasti plugin yang terbiar (abandoned), tidak aktif, atau memerlukan kemas kini.
* **⚡ Active Optimizer:** Butang "Quick Fix" untuk cuci Post Revisions dan Expired Transients dengan satu klik.
* **🎚️ Performance Toggles:** Matikan Emojis, sembunyi versi WP, dan matikan XML-RPC terus dari dashboard.
* **⏱️ Analisis Frontend:** Mengukur TTFB, saiz HTML, serta bilangan aset CSS/JS.
* **💻 Smart PHP & DB Audit:** Cadangan pintar untuk InnoDB Buffer Pool dan PHP settings berdasarkan RAM/CPU server anda.

== Installation ==

1. Muat naik folder `wp-health-cockpit` ke direktori `/wp-content/plugins/`.
2. Aktifkan plugin melalui menu 'Plugins' di WordPress.
3. Pergi ke **Tools > Health Cockpit** untuk mula memantau.

== Screenshots ==

1. Dashboard utama dengan status indicator warna.
2. Bahagian Optimizer dengan butang Quick Fix.
3. Tetapan spesifikasi server untuk cadangan pintar.

== Changelog ==

= 1.12.0 =
* **Feature:** Memperkenalkan alat "MyISAM to InnoDB Converter" dalam modul DB Optimizer.
* **Feature:** Menambah fungsi pengesanan jadual yang masih menggunakan enjin MyISAM secara automatik.
* **Feature:** Menambah butang penukaran enjin jadual secara individu dan pukal (Bulk Convert) dengan sokongan Checkboxes.
* **UI:** Menambah seksyen paparan jadual MyISAM yang kemas dengan indikator saiz (MB) setiap jadual.

= 1.11.0 =
* **Feature:** Memperkenalkan modul `WHC_Audit_Multisite` untuk sokongan penuh persekitaran Multisite.
* **Feature:** Menambah jadual "🌐 Analisis Multisite" ke dashboard apabila WordPress Multisite dikesan.
* **Audit:** Menambah audit saiz `sitemeta` (Network Settings) dan status global tables (`users`, `usermeta`).
* **Audit:** Menambah analisis beban sub-site (Top 5 Sub-sites Terbesar) untuk mengenal pasti penggunaan sumber DB yang tinggi.
* **Audit:** Menambah semakan status kesihatan sub-sites (Spam, Archived, Deleted).

= 1.10.0 =
* **Feature:** Memperkenalkan sistem "Manual Audit + Caching" - data dimuatkan dari internal storage untuk kelajuan dashboard yang maksimum.
* **Feature:** Menambah butang "Jalankan Audit Penuh" dengan pengesanan timestamp imbasan terakhir.
* **Feature:** Upgrade DB Optimizer dengan fungsi "Bulk Processing" (Checkboxes, Select All).
* **Feature:** Menambah "Option Identifier" untuk mengenal pasti asal-usul data database (WP Core vs Plugin).
* **Feature:** Menambah indikator visual "Saiz Autoloaded Semasa" dengan amaran warna (800KB - 1MB limit).
* **Feature:** Menambah section "Danger Zone" di Settings untuk reset data plugin dengan selamat.
* **Security:** Audit REST API sekarang dapat mengesan teknik "Obfuscation" untuk perlindungan username.
* **Security:** Menambah semak pendedahan fail `debug.log` secara terbuka.
* **Performance:** Toggles Performance & Security sekarang bijak mengesan jika fungsi tersebut sudah diuruskan oleh plugin lain.
* **Audit:** Menambah semakan `WP_MAX_MEMORY_LIMIT`, Status SSL/HTTPS, WP-Cron vs System Cron, dan Database Fragmentation.
* **Logic:** Smart Hardware Adequacy Check yang memantau kecukupan RAM & CPU berdasarkan profil projek.
* **UI:** Menyusun semula konfigurasi ke dalam submenu 'Settings' yang berasingan.

= 1.9.6 =
* **UI:** Menambah amaran keselamatan (Security Warning Notice) pada halaman DB Optimizer.
* **Safety:** Memberi peringatan kepada pengguna untuk membuat backup database sebelum melakukan sebarang tindakan pembersihan.

= 1.9.5 =
* **UI:** Menukar menu daripada sub-halaman 'Tools' kepada Top-Level Menu di sidebar.
* **UI:** Menambah submenu 'Dashboard' dan 'DB Optimizer' untuk navigasi yang lebih baik.
* **Bugfix:** Membetulkan isu pemuatan skrip pada halaman menu yang baharu.

= 1.9.4 =
* **Feature:** Menambah modul `DB Optimizer` sebagai sub-menu baharu.
* **Feature:** Memperkenalkan `Autoload Manager` untuk menguruskan data autoloaded yang besar.
* **Feature:** Menambah `Orphaned/Inactive Cleaner` untuk memadam options milik plugin yang tidak aktif.
* **Audit:** Menampilkan senarai "Top 10 Autoload Offenders" terus di dashboard audit utama.

= 1.9.3 =
* **Feature:** Menambah fail `readme.txt` yang lengkap untuk paparan "View Details" WordPress yang lebih kemas.
* **Feature:** Menyusun semula sejarah perubahan (Changelog) untuk rujukan pengguna.

= 1.9.2 =
* **Feature:** Memperkenalkan modul `WHC_Optimizer` untuk optimasi aktif.
* **Feature:** Menambah butang "Quick Fix" untuk pembersihan Post Revisions dan Expired Transients.
* **Feature:** Menambah Performance Toggles (Disable Emojis, Hide WP Version, Disable XML-RPC).
* **UI:** Menambah kolum 'Tindakan' (Action) dalam jadual audit.

= 1.9.1 =
* **Bugfix:** Membaiki isu pengiraan Autoload Data untuk WordPress 6.6+ (sokongan status 'on'/'yes').
* **Feature:** Memperkenalkan Smart Context Audit (Project Type, Storage Type, Traffic Level).
* **Feature:** Menambah semula input RAM dan CPU Cores untuk pengiraan cadangan yang lebih tepat.

= 1.9.0 =
* **Refactor:** Penukaran struktur kod sepenuhnya kepada OOP (Object-Oriented Programming).
* **Feature:** Melengkapkan 6 lapisan audit (Security, Plugins, WordPress Core, PHP, Database, Frontend).
* **UI:** Dashboard baru dengan indikator status warna (Hijau/Kuning/Merah).

= 1.8.0 =
* **Release:** Versi stabil pertama dengan senibina modular.
* **Feature:** Audit asas PHP, Database, dan WordPress Core.

= 1.0.0 =
* Pelancaran versi awal (Beta).

== Upgrade Notice ==

= 1.9.2 =
Versi ini membawakan modul Optimizer baru. Sangat disyorkan untuk semua pengguna bagi memudahkan urusan pembersihan pangkalan data.
