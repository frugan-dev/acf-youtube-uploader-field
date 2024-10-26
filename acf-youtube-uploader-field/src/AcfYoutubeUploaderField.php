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

namespace FruganAYUF;

use Google\Client;
use Google\Service\Oauth2;
use Google\Service\YouTube;
use Inpsyde\Wonolog\Configurator;

if (!\defined('ABSPATH')) {
    exit;
}

/**
 * Class AcfYoutubeUploaderField.
 *
 * @property string $title
 */
class AcfYoutubeUploaderField extends \acf_field
{
    /**
     * Controls field type visibilty in REST requests.
     *
     * @var bool
     */
    public $show_in_rest = true;

    /**
     * Environment values relating to the theme or plugin.
     *
     * @var array plugin or theme context such as 'url' and 'version'
     */
    private array $env;

    private ?Client $client = null;

    private null|array|bool $access_token = null;

    /**
     * Constructor.
     */
    public function __construct()
    {
        /*
         * Field type reference used in PHP and JS code.
         *
         * No spaces. Underscores allowed.
         */
        $this->name = 'youtube_uploader';

        $this->title = __('ACF YouTube Uploader Field', FRUGAN_AYUF_NAME);

        /*
         * Field type label.
         *
         * For public-facing UI. May contain spaces.
         */
        $this->label = __('YouTube uploader', FRUGAN_AYUF_NAME);

        // The category the field appears within in the field type picker.
        $this->category = 'basic'; // basic | content | choice | relational | jquery | layout | CUSTOM GROUP NAME

        /*
         * Field type Description.
         *
         * For field descriptions. May contain spaces.
         */
        $this->description = __('Advanced Custom Fields YouTube video uploader field via API', FRUGAN_AYUF_NAME);

        /*
         * Field type Doc URL.
         *
         * For linking to a documentation page. Displayed in the field picker modal.
         */
        $this->doc_url = 'https://github.com/frugan-dev/acf-youtube-uploader-field';

        /*
         * Field type Tutorial URL.
         *
         * For linking to a tutorial resource. Displayed in the field picker modal.
         */
        $this->tutorial_url = 'https://github.com/frugan-dev/acf-youtube-uploader-field';

        // Defaults for your custom user-facing settings for this field type.
        $this->defaults = [
            'category_id' => 22, // People & Blogs
            'tags' => !empty($_SERVER['HTTP_HOST']) ? str_replace('www.', '', $_SERVER['HTTP_HOST']) : '',
            'privacy_status' => 'unlisted',
            'made_for_kids' => false,
            'allow_upload' => true,
            'allow_select' => true,
            'api_update_on_post_update' => true,
            'api_delete_on_post_delete' => false,
        ];

        /*
         * Strings used in JavaScript code.
         *
         * Allows JS strings to be translated in PHP and loaded in JS via:
         *
         * ```js
         * const errorMessage = acf._e("youtube_uploader", "error");
         * ```
         */
        $this->l10n = [
            'before_uploading' => __('Before uploading your video, make sure you:', FRUGAN_AYUF_NAME),
            'enter_title' => \sprintf(__('Enter a "%1$s"', FRUGAN_AYUF_NAME), __('Title', FRUGAN_AYUF_NAME)),
            'enter_description' => \sprintf(__('Enter a "%1$s"', FRUGAN_AYUF_NAME), __('Description', FRUGAN_AYUF_NAME)),
            'select_video_file' => \sprintf(__('Select a "%1$s"', FRUGAN_AYUF_NAME), __('Video file', FRUGAN_AYUF_NAME)),
            'preparing_upload' => __('Preparing to upload your file', FRUGAN_AYUF_NAME),
            'loading' => __('Loading', FRUGAN_AYUF_NAME),
            'wait_please' => __('Wait please', FRUGAN_AYUF_NAME),
            'video_uploaded_successfully' => __('Video uploaded successfully.', FRUGAN_AYUF_NAME),
            'error_while_uploading' => __('Error while uploading.', FRUGAN_AYUF_NAME),
            'network_error_while_uploading' => __('Network error while uploading.', FRUGAN_AYUF_NAME),
            'following_error' => __('The following error occurred:', FRUGAN_AYUF_NAME),
            'recommended_save_post' => __('It is recommended to save the post by clicking the "Publish" button.', FRUGAN_AYUF_NAME),
            'attention' => __('Attention', FRUGAN_AYUF_NAME),
            'technical_problem' => __('There was a technical problem, please try again later.', FRUGAN_AYUF_NAME),
            'select' => __('select', FRUGAN_AYUF_NAME),
        ];

        $this->env = [
            'version' => FRUGAN_AYUF_VERSION,
            'url' => FRUGAN_AYUF_URL,
            'path' => FRUGAN_AYUF_PATH,
            'cache_busting' => \defined('FRUGAN_AYUF_CACHE_BUSTING_ENABLED') && !empty(FRUGAN_AYUF_CACHE_BUSTING_ENABLED) && !is_numeric(FRUGAN_AYUF_CACHE_BUSTING_ENABLED) && filter_var(FRUGAN_AYUF_CACHE_BUSTING_ENABLED, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ? true : false,
        ];

        /*
         * Field type preview image.
         *
         * A preview image for the field type in the picker modal.
         */
        // $this->preview_image = $this->env['url'] . '/asset/img/preview-custom.png';

        parent::__construct();

        add_action('admin_init', [$this, 'admin_init']);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_notices', [$this, 'admin_notices']);
        add_action('before_delete_post', [$this, 'before_delete_post']);
        add_action('wp', [$this, 'schedule_oauth_check']);
        add_action(FRUGAN_AYUF_NAME.'_check_oauth_token', [$this, 'check_oauth_token']);
        add_action('wp_ajax_get_youtube_upload_url', [$this, 'wp_ajax_get_youtube_upload_url']);
        add_action('wp_ajax_save_youtube_video_id', [$this, 'wp_ajax_save_youtube_video_id']);
        add_action('wp_ajax_get_videos_by_playlist', [$this, 'wp_ajax_get_videos_by_playlist']);
    }

    /**
     * Settings to display when users configure a field of this type.
     *
     * These settings appear on the ACF “Edit Field Group” admin page when
     * setting up the field.
     *
     * @param array $field
     */
    public function render_field_settings($field): void
    {
        // Repeat for each setting you wish to display for this field type.
        acf_render_field_setting(
            $field,
            [
                'label' => __('Category ID', FRUGAN_AYUF_NAME),
                'instructions' => implode(
                    '<br>'.PHP_EOL,
                    [
                        \sprintf(__('Default: %1$s', FRUGAN_AYUF_NAME), '<code>'.$this->defaults['category_id'].'</code>'),
                    ]
                ),
                'type' => 'number',
                'name' => 'category_id',
                'min' => 0,
                'step' => 1,
            ]
        );

        acf_render_field_setting(
            $field,
            [
                'label' => __('Tags', FRUGAN_AYUF_NAME),
                'instructions' => implode(
                    '<br>'.PHP_EOL,
                    [
                        \sprintf(__('Default: %1$s', FRUGAN_AYUF_NAME), '<code>'.$this->defaults['tags'].'</code>'),
                    ]
                ),
                'type' => 'text',
                'name' => 'tags',
            ]
        );

        acf_render_field_setting(
            $field,
            [
                'label' => __('Privacy status', FRUGAN_AYUF_NAME),
                'instructions' => implode(
                    '<br>'.PHP_EOL,
                    [
                        \sprintf(__('Default: %1$s', FRUGAN_AYUF_NAME), '<code>'.$this->defaults['privacy_status'].'</code>'),
                    ]
                ),
                'type' => 'select',
                'name' => 'privacy_status',
                'choices' => [
                    'unlisted' => __('Unlisted', FRUGAN_AYUF_NAME),
                    'private' => __('Private', FRUGAN_AYUF_NAME),
                    'public' => __('Public', FRUGAN_AYUF_NAME),
                ],
            ]
        );

        acf_render_field_setting(
            $field,
            [
                'label' => __('Made for kids', FRUGAN_AYUF_NAME),
                'instructions' => implode(
                    '<br>'.PHP_EOL,
                    [
                        \sprintf(__('Default: %1$s', FRUGAN_AYUF_NAME), '<code>'.($this->defaults['made_for_kids'] ? __('Yes', FRUGAN_AYUF_NAME) : __('No', FRUGAN_AYUF_NAME)).'</code>'),
                    ]
                ),
                'type' => 'true_false',
                'name' => 'made_for_kids',
            ]
        );

        acf_render_field_setting(
            $field,
            [
                'label' => __('Allow upload', FRUGAN_AYUF_NAME),
                'instructions' => implode(
                    '<br>'.PHP_EOL,
                    [
                        \sprintf(__('Default: %1$s', FRUGAN_AYUF_NAME), '<code>'.($this->defaults['allow_upload'] ? __('Yes', FRUGAN_AYUF_NAME) : __('No', FRUGAN_AYUF_NAME)).'</code>'),
                    ]
                ),
                'type' => 'true_false',
                'name' => 'allow_upload',
            ]
        );

        acf_render_field_setting(
            $field,
            [
                'label' => __('Allow select', FRUGAN_AYUF_NAME),
                'instructions' => implode(
                    '<br>'.PHP_EOL,
                    [
                        \sprintf(__('Default: %1$s', FRUGAN_AYUF_NAME), '<code>'.($this->defaults['allow_select'] ? __('Yes', FRUGAN_AYUF_NAME) : __('No', FRUGAN_AYUF_NAME)).'</code>'),
                    ]
                ),
                'type' => 'true_false',
                'name' => 'allow_select',
            ]
        );

        acf_render_field_setting(
            $field,
            [
                'label' => __('Update YouTube video on post update', FRUGAN_AYUF_NAME),
                'instructions' => implode(
                    '<br>'.PHP_EOL,
                    [
                        \sprintf(__('Default: %1$s', FRUGAN_AYUF_NAME), '<code>'.($this->defaults['api_update_on_post_update'] ? __('Yes', FRUGAN_AYUF_NAME) : __('No', FRUGAN_AYUF_NAME)).'</code>'),
                    ]
                ),
                'type' => 'true_false',
                'name' => 'api_update_on_post_update',
            ]
        );

        acf_render_field_setting(
            $field,
            [
                'label' => __('Delete YouTube video on post delete', FRUGAN_AYUF_NAME),
                'instructions' => implode(
                    '<br>'.PHP_EOL,
                    [
                        \sprintf(__('Default: %1$s', FRUGAN_AYUF_NAME), '<code>'.($this->defaults['api_delete_on_post_delete'] ? __('Yes', FRUGAN_AYUF_NAME) : __('No', FRUGAN_AYUF_NAME)).'</code>'),
                    ]
                ),
                'type' => 'true_false',
                'name' => 'api_delete_on_post_delete',
            ]
        );

        // To render field settings on other tabs in ACF 6.0+:
        // https://www.advancedcustomfields.com/resources/adding-custom-settings-fields/#moving-field-setting
    }

    /**
     * HTML content to show when a publisher edits the field on the edit screen.
     *
     * @param array $field the field settings and values
     */
    public function render_field($field): void
    {
        ?>
		<div class="<?php echo esc_attr($field['key']); ?>__wrapper <?php echo esc_attr($field['type']); ?>__wrapper">
            <input type="hidden" name="<?php echo esc_attr($field['name']); ?>" value="<?php echo esc_attr($field['value']); ?>" class="<?php echo esc_attr($field['key']); ?>__hidden_value_input">

            <?php if (!empty($field['value'])) { ?>
        	    <p>
                    <a href="https://www.youtube.com/embed/<?php echo esc_attr($field['value']); ?>?rel=0&TB_iframe=true" class="thickbox">
                        <img src="https://img.youtube.com/vi/<?php echo esc_attr($field['value']); ?>/hqdefault.jpg" alt="">
                    </a>
                </p>
			<?php } elseif ($this->get_access_token()) { ?>
                <input type="hidden" name="mode" class="<?php echo esc_attr($field['key']); ?>__hidden_mode_input">

                <div class="<?php echo esc_attr($field['key']); ?>__tabs">
                    <ul>
                        <?php if (!empty($field['allow_upload'])) { ?>
                            <li>
                                <a href="#<?php echo esc_attr($field['key']); ?>__tab_1">
                                    <?php esc_html_e('Upload via API', FRUGAN_AYUF_NAME); ?>
                                </a>
                            </li>
                        <?php }
                        ?>
                        <?php if (!empty($field['allow_select'])) { ?>
                            <li>
                                <a href="#<?php echo esc_attr($field['key']); ?>__tab_2">
                                    <?php esc_html_e('Select from channel', FRUGAN_AYUF_NAME); ?>
                                </a>
                            </li>
                         <?php }
                        ?>
                    </ul>

                    <?php if (!empty($field['allow_upload'])) { ?>
                        <div id="<?php echo esc_attr($field['key']); ?>__tab_1">
                            <input type="file" class="<?php echo esc_attr($field['key']); ?>__file_input" name="<?php echo esc_attr($field['key']); ?>__file_input" lang="<?php echo esc_attr(get_locale()); ?>" accept="video/*">
                            <button type="button" class="<?php echo esc_attr($field['key']); ?>__button button button-secondary">
			                	<?php esc_html_e('Upload', FRUGAN_AYUF_NAME); ?>
			                </button>
                        </div>
                    <?php }
                    ?>

                    <?php if (!empty($field['allow_select'])) { ?>
                        <div id="<?php echo esc_attr($field['key']); ?>__tab_2">
                            <?php
                                       $result = $this->get_playlists_by_privacy_status($field['privacy_status']);
                        if (!empty($result['items'])) { ?>
                                <p>
                                    <label for="<?php echo esc_attr($field['key']); ?>__playlist_select">
                                        <?php esc_html_e('Playlist', FRUGAN_AYUF_NAME); ?>
                                    </label>
                                    <select class="<?php echo esc_attr($field['key']); ?>__playlist_select" name="<?php echo esc_attr($field['key']); ?>__playlist_select">
                                        <option value="">- <?php _e('select', FRUGAN_AYUF_NAME); ?>- </option>
                                        <?php foreach ($result['items'] as $item) { ?>
                                            <option value="<?php echo esc_attr($item['id']); ?>">
                                                <?php echo esc_html($item['title']); ?> (<?php echo esc_html($item['id']); ?>)
                                            </option>
                                        <?php }
                                        ?>
                                    </select>
                                </p>

                                <p>
                                    <label for="<?php echo esc_attr($field['key']); ?>__video_select">
                                        <?php esc_html_e('Video', FRUGAN_AYUF_NAME); ?>
                                    </label>
                                    <select class="<?php echo esc_attr($field['key']); ?>__video_select" name="<?php echo esc_attr($field['key']); ?>__video_select"></select>
                                </p>
                            <?php } else { ?>
                                <p><?php esc_html_e('No playlists available', FRUGAN_AYUF_NAME); ?></p>
                            <?php }
                            ?>
                        </div>
<?php }
                    ?>
                </div>

                <p class="<?php echo esc_attr($field['key']); ?>__response <?php echo esc_attr($field['type']); ?>__response"></p>
			    <p class="<?php echo esc_attr($field['key']); ?>__spinner <?php echo esc_attr($field['type']); ?>__spinner">
			        <span class="spinner is-active"></span>
			    </p>
<?php } else { ?>
                <p><?php esc_html_e('You are not logged in', FRUGAN_AYUF_NAME); ?></p>
            <?php }
?>
		</div>
<?php
    }

    /**
     * Enqueues CSS and JavaScript needed by HTML in the render_field() method.
     *
     * Callback for admin_enqueue_script.
     */
    public function input_admin_enqueue_scripts(): void
    {
        global $post;

        $version = $this->env['version'];
        $url = trailingslashit($this->env['url']);
        $path = trailingslashit($this->env['path']);
        $cache_busting = $this->env['cache_busting'];

        // https://wordpress.stackexchange.com/a/273996/99214
        // https://stackoverflow.com/a/59665364/3929620
        // No need to enqueue -core, because dependancies are set.
        wp_enqueue_script('jquery-ui-tabs');

        // WordPress does not register jQuery UI styles by default!
        // If you're going to submit your plugin to the wordpress.org repo, then you need to load the CSS locally
        // (see: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/#8-plugins-may-not-send-executable-code-via-third-party-systems).
        wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');

        add_thickbox();

        wp_register_script(
            FRUGAN_AYUF_NAME,
            $url.'asset/js/main'.($cache_busting ? '.'.filemtime($path.'asset/js/main.js') : '').'.js',
            ['acf-input'],
            $version
        );

        // $object_name is the name of the variable which will contain the data.
        // Note that this should be unique to both the script and to the plugin or theme.
        // Thus, the value here should be properly prefixed with the slug or another unique value,
        // to prevent conflicts. However, as this is a JavaScript object name, it cannot contain dashes.
        // Use underscores or camelCasing.
        wp_localize_script(FRUGAN_AYUF_NAME, $this->name.'_obj', [
            'postStatus' => $post ? $post->post_status : null,
        ]);

        wp_register_style(
            FRUGAN_AYUF_NAME,
            $url.'asset/css/main'.($cache_busting ? '.'.filemtime($path.'asset/css/main.css') : '').'.css',
            ['acf-input'],
            $version
        );

        wp_enqueue_script(FRUGAN_AYUF_NAME);
        wp_enqueue_style(FRUGAN_AYUF_NAME);
    }

    public function validate_value($valid, mixed $value, $field, $input)
    {
        try {
            if (empty($value) && !empty($field['required'])) {
                throw new \InvalidArgumentException(\sprintf(__('Empty field "%1$s"', FRUGAN_AYUF_NAME), $field['label']));
            }

            if (!empty($value)) {
                $api_update_on_post_update = (!empty($_POST['mode']) && 'upload' === $_POST['mode']) || !empty($field['api_update_on_post_update']);
                if ($api_update_on_post_update) {
                    $post_id = (int) ($_POST['post_ID'] ?? $_POST['post_id']);
                    $is_gutenberg = \function_exists('use_block_editor_for_post') && use_block_editor_for_post($post_id);
                    if ($is_gutenberg) {
                        $post = get_post($post_id);
                        $title = sanitize_text_field($post->post_title ?? '');
                        $excerpt = sanitize_text_field($post->post_excerpt ?? '');
                    } else {
                        $title = isset($_POST['post_title']) ? sanitize_text_field($_POST['post_title']) : '';
                        $excerpt = isset($_POST['excerpt']) ? sanitize_text_field($_POST['excerpt']) : '';
                    }

                    if (empty($title)) {
                        throw new \LengthException(\sprintf(__('Empty field "%1$s"', FRUGAN_AYUF_NAME), __('Title', FRUGAN_AYUF_NAME)));
                    }

                    /*if (empty($excerpt)) {
                        throw new \LengthException(\sprintf(__('Empty field "%1$s"', FRUGAN_AYUF_NAME), __('Excerpt', FRUGAN_AYUF_NAME)));
                    }*/
                }

                $this->check_oauth_token();
                $googleServiceYouTube = new \Google_Service_YouTube($this->get_google_client());

                // Quota impact: A call to this method has a quota cost of 1 unit.
                $response = $googleServiceYouTube->videos->listVideos('snippet', ['id' => $value]);

                $this->log('debug', \sprintf(__('Video "%1$s" retrieved successfully', FRUGAN_AYUF_NAME), $value), ['response' => $response]);

                if (empty($response->getItems())) {
                    throw new \Exception(__('This video is not associated with your authorized YouTube account', FRUGAN_AYUF_NAME));
                }
            }
        } catch (\InvalidArgumentException|\LengthException $exception) {
            // FIXED - https://github.com/inpsyde/Wonolog/blob/2.x/src/HookLogFactory.php#L135
            // use `$exception->getMessage()` instead of `$exception`, because Wonolog
            // assigns the ERROR level to messages that are instances of Throwable
            $this->log('warning', $exception->getMessage());
            $valid = $exception->getMessage();
        } catch (\Google_Service_Exception $exception) {
            $this->log('error', $exception, ['response' => $response ?? null]);
            $error_data = json_decode($exception->getMessage(), true);
            $valid = $error_data['error']['message'] ?? $exception->getMessage();
        } catch (\Exception $exception) {
            $this->log('error', $exception);
            $valid = $exception->getMessage();
        }

        return $valid;
    }

    public function update_value(mixed $value, mixed $post_id, $field)
    {
        try {
            $api_update_on_post_update = (!empty($_POST['mode']) && 'upload' === $_POST['mode']) || !empty($field['api_update_on_post_update']);
            if (!$api_update_on_post_update) {
                return $value;
            }

            if (!empty($value)) {
                $this->check_oauth_token();
                $googleServiceYouTube = new \Google_Service_YouTube($this->get_google_client());

                // Quota impact: A call to this method has a quota cost of 1 unit.
                $response = $googleServiceYouTube->videos->listVideos('snippet', ['id' => $value]);
                $videoSnippet = $response->getItems()[0]->getSnippet();

                $googleServiceYouTubeVideoSnippet = new \Google_Service_YouTube_VideoSnippet();
                $googleServiceYouTubeVideoSnippet->setCategoryId($videoSnippet->getCategoryId() ?? $field['category_id']);
                $googleServiceYouTubeVideoSnippet->setTitle(get_the_title($post_id));

                if (!empty($excerpt = get_post_field('post_excerpt', $post_id))) {
                    $googleServiceYouTubeVideoSnippet->setDescription($excerpt);
                }

                $googleServiceYouTubeVideo = new \Google_Service_YouTube_Video();
                $googleServiceYouTubeVideo->setId($value);
                $googleServiceYouTubeVideo->setSnippet($googleServiceYouTubeVideoSnippet);

                // Quota impact: A call to this method has a quota cost of 50 units.
                $response = $googleServiceYouTube->videos->update('snippet', $googleServiceYouTubeVideo);

                $this->log('info', \sprintf(__('Video "%1$s" updated successfully', FRUGAN_AYUF_NAME), $value), ['response' => $response]);
            }
        } catch (\Exception $exception) {
            $this->log('error', $exception, ['response' => $response ?? null]);
        }

        return $value;
    }

    public function before_delete_post(int $post_id): void
    {
        $fields = get_field_objects($post_id);

        if ($fields) {
            foreach ($fields as $field) {
                if (!empty($field['type']) && !empty($field['value']) && $field['type'] === $this->name) {
                    try {
                        if (empty($field['api_delete_on_post_delete'])) {
                            continue;
                        }

                        $this->check_oauth_token();
                        $googleServiceYouTube = new \Google_Service_YouTube($this->get_google_client());

                        // Quota impact: A call to this method has a quota cost of 50 units.
                        $response = $googleServiceYouTube->videos->delete($field['value']);

                        $this->log('info', \sprintf(__('Video "%1$s" deleted successfully', FRUGAN_AYUF_NAME), $field['value']), ['response' => $response]);
                    } catch (\Exception $exception) {
                        $this->log('error', $exception, ['response' => $response ?? null]);
                    }
                }
            }
        }
    }

    public static function muplugins_loaded(): void
    {
        if (is_dir(trailingslashit(WPMU_PLUGIN_DIR).FRUGAN_AYUF_NAME)) {
            load_muplugin_textdomain(
                FRUGAN_AYUF_NAME,
                trailingslashit(FRUGAN_AYUF_NAME).'lang'
            );
        }
    }

    public static function plugins_loaded(): void
    {
        if (is_dir(trailingslashit(WP_PLUGIN_DIR).FRUGAN_AYUF_NAME)) {
            load_plugin_textdomain(
                FRUGAN_AYUF_NAME,
                false,
                trailingslashit(FRUGAN_AYUF_NAME).'lang'
            );
        }
    }

    public function admin_init(): void
    {
        $role = get_role('administrator');
        if ($role) {
            $role->add_cap('manage_'.$this->name);
        }

        if (isset($_POST['action']) && 'logout' === $_POST['action']) {
            delete_option(FRUGAN_AYUF_NAME.'__access_token');

            add_action('admin_notices', static function (): void {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p><strong>'.esc_html__('Successfully logged out from YouTube.', FRUGAN_AYUF_NAME).'</strong></p>';
                echo '</div>';
            });
        }
    }

    public function admin_menu(): void
    {
        if (current_user_can('manage_options')) {
            add_options_page(
                $this->label,               // Page title
                $this->label,               // Menu title
                'manage_options',           // Capability
                $this->name,                // Menu slug
                [$this, 'settings_page'],   // Callback function
            );
        } elseif (current_user_can('manage_'.$this->name)) {
            add_menu_page(
                $this->label,               // Page title
                $this->label,               // Menu title
                'manage_'.$this->name,      // Capability
                $this->name,                // Menu slug
                [$this, 'settings_page'],   // Callback function
                'dashicons-video-alt3'      // Icon (optional)
            );
        }
    }

    public function settings_page(): void
    {
        $oauth = $this->handle_oauth();
        $status = $oauth['status'] ?? 'error';

        echo '<div class="wrap">';
        echo '<h1>'.esc_html($this->label).'</h1>';

        switch ($status) {
            case 'authorize':
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<h3>'.$this->label.'</h3>';
                echo '<p><strong>'.$oauth['message'].'</strong></p>';
                echo '<p><a href="'.esc_url($oauth['auth_url']).'" class="button button-primary">'.esc_html__('Authorize App', FRUGAN_AYUF_NAME).'</a></p>';
                echo '</div>';

                break;

            case 'authorized':
                echo '<div class="notice notice-success">';
                echo '<p><strong>'.$oauth['message'].'</strong></p>';
                echo '</div>';

                echo '<form method="post" action="">';
                echo '<input type="hidden" name="action" value="logout">';
                submit_button(__('Logout from YouTube', FRUGAN_AYUF_NAME));
                echo '</form>';

                break;

            case 'error':
                echo '<div class="notice notice-error">';
                echo '<p><strong>'.$oauth['message'].'</strong></p>';
                echo '</div>';

                break;
        }

        echo '</div>';
    }

    public function admin_notices(): void
    {
        if (isset($_GET['page']) && $this->name === $_GET['page']) {
            return;
        }

        $oauth = $this->handle_oauth();
        $status = $oauth['status'] ?? 'error';

        switch ($status) {
            case 'authorize':
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<h3>'.$this->label.'</h3>';
                echo '<p><strong>'.$oauth['message'].'</strong></p>';
                echo '<p><a href="'.esc_url($oauth['auth_url']).'" class="button button-primary">'.esc_html__('Authorize App', FRUGAN_AYUF_NAME).'</a></p>';
                echo '</div>';

                break;

            case 'success':
                echo '<div class="notice notice-success is-dismissible">';
                echo '<h3>'.$this->label.'</h3>';
                echo '<p><strong>'.$oauth['message'].'</strong></p>';
                echo '</div>';

                break;

            case 'error':
                echo '<div class="notice notice-error">';
                echo '<h3>'.$this->label.'</h3>';
                echo '<p><strong>'.$oauth['message'].'</strong></p>';
                echo '</div>';

                break;
        }
    }

    public function set_google_client(): void
    {
        if ($this->get_google_client() instanceof Client) {
            return;
        }

        $this->client = new Client();
        $this->client->setClientId(FRUGAN_AYUF_GOOGLE_OAUTH_CLIENT_ID);
        $this->client->setClientSecret(FRUGAN_AYUF_GOOGLE_OAUTH_CLIENT_SECRET);
        $this->client->setRedirectUri(admin_url());
        $this->client->addScope(Oauth2::USERINFO_EMAIL);
        $this->client->addScope(YouTube::YOUTUBE_FORCE_SSL);
        $this->client->addScope(YouTube::YOUTUBE_UPLOAD);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('select_account consent');

        if ($this->is_wonolog_active()) {
            // https://github.com/inpsyde/Wonolog/pull/55
            $this->client->setLogger(\Inpsyde\Wonolog\makeLogger());
        }
    }

    public function get_google_client()
    {
        return $this->client;
    }

    public function set_access_token(null|array|bool $token): void
    {
        $this->access_token = $token;
    }

    public function get_access_token(): null|array|bool
    {
        return $this->access_token;
    }

    public function check_oauth_token(): void
    {
        $this->set_access_token(get_option(FRUGAN_AYUF_NAME.'__access_token'));

        if ($this->get_access_token()) {
            $this->set_google_client();

            $this->client->setAccessToken($this->get_access_token());

            if ($this->client->isAccessTokenExpired()) {
                $this->set_access_token($this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken()));
                update_option(FRUGAN_AYUF_NAME.'__access_token', $this->get_access_token());

                $this->client->setAccessToken($this->get_access_token());
            }
        }
    }

