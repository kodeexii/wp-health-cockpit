<?php
/**
 * Plugin Name:       WP Health Cockpit
 * Description:       Satu dashboard untuk audit prestasi asas WordPress.
 * Version:           1.1.0
 * Author:            Mat Gem for Hadee Roslan
 * Author URI:        https://had.ee/
 * GitHub Plugin URI: kodeexii/wp-health-cockpit
 */

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

if ( file_exists(__DIR__ . '/vendor/plugin-update-checker/plugin-update-checker.php') ) {
    require_once __DIR__ . '/vendor/plugin-update-checker/plugin-update-checker.php';
    $myUpdateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/kodeexii/wp-health-cockpit/',
        __FILE__,
        'wp-health-cockpit'
    );
}

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

add_action( 'admin_menu', 'matgem_add_admin_menu' );
function matgem_add_admin_menu() {
    add_management_page('WP Health Cockpit', 'Health Cockpit', 'manage_options', 'wp-health-cockpit', 'matgem_render_audit_page');
}

function matgem_get_php_info() {
    $php_info = [];

    // Kumpulan 1: Versi & Sumber Asas
    $current_php_version = phpversion(); $php_info['php_version'] = ['label' => 'Versi PHP','value' => $current_php_version,'recommended' => '8.2+','status' => version_compare($current_php_version, '8.2', '>=') ? 'ok' : 'warning','notes' => 'Versi PHP yang lebih baru adalah lebih laju dan selamat.'];
    $memory_limit = ini_get('memory_limit'); $mem_limit_val = wp_convert_hr_to_bytes($memory_limit) / 1024 / 1024; $php_info['memory_limit'] = ['label' => 'PHP Memory Limit (Server)','value' => $memory_limit,'recommended' => '256M+','status' => $mem_limit_val >= 256 ? 'ok' : 'warning','notes' => 'Had memori peringkat server. Ini adalah had tertinggi.'];
    $max_execution_time = ini_get('max_execution_time'); $php_info['max_execution_time'] = ['label' => 'Max Execution Time','value' => $max_execution_time . 's','recommended' => '120s+','status' => $max_execution_time >= 120 ? 'ok' : 'warning','notes' => 'Masa singkat boleh ganggu proses import/export atau backup.'];
    $upload_max = ini_get('upload_max_filesize'); $upload_max_val = wp_convert_hr_to_bytes($upload_max) / 1024 / 1024; $php_info['upload_max_filesize'] = ['label' => 'Upload Max Filesize','value' => $upload_max,'recommended' => '64M+','status' => $upload_max_val >= 64 ? 'ok' : 'warning','notes' => 'Punca biasa pengguna tak boleh muat naik fail/gambar besar.'];
    $post_max = ini_get('post_max_size'); $post_max_val = wp_convert_hr_to_bytes($post_max) / 1024 / 1024; $php_info['post_max_size'] = ['label' => 'Post Max Size','value' => $post_max,'recommended' => '64M+ (mesti >= upload_max_filesize)','status' => ($post_max_val >= 64 && $post_max_val >= $upload_max_val) ? 'ok' : 'warning','notes' => 'Mesti lebih besar dari saiz muat naik untuk benarkan data POST lain.'];
    $max_input_vars = ini_get('max_input_vars'); $php_info['max_input_vars'] = ['label' => 'Max Input Vars','value' => $max_input_vars,'recommended' => '3000+','status' => $max_input_vars >= 3000 ? 'ok' : 'warning','notes' => '"Pembunuh senyap" untuk page builder & menu kompleks.'];
    
    // Kumpulan 2: OPcache
    $opcache_enabled = function_exists('opcache_get_status') && opcache_get_status()['opcache_enabled'];
    $php_info['opcache'] = ['label' => 'OPcache','value' => $opcache_enabled ? 'Aktif' : 'Tidak Aktif','recommended' => 'Aktif','status' => $opcache_enabled ? 'ok' : 'critical','notes' => 'Wajib "On". Ini \'turbocharger\' utama PHP.'];
    if ($opcache_enabled) {
        $opcache_mem = ini_get('opcache.memory_consumption'); $php_info['opcache_mem'] = ['label' => 'OPcache Memory', 'value' => $opcache_mem . 'M', 'recommended' => '128+', 'status' => $opcache_mem >= 128 ? 'ok' : 'warning', 'notes' => 'Saiz memori (MB) untuk OPcache.'];
        $opcache_strings = ini_get('opcache.interned_strings_buffer'); $php_info['opcache_strings'] = ['label' => 'OPcache Strings Buffer', 'value' => $opcache_strings . 'M', 'recommended' => '16+', 'status' => $opcache_strings >= 16 ? 'ok' : 'warning', 'notes' => 'Memori (MB) untuk mengoptimumkan string berulang.'];
    }

    // Kumpulan 3: Keselamatan
    $expose_php = ini_get('expose_php'); $php_info['expose_php'] = ['label' => 'Expose PHP', 'value' => $expose_php ? 'On' : 'Off', 'recommended' => 'Off', 'status' => !$expose_php ? 'ok' : 'critical', 'notes' => 'Langkah keselamatan untuk sorokkan versi PHP anda dari penggodam.'];
    $display_errors = ini_get('display_errors'); $php_info['display_errors'] = ['label' => 'Display Errors', 'value' => $display_errors ? 'On' : 'Off', 'recommended' => 'Off', 'status' => !$display_errors ? 'ok' : 'critical', 'notes' => 'Wajib "Off" pada laman produksi untuk elak pendedahan maklumat sensitif.'];
    
    // Kumpulan 4: PHP Extensions
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
    $wp_mem_limit = defined('WP_MEMORY_LIMIT') ? constant('WP_MEMORY_LIMIT') : 'Tidak Ditetapkan'; $wp_info['wp_memory_limit'] = ['label' => 'WordPress Memory Limit','value' => $wp_mem_limit, 'recommended' => '128M+', 'status' => 'info', 'notes' => 'Had memori untuk operasi frontend. WordPress akan guna default (40M) jika tidak ditetapkan.'];
    $wp_max_mem_limit = defined('WP_MAX_MEMORY_LIMIT') ? constant('WP_MAX_MEMORY_LIMIT') : 'Tidak Ditetapkan'; $wp_info['wp_max_memory_limit'] = ['label' => 'WordPress Max Memory Limit','value' => $wp_max_mem_limit, 'recommended' => '256M+', 'status' => 'info', 'notes' => 'Had memori untuk proses backend/admin. WordPress guna default (256M) jika tidak ditetapkan.'];
    $is_wp_cron_disabled = (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON); $wp_info['wp_cron'] = ['label' => 'Status WP-Cron','value' => $is_wp_cron_disabled ? 'Dinyahaktifkan' : 'Aktif (Default)','recommended' => 'Dinyahaktifkan (guna server cron)','status' => $is_wp_cron_disabled ? 'ok' : 'warning','notes' => 'Menyahaktifkan WP-Cron adalah lebih efisien.'];
    $is_object_cache_persistent = function_exists('wp_using_ext_object_cache') ? wp_using_ext_object_cache() : false; $wp_info['object_cache'] = ['label' => 'Object Cache Kekal','value' => $is_object_cache_persistent ? 'Aktif' : 'Tidak Aktif','recommended' => 'Aktif (cth: Redis/Memcached)','status' => $is_object_cache_persistent ? 'ok' : 'critical','notes' => 'Sangat kritikal untuk prestasi laman dinamik.'];
    $revisions_status = 'Aktif (Default)'; if (defined('WP_POST_REVISIONS')) { if (WP_POST_REVISIONS === false) { $revisions_status = 'Dinyahaktifkan'; } elseif (is_numeric(WP_POST_REVISIONS)) { $revisions_status = 'Dihadkan kepada ' . WP_POST_REVISIONS; } } $wp_info['post_revisions'] = ['label' => 'Revisi Pos','value' => $revisions_status,'recommended' => 'Dihadkan (cth: 3)','status' => (strpos($revisions_status, 'Default') === false) ? 'ok' : 'warning','notes' => 'Menghadkan revisi mengurangkan saiz jadual wp_posts.'];
    $is_debug_on = (defined('WP_DEBUG') && WP_DEBUG); $wp_info['debug_mode'] = ['label' => 'WordPress Debug Mode','value' => $is_debug_on ? 'Aktif' : 'Tidak Aktif','recommended' => 'Tidak Aktif (di laman produksi)','status' => !$is_debug_on ? 'ok' : 'critical','notes' => 'Tidak sepatutnya aktif pada laman sebenar.'];
    if ( ! function_exists( 'get_plugins' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; } $active_plugins = get_option('active_plugins', []); $active_plugins_count = count($active_plugins);
    $wp_info['active_plugins'] = ['label' => 'Bilangan Plugin Aktif', 'value' => $active_plugins_count, 'recommended' => '< 25', 'status' => $active_plugins_count <= 25 ? 'ok' : 'warning', 'notes' => 'Jumlah plugin tinggi boleh jadi petunjuk isu prestasi.'];
    $active_theme = wp_get_theme(); $theme_name = $active_theme->get('Name'); $theme_version = $active_theme->get('Version'); $wp_info['active_theme'] = ['label' => 'Theme Aktif', 'value' => "{$theme_name} (v{$theme_version})", 'recommended' => 'N/A', 'status' => 'info', 'notes' => 'Pastikan theme sentiasa dikemas kini.'];
    $all_plugins = get_plugins(); $inactive_plugins_count = count($all_plugins) - $active_plugins_count;
    $wp_info['inactive_plugins'] = ['label' => 'Plugin Tidak Aktif', 'value' => $inactive_plugins_count, 'recommended' => '0', 'status' => $inactive_plugins_count == 0 ? 'ok' : 'warning', 'notes' => 'Amalan terbaik adalah memadam plugin yang tidak aktif.'];
    $wp_info['heartbeat'] = ['label' => 'WordPress Heartbeat API','value' => 'Default (Aktif)','recommended' => 'Kawal (guna plugin)','status' => 'info','notes' => 'API ini boleh menyebabkan beban CPU tinggi.'];
    return $wp_info;
}

function matgem_get_frontend_info($target_url) {
    $frontend_info = []; $start_time = microtime(true); $response = wp_remote_get($target_url, ['timeout' => 20, 'sslverify' => false]); $end_time = microtime(true);
    $ttfb = round(($end_time - $start_time) * 1000); $ttfb_status = 'ok'; if ($ttfb > 600) { $ttfb_status = 'critical'; } elseif ($ttfb > 200) { $ttfb_status = 'warning'; }
    $frontend_info['ttfb'] = ['label' => 'Masa Respons Server (TTFB Belakang)', 'value' => "{$ttfb} ms", 'recommended' => '< 200 ms', 'status' => $ttfb_status, 'notes' => 'Masa yang diambil oleh server untuk mula memulangkan data. Sangat penting untuk persepsi kelajuan.'];
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) { $error_message = is_wp_error($response) ? $response->get_error_message() : 'Kod Respons: ' . wp_remote_retrieve_response_code($response); $frontend_info['homepage_access'] = ['label' => 'Akses URL', 'value' => 'Gagal diakses', 'recommended' => 'Boleh diakses', 'status' => 'critical', 'notes' => esc_html($error_message)]; return $frontend_info; }
    $html = wp_remote_retrieve_body($response);
    $css_files = preg_match_all('/<link[^>]+rel=[\'"]stylesheet[\'"]/i', $html, $matches); $js_files = preg_match_all('/<script[^>]+src=[\'"]/i', $html, $matches);
    $total_assets = $css_files + $js_files;
    $frontend_info['asset_count'] = ['label' => 'Bilangan Fail CSS & JS', 'value' => "CSS: {$css_files}, JS: {$js_files} (Total: {$total_assets})", 'recommended' => '< 25', 'status' => $total_assets < 25 ? 'ok' : 'warning', 'notes' => 'Terlalu banyak fail aset boleh melambatkan masa muat turun dan render halaman.'];
    $doc_size_kb = round(strlen($html) / 1024); $frontend_info['doc_size'] = ['label' => 'Saiz Dokumen HTML', 'value' => "{$doc_size_kb} KB", 'recommended' => '< 100 KB', 'status' => $doc_size_kb < 100 ? 'ok' : 'warning', 'notes' => 'Saiz HTML yang besar menunjukkan kod yang tidak efisien atau terlalu banyak inline script/style.'];
    preg_match_all('/<img[^>]+>/i', $html, $images);
    $images_without_alt = 0; if (!empty($images[0])) { foreach ($images[0] as $img_tag) { if (preg_match('/alt=[\'"]\s*[\'"]/i', $img_tag) || !preg_match('/alt=/i', $img_tag)) { $images_without_alt++; } } }
    $frontend_info['alt_tags'] = ['label' => 'Imej Tanpa Teks Alt', 'value' => "{$images_without_alt} dari " . count($images[0]), 'recommended' => '0', 'status' => $images_without_alt == 0 ? 'ok' : 'warning', 'notes' => 'Teks Alt adalah penting untuk SEO imej dan kebolehaksesan (accessibility).'];
    $h1_tags = preg_match_all('/<h1/i', $html, $matches); $frontend_info['h1_tags'] = ['label' => 'Bilangan Tag <h1>', 'value' => $h1_tags, 'recommended' => '1', 'status' => $h1_tags === 1 ? 'ok' : 'warning', 'notes' => 'Amalan terbaik SEO adalah untuk mempunyai hanya satu tag H1 pada setiap halaman.'];
    return $frontend_info;
}

