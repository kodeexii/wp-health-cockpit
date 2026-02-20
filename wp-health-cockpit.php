<?php
/**
 * Plugin Name:       WP Health Cockpit
 * Description:       Plugin diagnostik ringan dengan senibina modular (OOP).
 * Version:           1.9.1
 * Author:            Hadee Roslan & Mat Gem
 * Author URI:        https://hadeeroslan.my/
 * GitHub Plugin URI: kodeexii/wp-health-cockpit
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Takrifkan konstanta plugin
define( 'WHC_VERSION', '1.9.1' );
define( 'WHC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Memuatkan Bootstrap Utama
require_once WHC_PLUGIN_DIR . 'includes/class-whc-core.php';

/**
 * Memulakan Plugin
 */
function whc_init_plugin() {
    new WHC_Core();
}
whc_init_plugin();
