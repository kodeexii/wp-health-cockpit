<?php
/**
 * Modul Audit Database
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class WHC_Audit_Database {

    public function get_info() {
        global $wpdb; 
        $db_info = []; 
        
        $db_version = $wpdb->get_var('SELECT VERSION()'); 
        $db_charset = $wpdb->charset;
        $db_info['db_version'] = [
            'label'       => 'Versi Database',
            'value'       => $db_version,
            'recommended' => 'MySQL 8.0+ / MariaDB 10.6+',
            'status'      => 'info',
            'notes'       => 'Versi baru selalunya lebih pantas dan selamat.'
        ];
        
        $db_info['db_charset'] = [
            'label'       => 'Database Charset',
            'value'       => $db_charset,
            'recommended' => 'utf8mb4',
            'status'      => ($db_charset === 'utf8mb4') ? 'ok' : 'warning',
            'notes'       => 'utf8mb4 diperlukan untuk sokongan penuh emoji.'
        ];
        
        $autoload_size = $wpdb->get_var("SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload = 'yes'"); 
        $autoload_size_kb = $autoload_size ? round($autoload_size / 1024, 2) : 0;
        $status_autoload = 'ok'; 
        if ($autoload_size_kb > 1024) { $status_autoload = 'critical'; } 
        elseif ($autoload_size_kb > 700) { $status_autoload = 'warning'; }
        
        $db_info['autoload_size'] = [
            'label'       => 'Saiz Autoloaded Data',
            'value'       => $autoload_size_kb . ' KB',
            'recommended' => '< 700 KB',
            'status'      => $status_autoload,
            'notes'       => 'Data yang dimuatkan pada setiap halaman.'
        ];
        
        return $db_info;
    }
}
