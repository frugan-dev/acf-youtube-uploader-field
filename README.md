![](.wordpress-org/banner-1544x500.jpg)

![GitHub Downloads (all assets, all releases)](https://img.shields.io/github/downloads/frugan-dev/acf-youtube-uploader-field/total)
![GitHub Actions Workflow Status](https://github.com/frugan-dev/acf-youtube-uploader-field/actions/workflows/main.yml/badge.svg)
![GitHub Issues](https://img.shields.io/github/issues/frugan-dev/acf-youtube-uploader-field)
![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen)
![GitHub Release](https://img.shields.io/github/v/release/frugan-dev/acf-youtube-uploader-field)
![License](https://img.shields.io/github/license/frugan-dev/acf-youtube-uploader-field)
<!--
![PHP Version](https://img.shields.io/packagist/php-v/frugan-dev/acf-youtube-uploader-field)
![Coverage Status](https://img.shields.io/codecov/c/github/frugan-dev/acf-youtube-uploader-field)
![Code Climate](https://img.shields.io/codeclimate/maintainability/frugan-dev/acf-youtube-uploader-field)
-->

# ACF YouTube Uploader Field (WordPress Plugin)

__ACF YouTube Uploader Field__ is a WordPress plugin that allows you to upload videos directly to YouTube via API from the WordPress admin area and select existing videos on your YouTube channel based on playlists. It is particularly useful for managing videos that may be associated with Custom Post Types (CPT).

To use this plugin, you need to configure Google oAuth credentials so the plugin can authenticate with the user's YouTube channel.

## Requirements

- PHP ^8.0
- WordPress ^5.7 || ^6.0
- [Advanced Custom Fields](https://www.advancedcustomfields.com) ^5.9 || ^6.0

## Features

- direct video upload to YouTube from CPTs within the WordPress interface
- selection of existing YouTube videos, filtered by playlists
- support for "unlisted" video privacy, making this plugin ideal for managing private or exclusive videos tied to specific content
- support for logging with [Wonolog](https://github.com/inpsyde/Wonolog) ^2.x, if available
- use official [ACF Example Field Type](https://github.com/AdvancedCustomFields/acf-example-field-type)
- made with [Vanilla JS](http://vanilla-js.com) (no jQuery)
- autoload classes with Composer and PSR-4
- support ACF nested repeater
- translations managed via [Crowdin](https://crowdin.com/project/acf-youtube-uploader-field)

## Installation

You can install the plugin in three ways: manually, via Composer (wpackagist) _(coming soon)_ or via Composer (package).

<details>
<summary>Manual Installation</summary>

1. Go to the [Releases](../../releases) section of this repository.
2. Download the latest release zip file.
3. Log in to your WordPress admin dashboard.
4. Navigate to `Plugins` > `Add New`.
5. Click `Upload Plugin`.
6. Choose the downloaded zip file and click `Install Now`.

</details>

<details>
<summary>Installation via Composer "wpackagist" (coming soon)</summary>

If you use Composer to manage WordPress plugins, you can install it from [WordPress Packagist](https://wpackagist.org):

1. Open your terminal.
2. Navigate to the root directory of your WordPress installation.
3. Ensure your `composer.json` file has the following configuration: *

```json
{
    "require": {
        "composer/installers": "^1.0 || ^2.0",
        "wpackagist-plugin/acf-youtube-uploader-field": "^0.1"
    },
    "extra": {
        "installer-paths": {
            "wp-content/plugins/{$name}/": [
               "type:wordpress-plugin"
            ]
        }
    }
}
```
4. Run the following command:

```sh
composer update
```

<sub><i>
_Note:_  
_* `composer/installers` might already be required by another dependency._
</i></sub>
</details>

<details>
<summary>Installation via Composer "package"</summary>

If you use Composer to manage WordPress plugins, you can install it from this repository directly:

1. Open your terminal.
2. Navigate to the root directory of your WordPress installation.
3. Ensure your `composer.json` file has the following configuration: *

```json
{
    "require": {
        "composer/installers": "^1.0 || ^2.0",
        "frugan-dev/acf-youtube-uploader-field": "^0.1"
    },
    "repositories": [
        {
            "type": "package",
            "package": {
                "name": "frugan-dev/acf-youtube-uploader-field",
                "version": "0.1.0",
                "type": "wordpress-plugin",
                "dist": {
                    "url": "https://github.com/frugan-dev/acf-youtube-uploader-field/releases/download/v0.1.0/acf-youtube-uploader-field.zip",
                    "type": "zip"
                }
            }
        }
    ],
    "extra": {
        "installer-paths": {
            "wp-content/plugins/{$name}/": [
               "type:wordpress-plugin"
            ]
        }
    }
}
```
4. Run the following command:

```sh
composer update
```

<sub><i>
_Note:_  
_* `composer/installers` might already be required by another dependency._
</i></sub>
</details>

## Configuration

Once installed:

1. In your WordPress admin dashboard, navigate to the `Plugins` section and click `Activate Plugin`.
2. Create a new field via ACF and select the `YouTube uploader` type.
3. Read the description above for advanced usage instructions.

### Creating the oAuth Credentials

1. Go to the [Google API Console](https://console.developers.google.com/).
2. Create a project or select an existing one.
3. Navigate to the **OAuth consent screen** section and configure the required details to enable authentication.
4. Then, go to **Credentials** and create an **OAuth 2.0 Client ID**.
5. In the **Authorized redirect URIs** field, enter your WordPress site's callback URL (e.g., `https://domain.tld/wp-admin/`).
6. Obtain the `Client ID` and `Client Secret`.

### Setting in wp-config.php

Add the following lines to `wp-config.php` to define the oAuth credentials:

```php
define('FRUGAN_AYUF_GOOGLE_OAUTH_CLIENT_ID', 'Client ID');
define('FRUGAN_AYUF_GOOGLE_OAUTH_CLIENT_SECRET', 'Client Secret');
```

### Enabling Cache Busting

If you use filename-based cache busting, the plugin supports the following definition in `wp-config.php`:

```php
define('FRUGAN_AYUF_CACHE_BUSTING_ENABLED', true);
```

For more information, see filename-based cache busting on [Nginx](https://github.com/h5bp/server-configs-nginx/blob/main/h5bp/location/web_performance_filename-based_cache_busting.conf) and [Apache](https://github.com/h5bp/server-configs-apache/blob/main/h5bp/web_performance/filename-based_cache_busting.conf).

## More info

See [LINKS](docs/LINKS.md) file.

## Changelog

See auto-[CHANGELOG](CHANGELOG.md) file.

## Contributing

For your contributions please use:

- [git-flow workflow](https://danielkummer.github.io/git-flow-cheatsheet/)
- [conventional commits](https://www.conventionalcommits.org)

## Sponsor

[<img src="https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png" width="200" alt="Buy Me A Coffee">](https://buymeacoff.ee/frugan)

## License

(ɔ) Copyleft 2024 [Frugan](https://frugan.it).  
[GNU GPLv3](https://choosealicense.com/licenses/gpl-3.0/), see [LICENSE](LICENSE) file.
