<?php
/**
 * Plugin Name:       Simple AVIF & WebP Converter
 * Plugin URI:        https://nuvomo.de/simple-avif-webp-converter
 * Description:       Konvertiert hochgeladene Bilder in AVIF und WebP und liefert sie mit einem Picture-Tag aus. Unterstützt Imagick und GD.
 * Version:           4.6
 * Author:            nuvomo.de {we.love.plugins}
 * Author URI:        https://nuvomo.de
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       simple-avif-webp-converter
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SAWC_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SAWC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SAWC_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Lade alle Klassen
require_once SAWC_PLUGIN_PATH . 'includes/class-sawc-conversion.php';
require_once SAWC_PLUGIN_PATH . 'includes/class-sawc-core.php';
require_once SAWC_PLUGIN_PATH . 'includes/class-sawc-frontend.php';
require_once SAWC_PLUGIN_PATH . 'includes/class-sawc-admin.php';
require_once SAWC_PLUGIN_PATH . 'includes/class-sawc-ajax.php';


// Lade Textdomain für Übersetzungen
add_action('plugins_loaded', 'sawc_load_textdomain');
function sawc_load_textdomain() {
    load_plugin_textdomain('simple-avif-webp-converter', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

// Initialisiere das Plugin
function sawc_init() {
    SAWC_Core::init();
    SAWC_Frontend::init();
    SAWC_Admin::init();
    SAWC_Ajax::init();
}
add_action('plugins_loaded', 'sawc_init');