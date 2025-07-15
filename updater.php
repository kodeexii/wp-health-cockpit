<?php
/**
 * Mat Gem's GitHub Plugin Updater Class - Versi Lasak
 *
 * @version 0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class MatGem_GitHub_Plugin_Updater {
    private $file;
    private $plugin_slug;
    private $plugin_data;
    private $github_repo_user;
    private $github_repo_name;
    private $github_api_url;

    public function __construct($file, $github_user, $github_repo) {
        $this->file = $file;
        $this->plugin_slug = plugin_basename($file);
        $this->github_repo_user = $github_user;
        $this->github_repo_name = $github_repo;
        $this->github_api_url = "https://api.github.com/repos/{$github_user}/{$github_repo}/releases/latest";
        
        $this->plugin_data = get_plugin_data($file);

        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_updates']);
        add_filter('upgrader_source_selection', [$this, 'rename_github_zip'], 10, 3);
    }

    public function check_for_updates($transient) {
        if (empty($transient->checked)) return $transient;

        $response = wp_remote_get($this->github_api_url);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) return $transient;

        $release_data = json_decode(wp_remote_retrieve_body($response));

        if (empty($release_data) || !isset($release_data->tag_name)) return $transient;
        
        $current_version = $this->plugin_data['Version'];
        $clean_github_version = ltrim($release_data->tag_name, 'v');

        if (version_compare($current_version, $clean_github_version, '<')) {
            $obj = new stdClass();
            $obj->slug = $this->plugin_slug;
            $obj->new_version = $clean_github_version;
            $obj->url = $this->plugin_data['PluginURI'];
            $obj->package = $release_data->zipball_url;
            $transient->response[$obj->slug] = $obj;
        }
        
        return $transient;
    }

    public function rename_github_zip($source, $remote_source, $upgrader) {
        // Semak jika ia adalah plugin kita
        // Cara yang lebih lasak dari sebelum ini
        if (isset($upgrader->skin->plugin) && $upgrader->skin->plugin === $this->plugin_slug) {
            
            // Nama folder baru yang kita mahu (nama repo)
            $new_source = trailingslashit($remote_source) . $this->github_repo_name;
            
            // Namakan semula folder
            if (rename($source, $new_source)) {
                return $new_source;
            }
        }

        return $source;
    }
}
