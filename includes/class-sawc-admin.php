<?php
if (!defined('ABSPATH')) {
    exit;
}

class SAWC_Admin {
    
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
        add_action('admin_init', [__CLASS__, 'handle_settings_save']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_scripts']);
        add_action('nuvomo_dashboard_register_plugin', [__CLASS__, 'register_dashboard_card']);
    }

    public static function add_admin_menu() {
        add_menu_page(
            __('nuvomo Dashboard', 'simple-avif-webp-converter'),
            'nuvomo',
            'manage_options',
            'nuvomo-dashboard',
            [__CLASS__, 'nuvomo_dashboard_page_html'],
            'dashicons-cloud',
            60
        );

        add_submenu_page(
            'nuvomo-dashboard',
            __('Dashboard', 'simple-avif-webp-converter'),
            __('Dashboard', 'simple-avif-webp-converter'),
            'manage_options',
            'nuvomo-dashboard',
            [__CLASS__, 'nuvomo_dashboard_page_html']
        );

        add_submenu_page(
            'nuvomo-dashboard',
            __('AVIF/WebP Converter', 'simple-avif-webp-converter'),
            __('AVIF/WebP Converter', 'simple-avif-webp-converter'),
            'manage_options',
            'simple-avif-webp-converter',
            [__CLASS__, 'admin_page_html']
        );
    }

    public static function nuvomo_dashboard_page_html() {
        ?>
        <div class="wrap nuvomo-dashboard">
            <h1><?php esc_html_e('nuvomo Dashboard', 'simple-avif-webp-converter'); ?></h1>
            <p><?php esc_html_e('Verwalten Sie hier alle Ihre nuvomo-Plugins an einem zentralen Ort.', 'simple-avif-webp-converter'); ?></p>
            <div class="nuvomo-plugin-list">
                <?php do_action('nuvomo_dashboard_register_plugin'); ?>
            </div>
        </div>
        <?php
    }

