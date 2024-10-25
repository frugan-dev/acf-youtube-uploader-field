<?php

declare(strict_types=1);

/*
 * This file is part of the "ACF YouTube Uploader Field" WordPress plugin.
 *
 * (ɔ) Frugan <dev@frugan.it>
 *
 * This source file is subject to the GNU GPLv3 or later license that is bundled
 * with this source code in the file LICENSE.
 */

use FruganAYUF\AcfYoutubeUploaderField;

/*
 * Plugin Name: ACF YouTube Uploader Field
 * Plugin URI: https://github.com/frugan-dev/acf-youtube-uploader-field
 * Description: Advanced Custom Fields YouTube video uploader field via API
 * Version: 0.1.0
 * Requires PHP: 8.0
 * Author: Frugan
 * Author URI: https://frugan.it
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Donate link: https://buymeacoff.ee/frugan
 */

if (!defined('ABSPATH')) {
    exit;
}

if (file_exists(__DIR__.'/vendor/autoload.php')) {
    require __DIR__.'/vendor/autoload.php';
}

define('FRUGAN_AYUF_VERSION', '0.1.0');
define('FRUGAN_AYUF_NAME', dirname(plugin_basename(__FILE__)));
define('FRUGAN_AYUF_NAME_UNDERSCORE', str_replace('-', '_', FRUGAN_AYUF_NAME));
define('FRUGAN_AYUF_URL', plugin_dir_url(__FILE__));
define('FRUGAN_AYUF_PATH', plugin_dir_path(__FILE__));

add_action('muplugins_loaded', [AcfYoutubeUploaderField::class, 'muplugins_loaded']);
add_action('plugins_loaded', [AcfYoutubeUploaderField::class, 'plugins_loaded']);

add_action('init', static function (): void {
    if (!function_exists('acf_register_field_type')) {
        return;
    }

    acf_register_field_type(AcfYoutubeUploaderField::class);
});

register_activation_hook(__FILE__, [AcfYoutubeUploaderField::class, 'activate']);
register_deactivation_hook(__FILE__, [AcfYoutubeUploaderField::class, 'deactivate']);
