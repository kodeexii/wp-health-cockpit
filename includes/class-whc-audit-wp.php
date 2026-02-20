<?php
/**
 * Modul Audit WordPress
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class WHC_Audit_WP {

    public function get_info() {
        $wp_info = [];
        
        $wp_mem_limit_val = defined('WP_MEMORY_LIMIT') ? constant('WP_MEMORY_LIMIT') : 'Default (40M)';
        $wp_info['wp_memory_limit'] = [
            'label'       => 'WP_MEMORY_LIMIT', 
            'value'       => $wp_mem_limit_val, 
            'recommended' => '256M', 
            'status'      => 'info', 
            'notes'       => 'Had memori untuk operasi frontend.'
        ];
        
        $is_object_cache_persistent = function_exists('wp_using_ext_object_cache') ? wp_using_ext_object_cache() : false;
        $wp_info['object_cache'] = [
            'label'       => 'Object Cache Kekal', 
            'value'       => $is_object_cache_persistent ? 'Aktif' : 'Tidak Aktif', 
            'recommended' => 'Aktif (Redis/Memcached)', 
            'status'      => $is_object_cache_persistent ? 'ok' : 'critical', 
            'notes'       => 'Sangat kritikal untuk prestasi laman dinamik.'
        ];

        return $wp_info;
    }
}
