<?php
/**
 * Mat Gem's GitHub Plugin Updater Class
 *
 * @version 0.1
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
        
        // Dapatkan data plugin, terutamanya versi semasa
        $this->plugin_data = get_plugin_data($file);

        // Hook ke dalam proses semakan update WordPress
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_updates']);
    }

    public function check_for_updates($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        // Dapatkan data release terkini dari GitHub
        $response = wp_remote_get($this->github_api_url);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return $transient; // Gagal hubungi GitHub, abaikan
        }

        $release_data = json_decode(wp_remote_retrieve_body($response));

        if (empty($release_data) || !isset($release_data->tag_name)) {
            return $transient; // Tiada data release, abaikan
        }
        
        // Bandingkan versi dari GitHub (tag_name) dengan versi semasa
        if (version_compare($this->plugin_data['Version'], $release_data->tag_name, '<')) {
            $obj = new stdClass();
            $obj->slug = plugin_basename($this->file);
            $obj->new_version = $release_data->tag_name;
            $obj->url = $this->plugin_data['PluginURI'];
            $obj->package = $release_data->zipball_url; // URL untuk muat turun fail .zip
            
            $transient->response[$obj->slug] = $obj;
        }
        
        return $transient;
    }
}
