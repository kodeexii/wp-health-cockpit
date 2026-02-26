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
        $server_ram   = isset($options['total_ram']) ? (int)$options['total_ram'] : 0;
        $project_type = isset($options['project_type']) ? $options['project_type'] : 'blog';
        $traffic      = isset($options['traffic_level']) ? $options['traffic_level'] : 'low';

        // 1. WP_MEMORY_LIMIT Check
        $wp_mem_limit_val = defined('WP_MEMORY_LIMIT') ? constant('WP_MEMORY_LIMIT') : '40M';
        $mem_int = (int)$wp_mem_limit_val;
        
        // --- Logic: Project Demand ---
        $rec_mem = ($project_type === 'ecommerce' || $project_type === 'lms' || $traffic === 'high') ? 256 : 128;
        
        $wp_info['wp_memory_limit'] = [
            'label'       => 'WP_MEMORY_LIMIT', 
            'value'       => $wp_mem_limit_val, 
            'recommended' => $rec_mem . 'M', 
            'status'      => ($mem_int >= $rec_mem) ? 'ok' : 'warning', 
            'notes'       => 'Had memori untuk operasi frontend.',
            'action_desc' => ($mem_int < $rec_mem) ? "Tambah define('WP_MEMORY_LIMIT', '{$rec_mem}M'); ke dalam fail wp-config.php anda." : 'Tiada tindakan diperlukan.'
        ];

        // 1b. WP_MAX_MEMORY_LIMIT Check (Backend/Admin)
        $wp_max_mem_limit_val = defined('WP_MAX_MEMORY_LIMIT') ? constant('WP_MAX_MEMORY_LIMIT') : '256M';
        $max_mem_int = (int)$wp_max_mem_limit_val;

        // --- Logic: Project Demand ---
        $rec_max_mem = ($project_type === 'ecommerce' || $project_type === 'lms' || $traffic === 'high') ? 512 : 256;

        $wp_info['wp_max_memory_limit'] = [
            'label'       => 'WP_MAX_MEMORY_LIMIT', 
            'value'       => $wp_max_mem_limit_val, 
            'recommended' => $rec_max_mem . 'M', 
            'status'      => ($max_mem_int >= $rec_max_mem) ? 'ok' : 'warning', 
            'notes'       => 'Had memori khusus untuk operasi di ruangan Admin (Backend) dan pemprosesan imej.',
            'action_desc' => ($max_mem_int < $rec_max_mem) ? "Tambah define('WP_MAX_MEMORY_LIMIT', '{$rec_max_mem}M'); ke dalam fail wp-config.php anda." : 'Tiada tindakan diperlukan.'
        ];

        // 1c. Kecukupan Hardware Check (Updated: RAM & CPU)
        $cpu_cores = isset($options['cpu_cores']) ? (int)$options['cpu_cores'] : 0;
        $is_heavy_load = ($project_type === 'ecommerce' || $project_type === 'lms' || $traffic === 'high');
        
        $hardware_status = 'ok';
        $hardware_value  = "{$server_ram}GB RAM / {$cpu_cores} Core";
        $hardware_notes  = 'Spesifikasi server mencukupi untuk profil projek anda.';
        $action_desc     = 'Tiada tindakan diperlukan.';

        if ($is_heavy_load) {
            if ($server_ram > 0 && $server_ram < 2 || ($cpu_cores > 0 && $cpu_cores < 2)) {
                $hardware_status = 'critical';
                $hardware_notes  = 'Spesifikasi server terlalu rendah untuk profil berat (LMS/E-commerce/Trafik Tinggi).';
                $action_desc     = 'Naik taraf pelan hosting ke sekurang-kurangnya 2GB RAM dan 2 Core CPU untuk prestasi yang stabil.';
            }
        }

        if ($server_ram > 0) {
            $wp_info['hardware_adequacy'] = [
                'label'       => 'Kecukupan Hardware', 
                'value'       => $hardware_value, 
                'recommended' => $is_heavy_load ? '2GB / 2 Core+' : '1GB / 1 Core+', 
                'status'      => $hardware_status, 
                'notes'       => $hardware_notes,
                'action_desc' => $action_desc
            ];
        }
        
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
            'notes'       => ($traffic === 'high') ? 'Wajib aktif untuk trafik tinggi bagi mengelakkan database "crash".' : 'Sangat kritikal untuk prestasi laman yang dinamik.',
            'action_desc' => (!$is_object_cache_persistent) ? 'Hubungi pihak hosting untuk mengaktifkan Redis atau Memcached, kemudian pasang plugin Object Cache yang berkaitan.' : 'Tiada tindakan diperlukan.'
        ];

        // 3. Post Revision Check
        $revisions = defined('WP_POST_REVISIONS') ? constant('WP_POST_REVISIONS') : true;
        $rev_val = ($revisions === true) ? 'Tak Terhad' : ($revisions === false ? '0' : $revisions);
        $wp_info['post_revisions'] = [
            'label'       => 'Revisi Pos', 
            'value'       => $rev_val, 
            'recommended' => '3 - 5', 
            'status'      => ($revisions === true || (int)$revisions > 10) ? 'warning' : 'ok', 
            'notes'       => 'Mengehadkan revisi membantu mengelakkan pangkalan data dari membengkak.',
            'action_desc' => ($revisions === true || (int)$revisions > 10) ? "Tambah define('WP_POST_REVISIONS', 5); ke dalam fail wp-config.php anda." : 'Tiada tindakan diperlukan.'
        ];

        // 4. WP_DEBUG Status
        $wp_debug = defined('WP_DEBUG') && WP_DEBUG;
        $wp_info['wp_debug'] = [
            'label'       => 'WP_DEBUG', 
            'value'       => $wp_debug ? 'On (Berisiko)' : 'Off (Selamat)', 
            'recommended' => 'Off', 
            'status'      => $wp_debug ? 'critical' : 'ok', 
            'notes'       => 'Jangan biarkan DEBUG aktif di laman produksi (Live).',
            'action_desc' => $wp_debug ? "Tukar define('WP_DEBUG', true); kepada false dalam fail wp-config.php anda." : 'Tiada tindakan diperlukan.'
        ];

        // 5. SSL & HTTPS Check
        $is_ssl = is_ssl();
        $force_ssl_admin = defined('FORCE_SSL_ADMIN') && FORCE_SSL_ADMIN;
        $ssl_status = ($is_ssl && $force_ssl_admin) ? 'ok' : 'warning';
        
        $wp_info['ssl_status'] = [
            'label'       => 'Status SSL/HTTPS', 
            'value'       => $is_ssl ? 'Aktif' : 'Tiada HTTPS', 
            'recommended' => 'Aktif & Dipaksa', 
            'status'      => $ssl_status, 
            'notes'       => 'Melindungi data komunikasi antara browser dan server.',
            'action_desc' => (!$is_ssl || !$force_ssl_admin) ? "Pastikan sijil SSL dipasang dan tambah define('FORCE_SSL_ADMIN', true); dalam wp-config.php." : 'Tiada tindakan diperlukan.'
        ];

        // 6. WP-Cron Status
        $disable_wp_cron = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $cron_status = ($traffic === 'high' && !$disable_wp_cron) ? 'warning' : 'ok';

        $wp_info['cron_status'] = [
            'label'       => 'Sistem Penjadualan (Cron)', 
            'value'       => $disable_wp_cron ? 'System Cron (Laju)' : 'WP-Cron (Default)', 
            'recommended' => ($traffic === 'high') ? 'System Cron' : 'Mana-mana', 
            'status'      => $cron_status, 
            'notes'       => 'System Cron mengurangkan beban pemuatan halaman berbanding WP-Cron.',
            'action_desc' => ($traffic === 'high' && !$disable_wp_cron) ? "Tambah define('DISABLE_WP_CRON', true); dan setkan cron job di peringkat server (Control Panel)." : 'Tiada tindakan diperlukan.'
        ];

        return $wp_info;
    }
}
