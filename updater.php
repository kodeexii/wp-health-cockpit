<?php
/**
 * Mat Gem's GitHub Plugin Updater Class
 *
 * @version 0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class MatGem_GitHub_Plugin_Updater {
    private $file;
    private $plugin_data;
    private $github_repo_user;
    private $github_repo_name;
    private $github_api_url;

    public function __construct($file, $github_user, $github_repo) {
        $this->file = $file;
        $this->github_repo_user = $github_user;
        $this->github_repo_name = $github_repo;
        $this->github_api_url = "https://api.github.com/repos/{$github_user}/{$github_repo}/releases/latest";
        
        $this->plugin_data = get_plugin_data($file);

        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_updates']);
    }

    public function check_for_updates($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $response = wp_remote_get($this->github_api_url);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return $transient;
        }

        $release_data = json_decode(wp_remote_retrieve_body($response));

        if (empty($release_data) || !isset($release_data->tag_name)) {
            return $transient;
        }
        
        // --- PENYELESAIAN MUKTAMAD DI SINI ---
        $current_version = $this->plugin_data['Version'];
        $github_version_tag = $release_data->tag_name;

        // 'Bersihkan' tag dari GitHub dengan membuang huruf 'v' di depan (jika ada)
        $clean_github_version = ltrim($github_version_tag, 'v');

        // Lakukan perbandingan pada versi yang telah dibersihkan
        if (version_compare($current_version, $clean_github_version, '<')) {
            
            $obj = new stdClass();
            $obj->slug = plugin_basename($this->file);
            $obj->new_version = $clean_github_version; // Guna versi bersih
            $obj->url = $this->plugin_data['PluginURI'];
            $obj->package = $release_data->zipball_url;
            
            $transient->response[$obj->slug] = $obj;
        }
        // --- TAMAT PENYELESAIAN ---
        
        return $transient;
    }
}
