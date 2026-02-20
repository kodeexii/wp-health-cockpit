<?php
/**
 * Modul Audit Kitaran Hayat Plugin
 * Menilai status dan risiko plugin yang dipasang.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class WHC_Audit_Plugins {

    public function get_info() {
        if ( ! function_exists('get_plugins') ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins');
        $plugin_info = [];

        // 1. Plugins with Updates Available
        $update_plugins = get_site_transient('update_plugins');
        $updates_count = isset($update_plugins->response) ? count($update_plugins->response) : 0;
        $plugin_info['updates_available'] = [
            'label'       => 'Kemas Kini Tersedia',
            'value'       => $updates_count . ' Plugin',
            'recommended' => '0 Plugin',
            'status'      => ($updates_count > 0) ? 'warning' : 'ok',
            'notes'       => 'Sentiasa pastikan plugin anda adalah versi terkini untuk keselamatan.'
        ];

        // 2. Inactive Plugins (Should be removed)
        $inactive_count = count($all_plugins) - count($active_plugins);
        $plugin_info['inactive_plugins'] = [
            'label'       => 'Plugin Tidak Aktif',
            'value'       => $inactive_count . ' Plugin',
            'recommended' => '0 Plugin',
            'status'      => ($inactive_count > 0) ? 'warning' : 'ok',
            'notes'       => 'Plugin tidak aktif tetap menjadi risiko keselamatan. Lebih baik dibuang.'
        ];

        // 3. Abandoned Plugins (Not updated in 2+ years)
        $abandoned_count = $this->check_abandoned_plugins($all_plugins);
        $plugin_info['abandoned_plugins'] = [
            'label'       => 'Plugin Terbiar (Abandoned)',
            'value'       => $abandoned_count . ' Plugin',
            'recommended' => '0 Plugin',
            'status'      => ($abandoned_count > 0) ? 'critical' : 'ok',
            'notes'       => 'Plugin yang tidak dikemas kini melebihi 2 tahun mungkin tidak serasi dengan versi WordPress terkini.'
        ];

        return $plugin_info;
    }

    /**
     * Semak plugin yang tidak dikemas kini oleh pembangun selama 2 tahun+.
     */
    private function check_abandoned_plugins($all_plugins) {
        $abandoned_count = 0;
        $two_years_ago = strtotime('-2 years');

        foreach ($all_plugins as $path => $data) {
            $slug = dirname($path);
            if ($slug === '.' || empty($slug)) continue;

            // Dapatkan info dari WordPress.org API (Cache transient untuk kelajuan)
            $transient_key = 'whc_plugin_info_' . md5($slug);
            $info = get_transient($transient_key);

            if ( false === $info ) {
                $response = wp_remote_get('https://api.wordpress.org/plugins/info/1.0/' . $slug . '.json');
                if ( ! is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200 ) {
                    $info = json_decode(wp_remote_retrieve_body($response), true);
                    set_transient($transient_key, $info, WEEK_IN_SECONDS);
                }
            }

            if ( isset($info['last_updated']) ) {
                $last_updated = strtotime($info['last_updated']);
                if ( $last_updated < $two_years_ago ) {
                    $abandoned_count++;
                }
            }
        }

        return $abandoned_count;
    }
}
