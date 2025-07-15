<?php
/**
 * Plugin Name:       WP Health Cockpit
 * Description:       Satu dashboard untuk audit prestasi asas WordPress.
 * Version:           0.6.1
 * Author:            Mat Gem for Hadee Roslan
 * Author URI:        https://had.ee/
 * GitHub Plugin URI: kodeexii/wp-health-cockpit
 */

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

// =================================================================================
// Bahagian Auto-Updater (Menggunakan Pustaka Profesional)
// =================================================================================
require_once __DIR__ . '/vendor/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/kodeexii/wp-health-cockpit/',
    __FILE__,
    'wp-health-cockpit'
);

// =================================================================================
// Pendaftaran Tetapan (Settings API)
// =================================================================================

add_action( 'admin_init', 'matgem_register_settings' );

function matgem_register_settings() {
    register_setting( 'whc_options_group', 'whc_server_specs' );
    add_settings_section('whc_settings_section', 'Konfigurasi Server (Pilihan)', 'matgem_settings_section_callback', 'wp-health-cockpit');
    add_settings_field('whc_total_ram', 'Jumlah RAM Server (GB)', 'matgem_ram_field_callback', 'wp-health-cockpit', 'whc_settings_section');
}

function matgem_settings_section_callback() {
    echo '<p>Masukkan spesifikasi server untuk dapatkan cadangan yang lebih tepat. Biarkan kosong jika tidak pasti.</p>';
}

function matgem_ram_field_callback() {
    $options = get_option('whc_server_specs');
    $ram = isset($options['total_ram']) ? $options['total_ram'] : '';
    echo "<input type='number' name='whc_server_specs[total_ram]' value='" . esc_attr($ram) . "' placeholder='cth: 8' /> GB";
}

// =================================================================================
// Bahagian Audit Plugin
// =================================================================================

add_action( 'admin_menu', 'matgem_add_admin_menu' );

function matgem_add_admin_menu() {
    add_management_page('WP Health Cockpit', 'Health Cockpit', 'manage_options', 'wp-health-cockpit', 'matgem_render_audit_page');
}

// (Fungsi get_php_info dan get_database_info tidak berubah)
function matgem_get_php_info() {
    $php_info = [];
    $current_php_version = phpversion(); $php_info['php_version'] = ['label' => 'Versi PHP','value' => $current_php_version,'recommended' => '8.2+','status' => version_compare($current_php_version, '8.2', '>=') ? 'ok' : 'warning','notes' => 'Versi PHP yang lebih baru adalah lebih laju dan selamat.'];
    $memory_limit = ini_get('memory_limit'); $mem_limit_val = wp_convert_hr_to_bytes($memory_limit) / 1024 / 1024; $php_info['memory_limit'] = ['label' => 'PHP Memory Limit','value' => $memory_limit,'recommended' => '256M+','status' => $mem_limit_val >= 256 ? 'ok' : 'warning','notes' => 'Had memori rendah boleh sebabkan ralat "fatal error".'];
    $max_execution_time = ini_get('max_execution_time'); $php_info['max_execution_time'] = ['label' => 'Max Execution Time','value' => $max_execution_time . 's','recommended' => '120s+','status' => $max_execution_time >= 120 ? 'ok' : 'warning','notes' => 'Masa singkat boleh ganggu proses import/export atau backup.'];
    $upload_max = ini_get('upload_max_filesize'); $upload_max_val = wp_convert_hr_to_bytes($upload_max) / 1024 / 1024; $php_info['upload_max_filesize'] = ['label' => 'Upload Max Filesize','value' => $upload_max,'recommended' => '64M+','status' => $upload_max_val >= 64 ? 'ok' : 'warning','notes' => 'Punca biasa pengguna tak boleh muat naik fail/gambar besar.'];
    $post_max = ini_get('post_max_size'); $post_max_val = wp_convert_hr_to_bytes($post_max) / 1024 / 1024; $php_info['post_max_size'] = ['label' => 'Post Max Size','value' => $post_max,'recommended' => '64M+ (mesti >= upload_max_filesize)','status' => ($post_max_val >= 64 && $post_max_val >= $upload_max_val) ? 'ok' : 'warning','notes' => 'Mesti lebih besar dari saiz muat naik untuk benarkan data POST lain.'];
    $max_input_vars = ini_get('max_input_vars'); $php_info['max_input_vars'] = ['label' => 'Max Input Vars','value' => $max_input_vars,'recommended' => '3000+','status' => $max_input_vars >= 3000 ? 'ok' : 'warning','notes' => '"Pembunuh senyap" untuk page builder & menu kompleks.'];
    $opcache_enabled = function_exists('opcache_get_status') && opcache_get_status()['opcache_enabled']; $php_info['opcache'] = ['label' => 'OPcache','value' => $opcache_enabled ? 'Aktif' : 'Tidak Aktif','recommended' => 'Aktif','status' => $opcache_enabled ? 'ok' : 'critical','notes' => 'Mempercepatkan PHP dengan ketara dengan menyimpan kod yang telah dikompil.'];
    $extensions = ['curl', 'gd', 'imagick', 'sodium']; $loaded_extensions = []; foreach ($extensions as $ext) { if (extension_loaded($ext)) { $loaded_extensions[] = ucfirst($ext); } } $imagick_or_gd = extension_loaded('imagick') || extension_loaded('gd');
    $php_info['extensions'] = ['label' => 'PHP Extensions Kritikal','value' => !empty($loaded_extensions) ? implode(', ', $loaded_extensions) : 'Tiada','recommended' => 'cURL, Sodium, & (Imagick atau GD)','status' => (extension_loaded('curl') && $imagick_or_gd) ? 'ok' : 'warning','notes' => 'Komponen penting untuk keselamatan, pemprosesan imej, dan komunikasi API.'];
    return $php_info;
}

