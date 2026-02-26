<?php
/**
 * Modul Audit PHP
 * Menilai konfigurasi PHP server.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class WHC_Audit_PHP {

    public function get_info() {
        $php_info = [];
        
        // Dapatkan specs server dari settings
        $options = get_option('whc_server_specs');
        $server_ram = isset($options['total_ram']) ? (int)$options['total_ram'] : 0;
        $cpu_cores  = isset($options['cpu_cores']) ? (int)$options['cpu_cores'] : 0;

        // 1. Versi PHP
        $current_php_version = phpversion(); 
        $php_info['php_version'] = [
            'label'       => 'Versi PHP',
            'value'       => $current_php_version,
            'recommended' => '8.2+',
            'status'      => version_compare($current_php_version, '8.2', '>=') ? 'ok' : 'warning',
            'notes'       => 'Versi PHP yang lebih baru adalah lebih laju dan selamat.',
            'action_desc' => version_compare($current_php_version, '8.2', '<') ? 'Kemas kini versi PHP melalui panel kawalan hosting anda (cPanel/CyberPanel/Plesk).' : 'Tiada tindakan diperlukan.'
        ];
        
        // 2. Memory Limit
        $memory_limit = ini_get('memory_limit'); 
        $mem_limit_val = wp_convert_hr_to_bytes($memory_limit) / 1024 / 1024; 
        
        $rec_mem = '256M+';
        if ($server_ram >= 8) $rec_mem = '512M';
        if ($server_ram >= 16) $rec_mem = '1024M';

        $php_info['memory_limit'] = [
            'label'       => 'PHP Memory Limit',
            'value'       => $memory_limit,
            'recommended' => $rec_mem,
            'status'      => $mem_limit_val >= 256 ? 'ok' : 'warning',
            'notes'       => 'Had memori peringkat server. Ini adalah had tertinggi.',
            'action_desc' => $mem_limit_val < 256 ? "Tingkatkan memory_limit dalam fail php.ini atau .htaccess kepada sekurang-kurangnya 256M." : 'Tiada tindakan diperlukan.'
        ];
        
        // 3. Max Execution Time
        $max_execution_time = ini_get('max_execution_time'); 
        $rec_exec = '120s+';
        if ($cpu_cores >= 4) $rec_exec = '300s';

        $php_info['max_execution_time'] = [
            'label'       => 'Max Execution Time',
            'value'       => $max_execution_time . 's',
            'recommended' => $rec_exec,
            'status'      => $max_execution_time >= 120 ? 'ok' : 'warning',
            'notes'       => 'Masa singkat boleh ganggu proses import/export atau backup.',
            'action_desc' => $max_execution_time < 120 ? "Tingkatkan max_execution_time dalam fail php.ini atau .htaccess kepada sekurang-kurangnya 120." : 'Tiada tindakan diperlukan.'
        ];
        
        // 4. OPcache Status
        $opcache_enabled = function_exists('opcache_get_status') && opcache_get_status()['opcache_enabled']; 
        $php_info['opcache'] = [
            'label'       => 'OPcache Status',
            'value'       => $opcache_enabled ? 'Aktif' : 'Tidak Aktif',
            'recommended' => 'Aktif',
            'status'      => $opcache_enabled ? 'ok' : 'critical',
            'notes'       => 'Wajib "On". Ini pemacu utama kelajuan PHP.',
            'action_desc' => (!$opcache_enabled) ? 'Aktifkan OPcache melalui konfigurasi PHP di server untuk meningkatkan prestasi script PHP.' : 'Tiada tindakan diperlukan.'
        ];
        
        // 5. Display Errors
        $display_errors = ini_get('display_errors'); 
        $php_info['display_errors'] = [
            'label'       => 'Display Errors',
            'value'       => $display_errors ? 'On (Berisiko)' : 'Off (Selamat)',
            'recommended' => 'Off',
            'status'      => !$display_errors ? 'ok' : 'critical',
            'notes'       => 'Pastikan "Off" pada laman produksi (Live).',
            'action_desc' => $display_errors ? 'Set display_errors = Off dalam fail php.ini untuk mengelakkan pendedahan kod ralat kepada pelawat.' : 'Tiada tindakan diperlukan.'
        ];
        
        return $php_info;
    }
}