    public function handle_oauth(): array
    {
        // @phpstan-ignore-next-line
        if (!\defined('FRUGAN_AYUF_GOOGLE_OAUTH_CLIENT_ID') || !\defined('FRUGAN_AYUF_GOOGLE_OAUTH_CLIENT_SECRET') || empty(FRUGAN_AYUF_GOOGLE_OAUTH_CLIENT_ID) || empty(FRUGAN_AYUF_GOOGLE_OAUTH_CLIENT_SECRET)) {
            $data = [
                'status' => 'error',
                'message' => __('Missing or wrong OAuth credentials.', FRUGAN_AYUF_NAME),
            ];

            $this->log('error', $data);

            return $data;
        }

        $this->check_oauth_token();

        if (!$this->get_access_token()) {
            if (!current_user_can('manage_options') || !current_user_can('manage_'.$this->name)) {
                $data = [
                    'status' => 'error',
                    'message' => __('App not authorized, contact your system administrator.', FRUGAN_AYUF_NAME),
                ];

                $this->log('error', $data);

                return $data;
            }

            $this->set_google_client();

            if (isset($_GET['code'])) {
                $this->client->authenticate($_GET['code']);
                $this->set_access_token($this->client->getAccessToken());
                update_option(FRUGAN_AYUF_NAME.'__access_token', $this->get_access_token());

                $data = [
                    'status' => 'success',
                    'message' => __('App authorized! You can now upload videos to YouTube.', FRUGAN_AYUF_NAME),
                ];

                $this->log('info', $data);

                return $data;
            }

            $auth_url = $this->client->createAuthUrl();

            return [
                'status' => 'authorize',
                'message' => __('Authorize the app to upload videos to YouTube:', FRUGAN_AYUF_NAME),
                'auth_url' => $auth_url,
            ];
        }

        $googleServiceOauth2 = new \Google_Service_Oauth2($this->get_google_client());
        $user_info = $googleServiceOauth2->userinfo->get();

        return [
            'status' => 'authorized',
            'message' => \sprintf(__('App authorized! You are logged in as: %1$s', FRUGAN_AYUF_NAME), $user_info->email),
        ];
    }

