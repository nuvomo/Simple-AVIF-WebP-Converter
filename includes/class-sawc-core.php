<?php
if (!defined('ABSPATH')) { exit; }

class SAWC_Core {
    public static function init() {
        add_filter('wp_generate_attachment_metadata', [__CLASS__, 'trigger_on_upload'], 10, 2);
        add_action('delete_attachment', [__CLASS__, 'delete_converted_images']);
        add_filter('plugin_action_links_' . SAWC_PLUGIN_BASENAME, [__CLASS__, 'add_settings_link']);
    }

    public static function trigger_on_upload($metadata, $attachment_id) {
        if (get_option('sawc_auto_convert_on_upload', 'on') === 'on') {
            SAWC_Conversion::process_single_attachment($attachment_id);
        }
        return $metadata;
    }

    public static function delete_converted_images($post_id) {
        $metadata = wp_get_attachment_metadata($post_id);
        if (!$metadata || !isset($metadata['file'])) {
            return;
        }

        $upload_dir = wp_get_upload_dir();
        $base_dir = $upload_dir['basedir'];
        $files_to_delete = [];

        $original_path = $base_dir . '/' . $metadata['file'];
        $files_to_delete[] = pathinfo($original_path, PATHINFO_DIRNAME) . '/' . pathinfo($original_path, PATHINFO_FILENAME) . '.webp';
        $files_to_delete[] = pathinfo($original_path, PATHINFO_DIRNAME) . '/' . pathinfo($original_path, PATHINFO_FILENAME) . '.avif';

        if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
            $path_parts = pathinfo($original_path);
            foreach ($metadata['sizes'] as $size_data) {
                $thumb_path = $path_parts['dirname'] . '/' . $size_data['file'];
                $files_to_delete[] = pathinfo($thumb_path, PATHINFO_DIRNAME) . '/' . pathinfo($thumb_path, PATHINFO_FILENAME) . '.webp';
                $files_to_delete[] = pathinfo($thumb_path, PATHINFO_DIRNAME) . '/' . pathinfo($thumb_path, PATHINFO_FILENAME) . '.avif';
            }
        }

        foreach ($files_to_delete as $file) {
            if (file_exists($file)) {
                // FIX: Verwende wp_delete_file() anstelle von unlink()
                wp_delete_file($file);
            }
        }
    }

    public static function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=simple-avif-webp-converter') . '">' . esc_html__('Einstellungen', 'simple-avif-webp-converter') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}