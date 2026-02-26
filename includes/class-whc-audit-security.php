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
            'notes'       => 'Menggunakan "wp_" memudahkan serangan SQL injection yang disasarkan.',
            'action_desc' => ($prefix === 'wp_') ? 'Gunakan plugin seperti "Brozzme DB Prefix" atau ubah prefix secara manual dalam wp-config.php dan pangkalan data.' : 'Tiada tindakan diperlukan.'
        ];

        // 2. Security Keys (Auth Salt)
        $salts_defined = defined('AUTH_KEY') && AUTH_KEY !== 'put your unique phrase here' && !empty(AUTH_KEY);
        $security_info['security_salts'] = [
            'label'       => 'Kunci Keselamatan (Salts)',
            'value'       => $salts_defined ? 'Telah Ditetapkan' : 'Default / Tiada',
            'recommended' => 'Telah Ditetapkan',
            'status'      => $salts_defined ? 'ok' : 'critical',
            'notes'       => 'Kunci unik membantu menyulitkan kuki pengguna dan sesi login.',
            'action_desc' => (!$salts_defined) ? 'Janakan kunci baru di api.wordpress.org/secret-key/1.1/ dan kemas kini fail wp-config.php anda.' : 'Tiada tindakan diperlukan.'
        ];

        // 3. File Editing Disabled
        $disallow_edit = defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT;
        $security_info['file_editing'] = [
            'label'       => 'Suntingan Fail Dashboard',
            'value'       => $disallow_edit ? 'Disekat (Selamat)' : 'Dibenarkan',
            'recommended' => 'Disekat (Selamat)',
            'status'      => $disallow_edit ? 'ok' : 'warning',
            'notes'       => 'Menutup akses editor di dashboard mengurangkan risiko backdoor.',
            'action_desc' => (!$disallow_edit) ? 'Tambah define("DISALLOW_FILE_EDIT", true); ke dalam fail wp-config.php anda.' : 'Tiada tindakan diperlukan.'
        ];

        // 4. REST API User Enumeration
        $rest_status = $this->check_rest_api_user_exposure();
        
        $status_label = 'Terlindung';
        $status_type  = 'ok';
        $action_desc  = 'Tiada tindakan diperlukan.';

        if ($rest_status === 'exposed') {
            $status_label = 'Terdedah (Bahaya)';
            $status_type  = 'warning';
            $action_desc  = 'Gunakan plugin keselamatan (seperti Wordfence) atau tambah kod dalam functions.php untuk menyekat akses REST API kepada pengguna yang tidak log masuk.';
        } elseif ($rest_status === 'obfuscated') {
            $status_label = 'Obfuscated (Terlindung)';
            $status_type  = 'ok';
            $action_desc  = 'Teknik penyamaran dikesan. Username anda selamat dari imbasan bot.';
        }

        $security_info['rest_api_users'] = [
            'label'       => 'Pendedahan Nama Pengguna',
            'value'       => $status_label,
            'recommended' => 'Terlindung / Obfuscated',
            'status'      => $status_type,
            'notes'       => 'Endpoint /wp-json/wp/v2/users sering digunakan untuk "brute-force" mencari username.',
            'action_desc' => $action_desc
        ];

        // 5. Username "admin" Check
        $admin_exists = username_exists('admin');
        $security_info['admin_user'] = [
            'label'       => 'Nama Pengguna "admin"',
            'value'       => $admin_exists ? 'Ada' : 'Tiada (Selamat)',
            'recommended' => 'Tiada (Selamat)',
            'status'      => $admin_exists ? 'critical' : 'ok',
            'notes'       => '"admin" adalah nama pengguna pertama yang akan dicuba oleh bot.',
            'action_desc' => $admin_exists ? 'Cipta pengguna baru dengan peranan Administrator, log masuk sebagai pengguna tersebut, dan padam pengguna "admin".' : 'Tiada tindakan diperlukan.'
        ];

        // 6. Plugin Integrity Check
        $plugin_integrity = $this->check_plugin_integrity();
        $security_info['plugin_integrity'] = [
            'label'       => 'Integriti Folder Plugin',
            'value'       => $plugin_integrity['status'] === 'ok' ? 'Bersih' : 'Isu Dikesan',
            'recommended' => 'Bersih',
            'status'      => $plugin_integrity['status'],
            'notes'       => $plugin_integrity['message'],
            'action_desc' => $plugin_integrity['status'] !== 'ok' ? 'Semak fail mencurigakan yang disenaraikan melalui FTP/File Manager dan padam jika ia bukan sebahagian daripada plugin yang sah.' : 'Tiada tindakan diperlukan.'
        ];

        // 7. Debug Log Exposure Check
        $debug_exposed = $this->check_debug_log_exposure();
        $security_info['debug_log_exposure'] = [
            'label'       => 'Pendedahan Fail debug.log',
            'value'       => $debug_exposed ? 'Terdedah (Bahaya)' : 'Terlindung / Tiada',
            'recommended' => 'Terlindung / Tiada',
            'status'      => $debug_exposed ? 'critical' : 'ok',
            'notes'       => 'Fail debug.log menyimpan maklumat teknikal ralat yang sensitif.',
            'action_desc' => $debug_exposed ? 'Padam fail debug.log dalam folder wp-content atau sekat akses melalui fail .htaccess.' : 'Tiada tindakan diperlukan.'
        ];

        return $security_info;
    }

    /**
     * Semak jika fail debug.log boleh diakses secara terbuka.
     */
    private function check_debug_log_exposure() {
        $log_url = content_url('debug.log');
        $response = wp_remote_get($log_url, [
            'timeout'   => 5, 
            'sslverify' => false,
            'user-agent' => 'WP-Health-Cockpit-Bot/1.0'
        ]);

        if (is_wp_error($response)) return false;
        return (wp_remote_retrieve_response_code($response) === 200);
    }

    /**
     * Semak jika REST API mendedahkan senarai pengguna secara terbuka.
     * Mengesan perbezaan antara Terdedah, Disekat, atau Di-obfuscate.
     */
    private function check_rest_api_user_exposure() {
        $response = wp_remote_get(get_rest_url(null, 'wp/v2/users'), [
            'timeout'   => 5, 
            'sslverify' => false,
            'user-agent' => 'WP-Health-Cockpit-Bot/1.0'
        ]);

        if (is_wp_error($response)) {
            return 'protected'; // Gagal panggil biasanya maksudnya disekat/firewall
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return 'protected'; // Akses dinafikan (401/403)
        }

        $body = wp_remote_retrieve_body($response);
        $users = json_decode($body, true);

        // Jika pulangkan 200 tapi array kosong, itu obfuscation
        if (!is_array($users) || empty($users)) {
            return 'obfuscated';
        }

        // Semak jika dalam data yang ada, username (slug) terdedah
        foreach ($users as $user) {
            if (isset($user['slug'])) {
                return 'exposed';
            }
        }

        return 'obfuscated';
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