    public function schedule_oauth_check(): void
    {
        if (!wp_next_scheduled(FRUGAN_AYUF_NAME.'_check_oauth_token')) {
            wp_schedule_event(time(), 'hourly', FRUGAN_AYUF_NAME.'_check_oauth_token');
        }
    }

    public static function activate(): void {}

    public static function deactivate($network_deactivating = false): void
    {
        delete_option(FRUGAN_AYUF_NAME.'__access_token');

        $timestamp = wp_next_scheduled(FRUGAN_AYUF_NAME.'_check_oauth_token');
        wp_unschedule_event($timestamp, FRUGAN_AYUF_NAME.'_check_oauth_token');
    }

    public function get_playlists_by_privacy_status($privacy_status): array
    {
        $result = [];

        try {
            $this->check_oauth_token();
            $googleServiceYouTube = new \Google_Service_YouTube($this->get_google_client());
            $params = [
                'part' => 'snippet,status',
                'mine' => true,
                'maxResults' => 50,
            ];

            // Quota impact: A call to this method has a quota cost of 1 unit.
            $response = $googleServiceYouTube->playlists->listPlaylists('snippet,status', $params);

            $this->log('debug', __('Playlists retrieved successfully', FRUGAN_AYUF_NAME), [
                'privacy_status' => $privacy_status,
                'response' => $response,
            ]);

            foreach ($response->getItems() as $item) {
                $playlistId = $item->getId();
                if (!isset($result[$playlistId])) {
                    $status = $item->getStatus();
                    if ($status && $status->getPrivacyStatus() === $privacy_status) {
                        $result[$playlistId] = [
                            'id' => $playlistId,
                            'title' => $item->getSnippet()->getTitle(),
                        ];
                    }
                }
            }

            if ($result) {
                $result = [
                    'items' => array_values($result),
                ];

                if (!empty($nextPageToken = $response->getNextPageToken())) {
                    $result['nextPageToken'] = $nextPageToken;
                }
            }
        } catch (\Exception $exception) {
            $this->log('error', $exception, ['response' => $response ?? null]);
        }

        return $result;
    }

