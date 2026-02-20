<?php
/**
 * Modul Audit Database
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class WHC_Audit_Database {

    public function get_info() {
        global $wpdb; 
        $db_info = []; 
        
        // 1. Versi Database
        $db_version = $wpdb->get_var('SELECT VERSION()'); 
        $db_info['db_version'] = [
            'label'       => 'Versi Database',
            'value'       => $db_version,
            'recommended' => 'MySQL 8.0+ / MariaDB 10.6+',
            'status'      => 'info',
            'notes'       => 'Versi baru selalunya lebih pantas dan selamat.'
        ];
        
        // 2. Charset Check
        $db_charset = $wpdb->charset;
        $db_info['db_charset'] = [
            'label'       => 'Database Charset',
            'value'       => $db_charset,
            'recommended' => 'utf8mb4',
            'status'      => ($db_charset === 'utf8mb4') ? 'ok' : 'warning',
            'notes'       => 'utf8mb4 diperlukan untuk sokongan penuh emoji.'
        ];

        // 3. Engine Check (InnoDB)
        $tables_not_innodb = $wpdb->get_results("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND engine != 'InnoDB'");
        $not_innodb_count = count($tables_not_innodb);
        $db_info['db_engine'] = [
            'label'       => 'Enjin Jadual (Engine)',
            'value'       => ($not_innodb_count === 0) ? 'Semua InnoDB' : "$not_innodb_count Jadual Bukan InnoDB",
            'recommended' => 'InnoDB',
            'status'      => ($not_innodb_count === 0) ? 'ok' : 'warning',
            'notes'       => 'InnoDB lebih stabil dan menyokong "row-level locking".'
        ];
        
        // 4. Autoload Size Check
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
            'notes'       => 'Data yang dimuatkan pada setiap halaman. Terlalu besar melambatkan laman.'
        ];

        // 5. Smart Buffer Pool Recommendation
        $options = get_option('whc_server_specs');
        $server_ram = isset($options['total_ram']) ? (int)$options['total_ram'] : 0;
        
        $buffer_pool_size = $wpdb->get_row("SHOW VARIABLES LIKE 'innodb_buffer_pool_size'");
        $current_pool_mb = $buffer_pool_size ? round($buffer_pool_size->Value / 1024 / 1024) : 0;

        $notes = 'Gunakan tetapan di bawah untuk prestasi optimum.';
        if ($server_ram > 0) {
            $recommended_pool = round($server_ram * 1024 * 0.7); // 70% of RAM
            $notes = "Berdasarkan RAM {$server_ram}GB anda, cadangan saiz adalah ~{$recommended_pool}MB.";
        } else {
            $notes = "Sila masukkan jumlah RAM server di bahagian tetapan untuk cadangan pintar.";
        }

        $db_info['buffer_pool'] = [
            'label'       => 'InnoDB Buffer Pool',
            'value'       => $current_pool_mb . ' MB',
            'recommended' => ($server_ram > 0) ? "~{$recommended_pool} MB" : '70-80% RAM',
            'status'      => 'info',
            'notes'       => $notes
        ];
        
        return $db_info;
    }
}
