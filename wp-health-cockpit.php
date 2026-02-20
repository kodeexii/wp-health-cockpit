<?php
/**
 * Plugin Name:       WP Health Cockpit
 * Description:       Plugin diagnostik ringan yang direka untuk agensi, freelancer, dan pemilik laman web yang serius tentang prestasi dan keselamatan website.
 * Version:           1.7.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Hadee Roslan & Mat Gem
 * Author URI:        https://hadeeroslan.my/
 * GitHub Plugin URI: kodeexii/wp-health-cockpit
 */

if ( ! defined( 'ABSPATH' ) ) { die; }

/**
 * Kelas Utama WHC_Admin
 * Menguruskan semua logik Dashboard dan Tetapan Admin.
 */
class WHC_Admin {

    /**
     * Constructor: Mendaftarkan semua WordPress Hooks.
     */
    public function __construct() {
        // Auto-Updater
        add_action( 'plugins_loaded', [ $this, 'init_updater' ] );

        // Admin Menu & Settings
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

        // AJAX Handlers
        add_action( 'wp_ajax_run_frontend_audit', [ $this, 'handle_frontend_audit_ajax' ] );
    }

    /**
     * Inisialisasi Auto-Updater.
     */
    public function init_updater() {
        if ( file_exists(__DIR__ . '/vendor/plugin-update-checker/plugin-update-checker.php') ) {
            require_once __DIR__ . '/vendor/plugin-update-checker/plugin-update-checker.php';
            \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker( 
                'https://github.com/kodeexii/wp-health-cockpit/', 
                __FILE__, 
                'wp-health-cockpit' 
            );
        }
    }

    /**
     * Mendaftarkan Halaman Tools.
     */
    public function add_admin_menu() {
        add_management_page(
            'WP Health Cockpit',
            'Health Cockpit',
            'manage_options',
            'wp-health-cockpit',
            [ $this, 'render_audit_page' ]
        );
    }

    /**
     * Mendaftarkan Tetapan Plugin.
     */
    public function register_settings() {
        register_setting( 'whc_options_group', 'whc_server_specs' );
        add_settings_section(
            'whc_settings_section', 
            'Konfigurasi Server (Pilihan)', 
            function() { echo '<p>Masukkan spesifikasi server untuk dapatkan cadangan yang lebih tepat. Biarkan kosong jika tidak pasti.</p>'; }, 
            'wp-health-cockpit'
        );
        add_settings_field(
            'whc_total_ram', 
            'Jumlah RAM Server (GB)', 
            [ $this, 'ram_field_callback' ], 
            'wp-health-cockpit', 
            'whc_settings_section'
        );
    }

    public function ram_field_callback() {
        $options = get_option('whc_server_specs');
        $ram = isset($options['total_ram']) ? $options['total_ram'] : '';
        echo "<input type='number' name='whc_server_specs[total_ram]' value='" . esc_attr($ram) . "' placeholder='cth: 8' /> GB";
    }

    /**
     * Memuatkan Skrip dan Gaya (Styles/Scripts).
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'tools_page_wp-health-cockpit') { return; }

        // Inline CSS
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

        // JS Audit
        wp_enqueue_script('whc-audit-script', plugin_dir_url(__FILE__) . 'assets/audit.js', ['jquery'], '1.7.0', true);
        wp_localize_script('whc-audit-script', 'whc_ajax_object', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('whc_frontend_audit_nonce'),
        ]);
    }

    /**
     * AJAX: Menjalankan Audit Frontend.
     */
    public function handle_frontend_audit_ajax() {
        check_ajax_referer('whc_frontend_audit_nonce');

        if (!isset($_POST['url']) || empty($_POST['url'])) {
            wp_send_json_error(['message' => 'URL tidak diterima.']);
        }
        $sanitized_url = esc_url_raw($_POST['url']);

        if (strpos($sanitized_url, home_url()) !== 0) {
            wp_send_json_error(['message' => 'URL tidak sah.']);
        }

        $frontend_data = $this->get_frontend_info($sanitized_url);
        wp_send_json_success($frontend_data);
    }

