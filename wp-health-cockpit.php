<?php
/**
 * Plugin Name:       WP Health Cockpit
 * Description:       Satu dashboard untuk audit prestasi asas WordPress.
 * Version:           0.6.3-debug
 * Author:            Mat Gem for Hadee Roslan
 * Author URI:        https://had.ee/
 * GitHub Plugin URI: kodeexii/wp-health-cockpit
 */

if ( ! defined( 'ABSPATH' ) ) { die; }

// Kita akan matikan updater buat sementara waktu untuk fokus pada debug
// require_once __DIR__ . '/updater.php';
// new MatGem_GitHub_Plugin_Updater(__FILE__, 'kodeexii', 'wp-health-cockpit');

// (Fungsi lain tidak berubah)
add_action( 'admin_init', 'matgem_register_settings' ); function matgem_register_settings() { register_setting( 'whc_options_group', 'whc_server_specs' ); add_settings_section('whc_settings_section','Konfigurasi Server (Pilihan)','matgem_settings_section_callback','wp-health-cockpit'); add_settings_field('whc_total_ram','Jumlah RAM Server (GB)','matgem_ram_field_callback','wp-health-cockpit','whc_settings_section'); } function matgem_settings_section_callback() { echo '<p>Masukkan spesifikasi server untuk dapatkan cadangan yang lebih tepat. Biarkan kosong jika tidak pasti.</p>'; } function matgem_ram_field_callback() { $options = get_option('whc_server_specs'); $ram = isset($options['total_ram']) ? $options['total_ram'] : ''; echo "<input type='number' name='whc_server_specs[total_ram]' value='" . esc_attr($ram) . "' placeholder='cth: 8' /> GB"; }
add_action( 'admin_menu', 'matgem_add_admin_menu' ); function matgem_add_admin_menu() { add_management_page('WP Health Cockpit', 'Health Cockpit', 'manage_options', 'wp-health-cockpit', 'matgem_render_audit_page'); }
function matgem_get_php_info() { /* ... kod php info sama seperti sebelum ... */ return []; }


// FUNGSI YANG KITA SIASAT
function matgem_get_database_info() {
    global $wpdb;
    error_log("WPHC DBG: [MULA] Memulakan fungsi matgem_get_database_info.");
    $db_info = [];

    // 1
    $db_version = $wpdb->get_var('SELECT VERSION()');
    $db_info['db_version'] = ['label' => 'Versi Database', 'value' => $db_version, 'recommended' => 'MySQL 8.0+ / MariaDB 10.6+', 'status' => 'info', 'notes' => 'Versi baru selalunya lebih pantas dan selamat.'];
    error_log("WPHC DBG: [OK] Langkah 1 Selesai - DB Version: {$db_version}");

    // 2
    $db_charset = $wpdb->charset;
    $db_info['db_charset'] = ['label' => 'Database Charset', 'value' => $db_charset, 'recommended' => 'utf8mb4', 'status' => ($db_charset === 'utf8mb4') ? 'ok' : 'warning', 'notes' => 'utf8mb4 diperlukan untuk sokongan penuh emoji dan pelbagai bahasa.'];
    error_log("WPHC DBG: [OK] Langkah 2 Selesai - DB Charset: {$db_charset}");

    // 3
    $core_tables = [$wpdb->prefix . 'options', $wpdb->prefix . 'posts', $wpdb->prefix . 'postmeta', $wpdb->prefix . 'users'];
    $table_engines_query = $wpdb->get_results("SELECT table_name, engine FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "' AND table_name IN ('" . implode("','", $core_tables) . "')");
    if ($wpdb->last_error) { error_log("WPHC DBG: [RALAT!] MySQL Error on Engine Query: " . $wpdb->last_error); }
    
    $wrong_engine = false;
    if (is_array($table_engines_query)) {
        foreach ($table_engines_query as $table) {
            if (isset($table->engine) && strtoupper($table->engine) !== 'INNODB') {
                $wrong_engine = true;
                break;
            }
        }
    }
    $db_info['db_engine'] = ['label' => 'Enjin Storan Jadual Teras', 'value' => $wrong_engine ? 'Ada MyISAM' : 'Semua InnoDB', 'recommended' => 'InnoDB', 'status' => $wrong_engine ? 'critical' : 'ok', 'notes' => 'InnoDB adalah enjin moden yang penting untuk prestasi laman dinamik.'];
    error_log("WPHC DBG: [OK] Langkah 3 Selesai - Pemeriksaan Enjin Storan.");

    // 4
    $total_db_size_bytes = $wpdb->get_var("SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "'");
    if ($wpdb->last_error) { error_log("WPHC DBG: [RALAT!] MySQL Error on Total Size Query: " . $wpdb->last_error); }
    $total_db_size_mb = $total_db_size_bytes ? round($total_db_size_bytes / 1024 / 1024) : 0;
    $db_info['total_db_size'] = ['label' => 'Saiz Keseluruhan DB', 'value' => $total_db_size_mb . ' MB', 'recommended' => 'N/A', 'status' => 'info', 'notes' => 'Saiz total memberi konteks kepada saiz jadual individu.'];
    error_log("WPHC DBG: [OK] Langkah 4 Selesai - Saiz DB Total: {$total_db_size_mb}MB");

    // Jika mana-mana langkah di atas gagal, ia sepatutnya tercatat dalam log.
    // Kita hentikan fungsi di sini buat sementara untuk analisis.
    
    // Semua kod lain dari fungsi ini dimatikan sementara
    error_log("WPHC DBG: [TAMAT] Fungsi selesai (versi debug).");

    return $db_info;
}


function matgem_render_audit_page() {
    // Paparkan HANYA jadual DB untuk fokus pada debugging
    $db_info_data = matgem_get_database_info();
    ?>
    <style> /* ... CSS sama seperti sebelum ... */ </style>
    <div class="wrap">
        <h1><span class="dashicons dashicons-dashboard" style="font-size:30px;margin-right:10px;"></span>WP Health Cockpit (Mod Forensik)</h1>
        <p>Sila periksa fail /wp-content/debug.log untuk output siasatan.</p>
        
        <h2 style="margin-top: 40px;">Analisis Database (Debug)</h2>
        <table class="whc-table">
            <thead><tr><th>Tetapan</th><th>Nilai Semasa</th><th>Cadangan</th><th>Status</th><th>Nota</th></tr></thead>
            <tbody>
                <?php if (empty($db_info_data)) : ?>
                    <tr><td colspan="5">Tiada data untuk dipaparkan. Sila periksa debug.log.</td></tr>
                <?php else: ?>
                    <?php foreach ($db_info_data as $data) : ?>
                        <tr><td><strong><?php echo esc_html($data['label']); ?></strong></td><td><?php echo $data['value']; ?></td><td><?php echo esc_html($data['recommended']); ?></td><td class="whc-status"><span class="<?php echo esc_attr('status-' . $data['status']); ?>"><?php echo esc_html($data['status']); ?></span></td><td><?php echo wp_kses_post($data['notes']); ?></td></tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}