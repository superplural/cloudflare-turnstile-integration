<?php
/*
Plugin Name: Cloudflare Turnstile Integration
Description: Adds Cloudflare Turnstile CAPTCHA to WordPress login and comment forms.
Version: 1.1
Author: Super Plural
Author URI: https://superplural.com
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Create the menu item for the settings page
function turnstile_settings_menu() {
    add_options_page(
        'Cloudflare Turnstile Settings',
        'Turnstile Settings',
        'manage_options',
        'turnstile-settings',
        'turnstile_settings_page'
    );
}
add_action('admin_menu', 'turnstile_settings_menu');

// Display the settings page
function turnstile_settings_page() {
    ?>
    <div class="wrap">
        <h1>Cloudflare Turnstile Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('turnstile_settings_group');
            do_settings_sections('turnstile-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Initialize the settings
function turnstile_settings_init() {
    register_setting('turnstile_settings_group', 'turnstile_site_key');
    register_setting('turnstile_settings_group', 'turnstile_secret_key');

    add_settings_section(
        'turnstile_settings_section',
        'Cloudflare Turnstile Configuration',
        null,
        'turnstile-settings'
    );

    add_settings_field(
        'turnstile_site_key',
        'Site Key',
        'turnstile_site_key_render',
        'turnstile-settings',
        'turnstile_settings_section'
    );

    add_settings_field(
        'turnstile_secret_key',
        'Secret Key',
        'turnstile_secret_key_render',
        'turnstile-settings',
        'turnstile_settings_section'
    );
}
add_action('admin_init', 'turnstile_settings_init');

function turnstile_site_key_render() {
    $site_key = get_option('turnstile_site_key');
    echo "<input type='text' name='turnstile_site_key' value='" . esc_attr($site_key) . "'>";
}

function turnstile_secret_key_render() {
    $secret_key = get_option('turnstile_secret_key');
    echo "<input type='text' name='turnstile_secret_key' value='" . esc_attr($secret_key) . "'>";
}

// Load Turnstile scripts
function load_turnstile_scripts() {
    wp_enqueue_script('cf-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', [], null, true);
}
add_action('wp_enqueue_scripts', 'load_turnstile_scripts');
add_action('login_enqueue_scripts', 'load_turnstile_scripts');

// Add Turnstile to comment form
function add_turnstile_to_comment_form() {
    $site_key = get_option('turnstile_site_key');
    echo '<div class="cf-turnstile" data-sitekey="' . esc_attr($site_key) . '"></div>';
}
add_action('comment_form_after_fields', 'add_turnstile_to_comment_form');

// Validate Turnstile response for comments
function validate_turnstile_comment($commentdata) {
    $response = sanitize_text_field($_POST['cf-turnstile-response']);
    $remoteip = $_SERVER['REMOTE_ADDR'];

    $verify = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
        'body' => [
            'secret' => get_option('turnstile_secret_key'),
            'response' => $response,
            'remoteip' => $remoteip
        ]
    ]);

    $result = json_decode(wp_remote_retrieve_body($verify));

    if (!$result->success) {
        wp_die('CAPTCHA validation failed. Please try again.');
    }

    return $commentdata;
}
add_filter('preprocess_comment', 'validate_turnstile_comment');

// Add Turnstile to login form
function add_turnstile_to_login_form() {
    $site_key = get_option('turnstile_site_key');
    echo '<div class="cf-turnstile" data-sitekey="' . esc_attr($site_key) . '"></div>';
}
add_action('login_form', 'add_turnstile_to_login_form');

// Validate Turnstile response for login
function validate_turnstile_login($user, $username, $password) {
    $response = sanitize_text_field($_POST['cf-turnstile-response']);
    $remoteip = $_SERVER['REMOTE_ADDR'];

    $verify = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
        'body' => [
            'secret' => get_option('turnstile_secret_key'),
            'response' => $response,
            'remoteip' => $remoteip
        ]
    ]);

    $result = json_decode(wp_remote_retrieve_body($verify));

    if (!$result->success) {
        return new WP_Error('captcha_invalid', __('CAPTCHA validation failed. Please try again.'));
    }

    return $user;
}
add_filter('wp_authenticate_user', 'validate_turnstile_login', 10, 3);

?>