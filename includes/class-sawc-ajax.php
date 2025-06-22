<?php
if (!defined('ABSPATH')) {
    exit;
}

class SAWC_Ajax {
    public static function init() {
        add_action('wp_ajax_sawc_scan_library', [__CLASS__, 'scan_library_handler']);
        add_action('wp_ajax_sawc_bulk_process', [__CLASS__, 'bulk_process_handler']);
    }

    public static function scan_library_handler() {
        check_ajax_referer('sawc_bulk_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Keine Berechtigung.']);
        }

        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        
        // Initial call to get total count
        if ($page === 1) {
            delete_transient('sawc_total_images_to_scan');
            delete_transient('sawc_unconverted_webp_list');
            delete_transient('sawc_unconverted_avif_list');

            $query = new WP_Query([
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'post_mime_type' => ['image/jpeg', 'image/png'],
                'fields'         => 'ids',
                'posts_per_page' => 1,
            ]);
            $total_images = $query->found_posts;
            set_transient('sawc_total_images_to_scan', $total_images, HOUR_IN_SECONDS);
            wp_send_json_success(['total_to_scan' => $total_images]);
            return;
        }

        $scan_batch_size = 50;
        $query           = new WP_Query([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => ['image/jpeg', 'image/png'],
            'fields'         => 'ids',
            'posts_per_page' => $scan_batch_size,
            'paged'          => $page - 1,
        ]);
        
        if (!$query->have_posts()) {
            $unconverted_webp = get_transient('sawc_unconverted_webp_list') ?: [];
            $unconverted_avif = get_transient('sawc_unconverted_avif_list') ?: [];
            $total_images     = get_transient('sawc_total_images_to_scan') ?: 0;
            delete_transient('sawc_total_images_to_scan');
            wp_send_json_success([
                'done'             => true,
                'total_images'     => $total_images,
                'unconverted_webp' => count($unconverted_webp),
                'unconverted_avif' => count($unconverted_avif),
            ]);
            return;
        }

        $unconverted_webp = get_transient('sawc_unconverted_webp_list') ?: [];
        $unconverted_avif = get_transient('sawc_unconverted_avif_list') ?: [];

        foreach ($query->posts as $id) {
            $file_path = get_attached_file($id);
            if (!$file_path || !file_exists($file_path)) {
                continue;
            }
            
            $path_info = pathinfo($file_path);
            $base_path = $path_info['dirname'] . '/' . $path_info['filename'];

            if (!file_exists($base_path . '.webp')) {
                $unconverted_webp[] = $id;
            }
            if (!file_exists($base_path . '.avif')) {
                $unconverted_avif[] = $id;
            }
        }

        set_transient('sawc_unconverted_webp_list', array_unique($unconverted_webp), HOUR_IN_SECONDS);
        set_transient('sawc_unconverted_avif_list', array_unique($unconverted_avif), HOUR_IN_SECONDS);
        wp_send_json_success(['done' => false]);
    }

    public static function bulk_process_handler() {
        check_ajax_referer('sawc_bulk_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Keine Berechtigung.']);
        }
        
        // FIX: The discouraged function set_time_limit() has been removed.
        // Our batch processing architecture makes it unnecessary.

        $unconverted_ids_webp = get_transient('sawc_unconverted_webp_list') ?: [];
        $unconverted_ids_avif = get_transient('sawc_unconverted_avif_list') ?: [];
        $all_unconverted_ids  = array_values(array_unique(array_merge($unconverted_ids_webp, $unconverted_ids_avif)));

        if (empty($all_unconverted_ids)) {
            wp_send_json_success(['done' => true, 'processed_in_batch' => 0, 'log' => '']);
            return;
        }

        $convert_batch_size = 5;
        $batch_ids          = array_slice($all_unconverted_ids, 0, $convert_batch_size);
        $log                = [];

        foreach ($batch_ids as $attachment_id) {
            $result = SAWC_Conversion::process_single_attachment($attachment_id);
            if (get_option('sawc_debug_mode', 'off') === 'on') {
                $log_message = "ID {$attachment_id}: ";
                if ($result['status'] === 'success') {
                    $log_message .= "OK (AVIF: " . ($result['avif'] ? '✓' : '✗') . ", WebP: " . ($result['webp'] ? '✓' : '✗') . ")";
                } else {
                    $log_message .= "FEHLER - " . $result['message'];
                }
                $log[] = $log_message;
            }
        }

        // Update the transient lists by removing processed IDs
        $remaining_webp = array_values(array_diff($unconverted_ids_webp, $batch_ids));
        $remaining_avif = array_values(array_diff($unconverted_ids_avif, $batch_ids));
        set_transient('sawc_unconverted_webp_list', $remaining_webp, HOUR_IN_SECONDS);
        set_transient('sawc_unconverted_avif_list', $remaining_avif, HOUR_IN_SECONDS);

        $is_done = empty(array_unique(array_merge($remaining_webp, $remaining_avif)));
        if ($is_done) {
            delete_transient('sawc_unconverted_webp_list');
            delete_transient('sawc_unconverted_avif_list');
        }

        wp_send_json_success([
            'done'               => $is_done,
            'processed_in_batch' => count($batch_ids),
            'log'                => implode("\n", $log),
        ]);
    }
}