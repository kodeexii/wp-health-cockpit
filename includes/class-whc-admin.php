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
        add_action( 'wp_ajax_whc_run_optimization', [ $this, 'handle_optimization_ajax' ] );
    }

    public function handle_optimization_ajax() {
        check_ajax_referer('whc_optimization_nonce');
        if (!current_user_can('manage_options')) wp_die();

        $action = sanitize_text_field($_POST['opt_action']);
        $optimizer = new WHC_Optimizer();
        $result = 0;

        if ($action === 'clean_revisions') $result = $optimizer->clean_post_revisions();
        if ($action === 'clean_transients') $result = $optimizer->clean_expired_transients();

        wp_send_json_success(['count' => $result]);
    }

    public function add_admin_menu() {
        add_management_page('WP Health Cockpit','Health Cockpit','manage_options','wp-health-cockpit',[ $this, 'render_audit_page' ]);
    }

    public function register_settings() {
        register_setting( 'whc_options_group', 'whc_server_specs' );
        register_setting( 'whc_options_group', 'whc_optimizer_settings' );

        add_settings_section('whc_settings_section', 'Konfigurasi Projek & Server (Pilihan)', function() { echo '<p>Masukkan maklumat di bawah untuk mendapatkan cadangan audit yang lebih tepat mengikut skala projek anda.</p>'; }, 'wp-health-cockpit');
        
        add_settings_field('whc_project_type', 'Jenis Projek', [ $this, 'project_type_callback' ], 'wp-health-cockpit', 'whc_settings_section');
        add_settings_field('whc_storage_type', 'Jenis Storage', [ $this, 'storage_type_callback' ], 'wp-health-cockpit', 'whc_settings_section');
        add_settings_field('whc_traffic_level', 'Anggaran Trafik', [ $this, 'traffic_level_callback' ], 'wp-health-cockpit', 'whc_settings_section');
        add_settings_field('whc_total_ram', 'Jumlah RAM Server (GB)', [ $this, 'ram_field_callback' ], 'wp-health-cockpit', 'whc_settings_section');
        add_settings_field('whc_cpu_cores', 'Bilangan CPU Cores', [ $this, 'cpu_field_callback' ], 'wp-health-cockpit', 'whc_settings_section');

        add_settings_section('whc_optimizer_section', '🎚️ Performance & Security Toggles', function() { echo '<p>Aktifkan optimasi on-the-fly untuk meringankan laman web anda.</p>'; }, 'wp-health-cockpit');
        add_settings_field('whc_disable_emojis', 'Matikan WP Emojis', [ $this, 'checkbox_field_callback' ], 'wp-health-cockpit', 'whc_optimizer_section', ['id' => 'disable_emojis', 'notes' => 'Meringankan fail JS/CSS di frontend.']);
        add_settings_field('whc_hide_version', 'Sembunyi WP Version', [ $this, 'checkbox_field_callback' ], 'wp-health-cockpit', 'whc_optimizer_section', ['id' => 'hide_wp_version', 'notes' => 'Menyukarkan bot keselamatan untuk mengenali versi WP anda.']);
        add_settings_field('whc_disable_xmlrpc', 'Sembunyi XML-RPC', [ $this, 'checkbox_field_callback' ], 'wp-health-cockpit', 'whc_optimizer_section', ['id' => 'disable_xmlrpc', 'notes' => 'Menutup pintu belakang serangan brute-force.']);
    }

    public function checkbox_field_callback($args) {
        $options = get_option('whc_optimizer_settings', []);
        $id = $args['id'];
        $checked = isset($options[$id]) && $options[$id] ? 'checked' : '';
        echo "<input type='checkbox' name='whc_optimizer_settings[$id]' value='1' $checked /> <span class='description'>" . esc_html($args['notes']) . "</span>";
    }

    public function project_type_callback() {
        $options = get_option('whc_server_specs');
        $type = isset($options['project_type']) ? $options['project_type'] : 'blog';
        ?>
        <select name='whc_server_specs[project_type]'>
            <option value='blog' <?php selected($type, 'blog'); ?>>Blog / Website Biasa</option>
            <option value='ecommerce' <?php selected($type, 'ecommerce'); ?>>E-commerce (WooCommerce)</option>
            <option value='lms' <?php selected($type, 'lms'); ?>>LMS / Membership Site</option>
        </select>
        <?php
    }

    public function storage_type_callback() {
        $options = get_option('whc_server_specs');
        $storage = isset($options['storage_type']) ? $options['storage_type'] : 'ssd';
        ?>
        <select name='whc_server_specs[storage_type]'>
            <option value='hdd' <?php selected($storage, 'hdd'); ?>>HDD (Sangat Perlahan)</option>
            <option value='ssd' <?php selected($storage, 'ssd'); ?>>SSD (Standard)</option>
            <option value='nvme' <?php selected($storage, 'nvme'); ?>>NVMe (Pantas)</option>
        </select>
        <?php
    }

    public function traffic_level_callback() {
        $options = get_option('whc_server_specs');
        $traffic = isset($options['traffic_level']) ? $options['traffic_level'] : 'low';
        ?>
        <select name='whc_server_specs[traffic_level]'>
            <option value='low' <?php selected($traffic, 'low'); ?>>Rendah (< 10k visits/mo)</option>
            <option value='medium' <?php selected($traffic, 'medium'); ?>>Sederhana (10k - 100k visits/mo)</option>
            <option value='high' <?php selected($traffic, 'high'); ?>>Tinggi (> 100k visits/mo)</option>
        </select>
        <?php
    }

    public function ram_field_callback() {
        $options = get_option('whc_server_specs');
        $ram = isset($options['total_ram']) ? $options['total_ram'] : '';
        echo "<input type='number' name='whc_server_specs[total_ram]' value='" . esc_attr($ram) . "' style='width: 60px;' /> GB";
    }

    public function cpu_field_callback() {
        $options = get_option('whc_server_specs');
        $cpu = isset($options['cpu_cores']) ? $options['cpu_cores'] : '';
        echo "<input type='number' name='whc_server_specs[cpu_cores]' value='" . esc_attr($cpu) . "' style='width: 60px;' /> Cores";
    }

    public function enqueue_admin_assets($hook) {
        if ($hook !== 'tools_page_wp-health-cockpit') { return; }
        wp_enqueue_script('whc-audit-script', plugin_dir_url(dirname(__FILE__)) . 'assets/audit.js', ['jquery'], '1.9.2', true);
        wp_localize_script('whc-audit-script', 'whc_ajax_object', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('whc_frontend_audit_nonce'),
            'opt_nonce' => wp_create_nonce('whc_optimization_nonce'),
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
                    <th style="text-align:left;padding:12px;border-bottom:1px solid #ccd0d4;">Tindakan</th>
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
                        <td style="padding:12px;border-bottom:1px solid #eee;">
                            <?php if (isset($data['action'])) : ?>
                                <button class="button whc-quick-fix" data-action="<?php echo esc_attr($data['action']); ?>">Fix Now</button>
                            <?php endif; ?>
                        </td>
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

            <hr style="margin-top: 50px;">
            <form action="options.php" method="post" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin-top: 20px;">
                <?php
                settings_fields( 'whc_options_group' );
                do_settings_sections( 'wp-health-cockpit' );
                submit_button('Simpan Spesifikasi Server');
                ?>
            </form>
        </div>
        <style>
            .whc-table strong { color: #23282d; }
            h2 { border-left: 4px solid #2271b1; padding-left: 10px; }
        </style>
        <?php
    }
}
