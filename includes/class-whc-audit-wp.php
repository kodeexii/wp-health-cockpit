<?php
/**
 * Modul Audit WordPress
 * Menilai konfigurasi teras (Core) WordPress.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class WHC_Audit_WP {

    public function get_info() {
        $wp_info = [];
        
        // 1. WP_MEMORY_LIMIT Check
        $wp_mem_limit_val = defined('WP_MEMORY_LIMIT') ? constant('WP_MEMORY_LIMIT') : 'Default (40M)';
        $wp_info['wp_memory_limit'] = [
            'label'       => 'WP_MEMORY_LIMIT', 
            'value'       => $wp_mem_limit_val, 
            'recommended' => '256M', 
            'status'      => (int)$wp_mem_limit_val >= 256 ? 'ok' : 'warning', 
            'notes'       => 'Had memori WordPress untuk operasi frontend.'
        ];
        
        // 2. Object Cache Check
        $is_object_cache_persistent = function_exists('wp_using_ext_object_cache') ? wp_using_ext_object_cache() : false;
        $wp_info['object_cache'] = [
            'label'       => 'Object Cache Kekal', 
            'value'       => $is_object_cache_persistent ? 'Aktif' : 'Tidak Aktif', 
            'recommended' => 'Aktif (Redis/Memcached)', 
            'status'      => $is_object_cache_persistent ? 'ok' : 'critical', 
            'notes'       => 'Sangat kritikal untuk prestasi laman yang sibuk.'
        ];

        // 3. Post Revision Check
        $revisions = defined('WP_POST_REVISIONS') ? constant('WP_POST_REVISIONS') : true;
        $rev_val = ($revisions === true) ? 'Tak Terhad' : ($revisions === false ? '0' : $revisions);
        $wp_info['post_revisions'] = [
            'label'       => 'Revisi Pos', 
            'value'       => $rev_val, 
            'recommended' => '3 - 5', 
            'status'      => ($revisions === true || (int)$revisions > 10) ? 'warning' : 'ok', 
            'notes'       => 'Mengehadkan revisi membantu mengelakkan pangkalan data dari membengkak.'
        ];

        // 4. WP_DEBUG Status
        $wp_debug = defined('WP_DEBUG') && WP_DEBUG;
        $wp_info['wp_debug'] = [
            'label'       => 'WP_DEBUG', 
            'value'       => $wp_debug ? 'On (Berisiko)' : 'Off (Selamat)', 
            'recommended' => 'Off', 
            'status'      => $wp_debug ? 'critical' : 'ok', 
            'notes'       => 'Jangan biarkan DEBUG aktif di laman produksi (Live).'
        ];

        // 5. Cron Configuration
        $disable_cron = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $wp_info['wp_cron'] = [
            'label'       => 'WP Cron Status', 
            'value'       => $disable_cron ? 'Disekat' : 'Aktif', 
            'recommended' => 'Aktif (atau ganti dengan Server Cron)', 
            'status'      => 'info', 
            'notes'       => 'Jika "Disekat", pastikan anda setelkan Server Cron (cPanel/CLI).'
        ];

        return $wp_info;
    }
}
