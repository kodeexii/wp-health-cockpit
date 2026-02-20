<?php
/**
 * Modul Audit PHP
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class WHC_Audit_PHP {

    public function get_info() {
        $php_info = [];
        $current_php_version = phpversion(); 
        $php_info['php_version'] = [
            'label'       => 'Versi PHP',
            'value'       => $current_php_version,
            'recommended' => '8.2+',
            'status'      => version_compare($current_php_version, '8.2', '>=') ? 'ok' : 'warning',
            'notes'       => 'Versi PHP yang lebih baru adalah lebih laju dan selamat.'
        ];
        
        $memory_limit = ini_get('memory_limit'); 
        $mem_limit_val = wp_convert_hr_to_bytes($memory_limit) / 1024 / 1024; 
        $php_info['memory_limit'] = [
            'label'       => 'PHP Memory Limit (Server)',
            'value'       => $memory_limit,
            'recommended' => '256M+',
            'status'      => $mem_limit_val >= 256 ? 'ok' : 'warning',
            'notes'       => 'Had memori peringkat server. Ini adalah had tertinggi.'
        ];
        
        $max_execution_time = ini_get('max_execution_time'); 
        $php_info['max_execution_time'] = [
            'label'       => 'Max Execution Time',
            'value'       => $max_execution_time . 's',
            'recommended' => '120s+',
            'status'      => $max_execution_time >= 120 ? 'ok' : 'warning',
            'notes'       => 'Masa singkat boleh ganggu proses import/export atau backup.'
        ];
        
        $upload_max = ini_get('upload_max_filesize'); 
        $upload_max_val = wp_convert_hr_to_bytes($upload_max) / 1024 / 1024; 
        $php_info['upload_max_filesize'] = [
            'label'       => 'Upload Max Filesize',
            'value'       => $upload_max,
            'recommended' => '64M+',
            'status'      => $upload_max_val >= 64 ? 'ok' : 'warning',
            'notes'       => 'Punca biasa pengguna tak boleh muat naik fail/gambar besar.'
        ];
        
        $opcache_enabled = function_exists('opcache_get_status') && opcache_get_status()['opcache_enabled']; 
        $php_info['opcache'] = [
            'label'       => 'OPcache',
            'value'       => $opcache_enabled ? 'Aktif' : 'Tidak Aktif',
            'recommended' => 'Aktif',
            'status'      => $opcache_enabled ? 'ok' : 'critical',
            'notes'       => 'Wajib "On". Ini 'turbocharger' utama PHP.'
        ];
        
        $display_errors = ini_get('display_errors'); 
        $php_info['display_errors'] = [
            'label'       => 'Display Errors',
            'value'       => $display_errors ? 'On' : 'Off',
            'recommended' => 'Off',
            'status'      => !$display_errors ? 'ok' : 'critical',
            'notes'       => 'Wajib "Off" pada laman produksi.'
        ];
        
        return $php_info;
    }
}
