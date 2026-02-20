<?php
/**
 * Modul Audit Keselamatan
 * Menguruskan imbasan keselamatan asas WordPress.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class WHC_Audit_Security {

    public function get_info() {
        global $wpdb;
        $security_info = [];

        // 1. Database Prefix Check
        $prefix = $wpdb->prefix;
        $security_info['db_prefix'] = [
            'label'       => 'Awalan Database (Prefix)',
            'value'       => $prefix,
            'recommended' => 'Bukan "wp_"',
            'status'      => ($prefix === 'wp_') ? 'warning' : 'ok',
            'notes'       => 'Menggunakan "wp_" memudahkan serangan SQL injection yang disasarkan.'
        ];

        // 2. Security Keys (Auth Salt)
        $salts_defined = defined('AUTH_KEY') && AUTH_KEY !== 'put your unique phrase here' && !empty(AUTH_KEY);
        $security_info['security_salts'] = [
            'label'       => 'Kunci Keselamatan (Salts)',
            'value'       => $salts_defined ? 'Telah Ditetapkan' : 'Default / Tiada',
            'recommended' => 'Telah Ditetapkan',
            'status'      => $salts_defined ? 'ok' : 'critical',
            'notes'       => 'Kunci unik membantu menyulitkan kuki pengguna dan sesi login.'
        ];

        // 3. File Editing Disabled
        $disallow_edit = defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT;
        $security_info['file_editing'] = [
            'label'       => 'Suntingan Fail Dashboard',
            'value'       => $disallow_edit ? 'Disekat (Selamat)' : 'Dibenarkan',
            'recommended' => 'Disekat (Selamat)',
            'status'      => $disallow_edit ? 'ok' : 'warning',
            'notes'       => 'Menutup akses editor di dashboard mengurangkan risiko backdoor.'
        ];

        // 4. REST API User Enumeration
        $rest_api_user_exposure = $this->check_rest_api_user_exposure();
        $security_info['rest_api_users'] = [
            'label'       => 'Pendedahan Nama Pengguna',
            'value'       => $rest_api_user_exposure ? 'Terdedah' : 'Terlindung',
            'recommended' => 'Terlindung / Terhad',
            'status'      => $rest_api_user_exposure ? 'warning' : 'ok',
            'notes'       => 'Endpoint /wp-json/wp/v2/users sering digunakan untuk "brute-force".'
        ];

        // 5. Username "admin" Check
        $admin_exists = username_exists('admin');
        $security_info['admin_user'] = [
            'label'       => 'Nama Pengguna "admin"',
            'value'       => $admin_exists ? 'Ada' : 'Tiada (Selamat)',
            'recommended' => 'Tiada (Selamat)',
            'status'      => $admin_exists ? 'critical' : 'ok',
            'notes'       => '"admin" adalah nama pengguna pertama yang akan dicuba oleh bot.'
        ];

        // 6. Plugin Integrity Check
        $plugin_integrity = $this->check_plugin_integrity();
        $security_info['plugin_integrity'] = [
            'label'       => 'Integriti Folder Plugin',
            'value'       => $plugin_integrity['status'] === 'ok' ? 'Bersih' : 'Isu Dikesan',
            'recommended' => 'Bersih',
            'status'      => $plugin_integrity['status'],
            'notes'       => $plugin_integrity['message']
        ];

        return $security_info;
    }

    /**
     * Semak jika REST API mendedahkan senarai pengguna secara terbuka.
     */
    private function check_rest_api_user_exposure() {
        $response = wp_remote_get(get_rest_url(null, 'wp/v2/users'), ['timeout' => 5, 'sslverify' => false]);
        if (is_wp_error($response)) {
            return false;
        }
        $code = wp_remote_retrieve_response_code($response);
        return ($code === 200);
    }

    /**
     * Semak integriti folder plugin (Cari fail hantu & struktur pelik).
     */
    private function check_plugin_integrity() {
        $plugins_dir = WP_PLUGIN_DIR;
        if (!is_dir($plugins_dir)) return ['status' => 'critical', 'message' => 'Folder plugin tidak dijumpai.'];
        
        $files = scandir($plugins_dir);
        $suspicious_files = [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || $file === 'index.php') continue;
            
            // 1. Fail .php di root /plugins/ (sepatutnya semua dalam folder kecuali index.php)
            // Ada pengecualian untuk hello.php (default WP)
            if (is_file($plugins_dir . '/' . $file) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                if ($file !== 'hello.php') {
                    $suspicious_files[] = $file;
                }
            }
        }

        if (!empty($suspicious_files)) {
             return [
                'status' => 'warning',
                'message' => 'Fail mencurigakan dikesan di root plugin: ' . implode(', ', $suspicious_files) . '.'
            ];
        }

        return [
            'status' => 'ok',
            'message' => 'Struktur folder plugin nampak bersih.'
        ];
    }
}