    // https://stackoverflow.com/a/74402514/3929620
    // https://developers.google.com/youtube/v3/guides/using_resumable_upload_protocol
    // https://github.com/youtube/api-samples/blob/master/php/resumable_upload.php
    // https://github.com/googleapis/google-api-php-client
    // https://developers.google.com/youtube/v3/getting-started#quota
    // https://developers.google.com/youtube/v3/determine_quota_cost
    public function wp_ajax_get_youtube_upload_url(): void
    {
        try {
            $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
            if (empty($post_id)) {
                throw new \InvalidArgumentException(\sprintf(__('Empty field "%1$s"', FRUGAN_AYUF_NAME), 'post_id'));
            }

            $field_key = isset($_POST['field_key']) ? sanitize_text_field($_POST['field_key']) : '';
            if (empty($field_key)) {
                throw new \InvalidArgumentException(\sprintf(__('Empty field "%1$s"', FRUGAN_AYUF_NAME), 'field_key'));
            }

            // https://support.advancedcustomfields.com/forums/topic/get-choices-from-field-without-post_id/
            $field = get_field_object($field_key);
            if (!$field) {
                throw new \InvalidArgumentException(\sprintf(__('Unable to retrieve field "%1$s"', FRUGAN_AYUF_NAME), $field_key));
            }

            $this->check_oauth_token();
            $googleServiceYouTube = new \Google_Service_YouTube($this->get_google_client());

            $googleServiceYouTubeVideoSnippet = new \Google_Service_YouTube_VideoSnippet();
            $googleServiceYouTubeVideoSnippet->setCategoryId($field['category_id']);
            $googleServiceYouTubeVideoSnippet->setTags(explode(',', $field['tags']));
            $googleServiceYouTubeVideoSnippet->setTitle(get_the_title($post_id));

            if (!empty($excerpt = get_post_field('post_excerpt', $post_id))) {
                $googleServiceYouTubeVideoSnippet->setDescription($excerpt);
            }

            $googleServiceYouTubeVideoStatus = new \Google_Service_YouTube_VideoStatus();
            // All videos uploaded via the videos.insert endpoint from unverified API projects
            // created after 28 July 2020 will be restricted to private viewing mode.
            // To lift this restriction, each API project must undergo an audit to verify compliance
            // with the Terms of Service. Please see the API Revision History for more details.
            $googleServiceYouTubeVideoStatus->setPrivacyStatus($field['privacy_status']);
            $googleServiceYouTubeVideoStatus->setSelfDeclaredMadeForKids($field['made_for_kids']); // or setMadeForKids()

            $googleServiceYouTubeVideo = new \Google_Service_YouTube_Video();
            $googleServiceYouTubeVideo->setSnippet($googleServiceYouTubeVideoSnippet);
            $googleServiceYouTubeVideo->setStatus($googleServiceYouTubeVideoStatus);

            // Quota impact: A call to this method has a quota cost of 1600 units.
            $response = $googleServiceYouTube->videos->insert('snippet,status', $googleServiceYouTubeVideo, [
                'uploadType' => 'resumable',
            ]);

            $uploadUrl = $response->getRequest()->getLastHeaders()['location'] ?? null;
            if ($uploadUrl) {
                $this->log('debug', \sprintf(__('Video "%1$s" retrieved successfully', FRUGAN_AYUF_NAME), 'response'), ['response' => $response]);
                wp_send_json_success(['upload_url' => $uploadUrl]);
            } else {
                throw new \Exception(\sprintf(__('Unable to retrieve "%1$s" from response headers', FRUGAN_AYUF_NAME), 'location'));
            }
        } catch (\Google_Service_Exception $exception) {
            $this->log('error', $exception, ['response' => $response ?? null]);
            $error_data = json_decode($exception->getMessage(), true);
            $error_message = $error_data['error']['message'] ?? $exception->getMessage();
            wp_send_json_error(['message' => $error_message]);
        } catch (\Exception $exception) {
            $this->log('error', $exception);
            wp_send_json_error(['message' => $exception->getMessage()]);
        }
    }

