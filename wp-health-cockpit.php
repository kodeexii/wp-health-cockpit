<?php
/**
 * Plugin Name:       WP Health Cockpit
 * Description:       Plugin diagnostik ringan yang direka untuk agensi, freelancer, dan pemilik laman web yang serius tentang prestasi.
 * Version:           1.5.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Tested up to:      6.8.2
 * Author:            Hadee Roslan & Mat Gem
 * Author URI:        https://hadeeroslan.my/
 * GitHub Plugin URI: kodeexii/wp-health-cockpit
 */

if ( ! defined( 'ABSPATH' ) ) { die; }

// =================================================================================
// Bahagian Auto-Updater
// =================================================================================
if ( file_exists(__DIR__ . '/vendor/plugin-update-checker/plugin-update-checker.php') ) {
    require_once __DIR__ . '/vendor/plugin-update-checker/plugin-update-checker.php';
    $myUpdateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker( 'https://github.com/kodeexii/wp-health-cockpit/', __FILE__, 'wp-health-cockpit' );
}

// =================================================================================
// Pendaftaran Tetapan & Skrip
// =================================================================================
add_action( 'admin_init', 'matgem_register_settings' );
function matgem_register_settings() {
    register_setting( 'whc_options_group', 'whc_server_specs' );
    add_settings_section('whc_settings_section', 'Konfigurasi Server (Pilihan)', 'matgem_settings_section_callback', 'wp-health-cockpit');
    add_settings_field('whc_total_ram', 'Jumlah RAM Server (GB)', 'matgem_ram_field_callback', 'wp-health-cockpit', 'whc_settings_section');
}
function matgem_settings_section_callback() { echo '<p>Masukkan spesifikasi server untuk dapatkan cadangan yang lebih tepat. Biarkan kosong jika tidak pasti.</p>'; }
function matgem_ram_field_callback() {
    $options = get_option('whc_server_specs');
    $ram = isset($options['total_ram']) ? $options['total_ram'] : '';
    echo "<input type='number' name='whc_server_specs[total_ram]' value='" . esc_attr($ram) . "' placeholder='cth: 8' /> GB";
}

