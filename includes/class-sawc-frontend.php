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

            if (!$image_meta || !isset($image_meta['file'])) {
                return $img_tag;
            }

            // --- Phase 1: Pfade und URLs vorbereiten ---
            $upload_dir    = wp_upload_dir();
            $base_dir      = $upload_dir['basedir'];
            $base_url      = $upload_dir['baseurl'];
            if (is_ssl()) {
                $base_url = str_replace('http://', 'https://', $base_url);
            }

            $image_dir     = dirname($image_meta['file']);
            $image_dir_url = ('.' === $image_dir) ? '' : $image_dir . '/';
            $image_dir_path = ('.' === $image_dir) ? '' : $image_dir . '/';

            // --- Phase 2: Srcsets generieren UND auf Existenz prüfen ---
            $avif_srcset     = '';
            $webp_srcset     = '';
            $jpeg_png_srcset = '';

            // Sammle alle verfügbaren Bildgrößen
            $all_sizes = $image_meta['sizes'] ?? [];
            $all_sizes['full'] = [
                'file'   => basename($image_meta['file']),
                'width'  => $image_meta['width'],
                'height' => $image_meta['height'],
            ];

            foreach ($all_sizes as $size_data) {
                if (!isset($size_data['file'], $size_data['width'])) {
                    continue;
                }

                $original_filename = $size_data['file'];
                $base_filename     = pathinfo($original_filename, PATHINFO_FILENAME);
                
                $original_path = $base_dir . '/' . $image_dir_path . $original_filename;
                $avif_path     = $base_dir . '/' . $image_dir_path . $base_filename . '.avif';
                $webp_path     = $base_dir . '/' . $image_dir_path . $base_filename . '.webp';

                $original_url = $base_url . '/' . $image_dir_url . $original_filename;
                $avif_url     = $base_url . '/' . $image_dir_url . $base_filename . '.avif';
                $webp_url     = $base_url . '/' . $image_dir_url . $base_filename . '.webp';
                
                $image_width = $size_data['width'];

                // Füge nur Quellen hinzu, deren Datei tatsächlich existiert!
                if (file_exists($avif_path)) {
                    $avif_srcset .= $avif_url . ' ' . $image_width . 'w, ';
                }
                if (file_exists($webp_path)) {
                    $webp_srcset .= $webp_url . ' ' . $image_width . 'w, ';
                }
                if (file_exists($original_path)) {
                    $jpeg_png_srcset .= $original_url . ' ' . $image_width . 'w, ';
                }
            }

            // Entferne das letzte Komma und Leerzeichen
            $avif_srcset     = rtrim($avif_srcset, ', ');
            $webp_srcset     = rtrim($webp_srcset, ', ');
            $jpeg_png_srcset = rtrim($jpeg_png_srcset, ', ');

            // Wenn keine einzige konvertierte Version existiert, gib das Originalbild zurück und beende
            if (empty($avif_srcset) && empty($webp_srcset)) {
                return $img_tag;
            }

            // --- Phase 3: Baue das <picture>-Element ---
            $is_lazy_loaded = preg_match('/data-srcs?et=|lazyload/i', $img_tag);
            preg_match('/sizes="([^"]+)"/i', $img_tag, $sizes_matches);
            $sizes = $sizes_matches[1] ?? '';
            
            $picture_tag = '<picture>';
            $source_srcset_attr = $is_lazy_loaded ? 'data-srcset' : 'srcset';

            if (!empty($avif_srcset)) {
                $picture_tag .= '<source type="image/avif" ' . $source_srcset_attr . '="' . esc_attr($avif_srcset) . '"' . ($sizes ? ' sizes="' . esc_attr($sizes) . '"' : '') . '>';
            }
            if (!empty($webp_srcset)) {
                $picture_tag .= '<source type="image/webp" ' . $source_srcset_attr . '="' . esc_attr($webp_srcset) . '"' . ($sizes ? ' sizes="' . esc_attr($sizes) . '"' : '') . '>';
            }
            
            // Verwende das Original-<img>-Tag als Fallback, da es alle wichtigen Klassen und Attribute enthält
            $fallback_img = $img_tag;
            // Wenn der Fallback kein srcset hat, füge unser korrektes hinzu
            if (!preg_match('/srcset/i', $fallback_img) && !empty($jpeg_png_srcset)) {
                $img_srcset_attr = $is_lazy_loaded ? 'data-srcset' : 'srcset';
                $fallback_img = str_replace('<img ', '<img ' . $img_srcset_attr . '="' . esc_attr($jpeg_png_srcset) . '" ', $fallback_img);
            }
            
            $picture_tag .= $fallback_img . '</picture>';
            
            return $picture_tag;
        }, $content);

        return $content;
    }
}