    public function wp_ajax_save_youtube_video_id(): void
    {
        try {
            $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
            if (empty($post_id)) {
                throw new \InvalidArgumentException(\sprintf(__('Empty field "%1$s"', FRUGAN_AYUF_NAME), 'post_id'));
            }

            $video_id = isset($_POST['video_id']) ? sanitize_text_field($_POST['video_id']) : '';
            if (empty($video_id)) {
                throw new \InvalidArgumentException(\sprintf(__('Empty field "%1$s"', FRUGAN_AYUF_NAME), 'video_id'));
            }

            if (!current_user_can('edit_post', $post_id)) {
                throw new \Exception(\sprintf(__('Insufficient permissions to save video "%1$s"', FRUGAN_AYUF_NAME), $video_id));
            }

            $field_key = isset($_POST['field_key']) ? sanitize_text_field($_POST['field_key']) : '';
            if (empty($field_key)) {
                throw new \InvalidArgumentException(\sprintf(__('Empty field "%1$s"', FRUGAN_AYUF_NAME), 'field_key'));
            }

            $result = update_field($field_key, $video_id, $post_id);
            if ($result) {
                wp_send_json_success();
            }

            throw new \UnexpectedValueException(\sprintf(__('Unable to save video "%1$s" to field "%2$s" in post ID "%3$d"', FRUGAN_AYUF_NAME), $video_id, $field_key, $post_id));
        } catch (\Exception $exception) {
            $this->log('error', $exception);
            wp_send_json_error(['message' => $exception->getMessage()]);
        }
    }

