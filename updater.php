<?php
/**
 * Mat Gem's GitHub Plugin Updater Class
 *
 * @version 0.2.3
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
        
        // --- HOOK BARU UNTUK BETULKAN NAMA FOLDER ---
        add_filter('upgrader_source_selection', [$this, 'rename_github_zip'], 10, 4);
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
        
        $current_version = $this->plugin_data['Version'];
        $github_version_tag = $release_data->tag_name;
        $clean_github_version = ltrim($github_version_tag, 'v');

        if (version_compare($current_version, $clean_github_version, '<')) {
            $obj = new stdClass();
            $obj->slug = plugin_basename($this->file);
            $obj->new_version = $clean_github_version;
            $obj->url = $this->plugin_data['PluginURI'];
            $obj->package = $release_data->zipball_url;
            
            $transient->response[$obj->slug] = $obj;
        }
        
        return $transient;
    }

    /**
     * Fungsi 'Posmen Bijak'. Ia menamakan semula folder dari GitHub
     * supaya sepadan dengan nama folder plugin kita.
     */
    public function rename_github_zip($source, $remote_source, $upgrader, $hook_extra = null) {
        // Pastikan ia adalah plugin kita yang sedang dikemas kini
        if ( isset($hook_extra['plugin']) && $hook_extra['plugin'] === plugin_basename($this->file) ) {
            
            // Nama folder baru yang kita mahu
            $new_source = trailingslashit($remote_source) . $this->github_repo_name;
            
            // Namakan semula folder
            rename($source, $new_source);
            
            return $new_source;
        }

        return $source;
    }
}