add_action( 'admin_enqueue_scripts', 'matgem_enqueue_admin_styles' );
function matgem_enqueue_admin_styles($hook) {
    if ($hook !== 'tools_page_wp-health-cockpit') { return; }
    $custom_css = "
        .whc-table{width:100%;border-collapse:collapse;margin-top:20px;table-layout:fixed;}
        .whc-table th,.whc-table td{padding:12px 15px;border:1px solid #ddd;text-align:left;word-wrap:break-word;}
        .whc-table th{background-color:#f4f4f4;}
        .whc-table th:nth-child(1){width:25%;}
        .whc-table th:nth-child(2),.whc-table th:nth-child(3),.whc-table th:nth-child(4){width:15%;}
        .whc-table th:nth-child(5){width:30%;}
        .whc-status span{display:inline-block;padding:5px 10px;color:#fff;border-radius:4px;font-size:12px;text-transform:uppercase;font-weight:bold;}
        .status-ok{background-color:#28a745;}
        .status-info{background-color:#17a2b8;}
        .status-warning{background-color:#ffc107;color:#333;}
        .status-critical{background-color:#dc3545;}
        .whc-notes-box { margin-top: 25px; padding: 15px; border-left: 4px solid #17a2b8; background: #fff; box-shadow: 0 1px 1px 0 rgba(0,0,0,.1); }
        .whc-notes-box h3 { margin-top: 0; } .whc-notes-box ul { list-style-type: disc; padding-left: 20px; }
        .whc-code-box { background: #f7f7f7; padding: 15px; margin-top: 20px; border-radius: 4px; border: 1px solid #ddd; }
        .whc-code-box pre { white-space: pre-wrap; word-wrap: break-word; margin: 0; font-family: monospace; }
    ";
    wp_add_inline_style('wp-admin', $custom_css);
}

// =================================================================================
// Bahagian Audit Plugin
// =================================================================================

add_action( 'admin_menu', 'matgem_add_admin_menu' );
function matgem_add_admin_menu() {
    add_management_page('WP Health Cockpit','Health Cockpit','manage_options','wp-health-cockpit','matgem_render_audit_page');
}

// --- FUNGSI BARU #1: Mendaftarkan Skrip & Menghantar Data ke JavaScript ---
add_action( 'admin_enqueue_scripts', 'matgem_enqueue_admin_scripts' );
function matgem_enqueue_admin_scripts($hook) {
    // Hanya muatkan skrip pada halaman plugin kita
    if ($hook !== 'tools_page_wp-health-cockpit') {
        return;
    }

    // Daftarkan fail JavaScript kita
    wp_enqueue_script(
        'whc-audit-script',
        plugin_dir_url(__FILE__) . 'assets/audit.js',
        ['jquery'], // Bergantung pada jQuery
        '1.4.0',
        true // Muatkan di footer
    );

    // Hantar maklumat penting dari PHP ke JavaScript dengan selamat
    wp_localize_script(
        'whc-audit-script',
        'whc_ajax_object',
        [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('whc_frontend_audit_nonce'),
        ]
    );
}

// --- FUNGSI BARU #2: Handler untuk Permintaan AJAX ---
add_action( 'wp_ajax_run_frontend_audit', 'matgem_run_frontend_audit_ajax' );
function matgem_run_frontend_audit_ajax() {
    // 1. Sahkan keselamatan
    check_ajax_referer('whc_frontend_audit_nonce');

    // 2. Dapatkan & bersihkan URL dari JavaScript
    if (!isset($_POST['url']) || empty($_POST['url'])) {
        wp_send_json_error(['message' => 'URL tidak diterima.']);
    }
    $sanitized_url = esc_url_raw($_POST['url']);

    // 3. Sahkan URL adalah dari domain yang sama
    if (strpos($sanitized_url, home_url()) !== 0) {
        wp_send_json_error(['message' => 'URL tidak sah.']);
    }

    // 4. Jalankan fungsi audit
    $frontend_data = matgem_get_frontend_info($sanitized_url);

    // 5. Hantar semula data sebagai respons JSON yang berjaya
    wp_send_json_success($frontend_data);
}


function matgem_get_php_info() {
    $php_info = [];
    $current_php_version = phpversion(); $php_info['php_version'] = ['label' => 'Versi PHP','value' => $current_php_version,'recommended' => '8.2+','status' => version_compare($current_php_version, '8.2', '>=') ? 'ok' : 'warning','notes' => 'Versi PHP yang lebih baru adalah lebih laju dan selamat.'];
    $memory_limit = ini_get('memory_limit'); $mem_limit_val = wp_convert_hr_to_bytes($memory_limit) / 1024 / 1024; $php_info['memory_limit'] = ['label' => 'PHP Memory Limit (Server)','value' => $memory_limit,'recommended' => '256M+','status' => $mem_limit_val >= 256 ? 'ok' : 'warning','notes' => 'Had memori peringkat server. Ini adalah had tertinggi.'];
    $max_execution_time = ini_get('max_execution_time'); $php_info['max_execution_time'] = ['label' => 'Max Execution Time','value' => $max_execution_time . 's','recommended' => '120s+','status' => $max_execution_time >= 120 ? 'ok' : 'warning','notes' => 'Masa singkat boleh ganggu proses import/export atau backup.'];
    $upload_max = ini_get('upload_max_filesize'); $upload_max_val = wp_convert_hr_to_bytes($upload_max) / 1024 / 1024; $php_info['upload_max_filesize'] = ['label' => 'Upload Max Filesize','value' => $upload_max,'recommended' => '64M+','status' => $upload_max_val >= 64 ? 'ok' : 'warning','notes' => 'Punca biasa pengguna tak boleh muat naik fail/gambar besar.'];
    $post_max = ini_get('post_max_size'); $post_max_val = wp_convert_hr_to_bytes($post_max) / 1024 / 1024; $php_info['post_max_size'] = ['label' => 'Post Max Size','value' => $post_max,'recommended' => '64M+ (mesti >= upload_max_filesize)','status' => ($post_max_val >= 64 && $post_max_val >= $upload_max_val) ? 'ok' : 'warning','notes' => 'Mesti lebih besar dari saiz muat naik untuk benarkan data POST lain.'];
    $max_input_vars = ini_get('max_input_vars'); $php_info['max_input_vars'] = ['label' => 'Max Input Vars','value' => $max_input_vars,'recommended' => '3000+','status' => $max_input_vars >= 3000 ? 'ok' : 'warning','notes' => '"Pembunuh senyap" untuk page builder & menu kompleks.'];
    $opcache_enabled = function_exists('opcache_get_status') && opcache_get_status()['opcache_enabled']; $php_info['opcache'] = ['label' => 'OPcache','value' => $opcache_enabled ? 'Aktif' : 'Tidak Aktif','recommended' => 'Aktif','status' => $opcache_enabled ? 'ok' : 'critical','notes' => 'Wajib "On". Ini \'turbocharger\' utama PHP.'];
    if ($opcache_enabled) { $opcache_mem = ini_get('opcache.memory_consumption'); $php_info['opcache_mem'] = ['label' => 'OPcache Memory', 'value' => $opcache_mem . 'M', 'recommended' => '128+', 'status' => $opcache_mem >= 128 ? 'ok' : 'warning', 'notes' => 'Saiz memori (MB) untuk OPcache.']; $opcache_strings = ini_get('opcache.interned_strings_buffer'); $php_info['opcache_strings'] = ['label' => 'OPcache Strings Buffer', 'value' => $opcache_strings . 'M', 'recommended' => '16+', 'status' => $opcache_strings >= 16 ? 'ok' : 'warning', 'notes' => 'Memori (MB) untuk mengoptimumkan string berulang.']; }
    $expose_php = ini_get('expose_php'); $php_info['expose_php'] = ['label' => 'Expose PHP', 'value' => $expose_php ? 'On' : 'Off', 'recommended' => 'Off', 'status' => !$expose_php ? 'ok' : 'critical', 'notes' => 'Langkah keselamatan untuk sorokkan versi PHP anda dari penggodam.'];
    $display_errors = ini_get('display_errors'); $php_info['display_errors'] = ['label' => 'Display Errors', 'value' => $display_errors ? 'On' : 'Off', 'recommended' => 'Off', 'status' => !$display_errors ? 'ok' : 'critical', 'notes' => 'Wajib "Off" pada laman produksi untuk elak pendedahan maklumat sensitif.'];
    $extensions = ['curl', 'gd', 'imagick', 'sodium']; $loaded_extensions = []; foreach ($extensions as $ext) { if (extension_loaded($ext)) { $loaded_extensions[] = ucfirst($ext); } } $imagick_or_gd = extension_loaded('imagick') || extension_loaded('gd');
    $php_info['extensions'] = ['label' => 'PHP Extensions Kritikal','value' => !empty($loaded_extensions) ? implode(', ', $loaded_extensions) : 'Tiada','recommended' => 'cURL, Sodium, & (Imagick atau GD)','status' => (extension_loaded('curl') && $imagick_or_gd) ? 'ok' : 'warning','notes' => 'Komponen penting untuk keselamatan, pemprosesan imej, dan komunikasi API.'];
    return $php_info;
}

function matgem_get_database_info() {
    global $wpdb; $db_info = []; $server_specs = get_option('whc_server_specs'); $total_ram_gb = isset($server_specs['total_ram']) ? (int)$server_specs['total_ram'] : 0;
    $db_version = $wpdb->get_var('SELECT VERSION()'); $db_charset = $wpdb->charset;
    $db_info['db_version'] = ['label' => 'Versi Database','value' => $db_version,'recommended' => 'MySQL 8.0+ / MariaDB 10.6+','status' => 'info','notes' => 'Versi baru selalunya lebih pantas dan selamat.'];
    $db_info['db_charset'] = ['label' => 'Database Charset','value' => $db_charset,'recommended' => 'utf8mb4','status' => ($db_charset === 'utf8mb4') ? 'ok' : 'warning','notes' => 'utf8mb4 diperlukan untuk sokongan penuh emoji dan pelbagai bahasa.'];
    $core_tables = [$wpdb->prefix . 'options', $wpdb->prefix . 'posts', $wpdb->prefix . 'postmeta', $wpdb->prefix . 'users']; $table_engines_query = $wpdb->get_results("SELECT table_name, engine FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "' AND table_name IN ('" . implode("','", $core_tables) . "')");
    $wrong_engine = false; if(is_array($table_engines_query)){ foreach ($table_engines_query as $table) { if (isset($table->engine) && strtoupper($table->engine) !== 'INNODB') { $wrong_engine = true; break; } } }
    $db_info['db_engine'] = ['label' => 'Enjin Storan Jadual Teras','value' => $wrong_engine ? 'Ada MyISAM' : 'Semua InnoDB','recommended' => 'InnoDB','status' => $wrong_engine ? 'critical' : 'ok','notes' => 'InnoDB adalah enjin moden yang penting untuk prestasi laman dinamik.'];
    $total_db_size_bytes = $wpdb->get_var("SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "'");
    $total_db_size_mb = $total_db_size_bytes ? round($total_db_size_bytes / 1024 / 1024) : 0;
    $db_info['total_db_size'] = ['label' => 'Saiz Keseluruhan DB','value' => $total_db_size_mb . ' MB','recommended' => 'N/A','status' => 'info','notes' => 'Saiz total memberi konteks kepada saiz jadual individu.'];
    $buffer_pool_size_row = $wpdb->get_row("SHOW VARIABLES LIKE 'innodb_buffer_pool_size'", ARRAY_A); $buffer_pool_size_bytes = $buffer_pool_size_row ? $buffer_pool_size_row['Value'] : 0; $buffer_pool_size_mb = round($buffer_pool_size_bytes / 1024 / 1024);
    $rec_from_db_size = floor($total_db_size_mb * 1.2); $final_rec_mb = $rec_from_db_size;
    if ($total_ram_gb > 0) { $rec_from_ram = floor($total_ram_gb * 1024 * 0.25); if ($rec_from_ram < $final_rec_mb) { $final_rec_mb = $rec_from_ram; } }
    if ($final_rec_mb < 256) { $final_rec_mb = 256; }
    $status_buffer_pool = 'ok'; if ($buffer_pool_size_mb < ($final_rec_mb * 0.75)) { $status_buffer_pool = 'warning'; } if ($buffer_pool_size_mb < ($final_rec_mb * 0.5)) { $status_buffer_pool = 'critical'; }
    $buffer_pool_notes = 'PENTING: Cadangan ini berdasarkan saiz DB ini & RAM (jika diisi). Ambil kira saiz SEMUA DB lain di server untuk nilai sebenar.';
    $db_info['buffer_pool'] = ['label' => 'InnoDB Buffer Pool Size','value' => $buffer_pool_size_mb . ' MB','recommended' => $final_rec_mb . 'MB','status' => $status_buffer_pool,'notes' => $buffer_pool_notes];
    $autoload_size = $wpdb->get_var("SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload = 'yes'"); $autoload_size_kb = $autoload_size ? round($autoload_size / 1024, 2) : 0;
    $status_autoload = 'ok'; if ($autoload_size_kb > 1024) { $status_autoload = 'critical'; } elseif ($autoload_size_kb > 700) { $status_autoload = 'warning'; }
    $db_info['autoload_size'] = ['label' => 'Saiz Autoloaded Data','value' => $autoload_size_kb . ' KB','recommended' => '< 700 KB','status' => $status_autoload,'notes' => 'Data yang dimuatkan pada setiap halaman. Saiz besar boleh melambatkan TTFB.'];
    $top_tables = $wpdb->get_results("SELECT table_name, round(((data_length + index_length) / 1024 / 1024), 2) as 'size_in_mb' FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "' ORDER BY (data_length + index_length) DESC LIMIT 5");
    $tables_list = []; if ($top_tables) { foreach ($top_tables as $table) { $tables_list[] = "{$table->table_name} ({$table->size_in_mb} MB)"; } }
    $db_info['top_tables'] = ['label' => '5 Jadual Database Terbesar','value' => implode('<br>', $tables_list),'recommended' => 'N/A','status' => 'info','notes' => 'Bantu kesan \'bloat\' dari plugin atau log. Periksa jadual yang luar biasa besar.'];
    return $db_info;
}

function matgem_get_wp_info() {
    $wp_info = [];
    
    $wp_mem_limit_val = defined('WP_MEMORY_LIMIT') ? constant('WP_MEMORY_LIMIT') : 'Default (40M)';
    $wp_info['wp_memory_limit'] = ['label' => 'WP_MEMORY_LIMIT', 'value' => $wp_mem_limit_val, 'recommended' => '256M', 'status' => 'info', 'notes' => 'Had memori untuk operasi frontend.'];
    
    $wp_max_mem_limit_val = defined('WP_MAX_MEMORY_LIMIT') ? constant('WP_MAX_MEMORY_LIMIT') : 'Default (256M)';
    $wp_info['wp_max_memory_limit'] = ['label' => 'WP_MAX_MEMORY_LIMIT', 'value' => $wp_max_mem_limit_val, 'recommended' => '512M', 'status' => 'info', 'notes' => 'Had memori untuk proses backend/admin.'];

    $is_wp_cron_disabled = (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON);
    $wp_info['wp_cron'] = ['label' => 'DISABLE_WP_CRON', 'value' => $is_wp_cron_disabled ? 'true' : 'false (Default)', 'recommended' => 'true', 'status' => $is_wp_cron_disabled ? 'ok' : 'warning', 'notes' => 'Gantikan dengan server cron untuk kecekapan.'];
    
    $is_object_cache_persistent = function_exists('wp_using_ext_object_cache') ? wp_using_ext_object_cache() : false;
    $wp_info['object_cache'] = ['label' => 'Object Cache Kekal', 'value' => $is_object_cache_persistent ? 'Aktif' : 'Tidak Aktif', 'recommended' => 'Aktif (Redis/Memcached)', 'status' => $is_object_cache_persistent ? 'ok' : 'critical', 'notes' => 'Sangat kritikal untuk prestasi laman dinamik.'];

    $revisions_status_val = 'Default (semua)';
    if (defined('WP_POST_REVISIONS')) {
        $revisions = constant('WP_POST_REVISIONS');
        if ($revisions === false) { $revisions_status_val = 'false (Dinyahaktifkan)'; } 
        elseif (is_numeric($revisions)) { $revisions_status_val = (string)$revisions; }
    }
    $wp_info['post_revisions'] = ['label' => 'WP_POST_REVISIONS', 'value' => $revisions_status_val, 'recommended' => '3', 'status' => defined('WP_POST_REVISIONS') && WP_POST_REVISIONS !== true ? 'ok' : 'warning', 'notes' => 'Menghadkan revisi mengurangkan saiz jadual wp_posts.'];
    
    $trash_days_val = defined('EMPTY_TRASH_DAYS') ? constant('EMPTY_TRASH_DAYS') : '30 (Default)';
    $wp_info['trash_days'] = ['label' => 'EMPTY_TRASH_DAYS', 'value' => $trash_days_val, 'recommended' => '7', 'status' => $trash_days_val <= 7 && $trash_days_val > 0 ? 'ok' : 'warning', 'notes' => 'Membersihkan DB secara automatik dengan lebih kerap.'];

    $is_debug_on = (defined('WP_DEBUG') && WP_DEBUG);
    $wp_info['debug_mode'] = ['label' => 'WP_DEBUG', 'value' => $is_debug_on ? 'true' : 'false', 'recommended' => 'false', 'status' => !$is_debug_on ? 'ok' : 'critical', 'notes' => 'Jangan diaktifkan pada laman produksi.'];

    $is_debug_display_on = (defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY);
    $wp_info['debug_display'] = ['label' => 'WP_DEBUG_DISPLAY', 'value' => $is_debug_display_on ? 'true' : 'false (Default)', 'recommended' => 'false', 'status' => !$is_debug_display_on ? 'ok' : 'critical', 'notes' => 'Sangat merbahaya untuk mendedahkan ralat di laman produksi.'];
    
    // $disallow_file_edit = (defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT);
    // $wp_info['disallow_file_edit'] = ['label' => 'DISALLOW_FILE_EDIT', 'value' => $disallow_file_edit ? 'true' : 'false', 'recommended' => 'true', 'status' => $disallow_file_edit ? 'ok' : 'critical', 'notes' => 'Langkah keselamatan kritikal untuk halang penggodam.'];

    $wp_auto_update_core_val = defined('WP_AUTO_UPDATE_CORE') ? (is_bool(constant('WP_AUTO_UPDATE_CORE')) ? (constant('WP_AUTO_UPDATE_CORE') ? 'true (Semua)' : 'false (Tiada)') : "'" . constant('WP_AUTO_UPDATE_CORE') . "'") : "'minor' (Default)";
    $wp_info['auto_update_core'] = ['label' => 'WP_AUTO_UPDATE_CORE', 'value' => $wp_auto_update_core_val, 'recommended' => "'minor'", 'status' => strpos($wp_auto_update_core_val, 'minor') !== false ? 'ok' : 'warning', 'notes' => 'Keseimbangan baik antara keselamatan dan kestabilan.'];

    if ( ! function_exists( 'get_plugins' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; } $active_plugins = get_option('active_plugins', []); $active_plugins_count = count($active_plugins);
    $wp_info['active_plugins'] = ['label' => 'Bilangan Plugin Aktif', 'value' => $active_plugins_count, 'recommended' => '< 25', 'status' => $active_plugins_count <= 25 ? 'ok' : 'warning', 'notes' => 'Jumlah plugin tinggi boleh jadi petunjuk isu prestasi.'];
    
    $active_theme = wp_get_theme(); $theme_name = $active_theme->get('Name'); $theme_version = $active_theme->get('Version');
    $wp_info['active_theme'] = ['label' => 'Theme Aktif', 'value' => "{$theme_name} (v{$theme_version})", 'recommended' => 'N/A', 'status' => 'info', 'notes' => 'Pastikan theme sentiasa dikemas kini.'];
    
    $all_plugins = get_plugins(); $inactive_plugins_count = count($all_plugins) - $active_plugins_count;
    $wp_info['inactive_plugins'] = ['label' => 'Plugin Tidak Aktif', 'value' => $inactive_plugins_count, 'recommended' => '0', 'status' => $inactive_plugins_count == 0 ? 'ok' : 'warning', 'notes' => 'Amalan terbaik adalah memadam plugin yang tidak aktif.'];
    
    $wp_info['heartbeat'] = ['label' => 'WordPress Heartbeat API','value' => 'Default (Aktif)','recommended' => 'Kawal (guna plugin)','status' => 'info','notes' => 'API ini boleh menyebabkan beban CPU tinggi.'];
    return $wp_info;
}

function matgem_get_frontend_info($target_url) {
    $frontend_info = [];
    
    // Ukur Masa Respons Server (TTFB Belakang)
    $start_time = microtime(true);
    $response = wp_remote_get($target_url, ['timeout' => 20, 'sslverify' => false]);
    $end_time = microtime(true);
    
    $ttfb = round(($end_time - $start_time) * 1000); // dalam milisaat

    $ttfb_status = 'ok';
    if ($ttfb > 600) { $ttfb_status = 'critical'; }
    elseif ($ttfb > 200) { $ttfb_status = 'warning'; }
    $frontend_info['ttfb'] = ['label' => 'Masa Respons Server (TTFB Belakang)', 'value' => "{$ttfb} ms", 'recommended' => '< 200 ms', 'status' => $ttfb_status, 'notes' => 'Masa yang diambil oleh server untuk mula memulangkan data. Sangat penting untuk persepsi kelajuan.'];

    // Semak jika permintaan gagal
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        $error_message = is_wp_error($response) ? $response->get_error_message() : 'Kod Respons: ' . wp_remote_retrieve_response_code($response);
        $frontend_info['homepage_access'] = ['label' => 'Akses URL', 'value' => 'Gagal diakses', 'recommended' => 'Boleh diakses', 'status' => 'critical', 'notes' => esc_html($error_message)];
        return $frontend_info;
    }

    $html = wp_remote_retrieve_body($response);
    
    $css_files = preg_match_all('/<link[^>]+rel=[\'"]stylesheet[\'"]/i', $html, $matches);
    $js_files = preg_match_all('/<script[^>]+src=[\'"]/i', $html, $matches);
    $total_assets = $css_files + $js_files;
    $frontend_info['asset_count'] = [
        'label'       => 'Aset Statik (dalam HTML Awal)', 
        'value'       => "CSS: {$css_files}, JS: {$js_files} (Total: {$total_assets})", 
        'recommended' => '< 25', 
        'status'      => $total_assets < 25 ? 'ok' : 'warning', 
        'notes'       => 'Kiraan aset dari HTML asal. Aset yang dimuatkan oleh JavaScript tidak termasuk.'
    ];
    
    $doc_size_kb = round(strlen($html) / 1024);
    $frontend_info['doc_size'] = ['label' => 'Saiz Dokumen HTML', 'value' => "{$doc_size_kb} KB", 'recommended' => '< 100 KB', 'status' => $doc_size_kb < 100 ? 'ok' : 'warning', 'notes' => 'Saiz HTML yang besar menunjukkan kod yang tidak efisien atau terlalu banyak inline script/style.'];
    
    preg_match_all('/<img[^>]+>/i', $html, $images);
    $images_without_alt = 0;
    if (!empty($images[0])) {
        foreach ($images[0] as $img_tag) {
            if (preg_match('/alt=[\'"]\s*[\'"]/i', $img_tag) || !preg_match('/alt=/i', $img_tag)) {
                $images_without_alt++;
            }
        }
    }
    $frontend_info['alt_tags'] = ['label' => 'Imej Tanpa Teks Alt', 'value' => "{$images_without_alt} dari " . count($images[0]), 'recommended' => '0', 'status' => $images_without_alt == 0 ? 'ok' : 'warning', 'notes' => 'Teks Alt adalah penting untuk SEO imej dan kebolehaksesan (accessibility).'];

    $h1_tags = preg_match_all('/<h1/i', $html, $matches);
    $frontend_info['h1_tags'] = ['label' => 'Bilangan Tag <h1>', 'value' => $h1_tags, 'recommended' => '1', 'status' => $h1_tags === 1 ? 'ok' : 'warning', 'notes' => 'Amalan terbaik SEO adalah untuk mempunyai hanya satu tag H1 pada setiap halaman.'];

    return $frontend_info;
}

/**
 * Mengumpul maklumat kitaran hayat untuk semua plugin yang dipasang.
 *
 * @return array
 */
function matgem_get_plugins_lifecycle_info() {
    if ( ! function_exists( 'get_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $all_plugins = get_plugins();
    $update_info = get_site_transient('update_plugins');
    $plugins_data = [];

    foreach ($all_plugins as $plugin_file => $plugin_data) {
        $slug = dirname($plugin_file);
        if (empty($slug) || $slug === '.') { // Untuk plugin yang hanya satu fail
            $slug = basename($plugin_file, '.php');
        }

        $info = [
            'name' => $plugin_data['Name'],
            'current_version' => $plugin_data['Version'],
            'new_version' => '',
            'last_updated' => 'N/A (Premium/Luar)',
            'status' => is_plugin_active($plugin_file) ? 'ok' : 'info',
            'notes' => is_plugin_active($plugin_file) ? 'Aktif' : 'Tidak Aktif',
        ];

        // Semak jika ada kemas kini tersedia
        if (isset($update_info->response[$plugin_file])) {
            $info['new_version'] = $update_info->response[$plugin_file]->new_version;
            $info['status'] = 'critical';
            $info['notes'] = 'Kemas kini tersedia!';
        }

        // Semak tarikh kemas kini terakhir dari WordPress.org (dengan caching)
        $transient_key = 'whc_plugin_info_' . $slug;
        $plugin_api_data = get_transient($transient_key);

        if (false === $plugin_api_data) {
            $request = wp_remote_get("https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]={$slug}");
            if (!is_wp_error($request) && wp_remote_retrieve_response_code($request) === 200) {
                $body = wp_remote_retrieve_body($request);
                $decoded_body = json_decode($body);
                // Pastikan ia adalah plugin yang sah, bukan 'plugin not found'
                if ($decoded_body && isset($decoded_body->name)) {
                    $plugin_api_data = $decoded_body;
                    set_transient($transient_key, $plugin_api_data, DAY_IN_SECONDS); // Cache data selama 1 hari
                } else {
                    // Ini adalah plugin premium atau tidak dijumpai. Simpan status ini.
                    $plugin_api_data = 'premium_or_not_found';
                    set_transient($transient_key, 'premium_or_not_found', DAY_IN_SECONDS);
                }
            } else {
                 // Gagal hubungi API. Simpan status ini untuk elak cuba lagi.
                $plugin_api_data = 'premium_or_not_found';
                set_transient($transient_key, 'premium_or_not_found', DAY_IN_SECONDS);
            }
        }

        // Hanya proses jika data API wujud dan bukan status 'premium'
        if (!empty($plugin_api_data) && $plugin_api_data !== 'premium_or_not_found' && isset($plugin_api_data->last_updated)) {
            $info['last_updated'] = date('Y-m-d', strtotime($plugin_api_data->last_updated));
            
            $last_updated_time = strtotime($plugin_api_data->last_updated);
            $one_year_ago = strtotime('-1 year');
            $two_years_ago = strtotime('-2 years');

            if ($info['status'] !== 'critical') { // Jangan override status 'update tersedia'
                if ($last_updated_time < $two_years_ago) {
                    $info['status'] = 'critical';
                    $info['notes'] = 'Terbiar > 2 tahun!';
                } elseif ($last_updated_time < $one_year_ago) {
                    $info['status'] = 'warning';
                    $info['notes'] = 'Terbiar > 1 tahun.';
                }
            }
        }
        
        $plugins_data[$plugin_file] = $info;
    }
    return $plugins_data;
}

// --- FUNGSI BARU UNTUK FASA 5 ---
function matgem_get_security_info() {
    global $wpdb, $wp_version;
    $security_info = [];

    // 1. Awalan Jadual DB
    $prefix = $wpdb->prefix;
    $security_info['db_prefix'] = ['label' => 'Awalan Jadual DB', 'value' => $prefix, 'recommended' => 'Unik (bukan wp_)', 'status' => $prefix === 'wp_' ? 'critical' : 'ok', 'notes' => 'Awalan default memudahkan serangan SQL Injection automatik.'];

    // 2. Kunci Keselamatan
    $keys = ['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'];
    $keys_defined = true;
    foreach ($keys as $key) { if (!defined($key) || constant($key) === 'put your unique phrase here') { $keys_defined = false; break; } }
    $security_info['security_keys'] = ['label' => 'Kunci Keselamatan (wp-config)', 'value' => $keys_defined ? 'Ditetapkan' : 'Tidak Ditetapkan / Lemah', 'recommended' => 'Ditetapkan', 'status' => $keys_defined ? 'ok' : 'critical', 'notes' => 'Kunci unik melindungi sesi pengguna dan cookies.'];

    // 3. DISALLOW_FILE_EDIT
    $disallow_file_edit = (defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT);
    $security_info['disallow_file_edit'] = ['label' => 'DISALLOW_FILE_EDIT', 'value' => $disallow_file_edit ? 'true' : 'false', 'recommended' => 'true', 'status' => $disallow_file_edit ? 'ok' : 'critical', 'notes' => 'Langkah keselamatan kritikal untuk halang suntingan kod dari dashboard.'];

    // 4. Penyenaraian Direktori
    $response = wp_remote_get(content_url('/uploads/'));
    $dir_listing_active = (!is_wp_error($response) && strpos(wp_remote_retrieve_body($response), '<title>Index of') !== false);
    $security_info['dir_listing'] = ['label' => 'Penyenaraian Direktori', 'value' => $dir_listing_active ? 'Aktif' : 'Dihalang', 'recommended' => 'Dihalang', 'status' => !$dir_listing_active ? 'ok' : 'warning', 'notes' => 'Mendedahkan senarai fail boleh memberi maklumat kepada penyerang.'];

    // 5. Pendedahan Pengguna (REST API)
    $rest_response = wp_remote_get(get_rest_url(null, '/wp/v2/users'));
    $user_enumeration = (!is_wp_error($rest_response) && wp_remote_retrieve_response_code($rest_response) === 200);
    $security_info['user_enumeration'] = ['label' => 'Pendedahan Pengguna (REST API)', 'value' => $user_enumeration ? 'Didedahkan' : 'Dihalang', 'recommended' => 'Dihalang', 'status' => !$user_enumeration ? 'ok' : 'critical', 'notes' => 'Mendedahkan nama pengguna memudahkan serangan brute-force.'];

    // 6. Nama Pengguna 'admin'
    $admin_exists = username_exists('admin');
    $security_info['admin_user'] = ['label' => "Nama Pengguna 'admin'", 'value' => $admin_exists ? 'Wujud' : 'Tidak Wujud', 'recommended' => 'Tidak Wujud', 'status' => !$admin_exists ? 'ok' : 'warning', 'notes' => 'Nama pengguna "admin" adalah sasaran utama serangan brute force.'];

    // 7. Versi WordPress
    $core_updates = get_core_updates();
    $is_core_updated = (isset($core_updates[0]->response) && $core_updates[0]->response === 'latest');
    $security_info['wp_version'] = ['label' => 'Versi WordPress', 'value' => $wp_version, 'recommended' => 'Terkini', 'status' => $is_core_updated ? 'ok' : 'critical', 'notes' => 'Versi lama mempunyai lubang keselamatan yang diketahui umum.'];
    
    // 8. Bilangan Administrator
    $admin_users = get_users(['role' => 'administrator']);
    $admin_count = count($admin_users);
    $admin_status = 'ok'; if ($admin_count > 5) { $admin_status = 'critical'; } elseif ($admin_count > 3) { $admin_status = 'warning'; }
    $security_info['admin_count'] = ['label' => 'Bilangan Administrator', 'value' => "{$admin_count} Pengguna", 'recommended' => '< 3', 'status' => $admin_status, 'notes' => 'Terlalu banyak akaun admin meningkatkan risiko keselamatan.'];
    
    // 9. Integriti Folder Plugin
    if ( ! function_exists( 'get_plugins' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
    $official_plugins_count = count(get_plugins());
    $physical_folders = new FilesystemIterator(WP_PLUGIN_DIR, FilesystemIterator::SKIP_DOTS);
    $physical_folders_count = iterator_count($physical_folders);
    $discrepancy = $physical_folders_count - $official_plugins_count;
    $security_info['plugin_integrity'] = ['label' => 'Integriti Folder Plugin', 'value' => $discrepancy > 0 ? "{$discrepancy} folder tidak dikenali" : 'Sepadan', 'recommended' => 'Sepadan (0)', 'status' => $discrepancy == 0 ? 'ok' : 'critical', 'notes' => 'Menunjukkan kemungkinan ada fail hasad atau sisa plugin yang gagal dipadam.'];

    return $security_info;
}


// --- FUNGSI PEMBANTU BARU UNTUK PAPARAN JADUAL ---
function matgem_render_audit_table($title, $header_text, $data_array) {
    ?>
    <h2 style="margin-top: 40px;"><?php echo esc_html($title); ?></h2>
    <table class="whc-table">
        <thead>
            <tr>
                <th><?php echo esc_html($header_text); ?></th>
                <th>Status Semasa</th>
                <th>Cadangan</th>
                <th>Status</th>
                <th>Nota</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($data_array)) : ?>
                <tr><td colspan="5">Tiada data untuk dipaparkan.</td></tr>
            <?php else: ?>
                <?php foreach ($data_array as $data) : ?>
                    <tr>
                        <td><strong><?php echo esc_html($data['label']); ?></strong></td>
                        <td><?php echo wp_kses_post($data['value']); ?></td>
                        <td><?php echo esc_html($data['recommended']); ?></td>
                        <td class="whc-status"><span class="<?php echo esc_attr('status-' . $data['status']); ?>"><?php echo esc_html($data['status']); ?></span></td>
                        <td><?php echo wp_kses_post($data['notes']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
}

/**
 * Fungsi pembantu baru untuk memaparkan jadual Kitaran Hayat Plugin.
 */
function matgem_render_plugins_table($title, $data_array) {
    ?>
    <h2 style="margin-top: 40px;"><?php echo esc_html($title); ?></h2>
    <table class="whc-table">
        <thead>
            <tr>
                <th style="width: 35%;">Plugin</th>
                <th style="width: 12%;">Versi Semasa</th>
                <th style="width: 12%;">Versi Baru</th>
                <th style="width: 21%;">Kemas Kini Terakhir</th>
                <th style="width: 20%;">Status / Nota</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($data_array)) : ?>
                <tr><td colspan="5">Tidak dapat memuatkan data plugin.</td></tr>
            <?php else: ?>
                <?php foreach ($data_array as $data) : ?>
                    <tr>
                        <td><strong><?php echo esc_html($data['name']); ?></strong></td>
                        <td><?php echo esc_html($data['current_version']); ?></td>
                        <td><?php echo esc_html($data['new_version']); ?></td>
                        <td><?php echo esc_html($data['last_updated']); ?></td>
                        <td class="whc-status">
                            <span class="<?php echo esc_attr('status-' . $data['status']); ?>">
                                <?php echo esc_html($data['notes']); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
}

// --- FUNGSI RENDER UTAMA YANG TELAH DIROMBAK & DIKEMASKINI ---
function matgem_render_audit_page() {
    // Tentukan URL sasaran untuk audit frontend
    $target_url = home_url('/');
    if (isset($_GET['whc_url_audit']) && !empty($_GET['whc_url_audit'])) {
        $sanitized_url = esc_url_raw($_GET['whc_url_audit']);
        if (strpos($sanitized_url, home_url()) === 0) { $target_url = $sanitized_url; }
    }

    // Kumpul semua data dari setiap modul
    $php_info_data = matgem_get_php_info(); 
    $db_info_data = matgem_get_database_info();
    $wp_info_data = matgem_get_wp_info();
    $frontend_info_data = matgem_get_frontend_info($target_url);
    $plugins_lifecycle_data = matgem_get_plugins_lifecycle_info();
    $security_info_data = matgem_get_security_info(); 

    ?>
    <div class="wrap">
        <h1><span class="dashicons dashicons-dashboard" style="font-size:30px;margin-right:10px;"></span>WP Health Cockpit</h1>
        <p>Analisis Peringkat Awal untuk Konfigurasi Server Anda.</p>
        
        <form action="options.php" method="post">
            <?php settings_fields( 'whc_options_group' ); do_settings_sections( 'wp-health-cockpit' ); submit_button( 'Simpan Tetapan' ); ?>
        </form>
        <hr>

        <h2 style="margin-top: 40px;">Analisis Muka Depan (Frontend)</h2>
        <div>
            <p>
                <input type="url" id="whc_url_to_audit" value="<?php echo esc_attr(home_url('/')); ?>" size="80" placeholder="Masukkan URL untuk diaudit...">
                <button type="button" id="whc_run_audit_button" class="button button-primary">Audit URL</button>
                <span class="spinner"></span>
            </p>
        </div>
        <p><i>Hasil audit akan dipaparkan di bawah.</i></p>
        <table class="whc-table" id="whc-frontend-table">
            <thead><tr><th>Perkara</th><th>Status Semasa</th><th>Cadangan</th><th>Status</th><th>Nota</th></tr></thead>
            <tbody>
                <tr><td colspan="5" style="text-align: center;">Sila klik butang "Audit URL" untuk memulakan analisis.</td></tr>
            </tbody>
        </table>

        <?php matgem_render_audit_table('Analisis Dalaman WordPress', 'Tetapan', $wp_info_data); // Guna helper function ?>
        <div class="whc-code-box">
            <h3>Contoh Konfigurasi <code>wp-config.php</code></h3>
            <p>Salin dan tampal kod yang berkaitan di bawah ke dalam fail <code>wp-config.php</code> anda.</p>
            <pre><code>/** Tetapan oleh WP Health Cockpit */
define( 'WP_MEMORY_LIMIT', '256M' );
define( 'WP_MAX_MEMORY_LIMIT', '512M' );
define( 'WP_POST_REVISIONS', 3 );
define( 'DISABLE_WP_CRON', true );
define( 'EMPTY_TRASH_DAYS', 7 );
define( 'DISALLOW_FILE_EDIT', true );
define( 'WP_AUTO_UPDATE_CORE', 'minor' );
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_DISPLAY', false );</code></pre>
        </div>

        <?php matgem_render_audit_table('Analisis PHP (Lengkap)', 'Tetapan', $php_info_data); // Guna helper function ?>

        <?php matgem_render_audit_table('Analisis Database (Lengkap)', 'Tetapan', $db_info_data); // Guna helper function ?>
        <div class="whc-code-box">
            <h3>Contoh Konfigurasi <code>my.cnf</code></h3>
            <p>Tetapan ini perlu diletakkan di bawah seksyen <code>[mysqld]</code> dalam fail konfigurasi MySQL/MariaDB.</p>
            <pre><code>innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
query_cache_type = 0
query_cache_size = 0</code></pre>
        </div>
        <div class="whc-notes-box">
            <h3>Nota Tambahan: Mentafsir Saiz Jadual</h3>
            <p>Gunakan ini sebagai panduan kasar untuk mentafsir laporan '5 Jadual Terbesar'.</p>
            <ul>
                <li><strong>wp_options:</strong> Saiz sihat selalunya di bawah 10MB. Waspada jika melebihi 50MB.</li>
                <li><strong>wp_postmeta:</strong> Waspada jika saiznya lebih 10x ganda dari saiz jadual <strong>wp_posts</strong>.</li>
                <li><strong>wp_posts:</strong> Saiz besar yang tidak sepadan dengan jumlah kandungan selalunya berpunca dari revisi.</li>
            </ul>
        </div>
        <?php matgem_render_plugins_table('Kitaran Hayat Plugin', $plugins_lifecycle_data); // Guna helper function ?>
        <?php matgem_render_audit_table('Analisis Keselamatan Asas', 'Pemeriksaan', $security_info_data); // Paparkan Jadual Baru ?>


    </div>
    <?php
}