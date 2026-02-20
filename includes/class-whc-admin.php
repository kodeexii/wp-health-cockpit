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
        wp_enqueue_script('whc-audit-script', plugin_dir_url(dirname(__FILE__)) . 'assets/audit.js', ['jquery'], '1.9.0', true);
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
        <table class="whc-table" style="width:100%;border-collapse:collapse;margin-top:20px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <thead>
                <tr style="background: #f6f7f7;">
                    <th style="text-align:left;padding:12px;border-bottom:1px solid #ccd0d4;">Tetapan</th>
                    <th style="text-align:left;padding:12px;border-bottom:1px solid #ccd0d4;">Status / Nilai</th>
                    <th style="text-align:left;padding:12px;border-bottom:1px solid #ccd0d4;">Nota</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data_array as $data) : 
                    $dot_color = '#ccc';
                    if (isset($data['status'])) {
                        if ($data['status'] === 'ok') $dot_color = '#46b450';
                        if ($data['status'] === 'warning') $dot_color = '#ffb900';
                        if ($data['status'] === 'critical') $dot_color = '#dc3232';
                    }
                ?>
                    <tr>
                        <td style="padding:12px;border-bottom:1px solid #eee;"><strong><?php echo esc_html($data['label']); ?></strong></td>
                        <td style="padding:12px;border-bottom:1px solid #eee;">
                            <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:<?php echo $dot_color; ?>; margin-right:8px;"></span>
                            <?php echo wp_kses_post($data['value']); ?>
                        </td>
                        <td style="padding:12px;border-bottom:1px solid #eee; font-size: 0.9em; color: #666;"><?php echo wp_kses_post($data['notes']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    public function render_audit_page() {
        $php_info      = (new WHC_Audit_PHP())->get_info();
        $db_info       = (new WHC_Audit_Database())->get_info();
        $wp_info       = (new WHC_Audit_WP())->get_info();
        $security_info = (new WHC_Audit_Security())->get_info();
        $plugin_info   = (new WHC_Audit_Plugins())->get_info();
        ?>
        <div class="wrap">
            <h1>WP Health Cockpit Dashboard</h1>
            <p>Diagnostik teknikal 360-darjah untuk WordPress anda.</p>
            
            <div class="whc-dashboard-grid">
                <?php 
                $this->render_table('🛡️ Keselamatan Asas', $security_info);
                $this->render_table('🔄 Kitaran Hayat Plugin', $plugin_info);
                $this->render_table('⚙️ Analisis WordPress', $wp_info);
                $this->render_table('💻 Konfigurasi PHP', $php_info);
                $this->render_table('🗃️ Kesihatan Database', $db_info);
                ?>
            </div>
        </div>
        <style>
            .whc-table strong { color: #23282d; }
            h2 { border-left: 4px solid #2271b1; padding-left: 10px; }
        </style>
        <?php
    }
}
