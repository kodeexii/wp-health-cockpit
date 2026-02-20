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
