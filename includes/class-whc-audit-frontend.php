<?php
/**
 * Modul Audit Frontend
 * Menguruskan imbasan prestasi muka depan (Frontend).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class WHC_Audit_Frontend {

    /**
     * Menjalankan audit pada URL sasaran.
     * 
     * @param string $target_url URL untuk diaudit.
     * @return array Keputusan audit.
     */
    public function get_info($target_url) {
        $frontend_info = [];
        
        // 1. Ukur TTFB (Time to First Byte)
        $start_time = microtime(true);
        $response = wp_remote_get($target_url, [
            'timeout'     => 20, 
            'sslverify'   => false,
            'user-agent'  => 'WP-Health-Cockpit-Bot/1.0'
        ]);
        $end_time = microtime(true);
        
        if ( is_wp_error($response) ) {
            return [
                'error' => [
                    'label' => 'Ralat Imbasan',
                    'value' => 'Gagal menghubungi URL',
                    'status' => 'critical',
                    'notes' => $response->get_error_message()
                ]
            ];
        }

        $ttfb = round(($end_time - $start_time) * 1000);
        $body = wp_remote_retrieve_body($response);
        
        // 2. Kira Saiz HTML
        $html_size_bytes = strlen($body);
        $html_size_kb    = round($html_size_bytes / 1024, 2);

        // 3. Kira Aset Statik (CSS & JS)
        // Gunakan regex untuk cari tag link stylesheet dan script
        $css_count = preg_match_all('/<link[^>]+rel=["\']stylesheet["\'][^>]*>/i', $body);
        $js_count  = preg_match_all('/<script[^>]*src=["\'][^"\' ]+["\'][^>]*>/i', $body);

        // --- Susun Data Keputusan ---

        // TTFB Status
        $ttfb_status = 'ok';
        if ($ttfb > 600) { $ttfb_status = 'critical'; }
        elseif ($ttfb > 250) { $ttfb_status = 'warning'; }

        $frontend_info['ttfb'] = [
            'label'       => 'Masa Respons Server (TTFB)',
            'value'       => $ttfb . ' ms',
            'recommended' => '< 200 ms',
            'status'      => $ttfb_status,
            'notes'       => 'Masa server mula memulangkan data (Backend performance).'
        ];

        // HTML Size Status
        $size_status = 'ok';
        if ($html_size_kb > 200) { $size_status = 'critical'; }
        elseif ($html_size_kb > 100) { $size_status = 'warning'; }

        $frontend_info['html_size'] = [
            'label'       => 'Saiz Dokumen HTML',
            'value'       => $html_size_kb . ' KB',
            'recommended' => '< 100 KB',
            'status'      => $size_status,
            'notes'       => 'Saiz kod HTML sahaja (tidak termasuk gambar/aset luar).'
        ];

        // Assets Status (Hanya amaran jika terlalu banyak)
        $assets_total = $css_count + $js_count;
        $assets_status = 'ok';
        if ($assets_total > 50) { $assets_status = 'critical'; }
        elseif ($assets_total > 30) { $assets_status = 'warning'; }

        $frontend_info['static_assets'] = [
            'label'       => 'Bilangan Aset (CSS/JS)',
            'value'       => "$css_count CSS / $js_count JS",
            'recommended' => '< 30 Fail',
            'status'      => $assets_status,
            'notes'       => 'Terlalu banyak request boleh melambatkan render halaman.'
        ];

        return $frontend_info;
    }
}
