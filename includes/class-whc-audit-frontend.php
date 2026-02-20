<?php
/**
 * Modul Audit Frontend
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class WHC_Audit_Frontend {

    public function get_info($target_url) {
        $frontend_info = [];
        $start_time = microtime(true);
        $response = wp_remote_get($target_url, ['timeout' => 20, 'sslverify' => false]);
        $end_time = microtime(true);
        $ttfb = round(($end_time - $start_time) * 1000);

        $ttfb_status = 'ok';
        if ($ttfb > 600) { $ttfb_status = 'critical'; }
        elseif ($ttfb > 200) { $ttfb_status = 'warning'; }
        
        $frontend_info['ttfb'] = [
            'label'       => 'Masa Respons Server (TTFB)', 
            'value'       => "{$ttfb} ms", 
            'recommended' => '< 200 ms', 
            'status'      => $ttfb_status, 
            'notes'       => 'Masa server mula memulangkan data.'
        ];

        return $frontend_info;
    }
}
