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
        add_action( 'wp_ajax_whc_run_full_audit', [ $this, 'handle_full_audit_ajax' ] );
        add_action( 'wp_ajax_whc_reset_all_settings', [ $this, 'handle_reset_settings_ajax' ] );
    }

    public function handle_reset_settings_ajax() {
        check_ajax_referer('whc_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Tiada kebenaran.');

        // Senarai options yang perlu dipadam
        $options_to_delete = [
            'whc_last_audit_results',
            'whc_last_audit_timestamp',
            'whc_server_specs',
            'whc_optimizer_settings'
        ];

        foreach ($options_to_delete as $opt) {
            delete_option($opt);
        }

        wp_send_json_success('Semua tetapan telah dikosongkan.');
    }

    public function handle_full_audit_ajax() {
        check_ajax_referer('whc_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Tiada kebenaran.');

        // Jalankan semua modul audit
        $results = [
            'security' => (new WHC_Audit_Security())->get_info(),
            'plugins'  => (new WHC_Audit_Plugins())->get_info(),
            'wp'       => (new WHC_Audit_WP())->get_info(),
            'php'      => (new WHC_Audit_PHP())->get_info(),
            'database' => (new WHC_Audit_Database())->get_info(),
        ];

        if ( is_multisite() ) {
            $results['multisite'] = (new WHC_Audit_Multisite())->get_info();
        }

        // Simpan ke database
        update_option('whc_last_audit_results', $results);
        update_option('whc_last_audit_timestamp', current_time('mysql'));

        wp_send_json_success([
            'message'   => 'Audit selesai!',
            'timestamp' => date_i18n('j F Y, g:i a', strtotime(current_time('mysql')))
        ]);
    }

    public function handle_optimization_ajax() {
        check_ajax_referer('whc_optimization_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();

        $action = sanitize_text_field($_POST['opt_action']);
        $optimizer = new WHC_Optimizer();
        $result = 0;

        if ($action === 'clean_revisions') $result = $optimizer->clean_post_revisions();
        if ($action === 'clean_transients') $result = $optimizer->clean_expired_transients();
        
        // Baru: Convert MyISAM to InnoDB
        if ($action === 'convert_innodb') {
            $tables = isset($_POST['tables']) ? array_map('sanitize_text_field', (array)$_POST['tables']) : [];
            foreach ($tables as $table) {
                $result += $optimizer->convert_table_to_innodb($table);
            }
        }

        // Baru: Bulk Toggle Autoload
        if ($action === 'toggle_autoload') {
            $opt_names = isset($_POST['opt_names']) ? array_map('sanitize_text_field', (array)$_POST['opt_names']) : [];
            foreach ($opt_names as $name) {
                $result += $optimizer->toggle_autoload($name, 'no');
            }
        }

        // Baru: Bulk Delete Option
        if ($action === 'delete_option') {
            $opt_names = isset($_POST['opt_names']) ? array_map('sanitize_text_field', (array)$_POST['opt_names']) : [];
            foreach ($opt_names as $name) {
                $result += $optimizer->delete_option($name);
            }
        }

        wp_send_json_success(['count' => $result]);
    }

    public function add_admin_menu() {
        // Menu Utama (Top Level)
        add_menu_page(
            'WP Health Cockpit', 
            'Health Cockpit', 
            'manage_options', 
            'wp-health-cockpit', 
            [ $this, 'render_audit_page' ], 
            'dashicons-performance', 
            80
        );

        // Submenu 1: Dashboard (Sama dengan menu utama)
        add_submenu_page(
            'wp-health-cockpit',
            'Health Dashboard',
            'Dashboard',
            'manage_options',
            'wp-health-cockpit',
            [ $this, 'render_audit_page' ]
        );

        // Submenu 2: DB Optimizer
        add_submenu_page(
            'wp-health-cockpit',
            'DB Optimizer',
            'DB Optimizer',
            'manage_options',
            'whc-db-optimizer',
            [ $this, 'render_db_optimizer_page' ]
        );

        // Submenu 3: Settings
        add_submenu_page(
            'wp-health-cockpit',
            'Health Cockpit Settings',
            'Settings',
            'manage_options',
            'whc-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Health Cockpit Settings</h1>
            <p>Konfigurasi profil projek dan server untuk audit yang lebih tepat.</p>

            <form action="options.php" method="post" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin-top: 20px; max-width: 800px;">
                <?php
                settings_fields( 'whc_options_group' );
                do_settings_sections( 'wp-health-cockpit' );
                submit_button('Simpan Tetapan');
                ?>
            </form>

            <hr style="margin-top: 50px;">
            <div style="background: #fff; padding: 20px; border: 1px solid #d63638; border-left: 4px solid #d63638; max-width: 800px; margin-top: 20px;">
                <h3 style="color: #d63638; margin-top: 0;"><span class="dashicons dashicons-warning" style="vertical-align: middle;"></span> Bahagian Bahaya (Danger Zone)</h3>
                <p>Tindakan di bawah akan memadam <strong>semua</strong> rekod imbasan, spesifikasi server yang disimpan, dan pilihan optimasi aktif anda.</p>
                <p style="font-weight: bold; color: #d63638;">⚠️ Tindakan ini tidak boleh diundur!</p>
                <button id="whc-reset-plugin" class="button" style="color: #d63638; border-color: #d63638;">Reset Semua Data Plugin</button>
            </div>
        </div>
        <script>
            jQuery(document).ready(function($) {
                $('#whc-reset-plugin').on('click', function() {
                    if (!confirm('AMARAN KERAS: Adakah anda pasti mahu memadam semua data dan tetapan WP Health Cockpit?')) return;
                    if (!confirm('PENGESAHAN KEDUA: Anda benar-benar pasti? Semua usaha konfigurasi anda akan hilang.')) return;

                    const btn = $(this);
                    btn.prop('disabled', true).text('Memadam Data...');

                    $.post(whc_ajax_object.ajax_url, {
                        action: 'whc_reset_all_settings',
                        nonce: whc_ajax_object.nonce
                    }, function(r) {
                        if (r.success) {
                            alert(r.data);
                            location.reload();
                        }
                    });
                });
            });
        </script>
        <?php
    }

    public function render_db_optimizer_page() {
        global $wpdb;
        $optimizer = new WHC_Optimizer();
        
        // 1. Top 50 Autoloaded Options
        $top_autoloaded = $wpdb->get_results("SELECT option_name, LENGTH(option_value) as size FROM $wpdb->options WHERE autoload IN ('yes', 'on') ORDER BY size DESC LIMIT 50");
        
        // 2. Potential Inactive/Orphaned
        $potential_orphans = $optimizer->get_potential_orphans();
        ?>
        <div class="wrap">
            <h1>Database Optimizer</h1>
            
            <div class="notice notice-error" style="border-left-color: #dc3232; padding: 15px; margin-top: 20px;">
                <p style="color: #dc3232; font-weight: bold; font-size: 1.1em; margin: 0 0 10px 0;">⚠️ AMARAN KESELAMATAN</p>
                <p style="margin: 0;">Tindakan di bawah akan mengubah pangkalan data anda secara terus. <strong>Pastikan anda membuat backup database yang lengkap</strong> sebelum melakukan sebarang pembersihan atau penukaran status autoload. Mat Gem tidak bertanggungjawab atas sebarang kerosakan laman web akibat penggunaan tool ini secara tidak sengaja.</p>
            </div>

            <p style="margin-top: 20px;">Alat kawalan jauh untuk membedah dan membersihkan pangkalan data anda.</p>

            <h2 style="margin-top:30px;">🛡️ Top 50 Autoloaded Options</h2>
            
            <?php
            $autoload_stats = $wpdb->get_row("SELECT COUNT(*) as count, SUM(LENGTH(option_value)) as size FROM $wpdb->options WHERE autoload IN ('yes', 'on')");
            $total_size_kb = round($autoload_stats->size / 1024, 2);
            $total_count   = $autoload_stats->count;
            
            $status_color = '#46b450'; // Green
            if ($total_size_kb > 1200) { $status_color = '#dc3232'; } // Red
            elseif ($total_size_kb > 800) { $status_color = '#ffb900'; } // Yellow
            ?>

            <div style="background: #fff; padding: 15px; border: 1px solid #ccd0d4; border-left: 4px solid <?php echo $status_color; ?>; margin-bottom: 20px;">
                <span style="font-size: 1.1em; font-weight: bold;">Saiz Autoloaded Semasa: 
                    <span style="color: <?php echo $status_color; ?>;"><?php echo $total_size_kb; ?> KB</span> 
                    (<?php echo $total_count; ?> Options)
                </span>
                <div style="background: #eee; height: 8px; border-radius: 4px; margin-top: 10px; overflow: hidden;">
                    <?php 
                    $percent = min(100, ($total_size_kb / 1500) * 100); 
                    ?>
                    <div style="background: <?php echo $status_color; ?>; height: 100%; width: <?php echo $percent; ?>%;"></div>
                </div>
            </div>

            <p class="description" style="max-width: 800px; line-height: 1.6;">
                <strong>Autoloaded Data</strong> adalah maklumat yang WordPress muatkan secara automatik ke dalam memori pada <strong>setiap</strong> request halaman (frontend dan backend). 
                <br><br>
                Saiz maksimum yang disyorkan untuk laman web yang sihat adalah antara <strong>800KB hingga 1MB</strong>. Jika saiz ini melebihi had, ia akan melambatkan masa respons server (TTFB) dan membebankan CPU server. 
                Tindakan <em>"De-Autoload"</em> akan menukar status data tersebut supaya ia hanya dimuatkan apabila kod spesifik memanggilnya sahaja.
            </p>
            
            <div class="whc-bulk-actions" style="margin-top: 20px; margin-bottom: 10px;">
                <button class="button whc-bulk-toggle-autoload" disabled>De-Autoload Terpilih</button>
            </div>

            <table class="wp-list-table widefat fixed striped whc-autoload-table">
                <thead>
                    <tr>
                        <td id="cb" class="manage-column column-cb check-column"><input id="cb-select-all-1" type="checkbox"></td>
                        <th>Option Name</th>
                        <th>Size (KB)</th>
                        <th>Source / Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_autoloaded as $opt) : 
                        $info = $optimizer->identify_option_source($opt->option_name);
                        $status_color = ($info['status'] === 'active') ? 'green' : (($info['status'] === 'inactive') ? 'orange' : '#666');
                    ?>
                        <tr>
                            <th scope="row" class="check-column"><input type="checkbox" name="option[]" value="<?php echo esc_attr($opt->option_name); ?>"></th>
                            <td><code><?php echo esc_html($opt->option_name); ?></code></td>
                            <td><?php echo round($opt->size / 1024, 2); ?> KB</td>
                            <td>
                                <strong><?php echo esc_html($info['source']); ?></strong><br>
                                <span style="color: <?php echo $status_color; ?>; font-size: 0.85em;">● <?php echo ucfirst($info['status']); ?></span>
                            </td>
                            <td><button class="button whc-toggle-autoload" data-name="<?php echo esc_attr($opt->option_name); ?>">De-Autoload</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h2 style="margin-top:50px;">🗑️ Options Plugin Tidak Aktif (Potensi Orphaned)</h2>
            <p class="description">Mat Gem mengesan options ini mungkin milik plugin yang <strong>tidak aktif</strong> atau <strong>sudah dibuang</strong>.</p>
            <?php if (empty($potential_orphans)) : ?>
                <p>Tiada options mencurigakan dikesan buat masa ini. Bersih! ✨</p>
            <?php else : ?>
                <div class="whc-bulk-actions" style="margin-bottom: 10px;">
                    <button class="button whc-bulk-delete-option" style="color:red; border-color:red;" disabled>Padam Terpilih</button>
                </div>
                <table class="wp-list-table widefat fixed striped whc-orphans-table">
                    <thead>
                        <tr>
                            <td id="cb" class="manage-column column-cb check-column"><input id="cb-select-all-2" type="checkbox"></td>
                            <th>Option Name</th>
                            <th>Size (KB)</th>
                            <th>Source / Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($potential_orphans as $opt) : 
                            $info = $optimizer->identify_option_source($opt->option_name);
                        ?>
                            <tr>
                                <th scope="row" class="check-column"><input type="checkbox" name="option[]" value="<?php echo esc_attr($opt->option_name); ?>"></th>
                                <td><code><?php echo esc_html($opt->option_name); ?></code></td>
                                <td><?php echo round($opt->size / 1024, 2); ?> KB</td>
                                <td>
                                    <strong><?php echo esc_html($info['source']); ?></strong><br>
                                    <span style="color: orange; font-size: 0.85em;">● Inactive</span>
                                </td>
                                <td><button class="button whc-delete-option" style="color:red;" data-name="<?php echo esc_attr($opt->option_name); ?>">Padam</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h2 style="margin-top:50px;">🏗️ Enjin Jadual Ketinggalan Zaman (MyISAM)</h2>
            <p class="description">Jadual di bawah masih menggunakan enjin <strong>MyISAM</strong>. Adalah sangat disyorkan untuk menukarnya kepada <strong>InnoDB</strong> untuk prestasi yang lebih baik dan integriti data yang lebih tinggi.</p>
            
            <?php 
            $myisam_tables = $optimizer->get_myisam_tables();
            if (empty($myisam_tables)) : 
            ?>
                <p>Semua jadual anda sudah menggunakan InnoDB atau enjin moden yang lain. Syabas! 🚀</p>
            <?php else : ?>
                <div class="whc-bulk-actions" style="margin-bottom: 10px;">
                    <button class="button whc-bulk-convert-innodb" style="color:blue; border-color:blue;" disabled>Convert Terpilih ke InnoDB</button>
                </div>
                <table class="wp-list-table widefat fixed striped whc-myisam-table">
                    <thead>
                        <tr>
                            <td id="cb" class="manage-column column-cb check-column"><input id="cb-select-all-3" type="checkbox"></td>
                            <th>Table Name</th>
                            <th>Size (MB)</th>
                            <th>Engine</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($myisam_tables as $table) : ?>
                            <tr>
                                <th scope="row" class="check-column"><input type="checkbox" name="table[]" value="<?php echo esc_attr($table['TABLE_NAME']); ?>"></th>
                                <td><code><?php echo esc_html($table['TABLE_NAME']); ?></code></td>
                                <td><?php echo round($table['size'] / 1024 / 1024, 2); ?> MB</td>
                                <td><span style="color: orange; font-weight: bold;">MyISAM</span></td>
                                <td><button class="button whc-convert-innodb" data-name="<?php echo esc_attr($table['TABLE_NAME']); ?>">Convert to InnoDB</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <script>
            jQuery(document).ready(function($) {
                // Function to toggle bulk button state
                function updateBulkButtons(tableClass, buttonClass) {
                    const checkedCount = $(tableClass + ' tbody input[type="checkbox"]:checked').length;
                    $(buttonClass).prop('disabled', checkedCount === 0);
                }

                // Handle Select All
                $('#cb-select-all-1, #cb-select-all-2, #cb-select-all-3').on('change', function() {
                    const isChecked = $(this).prop('checked');
                    const table = $(this).closest('table');
                    table.find('tbody input[type="checkbox"]').prop('checked', isChecked);
                    
                    let tableClass, buttonClass;
                    if (table.hasClass('whc-autoload-table')) { tableClass = '.whc-autoload-table'; buttonClass = '.whc-bulk-toggle-autoload'; }
                    else if (table.hasClass('whc-orphans-table')) { tableClass = '.whc-orphans-table'; buttonClass = '.whc-bulk-delete-option'; }
                    else if (table.hasClass('whc-myisam-table')) { tableClass = '.whc-myisam-table'; buttonClass = '.whc-bulk-convert-innodb'; }
                    
                    updateBulkButtons(tableClass, buttonClass);
                });

                // Handle Individual Checkbox
                $(document).on('change', '.whc-autoload-table tbody input[type="checkbox"]', function() {
                    updateBulkButtons('.whc-autoload-table', '.whc-bulk-toggle-autoload');
                });
                $(document).on('change', '.whc-orphans-table tbody input[type="checkbox"]', function() {
                    updateBulkButtons('.whc-orphans-table', '.whc-bulk-delete-option');
                });
                $(document).on('change', '.whc-myisam-table tbody input[type="checkbox"]', function() {
                    updateBulkButtons('.whc-myisam-table', '.whc-bulk-convert-innodb');
                });

                // Single Convert to InnoDB
                $('.whc-convert-innodb').on('click', function() {
                    const btn = $(this);
                    const name = btn.data('name');
                    if (!confirm('Adakah anda pasti mahu menukar enjin jadual "' + name + '" kepada InnoDB?')) return;
                    btn.prop('disabled', true).text('Converting...');
                    $.post(whc_ajax_object.ajax_url, {
                        action: 'whc_run_optimization',
                        nonce: whc_ajax_object.opt_nonce,
                        opt_action: 'convert_innodb',
                        tables: [name]
                    }, function(r) { if(r.success) { btn.closest('tr').fadeOut(); } });
                });

                // Bulk Convert to InnoDB
                $('.whc-bulk-convert-innodb').on('click', function() {
                    const names = $('.whc-myisam-table tbody input[type="checkbox"]:checked').map(function() {
                        return $(this).val();
                    }).get();
                    
                    if (!confirm('Menukar ' + names.length + ' jadual terpilih kepada InnoDB?')) return;
                    
                    const btn = $(this);
                    const originalText = 'Convert Terpilih ke InnoDB';
                    btn.prop('disabled', true).text('Converting...');
                    
                    $.post(whc_ajax_object.ajax_url, {
                        action: 'whc_run_optimization',
                        nonce: whc_ajax_object.opt_nonce,
                        opt_action: 'convert_innodb',
                        tables: names
                    }, function(r) { 
                        if(r.success) { 
                            $('.whc-myisam-table tbody input[type="checkbox"]:checked').closest('tr').fadeOut();
                            
                            // Reset UI
                            btn.text(originalText).after('<span class="whc-done-msg" style="color:green; margin-left:10px; font-weight:bold;">✅ Selesai</span>');
                            $('.whc-myisam-table input[type="checkbox"]').prop('checked', false);
                            $('#cb-select-all-3').prop('checked', false);
                            
                            // Hilangkan mesej selesai selepas 3 saat
                            setTimeout(function() {
                                $('.whc-done-msg').fadeOut(function() { $(this).remove(); });
                            }, 3000);
                        } 
                    });
                });

                // Single De-Autoload
                $('.whc-toggle-autoload').on('click', function() {
                    const btn = $(this);
                    const name = btn.data('name');
                    if (!confirm('Adakah anda pasti mahu matikan autoload untuk: ' + name + '?')) return;
                    btn.prop('disabled', true).text('Processing...');
                    $.post(whc_ajax_object.ajax_url, {
                        action: 'whc_run_optimization',
                        nonce: whc_ajax_object.opt_nonce,
                        opt_action: 'toggle_autoload',
                        opt_names: [name]
                    }, function(r) { if(r.success) { btn.closest('tr').fadeOut(); } });
                });

                // Bulk De-Autoload
                $('.whc-bulk-toggle-autoload').on('click', function() {
                    const names = $('.whc-autoload-table tbody input[type="checkbox"]:checked').map(function() {
                        return $(this).val();
                    }).get();
                    
                    if (!confirm('Matikan autoload untuk ' + names.length + ' item terpilih?')) return;
                    
                    const btn = $(this);
                    btn.prop('disabled', true).text('Processing...');
                    
                    $.post(whc_ajax_object.ajax_url, {
                        action: 'whc_run_optimization',
                        nonce: whc_ajax_object.opt_nonce,
                        opt_action: 'toggle_autoload',
                        opt_names: names
                    }, function(r) { 
                        if(r.success) { 
                            $('.whc-autoload-table tbody input[type="checkbox"]:checked').closest('tr').fadeOut();
                            btn.text('Selesai!');
                        } 
                    });
                });

                // Single Delete
                $('.whc-delete-option').on('click', function() {
                    const btn = $(this);
                    const name = btn.data('name');
                    if (!confirm('AWAS! Adakah anda pasti mahu PADAM option ini? Tindakan ini tidak boleh diundur: ' + name)) return;
                    btn.prop('disabled', true).text('Deleting...');
                    $.post(whc_ajax_object.ajax_url, {
                        action: 'whc_run_optimization',
                        nonce: whc_ajax_object.opt_nonce,
                        opt_action: 'delete_option',
                        opt_names: [name]
                    }, function(r) { if(r.success) { btn.closest('tr').fadeOut(); } });
                });

                // Bulk Delete
                $('.whc-bulk-delete-option').on('click', function() {
                    const names = $('.whc-orphans-table tbody input[type="checkbox"]:checked').map(function() {
                        return $(this).val();
                    }).get();
                    
                    if (!confirm('AWAS! Padam ' + names.length + ' item terpilih secara kekal?')) return;
                    
                    const btn = $(this);
                    btn.prop('disabled', true).text('Deleting...');
                    
                    $.post(whc_ajax_object.ajax_url, {
                        action: 'whc_run_optimization',
                        nonce: whc_ajax_object.opt_nonce,
                        opt_action: 'delete_option',
                        opt_names: names
                    }, function(r) { 
                        if(r.success) { 
                            $('.whc-orphans-table tbody input[type="checkbox"]:checked').closest('tr').fadeOut();
                            btn.text('Selesai!');
                        } 
                    });
                });
            });
        </script>
        <?php
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

        // --- SECTION: PERFORMANCE ---
        add_settings_section('whc_perf_section', '⚡ Performance Toggles', function() { echo '<p>Ringankan beban server dan lajukan pemuatan halaman.</p>'; }, 'wp-health-cockpit');
        add_settings_field('whc_disable_emojis', 'Matikan WP Emojis', [ $this, 'checkbox_field_callback' ], 'wp-health-cockpit', 'whc_perf_section', ['id' => 'disable_emojis', 'notes' => 'Meringankan fail JS/CSS di frontend.']);
        add_settings_field('whc_limit_heartbeat', 'Limit WP Heartbeat', [ $this, 'checkbox_field_callback' ], 'wp-health-cockpit', 'whc_perf_section', ['id' => 'limit_heartbeat', 'notes' => 'Kurangkan kekerapan AJAX request ke server (jimat CPU).']);
        add_settings_field('whc_remove_jqmigrate', 'Remove jQuery Migrate', [ $this, 'checkbox_field_callback' ], 'wp-health-cockpit', 'whc_perf_section', ['id' => 'remove_jqmigrate', 'notes' => 'Kurangkan 1 request fail JS yang tidak diperlukan tema moden.']);
        add_settings_field('whc_disable_pingbacks', 'Disable Self-Pingbacks', [ $this, 'checkbox_field_callback' ], 'wp-health-cockpit', 'whc_perf_section', ['id' => 'disable_pingbacks', 'notes' => 'Sekat notifikasi komen setiap kali link ke post sendiri.']);

        // --- SECTION: SECURITY ---
        add_settings_section('whc_sec_section', '🛡️ Security & Cleanup Toggles', function() { echo '<p>Kukuhkan pertahanan dan bersihkan maklumat teknikal WordPress.</p>'; }, 'wp-health-cockpit');
        add_settings_field('whc_hide_version', 'Sembunyi WP Version', [ $this, 'checkbox_field_callback' ], 'wp-health-cockpit', 'whc_sec_section', ['id' => 'hide_wp_version', 'notes' => 'Menyukarkan bot untuk mengenali versi WordPress anda.']);
        add_settings_field('whc_disable_xmlrpc', 'Matikan XML-RPC', [ $this, 'checkbox_field_callback' ], 'wp-health-cockpit', 'whc_sec_section', ['id' => 'disable_xmlrpc', 'notes' => 'Menutup pintu belakang serangan brute-force.']);
        add_settings_field('whc_clean_header', 'Header Cleanup', [ $this, 'checkbox_field_callback' ], 'wp-health-cockpit', 'whc_sec_section', ['id' => 'clean_header', 'notes' => 'Buang RSD, WLW Manifest, dan Shortlinks dari HTML head.']);
    }

    public function checkbox_field_callback($args) {
        $options = get_option('whc_optimizer_settings', []);
        $id = $args['id'];
        $is_checked = isset($options[$id]) && $options[$id];
        
        // Cek jika sudah diuruskan oleh plugin/tema lain
        $is_external = $this->check_external_control($id);
        
        $disabled = $is_external ? 'disabled' : '';
        $checked  = ($is_checked || $is_external) ? 'checked' : '';
        
        echo "<input type='checkbox' name='whc_optimizer_settings[$id]' value='1' $checked $disabled /> ";
        echo "<span class='description'>" . esc_html($args['notes']) . "</span>";
        
        if ($is_external && ! $is_checked) {
            echo "<br><span style='color: #2271b1; font-size: 0.85em; font-style: italic;'>ℹ️ Dikesan: Fungsi ini telah disekat oleh plugin/tema lain.</span>";
        }
    }

    /**
     * Semak jika setting tertentu sudah di-disable oleh pihak ketiga.
     */
    private function check_external_control($id) {
        $options = get_option('whc_optimizer_settings', []);
        $our_setting = isset($options[$id]) && $options[$id];

        switch ($id) {
            case 'disable_emojis':
                return ! has_action('wp_head', 'print_emoji_detection_script') && ! $our_setting;
            
            case 'hide_wp_version':
                return empty(apply_filters('the_generator', 'detect', 'html')) && ! $our_setting;

            case 'disable_xmlrpc':
                return ! apply_filters('xmlrpc_enabled', true) && ! $our_setting;

            case 'limit_heartbeat':
                // Check jika heartbeat sudah di-disable (returning empty settings)
                $heartbeat = apply_filters('heartbeat_settings', []);
                return isset($heartbeat['interval']) && $heartbeat['interval'] > 60 && ! $our_setting;

            case 'remove_jqmigrate':
                // Sukar dikesan sebelum runtime, tapi boleh cek jika script deregistered
                global $wp_scripts;
                return (isset($wp_scripts->registered['jquery-migrate']) && empty($wp_scripts->registered['jquery-migrate']->src)) && ! $our_setting;

            case 'disable_pingbacks':
                return ! has_action('pre_ping', 'wp_ping') && ! $our_setting;

            case 'clean_header':
                return ! has_action('wp_head', 'rsd_link') && ! $our_setting;
        }
        return false;
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
        if ($hook !== 'toplevel_page_wp-health-cockpit' && $hook !== 'health-cockpit_page_whc-db-optimizer') { return; }
        wp_enqueue_script('whc-audit-script', plugins_url('assets/audit.js', dirname(dirname(__FILE__)) . '/wp-health-cockpit.php'), ['jquery'], '1.9.6', true);
        wp_localize_script('whc-audit-script', 'whc_ajax_object', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('whc_nonce'),
            'opt_nonce' => wp_create_nonce('whc_optimization_nonce'),
        ]);
    }

    public function handle_frontend_audit_ajax() {
        check_ajax_referer('whc_nonce', 'nonce');
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
                    <th style="text-align:left;padding:12px;border-bottom:1px solid #ccd0d4;">Cadangan Tindakan</th>
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
                        <td style="padding:12px;border-bottom:1px solid #eee; font-size: 0.9em; color: #2271b1; font-weight: 500;">
                            <?php echo isset($data['action_desc']) ? wp_kses_post($data['action_desc']) : '-'; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    public function render_audit_page() {
        $stored_results = get_option('whc_last_audit_results', []);
        $last_run       = get_option('whc_last_audit_timestamp', '');
        
        $security_info = isset($stored_results['security']) ? $stored_results['security'] : [];
        $plugin_info   = isset($stored_results['plugins']) ? $stored_results['plugins'] : [];
        $wp_info       = isset($stored_results['wp']) ? $stored_results['wp'] : [];
        $php_info      = isset($stored_results['php']) ? $stored_results['php'] : [];
        $db_info       = isset($stored_results['database']) ? $stored_results['database'] : [];
        $ms_info       = isset($stored_results['multisite']) ? $stored_results['multisite'] : [];

        $timestamp_display = $last_run ? date_i18n('j F Y, g:i a', strtotime($last_run)) : 'Belum pernah dijalankan';
        ?>
        <div class="wrap">
            <h1>WP Health Cockpit Dashboard</h1>
            <p>Diagnostik teknikal 360-darjah untuk WordPress anda.</p>

            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; border-left: 4px solid #2271b1;">
                <div>
                    <span class="dashicons dashicons-backup" style="vertical-align: middle; color: #2271b1; margin-right: 5px;"></span>
                    <strong>Audit Terakhir:</strong> <span id="whc-last-run-time"><?php echo esc_html($timestamp_display); ?></span>
                </div>
                <button id="whc-run-full-audit" class="button button-primary button-large">
                    <span class="dashicons dashicons-update" style="vertical-align: middle; margin-top: 4px;"></span> Jalankan Audit Penuh
                </button>
            </div>
            
            <div id="whc-audit-results-container" style="<?php echo empty($stored_results) ? 'opacity: 0.5;' : ''; ?>">
                <?php if (empty($stored_results)) : ?>
                    <div class="notice notice-info inline"><p>Sila klik butang di atas untuk memulakan audit pertama anda.</p></div>
                <?php else : 
                    $this->render_table('🛡️ Keselamatan Asas', $security_info);
                    $this->render_table('🔄 Kitaran Hayat Plugin', $plugin_info);
                    $this->render_table('⚙️ Analisis WordPress', $wp_info);
                    if ( ! empty($ms_info) ) {
                        $this->render_table('🌐 Analisis Multisite', $ms_info);
                    }
                    $this->render_table('💻 Konfigurasi PHP', $php_info);
                    $this->render_table('🗃️ Kesihatan Database', $db_info);
                endif; ?>
            </div>
        </div>
        <style>
            .whc-table strong { color: #23282d; }
            h2 { border-left: 4px solid #2271b1; padding-left: 10px; }
        </style>
        <?php
    }
}
