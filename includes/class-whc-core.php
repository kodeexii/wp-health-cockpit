<?php
/**
 * Kelas WHC_Core
 * Menguruskan pemuatan modul dan bootstrap plugin.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class WHC_Core {

    public function __construct() {
        $this->load_dependencies();
        $this->init_modules();
    }

    /**
     * Memuatkan fail-fail kelas yang diperlukan.
     */
    private function load_dependencies() {
        $dir = plugin_dir_path(__FILE__);
        require_once $dir . 'class-whc-audit-php.php';
        require_once $dir . 'class-whc-audit-database.php';
        require_once $dir . 'class-whc-audit-wp.php';
        require_once $dir . 'class-whc-audit-frontend.php';
        require_once $dir . 'class-whc-audit-security.php';
        require_once $dir . 'class-whc-audit-plugins.php';
        require_once $dir . 'class-whc-admin.php';
    }

    /**
     * Memulakan modul-modul plugin.
     */
    private function init_modules() {
        // Mulakan Admin UI
        new WHC_Admin();

        // Auto-Updater (Optional)
        add_action('plugins_loaded', [ $this, 'init_updater' ]);
    }

    public function init_updater() {
        $vendor_puc = dirname(dirname(__FILE__)) . '/vendor/plugin-update-checker/plugin-update-checker.php';
        if ( file_exists($vendor_puc) ) {
            require_once $vendor_puc;
            \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker( 
                'https://github.com/kodeexii/wp-health-cockpit/', 
                dirname(dirname(__FILE__)) . '/wp-health-cockpit.php', 
                'wp-health-cockpit' 
            );
        }
    }
}
