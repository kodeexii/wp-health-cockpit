<?php
/**
 * Modul Audit WordPress
 * Menilai konfigurasi teras (Core) WordPress.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class WHC_Audit_WP {

    public function get_info() {
        $wp_info = [];
        $options = get_option('whc_server_specs');
        $project_type = isset($options['project_type']) ? $options['project_type'] : 'blog';
        $traffic      = isset($options['traffic_level']) ? $options['traffic_level'] : 'low';

        // 1. WP_MEMORY_LIMIT Check
        $wp_mem_limit_val = defined('WP_MEMORY_LIMIT') ? constant('WP_MEMORY_LIMIT') : '40M';
        $mem_int = (int)$wp_mem_limit_val;
        
        $rec_mem = 256;
        if ($project_type === 'ecommerce' || $project_type === 'lms') $rec_mem = 512;
        
        $wp_info['wp_memory_limit'] = [
            'label'       => 'WP_MEMORY_LIMIT', 
            'value'       => $wp_mem_limit_val, 
            'recommended' => $rec_mem . 'M', 
            'status'      => ($mem_int >= $rec_mem) ? 'ok' : 'warning', 
            'notes'       => ($project_type === 'ecommerce') ? 'Projek E-commerce memerlukan RAM lebih tinggi untuk kelancaran Checkout.' : 'Had memori untuk operasi frontend.'
        ];
        
        // 2. Object Cache Check
        $is_object_cache_persistent = function_exists('wp_using_ext_object_cache') ? wp_using_ext_object_cache() : false;
        $cache_status = 'ok';
        if (!$is_object_cache_persistent && ($traffic === 'high' || $project_type === 'ecommerce')) {
            $cache_status = 'critical';
        } elseif (!$is_object_cache_persistent) {
            $cache_status = 'warning';
        }

        $wp_info['object_cache'] = [
            'label'       => 'Object Cache Kekal', 
            'value'       => $is_object_cache_persistent ? 'Aktif' : 'Tidak Aktif', 
            'recommended' => 'Aktif (Redis/Memcached)', 
            'status'      => $cache_status, 
            'notes'       => ($traffic === 'high') ? 'Wajib aktif untuk trafik tinggi bagi mengelakkan database "crash".' : 'Sangat kritikal untuk prestasi laman yang dinamik.'
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

        return $wp_info;
    }
}
