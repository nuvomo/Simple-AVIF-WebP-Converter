<?php
if (!defined('ABSPATH')) {
    exit;
}

class SAWC_Frontend {
    public static function init() {
        add_filter('the_content', [__CLASS__, 'create_responsive_picture_tags'], 20);
    }

    public static function create_responsive_picture_tags($content) {
        $pattern = '/<img[^>]+class="[^"]*wp-image-([0-9]+)[^"]*"[^>]*>/i';

        $content = preg_replace_callback($pattern, function ($matches) {
            $img_tag       = $matches[0];
            $attachment_id = (int) $matches[1];
            $image_meta    = wp_get_attachment_metadata($attachment_id);

            if (!$image_meta) {
                return $img_tag;
            }

            $upload_dir = wp_upload_dir();
            $base_url   = $upload_dir['baseurl'];
            if (is_ssl()) {
                $base_url = str_replace('http://', 'https://', $base_url);
            }

            $avif_srcset     = '';
            $webp_srcset     = '';
            $jpeg_png_srcset = '';
            $image_dir       = dirname($image_meta['file']);
            $image_dir_url   = ('.' === $image_dir) ? '' : $image_dir . '/';

            if (isset($image_meta['sizes']) && is_array($image_meta['sizes'])) {
                foreach ($image_meta['sizes'] as $size => $size_data) {
                    $image_url     = $base_url . '/' . $image_dir_url . $size_data['file'];
                    $image_width   = $size_data['width'];

                    $avif_srcset     .= preg_replace('/\.(jpg|jpeg|png)$/i', '.avif', $image_url) . ' ' . $image_width . 'w, ';
                    $webp_srcset     .= preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $image_url) . ' ' . $image_width . 'w, ';
                    $jpeg_png_srcset .= $image_url . ' ' . $image_width . 'w, ';
                }
            }

            $full_image_url   = $base_url . '/' . $image_meta['file'];
            $full_image_width = $image_meta['width'];
            $avif_srcset     .= preg_replace('/\.(jpg|jpeg|png)$/i', '.avif', $full_image_url) . ' ' . $full_image_width . 'w';
            $webp_srcset     .= preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $full_image_url) . ' ' . $full_image_width . 'w';
            $jpeg_png_srcset .= $full_image_url . ' ' . $full_image_width . 'w';

            preg_match('/sizes="([^"]+)"/i', $img_tag, $size_matches);
            $sizes = $size_matches[1] ?? '';

            preg_match('/src="([^"]+)"/i', $img_tag, $src_matches);
            $src = $src_matches[1] ?? $full_image_url;

            $picture_tag = '<picture>';
            $picture_tag .= '<source type="image/avif" srcset="' . esc_attr(rtrim($avif_srcset, ', ')) . '"' . ($sizes ? ' sizes="' . esc_attr($sizes) . '"' : '') . '>';
            $picture_tag .= '<source type="image/webp" srcset="' . esc_attr(rtrim($webp_srcset, ', ')) . '"' . ($sizes ? ' sizes="' . esc_attr($sizes) . '"' : '') . '>';
            
            // Clean the original <img> tag from src, srcset, and sizes to use as a fallback.
            $fallback_img = preg_replace('/(srcset|sizes|src)="[^"]*"/i', '', $img_tag);

            /*
             * The following line is intentionally ignored by the PHP Code Sniffer.
             * The purpose of this function is to build a complete <picture> tag, which requires manual construction of the fallback <img>.
             * The $fallback_img variable is a securely constructed version of the original tag where sensitive attributes have been
             * replaced by properly escaped and sanitized values. Standard functions like wp_get_attachment_image() cannot produce this <picture> structure
             * while preserving all original attributes (like custom classes from page builders).
             */
            // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage, WordPress.Security.EscapeOutput.OutputNotEscaped
            $fallback_img = str_replace('<img', '<img src="' . esc_url($src) . '" srcset="' . esc_attr(rtrim($jpeg_png_srcset, ', ')) . '"' . ($sizes ? ' sizes="' . esc_attr($sizes) . '"' : ''), $fallback_img);
            
            $picture_tag .= $fallback_img . '</picture>';
            
            return $picture_tag;
        }, $content);

        return $content;
    }
}