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

            // --- Lazy Load Detection & Handling ---
            $is_lazy_loaded = preg_match('/data-srcs?et=/', $img_tag);
            
            // Extract attributes from the original tag
            preg_match('/src="([^"]+)"/i', $img_tag, $src_matches);
            $original_src = $src_matches[1] ?? '';

            preg_match('/srcset="([^"]+)"/i', $img_tag, $srcset_matches);
            $original_srcset = $srcset_matches[1] ?? '';

            preg_match('/data-src="([^"]+)"/i', $img_tag, $data_src_matches);
            $data_src = $data_src_matches[1] ?? '';

            preg_match('/data-srcset="([^"]+)"/i', $img_tag, $data_srcset_matches);
            $data_srcset = $data_srcset_matches[1] ?? '';

            preg_match('/sizes="([^"]+)"/i', $img_tag, $sizes_matches);
            $sizes = $sizes_matches[1] ?? '';
            
            // Decide which srcset to use as the base for our conversion
            $base_srcset = $data_srcset ?: $original_srcset;
            $base_src = $data_src ?: $original_src;

            // If we have no srcset, we need to build it from scratch
            if (empty($base_srcset)) {
                $upload_dir = wp_upload_dir();
                $base_url   = $upload_dir['baseurl'];
                if (is_ssl()) {
                    $base_url = str_replace('http://', 'https://', $base_url);
                }

                $image_dir       = dirname($image_meta['file']);
                $image_dir_url   = ('.' === $image_dir) ? '' : $image_dir . '/';
                
                $generated_srcset = '';
                if (isset($image_meta['sizes']) && is_array($image_meta['sizes'])) {
                    foreach ($image_meta['sizes'] as $size_data) {
                        $image_url = $base_url . '/' . $image_dir_url . $size_data['file'];
                        $image_width = $size_data['width'];
                        $generated_srcset .= $image_url . ' ' . $image_width . 'w, ';
                    }
                }
                $full_image_url = $base_url . '/' . $image_meta['file'];
                $full_image_width = $image_meta['width'];
                $generated_srcset .= $full_image_url . ' ' . $full_image_width . 'w';
                
                $base_srcset = rtrim($generated_srcset, ', ');
            }

            if (empty($base_srcset)) {
                return $img_tag; // Cannot proceed if no srcset is found or generated
            }

            // Create AVIF and WebP sources from the base srcset
            $avif_srcset = preg_replace('/\.(jpg|jpeg|png)/i', '.avif', $base_srcset);
            $webp_srcset = preg_replace('/\.(jpg|jpeg|png)/i', '.webp', $base_srcset);
            
            // Build the <picture> tag
            $picture_tag = '<picture>';

            // For lazy loaded images, the <source> tags also need the data-srcset attribute
            if ($is_lazy_loaded) {
                $picture_tag .= '<source type="image/avif" data-srcset="' . esc_attr($avif_srcset) . '"' . ($sizes ? ' sizes="' . esc_attr($sizes) . '"' : '') . '>';
                $picture_tag .= '<source type="image/webp" data-srcset="' . esc_attr($webp_srcset) . '"' . ($sizes ? ' sizes="' . esc_attr($sizes) . '"' : '') . '>';
            } else {
                $picture_tag .= '<source type="image/avif" srcset="' . esc_attr($avif_srcset) . '"' . ($sizes ? ' sizes="' . esc_attr($sizes) . '"' : '') . '>';
                $picture_tag .= '<source type="image/webp" srcset="' . esc_attr($webp_srcset) . '"' . ($sizes ? ' sizes="' . esc_attr($sizes) . '"' : '') . '>';
            }

            // Modify the original <img> tag to be a proper fallback.
            // We ensure it has the original srcset so lazy loading scripts can use it if they need to.
            $fallback_img = $img_tag;
            
            // If the original tag didn't have a srcset, add ours.
            if (empty($original_srcset) && !empty($base_srcset)) {
                 $attribute_to_add = $is_lazy_loaded ? ' data-srcset="' . esc_attr($base_srcset) . '"' : ' srcset="' . esc_attr($base_srcset) . '"';
                 $fallback_img = str_replace('<img ', '<img ' . $attribute_to_add . ' ', $fallback_img);
            }
            
            $picture_tag .= $fallback_img . '</picture>';
            
            return $picture_tag;
        }, $content);

        return $content;
    }
}