<?php
/**
 * Modul Optimizer
 * Menguruskan fungsi "Quick Fix" dan optimasi on-the-fly.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class WHC_Optimizer {

    public function __construct() {
        // Jalankan optimasi yang dipilih (disimpan dalam options)
        $this->init_active_optimizations();
    }

    /**
     * Memulakan optimasi yang di-enable oleh pengguna.
     */
    private function init_active_optimizations() {
        $options = get_option('whc_optimizer_settings', []);

        // 1. Disable Emoji
        if ( !empty($options['disable_emojis']) ) {
            add_action('init', [$this, 'disable_emojis']);
        }

        // 2. Hide WP Version
        if ( !empty($options['hide_wp_version']) ) {
            add_filter('the_generator', '__return_empty_string');
        }

        // 3. Disable XML-RPC
        if ( !empty($options['disable_xmlrpc']) ) {
            add_filter('xmlrpc_enabled', '__return_false');
        }

        // 4. Limit Heartbeat (New)
        if ( !empty($options['limit_heartbeat']) ) {
            add_filter('heartbeat_settings', function($settings) {
                $settings['interval'] = 60; // 1 minute
                return $settings;
            });
        }

        // 5. Remove jQuery Migrate (New)
        if ( !empty($options['remove_jqmigrate']) ) {
            add_action('wp_default_scripts', function($scripts) {
                if (!is_admin() && isset($scripts->registered['jquery'])) {
                    $script = $scripts->registered['jquery'];
                    if ($script->deps) {
                        $script->deps = array_diff($script->deps, ['jquery-migrate']);
                    }
                }
            });
        }

        // 6. Disable Self-Pingbacks (New)
        if ( !empty($options['disable_pingbacks']) ) {
            add_action('pre_ping', function(&$links) {
                $home = get_option('home');
                foreach ($links as $l => $link) {
                    if (strpos($link, $home) === 0) {
                        unset($links[$l]);
                    }
                }
            });
        }

        // 7. Header Cleanup (New)
        if ( !empty($options['clean_header']) ) {
            remove_action('wp_head', 'rsd_link');
            remove_action('wp_head', 'wlwmanifest_link');
            remove_action('wp_head', 'wp_shortlink_wp_head', 10);
            remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10);
        }
    }

    /**
     * FUNGSI QUICK FIX (Dijalankan secara manual melalui AJAX)
     */

    public function clean_post_revisions() {
        global $wpdb;
        $count = $wpdb->query("DELETE FROM $wpdb->posts WHERE post_type = 'revision'");
        return $count !== false ? $count : 0;
    }

    public function clean_expired_transients() {
        global $wpdb;
        $sql = "DELETE a, b FROM $wpdb->options a, $wpdb->options b
                WHERE a.option_name LIKE '_transient_timeout_%'
                AND a.option_name = CONCAT('_transient_timeout_', SUBSTRING(b.option_name, 12))
                AND b.option_name LIKE '_transient_%'
                AND a.option_value < UNIX_TIMESTAMP()";
        $count = $wpdb->query($sql);
        return $count !== false ? $count : 0;
    }

    public function toggle_autoload($option_name, $status = 'no') {
        global $wpdb;
        $result = $wpdb->update($wpdb->options, ['autoload' => $status], ['option_name' => $option_name]);
        return $result !== false ? 1 : 0;
    }

    /**
     * Memadam option tertentu secara manual.
     */
    public function delete_option($option_name) {
        return delete_option($option_name) ? 1 : 0;
    }

    /**
     * Mendapatkan senarai jadual yang masih menggunakan enjin MyISAM.
     */
    public function get_myisam_tables() {
        global $wpdb;
        return $wpdb->get_results("SELECT TABLE_NAME, (DATA_LENGTH + INDEX_LENGTH) as size FROM information_schema.tables WHERE table_schema = DATABASE() AND engine = 'MyISAM'", ARRAY_A);
    }

    /**
     * Menukar enjin jadual daripada MyISAM ke InnoDB.
     */
    public function convert_table_to_innodb($table_name) {
        global $wpdb;
        // Sanitasi nama jadual (Hanya benarkan alphanumeric dan underscore)
        $table_name = preg_replace('/[^a-zA-Z0-9_]/', '', $table_name);
        if (empty($table_name)) return 0;

        $result = $wpdb->query("ALTER TABLE $table_name ENGINE=InnoDB");
        return $result !== false ? 1 : 0;
    }

    /**
     * Mencari options yang berpotensi milik plugin yang tidak aktif atau didelete.
     * Ini adalah heuristic (tekaan) berdasarkan prefix biasa.
     */
    public function get_potential_orphans() {
        global $wpdb;
        
        // Dapatkan list plugin aktif & tak aktif
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $all_plugins = array_keys(get_plugins());
        $active_plugins = get_option('active_plugins', []);
        
        $inactive_slugs = [];
        foreach ($all_plugins as $p) {
            if (!in_array($p, $active_plugins)) {
                $inactive_slugs[] = explode('/', $p)[0]; // Ambil folder name (slug)
            }
        }

        // Cari options yang bermula dengan slug plugin tak aktif
        if (empty($inactive_slugs)) return [];

        $where_clauses = [];
        foreach ($inactive_slugs as $slug) {
            $where_clauses[] = "option_name LIKE '" . $wpdb->esc_like($slug) . "_%'";
        }

        $query = "SELECT option_name, LENGTH(option_value) as size FROM $wpdb->options WHERE " . implode(' OR ', $where_clauses) . " LIMIT 100";
        return $wpdb->get_results($query);
    }

    /**
     * Cuba kenalpasti asal-usul sesuatu option berdasarkan prefix atau nama.
     */
    public function identify_option_source($option_name) {
        // 1. WordPress Core (Specific common options without prefixes)
        $core_options = [
            'siteurl', 'home', 'blogname', 'blogdescription', 'users_can_register', 'admin_email', 
            'start_of_week', 'use_balanceTags', 'use_smilies', 'require_name_email', 'comments_notify', 
            'posts_per_rss', 'rss_use_excerpt', 'mailserver_url', 'mailserver_login', 'mailserver_pass', 
            'mailserver_port', 'default_category', 'default_comment_status', 'default_ping_status', 
            'default_pingback_flag', 'posts_per_page', 'date_format', 'time_format', 'links_updated_date_format', 
            'comment_moderation', 'moderation_notify', 'permalink_structure', 'gzipcompression', 'category_base', 
            'tag_base', 'sidebars_widgets', 'active_plugins', 'current_theme', 'template', 'stylesheet', 
            'page_for_posts', 'page_on_front', 'default_role', 'fresh_site', 'can_compress_scripts', 
            'col_diff', 'db_upgraded', 'db_version', 'thumbnail_size_w', 'thumbnail_size_h', 'thumbnail_crop', 
            'medium_size_w', 'medium_size_h', 'large_size_w', 'large_size_h', 'image_default_link_type', 
            'image_default_size', 'image_default_align', 'close_comments_for_old_posts', 'close_comments_days_old', 
            'thread_comments', 'thread_comments_depth', 'page_comments', 'comments_per_page', 'default_comments_page', 
            'comment_order', 'comment_whitelist', 'comment_registration', 'html_type', 'use_trackback', 
            'stich_special_chars', 'content_errors', 'upload_path', 'upload_url_path', 'uploads_use_yearmonth_folders', 
            'default_email_category', 'recently_edited', 'auto_core_update', 'auto_plugin_update', 'auto_theme_update', 
            'cron', 'auth_key', 'secure_auth_key', 'logged_in_key', 'nonce_key', 'auth_salt', 'secure_auth_salt', 
            'logged_in_salt', 'nonce_salt', 'wp_user_roles', 'initial_db_version', 'uninstall_plugins', 'rewrite_rules'
        ];
        
        if (in_array($option_name, $core_options)) return ['source' => 'WordPress Core', 'status' => 'active'];

        // 2. WordPress Core (Prefixes)
        $core_prefixes = ['wp_', '_transient_', '_site_transient_', 'rss_', 'widget_', 'theme_mods_', 'nav_menu_'];
        foreach ($core_prefixes as $p) {
            if (strpos($option_name, $p) === 0) return ['source' => 'WordPress Core', 'status' => 'active'];
        }

        // 3. Dapatkan list plugin untuk matching
        if (!function_exists('get_plugins')) require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', []);
        
        // 4. Heuristic matching dengan plugin slugs
        foreach ($all_plugins as $path => $data) {
            $slug = explode('/', $path)[0];
            // Match slug atau slug dengan underscore (e.g. contact-form-7 -> contact_form_7)
            if (strpos($option_name, $slug) === 0 || strpos($option_name, str_replace('-', '_', $slug)) === 0) {
                return [
                    'source' => $data['Name'],
                    'status' => in_array($path, $active_plugins) ? 'active' : 'inactive'
                ];
            }
        }

        return ['source' => 'Tidak Diketahui', 'status' => 'unknown'];
    }

    public function disable_emojis() {
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        add_filter('tiny_mce_plugins', function($plugins) {
            return is_array($plugins) ? array_diff($plugins, ['wpemoji']) : [];
        });
        add_filter('wp_resource_hints', function($urls, $relation_type) {
            if ('dns-prefetch' === $relation_type) {
                $emoji_svg_url = apply_filters('emoji_svg_url', 'https://s.w.org/images/core/emoji/14.0.0/svg/');
                $urls = array_diff($urls, [$emoji_svg_url]);
            }
            return $urls;
        }, 10, 2);
    }
}