    // --- LOGIK AUDIT (Ditukarkan ke Methods) ---

    private function get_php_info() {
        $php_info = [];
        $current_php_version = phpversion(); 
        $php_info['php_version'] = ['label' => 'Versi PHP','value' => $current_php_version,'recommended' => '8.2+','status' => version_compare($current_php_version, '8.2', '>=') ? 'ok' : 'warning','notes' => 'Versi PHP yang lebih baru adalah lebih laju dan selamat.'];
        
        $memory_limit = ini_get('memory_limit'); 
        $mem_limit_val = wp_convert_hr_to_bytes($memory_limit) / 1024 / 1024; 
        $php_info['memory_limit'] = ['label' => 'PHP Memory Limit (Server)','value' => $memory_limit,'recommended' => '256M+','status' => $mem_limit_val >= 256 ? 'ok' : 'warning','notes' => 'Had memori peringkat server. Ini adalah had tertinggi.'];
        
        $max_execution_time = ini_get('max_execution_time'); 
        $php_info['max_execution_time'] = ['label' => 'Max Execution Time','value' => $max_execution_time . 's','recommended' => '120s+','status' => $max_execution_time >= 120 ? 'ok' : 'warning','notes' => 'Masa singkat boleh ganggu proses import/export atau backup.'];
        
        $upload_max = ini_get('upload_max_filesize'); 
        $upload_max_val = wp_convert_hr_to_bytes($upload_max) / 1024 / 1024; 
        $php_info['upload_max_filesize'] = ['label' => 'Upload Max Filesize','value' => $upload_max,'recommended' => '64M+','status' => $upload_max_val >= 64 ? 'ok' : 'warning','notes' => 'Punca biasa pengguna tak boleh muat naik fail/gambar besar.'];
        
        $post_max = ini_get('post_max_size'); 
        $post_max_val = wp_convert_hr_to_bytes($post_max) / 1024 / 1024; 
        $php_info['post_max_size'] = ['label' => 'Post Max Size','value' => $post_max,'recommended' => '64M+ (mesti >= upload_max_filesize)','status' => ($post_max_val >= 64 && $post_max_val >= $upload_max_val) ? 'ok' : 'warning','notes' => 'Mesti lebih besar dari saiz muat naik untuk benarkan data POST lain.'];
        
        $max_input_vars = ini_get('max_input_vars'); 
        $php_info['max_input_vars'] = ['label' => 'Max Input Vars','value' => $max_input_vars,'recommended' => '3000+','status' => $max_input_vars >= 3000 ? 'ok' : 'warning','notes' => '"Pembunuh senyap" untuk page builder & menu kompleks.'];
        
        $opcache_enabled = function_exists('opcache_get_status') && opcache_get_status()['opcache_enabled']; 
        $php_info['opcache'] = ['label' => 'OPcache','value' => $opcache_enabled ? 'Aktif' : 'Tidak Aktif','recommended' => 'Aktif','status' => $opcache_enabled ? 'ok' : 'critical','notes' => 'Wajib "On". Ini \'turbocharger\' utama PHP.'];
        
        if ($opcache_enabled) { 
            $opcache_mem = ini_get('opcache.memory_consumption'); 
            $php_info['opcache_mem'] = ['label' => 'OPcache Memory', 'value' => $opcache_mem . 'M', 'recommended' => '128+', 'status' => $opcache_mem >= 128 ? 'ok' : 'warning', 'notes' => 'Saiz memori (MB) untuk OPcache.']; 
        }

        $expose_php = ini_get('expose_php'); 
        $php_info['expose_php'] = ['label' => 'Expose PHP', 'value' => $expose_php ? 'On' : 'Off', 'recommended' => 'Off', 'status' => !$expose_php ? 'ok' : 'critical', 'notes' => 'Langkah keselamatan untuk sorokkan versi PHP anda dari penggodam.'];
        
        $display_errors = ini_get('display_errors'); 
        $php_info['display_errors'] = ['label' => 'Display Errors', 'value' => $display_errors ? 'On' : 'Off', 'recommended' => 'Off', 'status' => !$display_errors ? 'ok' : 'critical', 'notes' => 'Wajib "Off" pada laman produksi untuk elak pendedahan maklumat sensitif.'];
        
        return $php_info;
    }