    public static function register_dashboard_card() {
        $settings_link = admin_url('admin.php?page=simple-avif-webp-converter');
        ?>
        <style>
            .nuvomo-plugin-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; margin-top: 20px;}
            .nuvomo-card { background: #fff; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04); padding: 20px; }
            .nuvomo-card h2 { margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #eee; }
            .nuvomo-card .actions { margin-top: 20px; }
        </style>
        <div class="nuvomo-card">
            <h2><?php esc_html_e('Simple AVIF/WebP Converter', 'simple-avif-webp-converter'); ?></h2>
            <p><?php esc_html_e('Konvertiert Bilder automatisch in moderne Formate und beschleunigt Ihre Webseite.', 'simple-avif-webp-converter'); ?></p>
            <div class="actions">
                <a href="<?php echo esc_url($settings_link); ?>" class="button button-primary">
                    <?php esc_html_e('Einstellungen', 'simple-avif-webp-converter'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    public static function handle_settings_save() {
        if (!isset($_POST['sawc_save_settings_nonce'])) {
            return;
        }
        check_admin_referer('sawc_save_settings_action', 'sawc_save_settings_nonce');
        
        if (!current_user_can('manage_options')) {
            // FIX: Verwende esc_html__() innerhalb von wp_die(), um den Fehler zu beheben.
            wp_die(esc_html__('Sie haben keine Berechtigung, diese Einstellungen zu speichern.', 'simple-avif-webp-converter'));
        }
        
        $debug_mode_value   = isset($_POST['sawc_debug_mode']) ? 'on' : 'off';
        $auto_convert_value = isset($_POST['sawc_auto_convert_on_upload']) ? 'on' : 'off';
        
        update_option('sawc_debug_mode', $debug_mode_value);
        update_option('sawc_auto_convert_on_upload', $auto_convert_value);

        if (isset($_POST['sawc_conversion_library'])) {
            $library = sanitize_text_field(wp_unslash($_POST['sawc_conversion_library']));
            if (in_array($library, ['imagick', 'gd'])) {
                update_option('sawc_conversion_library', $library);
            }
        }
        if (isset($_POST['sawc_quality_webp'])) {
            $webp_quality = max(1, min(100, intval(wp_unslash($_POST['sawc_quality_webp']))));
            update_option('sawc_quality_webp', $webp_quality);
        }
        if (isset($_POST['sawc_quality_avif'])) {
            $avif_quality = max(0, min(63, intval(wp_unslash($_POST['sawc_quality_avif']))));
            update_option('sawc_quality_avif', $avif_quality);
        }
        
        add_action('admin_notices', function () {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Einstellungen gespeichert!', 'simple-avif-webp-converter') . '</p></div>';
        });
    }

    private static function get_active_library() {
        $imagick_available = class_exists('Imagick');
        $gd_available      = function_exists('gd_info');
        $saved_lib         = get_option('sawc_conversion_library');

        if ($saved_lib && (($saved_lib === 'imagick' && $imagick_available) || ($saved_lib === 'gd' && $gd_available))) {
            return $saved_lib;
        }
        if ($imagick_available) {
            return 'imagick';
        }
        if ($gd_available) {
            return 'gd';
        }
        return 'none';
    }

    public static function enqueue_admin_scripts($hook) {
        $hook_converter = 'nuvomo_page_simple-avif-webp-converter';
        $hook_dashboard = 'toplevel_page_nuvomo-dashboard';

        if ($hook !== $hook_converter && $hook !== $hook_dashboard) {
            return;
        }
        
        wp_enqueue_style('sawc-admin-style', SAWC_PLUGIN_URL . 'css/admin-style.css', [], '4.6');
        
        if ($hook === $hook_converter) {
            wp_enqueue_script('sawc-admin-script', SAWC_PLUGIN_URL . 'js/admin-script.js', ['jquery'], '4.6', true);

            $active_lib     = self::get_active_library();
            $avif_supported = false;
            if ($active_lib === 'imagick' && class_exists('Imagick') && in_array('AVIF', Imagick::queryFormats())) {
                $avif_supported = true;
            } elseif ($active_lib === 'gd' && function_exists('imageavif')) {
                $avif_supported = true;
            }

            wp_localize_script('sawc-admin-script', 'sawc_ajax', [
                'ajax_url'       => admin_url('admin-ajax.php'),
                'nonce'          => wp_create_nonce('sawc_bulk_nonce'),
                'scan_action'    => 'sawc_scan_library',
                'process_action' => 'sawc_bulk_process',
                'debug_mode'     => get_option('sawc_debug_mode', 'off') === 'on',
                'avif_supported' => $avif_supported,
                'text'           => [
                    'preparing_scan'       => __('Vorbereitung...', 'simple-avif-webp-converter'),
                    'scan_error'           => __('Scan-Fehler:', 'simple-avif-webp-converter'),
                    'unknown_error'        => __('Unbekannter Fehler', 'simple-avif-webp-converter'),
                    'no_images_found'      => __('Keine Bilder gefunden.', 'simple-avif-webp-converter'),
                    'scanning'             => __('Scanne...', 'simple-avif-webp-converter'),
                    'scan_complete'        => __('Scan abgeschlossen!', 'simple-avif-webp-converter'),
                    'critical_error'       => __('Kritischer Serverfehler.', 'simple-avif-webp-converter'),
                    'preparing_conversion' => __('Vorbereitung...', 'simple-avif-webp-converter'),
                    'converting'           => __('Konvertiere...', 'simple-avif-webp-converter'),
                    'finishing_up'         => __('Abschluss...', 'simple-avif-webp-converter'),
                    'conversion_error'     => __('Fehler während der Konvertierung:', 'simple-avif-webp-converter'),
                    'not_available'        => __('N/V', 'simple-avif-webp-converter'),
                ],
            ]);
        }
    }
    
    public static function admin_page_html() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $debug_mode   = get_option('sawc_debug_mode', 'off');
        $auto_convert = get_option('sawc_auto_convert_on_upload', 'on');
        $lib          = self::get_active_library();
        $webp_quality = get_option('sawc_quality_webp', 80);
        $avif_quality = get_option('sawc_quality_avif', 28);
        ?>
        <div class="wrap"><h1><?php echo esc_html(get_admin_page_title()); ?></h1><div class="sawc-wrap"><div class="sawc-main-content">
            <form method="post" action=""><div class="sawc-card"><h2><?php esc_html_e('Einstellungen', 'simple-avif-webp-converter'); ?></h2><table class="form-table settings-table" role="presentation"><tbody>
                <tr><th scope="row"><label for="sawc_auto_convert_on_upload"><?php esc_html_e('Automatische Konvertierung', 'simple-avif-webp-converter'); ?></label></th><td><label class="sawc-switch"><input type="checkbox" name="sawc_auto_convert_on_upload" id="sawc_auto_convert_on_upload" value="on" <?php checked($auto_convert, 'on'); ?>><span class="sawc-slider"></span></label><span class="sawc-switch-label"><?php esc_html_e('Neue Bilder beim Upload automatisch konvertieren.', 'simple-avif-webp-converter'); ?></span></td></tr>
                <tr><th scope="row"><label for="sawc_debug_mode"><?php esc_html_e('Debug-Modus', 'simple-avif-webp-converter'); ?></label></th><td><label class="sawc-switch"><input type="checkbox" name="sawc_debug_mode" id="sawc_debug_mode" value="on" <?php checked($debug_mode, 'on'); ?>><span class="sawc-slider"></span></label><span class="sawc-switch-label"><?php esc_html_e('Detailliertes Log bei der Massen-Konvertierung anzeigen.', 'simple-avif-webp-converter'); ?></span></td></tr>
                <tr><th scope="row"><label for="sawc_conversion_library"><?php esc_html_e('Konvertierungs-Bibliothek', 'simple-avif-webp-converter'); ?></label></th><td><select name="sawc_conversion_library" id="sawc_conversion_library"><option value="imagick" <?php selected($lib, 'imagick'); ?> <?php disabled(!class_exists('Imagick')); ?>><?php esc_html_e('Imagick (empfohlen)', 'simple-avif-webp-converter'); ?></option><option value="gd" <?php selected($lib, 'gd'); ?> <?php disabled(!function_exists('gd_info')); ?>>GD</option></select></td></tr>
                <tr><th scope="row"><label for="sawc_quality_webp_number"><?php esc_html_e('WebP Qualität', 'simple-avif-webp-converter'); ?></label></th><td><input type="range" id="sawc_quality_webp_range" min="1" max="100" value="<?php echo esc_attr($webp_quality); ?>"><input type="number" name="sawc_quality_webp" id="sawc_quality_webp_number" min="1" max="100" value="<?php echo esc_attr($webp_quality); ?>"></td></tr>
                <tr><th scope="row"><label for="sawc_quality_avif_number"><?php esc_html_e('AVIF Qualität (Imagick)', 'simple-avif-webp-converter'); ?></label></th><td><input type="range" id="sawc_quality_avif_range" min="0" max="63" value="<?php echo esc_attr($avif_quality); ?>"><input type="number" name="sawc_quality_avif" id="sawc_quality_avif_number" min="0" max="63" value="<?php echo esc_attr($avif_quality); ?>"></td></tr>
            </tbody></table><?php wp_nonce_field('sawc_save_settings_action', 'sawc_save_settings_nonce'); submit_button(__('Einstellungen speichern', 'simple-avif-webp-converter')); ?></div></form>
            <div class="sawc-card"><h2><?php esc_html_e('Massen-Konvertierung', 'simple-avif-webp-converter'); ?></h2><p><strong><?php esc_html_e('Schritt 1:', 'simple-avif-webp-converter'); ?></strong> <?php esc_html_e('Klicken Sie hier, um Ihre Mediathek zu analysieren.', 'simple-avif-webp-converter'); ?></p><button id="sawc-scan-start" class="button button-primary"><?php esc_html_e('Mediathek scannen', 'simple-avif-webp-converter'); ?></button><div id="sawc-scan-progress" style="display:none; margin-top: 15px;"><div id="sawc-scan-progress-text">...</div><div class="sawc-progress-bar-container"><div id="sawc-scan-progress-bar" class="sawc-progress-bar">0%</div></div></div></div>
            <div id="sawc-results-wrapper" style="display: none;"><div class="sawc-card"><h2><?php esc_html_e('Schritt 2: Status', 'simple-avif-webp-converter'); ?></h2><div class="sawc-status-container"><div class="sawc-donut-chart-wrapper"><div class="sawc-donut-chart"><svg width="100" height="100" viewBox="0 0 100 100"><circle class="donut-background" cx="50" cy="50" r="46"/><circle class="donut-progress" id="sawc-donut-webp" cx="50" cy="50" r="46" stroke-dasharray="289.03" stroke-dashoffset="289.03"/></svg><div class="sawc-donut-chart-text"><div class="sawc-chart-percent" id="sawc-chart-percent-webp">--%</div></div></div><div class="sawc-chart-format">WebP</div></div><div class="sawc-donut-chart-wrapper"><div class="sawc-donut-chart"><svg width="100" height="100" viewBox="0 0 100 100"><circle class="donut-background" cx="50" cy="50" r="46"/><circle class="donut-progress" id="sawc-donut-avif" cx="50" cy="50" r="46" stroke-dasharray="289.03" stroke-dashoffset="289.03"/></svg><div class="sawc-donut-chart-text"><div class="sawc-chart-percent" id="sawc-chart-percent-avif">--%</div></div></div><div class="sawc-chart-format">AVIF</div></div></div></div><div class="sawc-card"><h2><?php esc_html_e('Schritt 3: Konvertieren', 'simple-avif-webp-converter'); ?></h2><p><?php esc_html_e('Starten Sie die Konvertierung für alle noch nicht optimierten Bilder.', 'simple-avif-webp-converter'); ?></p><button id="sawc-bulk-start" class="button button-primary" disabled><?php esc_html_e('Konvertierung starten', 'simple-avif-webp-converter'); ?></button><div id="sawc-bulk-progress" style="display:none; margin-top: 15px;"><div id="sawc-bulk-progress-text">...</div><div class="sawc-progress-bar-container"><div id="sawc-bulk-progress-bar" class="sawc-progress-bar">0%</div></div></div></div></div>
        </div><div class="sawc-sidebar">
            <div class="postbox"><h2 class="hndle"><span><?php esc_html_e('Server-Status', 'simple-avif-webp-converter'); ?></span></h2><div class="inside"><ul><?php $imagick_ok = class_exists('Imagick'); echo '<li>Imagick: '; if ($imagick_ok) { echo '<span style="color:green; font-weight:bold;">✔ ' . esc_html__('Verfügbar', 'simple-avif-webp-converter') . '</span></li><ul>'; $formats = Imagick::queryFormats(); echo '<li>AVIF Support: ' . (in_array('AVIF', $formats) ? '<span style="color:green;">✔</span>' : '<span style="color:red;">❌</span>') . '</li>'; echo '<li>WebP Support: ' . (in_array('WEBP', $formats) ? '<span style="color:green;">✔</span>' : '<span style="color:red;">❌</span>') . '</li></ul>'; } else { echo '<span style="color:red; font-weight:bold;">❌ ' . esc_html__('Nicht verfügbar', 'simple-avif-webp-converter') . '</span></li>'; } $gd_ok = function_exists('gd_info'); echo '<li>GD: '; if ($gd_ok) { echo '<span style="color:green; font-weight:bold;">✔ ' . esc_html__('Verfügbar', 'simple-avif-webp-converter') . '</span></li><ul>'; echo '<li>AVIF Support: ' . (function_exists('imageavif') ? '<span style="color:green;">✔</span>' : '<span style="color:red;">❌</span>') . '</li>'; echo '<li>WebP Support: ' . (function_exists('imagewebp') ? '<span style="color:green;">✔</span>' : '<span style="color:red;">❌</span>') . '</li></ul>'; } else { echo '<span style="color:red; font-weight:bold;">❌ ' . esc_html__('Nicht verfügbar', 'simple-avif-webp-converter') . '</span></li>'; } ?></ul></div></div>
            <div id="sawc-debug-log-wrapper" class="postbox" style="display: none;"><h2 class="hndle"><span><?php esc_html_e('Debug-Log', 'simple-avif-webp-converter'); ?></span></h2><div class="inside"><div id="sawc-debug-log" class="debug-log"></div></div></div>
        </div></div></div>
        <?php
    }
}