function matgem_get_database_info() {
    global $wpdb; $db_info = []; $server_specs = get_option('whc_server_specs'); $total_ram_gb = isset($server_specs['total_ram']) ? (int)$server_specs['total_ram'] : 0;
    $db_version = $wpdb->get_var('SELECT VERSION()'); $db_charset = $wpdb->charset;
    $db_info['db_version'] = ['label' => 'Versi Database','value' => $db_version,'recommended' => 'MySQL 8.0+ / MariaDB 10.6+','status' => 'info','notes' => 'Versi baru selalunya lebih pantas dan selamat.'];
    $db_info['db_charset'] = ['label' => 'Database Charset','value' => $db_charset,'recommended' => 'utf8mb4','status' => ($db_charset === 'utf8mb4') ? 'ok' : 'warning','notes' => 'utf8mb4 diperlukan untuk sokongan penuh emoji dan pelbagai bahasa.'];
    $core_tables = [$wpdb->prefix . 'options', $wpdb->prefix . 'posts', $wpdb->prefix . 'postmeta', $wpdb->prefix . 'users']; $table_engines = $wpdb->get_results("SELECT table_name, engine FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "' AND table_name IN ('" . implode("','", $core_tables) . "')");
    $wrong_engine = false; foreach ($table_engines as $table) { if (strtoupper($table->engine) !== 'INNODB') { $wrong_engine = true; break; } }
    $db_info['db_engine'] = ['label' => 'Enjin Storan Jadual Teras','value' => $wrong_engine ? 'Ada MyISAM' : 'Semua InnoDB','recommended' => 'InnoDB','status' => $wrong_engine ? 'critical' : 'ok','notes' => 'InnoDB adalah enjin moden yang penting untuk prestasi laman dinamik.'];
    $total_db_size_bytes = $wpdb->get_var("SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "'");
    $total_db_size_mb = round($total_db_size_bytes / 1024 / 1024);
    $db_info['total_db_size'] = ['label' => 'Saiz Keseluruhan DB','value' => $total_db_size_mb . ' MB','recommended' => 'N/A','status' => 'info','notes' => 'Saiz total memberi konteks kepada saiz jadual individu.'];
    $buffer_pool_size_row = $wpdb->get_row("SHOW VARIABLES LIKE 'innodb_buffer_pool_size'", ARRAY_A); $buffer_pool_size_bytes = $buffer_pool_size_row ? $buffer_pool_size_row['Value'] : 0; $buffer_pool_size_mb = round($buffer_pool_size_bytes / 1024 / 1024);
    $rec_from_db_size = floor($total_db_size_mb * 1.2); $final_rec_mb = $rec_from_db_size;
    if ($total_ram_gb > 0) { $rec_from_ram = floor($total_ram_gb * 1024 * 0.25); if ($rec_from_ram < $final_rec_mb) { $final_rec_mb = $rec_from_ram; } }
    if ($final_rec_mb < 256) { $final_rec_mb = 256; }
    $status_buffer_pool = 'ok'; if ($buffer_pool_size_mb < ($final_rec_mb * 0.75)) { $status_buffer_pool = 'warning'; } if ($buffer_pool_size_mb < ($final_rec_mb * 0.5)) { $status_buffer_pool = 'critical'; }
    $buffer_pool_notes = 'PENTING: Cadangan ini berdasarkan saiz DB ini & RAM (jika diisi). Ambil kira saiz SEMUA DB lain di server untuk nilai sebenar.';
    $db_info['buffer_pool'] = ['label' => 'InnoDB Buffer Pool Size','value' => $buffer_pool_size_mb . ' MB','recommended' => $final_rec_mb . 'MB','status' => $status_buffer_pool,'notes' => $buffer_pool_notes];
    $autoload_size = $wpdb->get_var("SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload = 'yes'"); $autoload_size_kb = $autoload_size / 1024;
    $status_autoload = 'ok'; if ($autoload_size_kb > 1024) { $status_autoload = 'critical'; } elseif ($autoload_size_kb > 700) { $status_autoload = 'warning'; }
    $db_info['autoload_size'] = ['label' => 'Saiz Autoloaded Data','value' => round($autoload_size_kb, 2) . ' KB','recommended' => '< 700 KB','status' => $status_autoload,'notes' => 'Data yang dimuatkan pada setiap halaman. Saiz besar boleh melambatkan TTFB.'];
    $top_tables = $wpdb->get_results("SELECT table_name, round(((data_length + index_length) / 1024 / 1024), 2) as 'size_in_mb' FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "' ORDER BY (data_length + index_length) DESC LIMIT 5");
    $tables_list = []; if ($top_tables) { foreach ($top_tables as $table) { $tables_list[] = "{$table->table_name} ({$table->size_in_mb} MB)"; } }
    $db_info['top_tables'] = ['label' => '5 Jadual Database Terbesar','value' => implode('<br>', $tables_list),'recommended' => 'N/A','status' => 'info','notes' => 'Bantu kesan \'bloat\' dari plugin atau log. Periksa jadual yang luar biasa besar.'];
    return $db_info;
}

function matgem_render_audit_page() {
    $php_info_data = matgem_get_php_info(); 
    $db_info_data = matgem_get_database_info();
    ?>
    <style> .whc-table{width:100%;border-collapse:collapse;margin-top:20px;table-layout:fixed;}.whc-table th,.whc-table td{padding:12px 15px;border:1px solid #ddd;text-align:left;word-wrap:break-word;}.whc-table th{background-color:#f4f4f4;}.whc-table th:nth-child(1){width:20%;}.whc-table th:nth-child(2),.whc-table th:nth-child(3),.whc-table th:nth-child(4){width:15%;}.whc-table th:nth-child(5){width:35%;}.whc-status span{display:inline-block;padding:5px 10px;color:#fff;border-radius:4px;font-size:12px;text-transform:uppercase;font-weight:bold;}.status-ok{background-color:#28a745;}.status-info{background-color:#17a2b8;}.status-warning{background-color:#ffc107;color:#333;}.status-critical{background-color:#dc3545;} </style>
    <div class="wrap">
        <h1><span class="dashicons dashicons-dashboard" style="font-size:30px;margin-right:10px;"></span>WP Health Cockpit</h1>
        <p>Analisis Peringkat Awal untuk Konfigurasi Server Anda.</p>
        
        <form action="options.php" method="post">
            <?php settings_fields( 'whc_options_group' ); do_settings_sections( 'wp-health-cockpit' ); submit_button( 'Simpan Tetapan' ); ?>
        </form>

        <hr>
        
        <h2 style="margin-top: 40px;">Analisis PHP (Lengkap)</h2>
        <table class="whc-table">
            <thead><tr><th>Tetapan</th><th>Nilai Semasa</th><th>Cadangan</th><th>Status</th><th>Nota</th></tr></thead>
            <tbody><?php foreach ($php_info_data as $data) : ?><tr><td><strong><?php echo esc_html($data['label']); ?></strong></td><td><?php echo esc_html($data['value']); ?></td><td><?php echo esc_html($data['recommended']); ?></td><td class="whc-status"><span class="<?php echo esc_attr('status-' . $data['status']); ?>"><?php echo esc_html($data['status']); ?></span></td><td><?php echo esc_html($data['notes']); ?></td></tr><?php endforeach; ?></tbody>
        </table>

        <h2 style="margin-top: 40px;">Analisis Database (Lengkap)</h2>
        <table class="whc-table">
            <thead><tr><th>Tetapan</th><th>Nilai Semasa</th><th>Cadangan</th><th>Status</th><th>Nota</th></tr></thead>
            <tbody><?php foreach ($db_info_data as $data) : ?><tr><td><strong><?php echo esc_html($data['label']); ?></strong></td><td><?php echo $data['value']; ?></td><td><?php echo esc_html($data['recommended']); ?></td><td class="whc-status"><span class="<?php echo esc_attr('status-' . $data['status']); ?></span></td><td><?php echo wp_kses_post($data['notes']); ?></td></tr><?php endforeach; ?></tbody>
        </table>
    </div>
    <?php
}