    private function get_database_info() {
        global $wpdb; 
        $db_info = []; 
        $server_specs = get_option('whc_server_specs'); 
        $total_ram_gb = isset($server_specs['total_ram']) ? (int)$server_specs['total_ram'] : 0;
        
        $db_version = $wpdb->get_var('SELECT VERSION()'); 
        $db_charset = $wpdb->charset;
        $db_info['db_version'] = ['label' => 'Versi Database','value' => $db_version,'recommended' => 'MySQL 8.0+ / MariaDB 10.6+','status' => 'info','notes' => 'Versi baru selalunya lebih pantas dan selamat.'];
        $db_info['db_charset'] = ['label' => 'Database Charset','value' => $db_charset,'recommended' => 'utf8mb4','status' => ($db_charset === 'utf8mb4') ? 'ok' : 'warning','notes' => 'utf8mb4 diperlukan untuk sokongan penuh emoji dan pelbagai bahasa.'];
        
        $autoload_size = $wpdb->get_var("SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload = 'yes'"); 
        $autoload_size_kb = $autoload_size ? round($autoload_size / 1024, 2) : 0;
        $status_autoload = 'ok'; if ($autoload_size_kb > 1024) { $status_autoload = 'critical'; } elseif ($autoload_size_kb > 700) { $status_autoload = 'warning'; }
        $db_info['autoload_size'] = ['label' => 'Saiz Autoloaded Data','value' => $autoload_size_kb . ' KB','recommended' => '< 700 KB','status' => $status_autoload,'notes' => 'Data yang dimuatkan pada setiap halaman. Saiz besar boleh melambatkan TTFB.'];
        
        return $db_info;
    }

    private function get_wp_info() {
        global $wp_version;
        $wp_info = [];
        
        $wp_mem_limit_val = defined('WP_MEMORY_LIMIT') ? constant('WP_MEMORY_LIMIT') : 'Default (40M)';
        $wp_info['wp_memory_limit'] = ['label' => 'WP_MEMORY_LIMIT', 'value' => $wp_mem_limit_val, 'recommended' => '256M', 'status' => 'info', 'notes' => 'Had memori untuk operasi frontend.'];
        
        $is_object_cache_persistent = function_exists('wp_using_ext_object_cache') ? wp_using_ext_object_cache() : false;
        $wp_info['object_cache'] = ['label' => 'Object Cache Kekal', 'value' => $is_object_cache_persistent ? 'Aktif' : 'Tidak Aktif', 'recommended' => 'Aktif (Redis/Memcached)', 'status' => $is_object_cache_persistent ? 'ok' : 'critical', 'notes' => 'Sangat kritikal untuk prestasi laman dinamik.'];

        return $wp_info;
    }

    private function get_frontend_info($target_url) {
        $frontend_info = [];
        $start_time = microtime(true);
        $response = wp_remote_get($target_url, ['timeout' => 20, 'sslverify' => false]);
        $end_time = microtime(true);
        $ttfb = round(($end_time - $start_time) * 1000);

        $ttfb_status = 'ok';
        if ($ttfb > 600) { $ttfb_status = 'critical'; }
        elseif ($ttfb > 200) { $ttfb_status = 'warning'; }
        
        $frontend_info['ttfb'] = ['label' => 'Masa Respons Server (TTFB)', 'value' => "{$ttfb} ms", 'recommended' => '< 200 ms', 'status' => $ttfb_status, 'notes' => 'Masa server mula memulangkan data.'];

        return $frontend_info;
    }

