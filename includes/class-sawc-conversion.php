<?php
if (!defined('ABSPATH')) { exit; }

class SAWC_Conversion {

    public static function process_single_attachment($attachment_id) {
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!$metadata || !isset($metadata['file'])) {
            return ['status' => 'error', 'message' => __('Metadaten nicht gefunden.', 'simple-avif-webp-converter')];
        }

        $upload_dir = wp_get_upload_dir();
        $base_dir = $upload_dir['basedir'];
        $file_path_original = $base_dir . '/' . $metadata['file'];
        $success_avif = true;
        $success_webp = true;

        $result_original = self::perform_conversion($file_path_original);
        $success_avif &= $result_original['avif'];
        $success_webp &= $result_original['webp'];

        if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size_data) {
                $thumbnail_path = $base_dir . '/' . dirname($metadata['file']) . '/' . $size_data['file'];
                $result_thumb = self::perform_conversion($thumbnail_path);
                $success_avif &= $result_thumb['avif'];
                $success_webp &= $result_thumb['webp'];
            }
        }

        return ['status' => 'success', 'avif' => $success_avif, 'webp' => $success_webp];
    }

    public static function perform_conversion($file_path) {
        $result = ['avif' => true, 'webp' => true];
        if (!file_exists($file_path)) return ['avif' => false, 'webp' => false];

        $file_info = pathinfo($file_path);
        if (!in_array(strtolower($file_info['extension']), ['jpg', 'jpeg', 'png'])) return $result;

        $lib = get_option('sawc_conversion_library', 'imagick');
        $webp_q = get_option('sawc_quality_webp', 80);
        $avif_q = get_option('sawc_quality_avif', 28);
        $avif_path = $file_info['dirname'] . '/' . $file_info['filename'] . '.avif';
        $webp_path = $file_info['dirname'] . '/' . $file_info['filename'] . '.webp';

        if ($lib === 'imagick' && class_exists('Imagick')) {
            $formats = Imagick::queryFormats();
            if (in_array('AVIF', $formats) && !file_exists($avif_path)) {
                try {
                    $imagick = new Imagick($file_path);
                    $imagick->setImageFormat('avif');
                    $imagick->setOption('avif:cq-level', (string)$avif_q);
                    $result['avif'] = $imagick->writeImage($avif_path);
                    $imagick->clear(); $imagick->destroy();
                } catch (Exception $e) { $result['avif'] = false; }
            }
            if (in_array('WEBP', $formats) && !file_exists($webp_path)) {
                try {
                    $imagick = new Imagick($file_path);
                    $imagick->setImageCompressionQuality($webp_q);
                    $imagick->setImageFormat('webp');
                    $result['webp'] = $imagick->writeImage($webp_path);
                    $imagick->clear(); $imagick->destroy();
                } catch (Exception $e) { $result['webp'] = false; }
            }
        } elseif ($lib === 'gd' && function_exists('gd_info')) {
            $source_image = null; $ext = strtolower($file_info['extension']);
            if ($ext === 'jpg' || $ext === 'jpeg') { $source_image = @imagecreatefromjpeg($file_path); }
            elseif ($ext === 'png') { $source_image = @imagecreatefrompng($file_path); if ($source_image) { imagepalettetotruecolor($source_image); imagealphablending($source_image, true); imagesavealpha($source_image, true); } }

            if ($source_image) {
                if (function_exists('imageavif') && !file_exists($avif_path)) { $result['avif'] = @imageavif($source_image, $avif_path, 60); } // AVIF quality in GD is 0-100, not 0-63. Hardcoding a sane default.
                if (function_exists('imagewebp') && !file_exists($webp_path)) { $result['webp'] = @imagewebp($source_image, $webp_path, $webp_q); }
                imagedestroy($source_image);
            }
        }
        return $result;
    }
}