    public function wp_ajax_get_videos_by_playlist(): void
    {
        try {
            $field_key = isset($_POST['field_key']) ? sanitize_text_field($_POST['field_key']) : '';
            if (empty($field_key)) {
                throw new \InvalidArgumentException(\sprintf(__('Empty field "%1$s"', FRUGAN_AYUF_NAME), 'field_key'));
            }

            // https://support.advancedcustomfields.com/forums/topic/get-choices-from-field-without-post_id/
            $field = get_field_object($field_key);
            if (!$field) {
                throw new \InvalidArgumentException(\sprintf(__('Unable to retrieve field "%1$s"', FRUGAN_AYUF_NAME), $field_key));
            }

            $playlist_id = isset($_POST['playlist_id']) ? sanitize_text_field($_POST['playlist_id']) : '';
            if (empty($playlist_id)) {
                throw new \InvalidArgumentException(\sprintf(__('Empty field "%1$s"', FRUGAN_AYUF_NAME), 'playlist_id'));
            }

            $this->check_oauth_token();
            $googleServiceYouTube = new \Google_Service_YouTube($this->get_google_client());
            $params = [
                'playlistId' => $playlist_id,
                'maxResults' => 50,
            ];

            // Quota impact: A call to this method has a quota cost of 1 unit.
            $response = $googleServiceYouTube->playlistItems->listPlaylistItems('snippet,status', $params);

            $this->log('debug', \sprintf(__('Videos retrieved successfully by playlist ID "%1$s"', FRUGAN_AYUF_NAME), $playlist_id), [
                'privacy_status' => $field['privacy_status'],
                'response' => $response,
            ]);

            $result = [];
            foreach ($response->getItems() as $item) {
                $videoId = $item->getSnippet()->getResourceId()->getVideoId();
                if (!isset($result[$videoId])) {
                    $status = $item->getStatus();
                    if ($status && $status->getPrivacyStatus() === $field['privacy_status']) {
                        $result[$videoId] = [
                            'id' => $videoId,
                            'title' => $item->getSnippet()->getTitle(),
                        ];
                    }
                }
            }

            if ($result) {
                $result = [
                    'items' => array_values($result),
                ];

                if (!empty($nextPageToken = $response->getNextPageToken())) {
                    $result['nextPageToken'] = $nextPageToken;
                }

                wp_send_json_success($result);
            }

            throw new \UnexpectedValueException(\sprintf(__('Unable to retrieve videos by playlist ID "%1$s"', FRUGAN_AYUF_NAME), $playlist_id));
        } catch (\UnexpectedValueException $exception) {
            // FIXED - https://github.com/inpsyde/Wonolog/blob/2.x/src/HookLogFactory.php#L135
            // use `$exception->getMessage()` instead of `$exception`, because Wonolog
            // assigns the ERROR level to messages that are instances of Throwable
            $this->log('warning', $exception->getMessage());
            wp_send_json_error(['message' => $exception->getMessage()]);
        } catch (\Exception $exception) {
            $this->log('error', $exception);
            wp_send_json_error(['message' => $exception->getMessage()]);
        }
    }

    public function log($level, $message, array $context = []): void
    {
        if ($this->is_wonolog_active()) {
            do_action('wonolog.log.'.$level, $message, $context);
        } else {
            if ($message instanceof \Throwable) {
                $message = $message->getMessage();
            } elseif (is_wp_error($message)) {
                $context['wp_error_data'] = $message->get_error_data();
                $message = $message->get_error_message();
            }

            if (\is_array($message)) {
                $message = 'Message: '.json_encode($message);
            }

            if (!empty($context)) {
                $message .= ' | Context: '.json_encode($context);
            }

            error_log($message);
        }
    }

    public function is_wonolog_active()
    {
        return \function_exists('did_action') && class_exists(Configurator::class) && \defined(Configurator::class.'::ACTION_SETUP') && did_action(Configurator::ACTION_SETUP);
    }
}
