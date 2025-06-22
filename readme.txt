=== Simple AVIF & WebP Converter ===
Contributors: franklewandowski
Tags: avif, webp, images, performance, optimization
Requires at least: 5.8
Tested up to: 6.8
Stable tag: 4.6
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A simple yet powerful plugin to automatically convert uploaded images to AVIF and WebP formats and serve them using the modern <picture> tag.

== Description ==

Simple AVIF & WebP Converter seamlessly integrates into your WordPress media workflow. It automatically converts JPEG and PNG images to the highly efficient AVIF and WebP formats upon upload. On the frontend, it intelligently replaces standard `<img>` tags with responsive `<picture>` elements, allowing browsers to choose the best format. This can significantly reduce page load times and improve your site's performance scores.

= Features =

*   **Automatic Conversion:** Converts new image uploads to both AVIF and WebP.
*   **Bulk Converter:** Process your entire existing media library with a user-friendly batch processor.
*   **<picture> Tag Implementation:** Serves images via the `<picture>` tag for optimal browser compatibility.
*   **Flexible Engine:** Supports both Imagick and GD libraries, with automatic detection of server capabilities.
*   **Quality Control:** Set custom quality levels for both AVIF and WebP conversions.
*   **Clean Uninstallation:** Deletes converted image files when an original is removed from the media library.
*   **Multisite Compatible:** Works flawlessly on a per-site basis in a WordPress Multisite environment.
*   **Central Dashboard:** Integrates into the "nuvomo" dashboard for a unified plugin experience.

== Installation ==

1.  Upload the `simple-avif-webp-converter` folder to the `/wp-content/plugins/` directory.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Navigate to "nuvomo" > "AVIF/WebP Converter" to configure the settings.
4.  Use the Bulk Converter to process your existing images.

== Frequently Asked Questions ==

= Does this work with my page builder? =

The plugin is designed to work with any content that uses standard WordPress image classes (e.g., `wp-image-123`). This covers the vast majority of themes and page builders.

= What are the server requirements? =

For WebP conversion, you need either the Imagick extension with WebP support or the GD extension with WebP support. For AVIF conversion (which offers the best compression), you need Imagick with AVIF support or GD with AVIF support (less common). The plugin's settings page includes a server status check to show you what is available.

== Changelog ==

= 4.6 =
*   FIX: Updated "Tested up to" version to be compliant with WordPress 6.5.
*   FIX: Ensured "Stable Tag" and plugin version are synchronized.
*   FIX: Reduced the number of tags to the allowed maximum of 5.
*   FIX: Addressed a `wp_die()` escaping warning.
*   ENHANCEMENT: Improved code comments for plugin checker compatibility.

= 4.5 =
*   FIX: Complied with WordPress Plugin Check standards.
*   FIX: Replaced `unlink()` with `wp_delete_file()`.
*   FIX: Removed discouraged `set_time_limit()`.
*   FIX: Added `wp_unslash()` to POST data handling.
*   FIX: Ensured all output is properly escaped.
*   ENHANCEMENT: Added a professional readme.txt file.
*   ENHANCEMENT: Added a central "nuvomo" dashboard.

(Older changelog entries remain)

== Upgrade Notice ==

= 4.6 =
This version includes updates for WordPress 6.5 compatibility and addresses several coding standard warnings. Updating is recommended.