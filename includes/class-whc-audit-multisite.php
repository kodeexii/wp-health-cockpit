<?php
/**
 * Modul Audit Multisite
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class WHC_Audit_Multisite {

    public function get_info() {
        if ( ! is_multisite() ) {
            return [];
        }

        global $wpdb;
        $ms_info = [];

        // 1. Network Status & Site Count
        $site_count = get_blog_count();
        $user_count = get_user_count();
        $ms_info['ms_status'] = [
            'label'       => 'Status Network',
            'value'       => "Aktif ($site_count Laman, $user_count Pengguna)",
            'recommended' => 'N/A',
            'status'      => 'info',
            'notes'       => 'Multisite membolehkan pengurusan berpusat untuk banyak laman web.',
            'action_desc' => 'Tiada tindakan diperlukan.'
        ];

        // 2. Sitemeta (Network Settings) Size
        $sitemeta_size = $wpdb->get_var("SELECT SUM(LENGTH(meta_value)) FROM {$wpdb->sitemeta}");
        $sitemeta_kb   = round($sitemeta_size / 1024, 2);
        $ms_info['sitemeta_size'] = [
            'label'       => 'Saiz Network Settings (sitemeta)',
            'value'       => $sitemeta_kb . ' KB',
            'recommended' => '< 500 KB',
            'status'      => ($sitemeta_kb > 500) ? 'warning' : 'ok',
            'notes'       => 'Setting global untuk seluruh network disimpan di sini.',
            'action_desc' => ($sitemeta_kb > 500) ? 'Semak jika ada plugin network-wide yang menyimpan data besar dalam sitemeta.' : 'Tiada tindakan diperlukan.'
        ];

        // 3. Global Tables Size (Users & Usermeta)
        $users_size = $wpdb->get_row("SELECT (data_length + index_length) as size FROM information_schema.TABLES WHERE table_schema = DATABASE() AND table_name = '{$wpdb->users}'");
        $usermeta_size = $wpdb->get_row("SELECT (data_length + index_length) as size FROM information_schema.TABLES WHERE table_schema = DATABASE() AND table_name = '{$wpdb->usermeta}'");
        
        $total_global_mb = round((($users_size->size ?? 0) + ($usermeta_size->size ?? 0)) / 1024 / 1024, 2);
        $ms_info['global_tables'] = [
            'label'       => 'Saiz Data Pengguna Global',
            'value'       => $total_global_mb . ' MB',
            'recommended' => 'N/A',
            'status'      => ($total_global_mb > 50) ? 'warning' : 'ok',
            'notes'       => 'Jadual users dan usermeta dikongsi oleh semua laman dalam network.',
            'action_desc' => ($total_global_mb > 50) ? 'Pertimbangkan untuk membersihkan usermeta yang tidak diperlukan atau membuang pengguna tidak aktif.' : 'Tiada tindakan diperlukan.'
        ];

        // 4. Sub-site Distribution (Health Check)
        $sites_status = $wpdb->get_results("SELECT public, archived, spam, deleted, COUNT(*) as count FROM {$wpdb->blogs} GROUP BY public, archived, spam, deleted", ARRAY_A);
        $status_summary = [];
        foreach ($sites_status as $s) {
            if ($s['spam'] == 1) $status_summary[] = "{$s['count']} Spam";
            if ($s['archived'] == 1) $status_summary[] = "{$s['count']} Archived";
            if ($s['deleted'] == 1) $status_summary[] = "{$s['count']} Deleted";
        }
        $status_text = empty($status_summary) ? 'Semua Aktif/Public' : implode(', ', $status_summary);

        $ms_info['site_distribution'] = [
            'label'       => 'Status Kesihatan Sub-sites',
            'value'       => $status_text,
            'recommended' => 'Minimakan Spam/Deleted',
            'status'      => (strpos($status_text, 'Spam') !== false || strpos($status_text, 'Deleted') !== false) ? 'warning' : 'ok',
            'notes'       => 'Laman yang ditanda sebagai spam atau dipadam masih mengambil ruang database.',
            'action_desc' => 'Gunakan Network Admin untuk memadam secara kekal laman yang tidak lagi diperlukan.'
        ];

        // 5. Largest Sub-sites (Top 5 by DB Size)
        $all_tables = $wpdb->get_results("SELECT table_name, (data_length + index_length) as size FROM information_schema.TABLES WHERE table_schema = DATABASE() AND table_name LIKE '{$wpdb->prefix}%_options'", ARRAY_A);
        
        $subsite_sizes = [];
        foreach ($all_tables as $t) {
            // Extract site ID from table name (e.g. wp_2_options -> 2)
            if (preg_match('/' . $wpdb->prefix . '(\d+)_options/', $t['table_name'], $matches)) {
                $site_id = $matches[1];
                $subsite_sizes[$site_id] = (int)$t['size'];
            }
        }
        arsort($subsite_sizes);
        $top_5 = array_slice($subsite_sizes, 0, 5, true);
        
        $offender_list = '<br><br><strong>Top 5 Sub-sites Terbesar (Size Options):</strong><ul style="margin:5px 0; font-size:0.85em;">';
        foreach ($top_5 as $id => $size) {
            $blog_details = get_blog_details($id);
            $size_kb = round($size / 1024, 2);
            $name = $blog_details ? $blog_details->blogname : "Site ID $id";
            $offender_list .= "<li>ID $id: <code>$name</code> ($size_kb KB)</li>";
        }
        $offender_list .= '</ul>';

        $ms_info['large_subsites'] = [
            'label'       => 'Analisis Beban Sub-site',
            'value'       => 'Lihat Senarai Top 5',
            'recommended' => 'N/A',
            'status'      => 'info',
            'notes'       => 'Mengenal pasti sub-site yang paling banyak menggunakan sumber database.' . $offender_list,
            'action_desc' => 'Lakukan audit individu pada sub-site yang disenaraikan jika ia terlalu berat.'
        ];

        return $ms_info;
    }
}