    private function get_vulnerability_info() {
        if ( ! function_exists( 'get_plugins' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
        $all_components = [];
        $found_vulnerabilities = [];

        // Kumpul plugin aktif
        $all_plugins = get_plugins();
        foreach ($all_plugins as $plugin_file => $plugin_data) {
            if (is_plugin_active($plugin_file)) {
                $slug = dirname($plugin_file);
                if (empty($slug) || $slug === '.') { $slug = basename($plugin_file, '.php'); }
                $all_components['plugin-' . $slug] = ['type' => 'plugin', 'slug' => $slug, 'name' => $plugin_data['Name'], 'version' => $plugin_data['Version']];
            }
        }
        
        // Semak limitasi demo (ambil 3 plugin pertama saja untuk kelajuan)
        $limited_components = array_slice($all_components, 0, 3);

        foreach ($limited_components as $key => $component) {
            $api_url = sprintf('https://wpvulnerability.com/api/v1/%ss/%s', $component['type'], $component['slug']);
            $response = wp_remote_get($api_url, ['timeout' => 10]);
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                if (!empty($data['vulnerabilities'])) {
                    $found_vulnerabilities[] = ['name' => $component['name'], 'version' => $component['version'], 'details' => 'Ada kerentanan ditemui.'];
                }
            }
        }
        return $found_vulnerabilities;
    }

    // --- RENDERING METHODS ---

    private function render_table($title, $header_text, $data_array) {
        ?>
        <h2 style="margin-top: 40px;"><?php echo esc_html($title); ?></h2>
        <table class="whc-table">
            <thead><tr><th><?php echo esc_html($header_text); ?></th><th>Status Semasa</th><th>Cadangan</th><th>Status</th><th>Nota</th></tr></thead>
            <tbody>
                <?php foreach ($data_array as $data) : ?>
                    <tr>
                        <td><strong><?php echo esc_html($data['label']); ?></strong></td>
                        <td><?php echo wp_kses_post($data['value']); ?></td>
                        <td><?php echo esc_html($data['recommended']); ?></td>
                        <td class="whc-status"><span class="<?php echo esc_attr('status-' . $data['status']); ?>"><?php echo esc_html($data['status']); ?></span></td>
                        <td><?php echo wp_kses_post($data['notes']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    public function render_audit_page() {
        $php_info = $this->get_php_info();
        $db_info = $this->get_database_info();
        $wp_info = $this->get_wp_info();
        ?>
        <div class="wrap">
            <h1><span class="dashicons dashicons-dashboard"></span> WP Health Cockpit (v1.7.0)</h1>
            <p>Diagnostik Sistem Berasaskan Objek (OOP).</p>
            
            <form action="options.php" method="post">
                <?php settings_fields( 'whc_options_group' ); do_settings_sections( 'wp-health-cockpit' ); submit_button( 'Simpan Tetapan' ); ?>
            </form>

            <h2 style="margin-top: 40px;">Analisis Muka Depan (Frontend)</h2>
            <div>
                <input type="url" id="whc_url_to_audit" value="<?php echo esc_attr(home_url('/')); ?>" size="60">
                <button type="button" id="whc_run_audit_button" class="button button-primary">Audit URL</button>
                <span class="spinner"></span>
            </div>
            <table class="whc-table" id="whc-frontend-table">
                <thead><tr><th>Perkara</th><th>Status Semasa</th><th>Cadangan</th><th>Status</th><th>Nota</th></tr></thead>
                <tbody><tr><td colspan="5" style="text-align: center;">Sila klik butang "Audit URL".</td></tr></tbody>
            </table>

            <?php 
            $this->render_table('Analisis PHP', 'Tetapan PHP', $php_info);
            $this->render_table('Analisis Database', 'Tetapan DB', $db_info);
            $this->render_table('Analisis WordPress', 'Tetapan WP', $wp_info);
            ?>
        </div>
        <?php
    }
}

// Inisialisasi Plugin
new WHC_Admin();
