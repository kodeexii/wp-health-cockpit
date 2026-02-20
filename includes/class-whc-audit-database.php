<?php
/**
 * Modul Audit Database
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class WHC_Audit_Database {

    public function get_info() {
        global $wpdb; 
        $db_info = []; 
        $options = get_option('whc_server_specs');
        $server_ram  = isset($options['total_ram']) ? (int)$options['total_ram'] : 0;
        $storage     = isset($options['storage_type']) ? $options['storage_type'] : 'ssd';
        $project_type = isset($options['project_type']) ? $options['project_type'] : 'blog';

        // 1. Versi Database
        $db_version = $wpdb->get_var('SELECT VERSION()'); 
        $db_info['db_version'] = [
            'label'       => 'Versi Database',
            'value'       => $db_version,
            'recommended' => 'MySQL 8.0+ / MariaDB 10.6+',
            'status'      => 'info',
            'notes'       => 'Versi baru selalunya lebih pantas dan selamat.'
        ];
        
        // 2. Storage Type Status (Baru!)
        $storage_label = [
            'hdd'  => 'HDD (Perlahan)',
            'ssd'  => 'SSD (Standard)',
            'nvme' => 'NVMe (Pantas)'
        ];
        $storage_status = [
            'hdd'  => 'critical',
            'ssd'  => 'ok',
            'nvme' => 'ok'
        ];
        $db_info['storage_type'] = [
            'label'       => 'Jenis Storage Server',
            'value'       => isset($storage_label[$storage]) ? $storage_label[$storage] : 'SSD',
            'recommended' => 'SSD / NVMe',
            'status'      => isset($storage_status[$storage]) ? $storage_status[$storage] : 'ok',
            'notes'       => ($storage === 'hdd') ? 'HDD menyebabkan I/O perlahan untuk database besar.' : 'Pilihan yang baik untuk I/O pangkalan data.'
        ];

        // 3. Engine Check (InnoDB)
        $tables_not_innodb = $wpdb->get_results("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND engine != 'InnoDB'");
        $not_innodb_count = count($tables_not_innodb);
        $db_info['db_engine'] = [
            'label'       => 'Enjin Jadual (Engine)',
            'value'       => ($not_innodb_count === 0) ? 'Semua InnoDB' : "$not_innodb_count Bukan InnoDB",
            'recommended' => 'InnoDB',
            'status'      => ($not_innodb_count === 0) ? 'ok' : 'warning',
            'notes'       => 'InnoDB lebih stabil dan efisien.'
        ];
        
        // 4. Autoload Size Check (Dah Update 'on/yes' logic)
        $autoload_query = $wpdb->get_row("SELECT COUNT(*) as count, SUM(LENGTH(option_value)) as size FROM {$wpdb->options} WHERE autoload IN ('yes', 'on')");
        $autoload_count   = $autoload_query->count ? (int)$autoload_query->count : 0;
        $autoload_size    = $autoload_query->size ? $autoload_query->size : 0;
        $autoload_size_kb = round($autoload_size / 1024, 2);
        
        $rec_autoload = 700;
        if ($project_type === 'ecommerce' || $project_type === 'lms') $rec_autoload = 1024; // Bertimbang rasa sikit untuk projek besar

        $status_autoload = 'ok'; 
        if ($autoload_size_kb > ($rec_autoload * 1.5)) { $status_autoload = 'critical'; } 
        elseif ($autoload_size_kb > $rec_autoload) { $status_autoload = 'warning'; }
        
        $db_info['autoload_size'] = [
            'label'       => 'Saiz Autoloaded Data',
            'value'       => $autoload_size_kb . " KB ($autoload_count Options)",
            'recommended' => "< $rec_autoload KB",
            'status'      => $status_autoload,
            'notes'       => 'Data yang dimuatkan secara automatik pada setiap request halaman.'
        ];

        // 5. Smart Buffer Pool Recommendation
        $buffer_pool_size = $wpdb->get_row("SHOW VARIABLES LIKE 'innodb_buffer_pool_size'");
        $current_pool_mb = $buffer_pool_size ? round($buffer_pool_size->Value / 1024 / 1024) : 0;

        if ($server_ram > 0) {
            $recommended_pool = round($server_ram * 1024 * 0.7); // 70% of RAM
            $notes = "Berdasarkan RAM {$server_ram}GB anda, cadangan saiz adalah ~{$recommended_pool}MB.";
        } else {
            $notes = "Masukkan RAM server untuk mendapatkan cadangan buffer pool.";
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
