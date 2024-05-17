<?php
/*
Plugin Name: WP Spell and Punctuation Correction
Description: A plugin to correct spelling and punctuation in WordPress posts and pages.
Version: 1.0
Author: Bunty prasad nayak
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class WPCorrectionPlugin {
    public function __construct() {
        add_action('admin_menu', array($this, 'create_admin_menu'));
        add_action('save_post', array($this, 'check_post_content'));
    }

    public function create_admin_menu() {
        add_menu_page(
            'Spell and Punctuation Correction',
            'Correction Settings',
            'manage_options',
            'wp-correction-settings',
            array($this, 'settings_page')
        );
    }

    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>Spell and Punctuation Correction Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('wp_correction_settings_group');
                do_settings_sections('wp-correction-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function check_post_content($post_id) {
        if (wp_is_post_revision($post_id)) {
            return;
        }

        $post_content = get_post_field('post_content', $post_id);

        if (!empty($post_content)) {
            $corrected_content = $this->correct_text($post_content);
            remove_action('save_post', array($this, 'check_post_content')); // Prevent infinite loop
            wp_update_post(array(
                'ID' => $post_id,
                'post_content' => $corrected_content
            ));
            add_action('save_post', array($this, 'check_post_content')); // Re-enable the save_post hook
        }
    }

    private function correct_text($text) {
        $api_key = get_option('wp_correction_api_key');
        if (empty($api_key)) {
            return $text;
        }

        $url = 'https://api.grammarbot.io/v2/check';
        $data = array(
            'text' => $text,
            'language' => 'en-US',
            'api_key' => $api_key
        );

        $response = wp_remote_post($url, array(
            'body' => $data
        ));

        if (is_wp_error($response)) {
            return $text;
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (isset($result['matches'])) {
            foreach ($result['matches'] as $match) {
                $offset = $match['offset'];
                $length = $match['length'];
                $replacement = $match['replacements'][0]['value'];

                $text = substr_replace($text, $replacement, $offset, $length);
            }
        }

        return $text;
    }
}

if (is_admin()) {
    new WPCorrectionPlugin();
}

// Register settings
function wp_correction_register_settings() {
    register_setting('wp_correction_settings_group', 'wp_correction_api_key');
    add_settings_section('wp_correction_settings_section', 'API Settings', null, 'wp-correction-settings');
    add_settings_field('wp_correction_api_key', 'GrammarBot API Key', 'wp_correction_api_key_field', 'wp-correction-settings', 'wp_correction_settings_section');
}

function wp_correction_api_key_field() {
    $api_key = get_option('wp_correction_api_key');
    echo '<input type="text" name="wp_correction_api_key" value="' . esc_attr($api_key) . '" />';
}

add_action('admin_init', 'wp_correction_register_settings');
