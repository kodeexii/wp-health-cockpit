<?php
/**
 * Kelas WHC_Admin
 * Menguruskan semua paparan Admin.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class WHC_Admin {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'wp_ajax_run_frontend_audit', [ $this, 'handle_frontend_audit_ajax' ] );
    }

    public function add_admin_menu() {
        add_management_page('WP Health Cockpit','Health Cockpit','manage_options','wp-health-cockpit',[ $this, 'render_audit_page' ]);
    }

    public function register_settings() {
        register_setting( 'whc_options_group', 'whc_server_specs' );
        add_settings_section('whc_settings_section', 'Konfigurasi Server (Pilihan)', function() { echo '<p>Masukkan spesifikasi server.</p>'; }, 'wp-health-cockpit');
        add_settings_field('whc_total_ram', 'Jumlah RAM Server (GB)', [ $this, 'ram_field_callback' ], 'wp-health-cockpit', 'whc_settings_section');
    }

    public function ram_field_callback() {
        $options = get_option('whc_server_specs');
        $ram = isset($options['total_ram']) ? $options['total_ram'] : '';
        echo "<input type='number' name='whc_server_specs[total_ram]' value='" . esc_attr($ram) . "' /> GB";
    }

    public function enqueue_admin_assets($hook) {
        if ($hook !== 'tools_page_wp-health-cockpit') { return; }
        wp_enqueue_script('whc-audit-script', plugin_dir_url(dirname(__FILE__)) . 'assets/audit.js', ['jquery'], '1.8.0', true);
        wp_localize_script('whc-audit-script', 'whc_ajax_object', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('whc_frontend_audit_nonce'),
        ]);
    }

    public function handle_frontend_audit_ajax() {
        check_ajax_referer('whc_frontend_audit_nonce');
        $sanitized_url = esc_url_raw($_POST['url']);
        $frontend_audit = new WHC_Audit_Frontend();
        wp_send_json_success($frontend_audit->get_info($sanitized_url));
    }

    private function render_table($title, $data_array) {
        ?>
        <h2 style="margin-top: 40px;"><?php echo esc_html($title); ?></h2>
        <table class="whc-table" style="width:100%;border-collapse:collapse;margin-top:20px;">
            <thead><tr><th style="text-align:left;padding:12px;border:1px solid #ddd;">Tetapan</th><th style="text-align:left;padding:12px;border:1px solid #ddd;">Status</th><th style="text-align:left;padding:12px;border:1px solid #ddd;">Nota</th></tr></thead>
            <tbody>
                <?php foreach ($data_array as $data) : ?>
                    <tr>
                        <td style="padding:12px;border:1px solid #ddd;"><strong><?php echo esc_html($data['label']); ?></strong></td>
                        <td style="padding:12px;border:1px solid #ddd;"><?php echo wp_kses_post($data['value']); ?></td>
                        <td style="padding:12px;border:1px solid #ddd;"><?php echo wp_kses_post($data['notes']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    public function render_audit_page() {
        $php_info = (new WHC_Audit_PHP())->get_info();
        $db_info = (new WHC_Audit_Database())->get_info();
        $wp_info = (new WHC_Audit_WP())->get_info();
        ?>
        <div class="wrap">
            <h1>WP Health Cockpit (Modular v1.8.0)</h1>
            <?php 
            $this->render_table('Audit PHP', $php_info);
            $this->render_table('Audit Database', $db_info);
            $this->render_table('Audit WordPress', $wp_info);
            ?>
        </div>
        <style>.status-ok{color:green;}.status-warning{color:orange;}.status-critical{color:red;}</style>
        <?php
    }
}