function matgem_render_audit_page() {
    $target_url = home_url('/');
    if (isset($_GET['whc_url_audit']) && !empty($_GET['whc_url_audit'])) {
        $sanitized_url = esc_url_raw($_GET['whc_url_audit']);
        if (strpos($sanitized_url, home_url()) === 0) { $target_url = $sanitized_url; }
    }
    $php_info_data = matgem_get_php_info(); 
    $db_info_data = matgem_get_database_info();
    $wp_info_data = matgem_get_wp_info();
    $frontend_info_data = matgem_get_frontend_info($target_url);
    ?>
    <style> .whc-table{width:100%;border-collapse:collapse;margin-top:20px;table-layout:fixed;}.whc-table th,.whc-table td{padding:12px 15px;border:1px solid #ddd;text-align:left;word-wrap:break-word;}.whc-table th{background-color:#f4f4f4;}.whc-table th:nth-child(1){width:25%;}.whc-table th:nth-child(2),.whc-table th:nth-child(3),.whc-table th:nth-child(4){width:15%;}.whc-table th:nth-child(5){width:30%;}.whc-status span{display:inline-block;padding:5px 10px;color:#fff;border-radius:4px;font-size:12px;text-transform:uppercase;font-weight:bold;}.status-ok{background-color:#28a745;}.status-info{background-color:#17a2b8;}.status-warning{background-color:#ffc107;color:#333;}.status-critical{background-color:#dc3545;} .whc-notes-box { margin-top: 25px; padding: 15px; border-left: 4px solid #17a2b8; background: #fff; box-shadow: 0 1px 1px 0 rgba(0,0,0,.1); } .whc-notes-box h3 { margin-top: 0; } .whc-notes-box ul { list-style-type: disc; padding-left: 20px; } </style>
    <div class="wrap">
        <h1><span class="dashicons dashicons-dashboard" style="font-size:30px;margin-right:10px;"></span>WP Health Cockpit</h1>
        <p>Analisis Peringkat Awal untuk Konfigurasi Server Anda.</p>
        <form action="options.php" method="post">
            <?php settings_fields( 'whc_options_group' ); do_settings_sections( 'wp-health-cockpit' ); submit_button( 'Simpan Tetapan' ); ?>
        </form>
        <hr>
        <h2 style="margin-top: 40px;">Analisis Muka Depan (Frontend)</h2>
        <form method="GET">
            <input type="hidden" name="page" value="wp-health-cockpit">
            <p><input type="url" name="whc_url_audit" value="<?php echo esc_attr($target_url); ?>" size="80" placeholder="Masukkan URL untuk diaudit..."><input type="submit" class="button button-secondary" value="Audit URL"></p>
        </form>
        <p><i>Hasil audit untuk: <code><?php echo esc_html($target_url); ?></code></i></p>
        <table class="whc-table">
            <thead><tr><th>Perkara</th><th>Status Semasa</th><th>Cadangan</th><th>Status</th><th>Nota</th></tr></thead>
            <tbody><?php foreach ($frontend_info_data as $data) : ?><tr><td><strong><?php echo esc_html($data['label']); ?></strong></td><td><?php echo wp_kses_post($data['value']); ?></td><td><?php echo esc_html($data['recommended']); ?></td><td class="whc-status"><span class="<?php echo esc_attr('status-' . $data['status']); ?>"><?php echo esc_html($data['status']); ?></span></td><td><?php echo wp_kses_post($data['notes']); ?></td></tr><?php endforeach; ?></tbody>
        </table>
        <h2 style="margin-top: 40px;">Analisis Dalaman WordPress</h2>
        <table class="whc-table">
            <thead><tr><th>Tetapan</th><th>Status Semasa</th><th>Cadangan</th><th>Status</th><th>Nota</th></tr></thead>
            <tbody><?php foreach ($wp_info_data as $data) : ?><tr><td><strong><?php echo esc_html($data['label']); ?></strong></td><td><?php echo wp_kses_post($data['value']); ?></td><td><?php echo esc_html($data['recommended']); ?></td><td class="whc-status"><span class="<?php echo esc_attr('status-' . $data['status']); ?>"><?php echo esc_html($data['status']); ?></span></td><td><?php echo wp_kses_post($data['notes']); ?></td></tr><?php endforeach; ?></tbody>
        </table>
        <h2 style="margin-top: 40px;">Analisis PHP (Lengkap)</h2>
        <table class="whc-table">
            <thead><tr><th>Tetapan</th><th>Nilai Semasa</th><th>Cadangan</th><th>Status</th><th>Nota</th></tr></thead>
            <tbody><?php foreach ($php_info_data as $data) : ?><tr><td><strong><?php echo esc_html($data['label']); ?></strong></td><td><?php echo wp_kses_post($data['value']); ?></td><td><?php echo esc_html($data['recommended']); ?></td><td class="whc-status"><span class="<?php echo esc_attr('status-' . $data['status']); ?>"><?php echo esc_html($data['status']); ?></span></td><td><?php echo wp_kses_post($data['notes']); ?></td></tr><?php endforeach; ?></tbody>
        </table>
        <h2 style="margin-top: 40px;">Analisis Database (Lengkap)</h2>
        <table class="whc-table">
            <thead><tr><th>Tetapan</th><th>Nilai Semasa</th><th>Cadangan</th><th>Status</th><th>Nota</th></tr></thead>
            <tbody><?php foreach ($db_info_data as $data) : ?><tr><td><strong><?php echo esc_html($data['label']); ?></strong></td><td><?php echo wp_kses_post($data['value']); ?></td><td><?php echo esc_html($data['recommended']); ?></td><td class="whc-status"><span class="<?php echo esc_attr('status-' . $data['status']); ?></span></td><td><?php echo wp_kses_post($data['notes']); ?></td></tr><?php endforeach; ?></tbody>
        </table>
        <div class="whc-notes-box">
            <h3>Nota Tambahan: Mentafsir Saiz Jadual</h3>
            <p>Gunakan ini sebagai panduan kasar untuk mentafsir laporan '5 Jadual Terbesar' di atas.</p>
            <ul>
                <li><strong>wp_options:</strong> Saiz sihat selalunya di bawah 10MB. Waspada jika melebihi 50MB. Punca biasa: Data sementara (transients) dari plugin.</li>
                <li><strong>wp_postmeta:</strong> Saiz sangat bergantung pada kandungan. Ambil perhatian jika saiznya lebih 10x ganda dari saiz jadual <strong>wp_posts</strong>. Punca biasa: Data dari Page Builder (Elementor, etc).</li>
                <li><strong>wp_posts:</strong> Saiz bergantung pada jumlah artikel/halaman. Saiz besar yang tidak sepadan dengan jumlah kandungan selalunya berpunca dari revisi (post revisions) yang berlebihan.</li>
            </ul>
        </div>
    </div>
    <?php
}
