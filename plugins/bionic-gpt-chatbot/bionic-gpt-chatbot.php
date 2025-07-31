<?php
/*
 * Plugin Name: Bionic GPT Chatbot
 * Description: A chatbot powered by Bionic GPT API for WordPress sites.
 * Version: 1.0.0
 * Author: SparkPoint
 * Author URI: https://sparkpoint.online
 * License: GPL-2.0+
 */

function bionic_chatbot_enqueue_assets() {
    wp_enqueue_script('bionic-chatbot-js', plugin_dir_url(__FILE__) . 'assets/chatbot.js', array(), '1.0.29', true);
    wp_enqueue_style('bionic-chatbot-css', plugin_dir_url(__FILE__) . 'assets/chatbot.css', array(), '1.0.20');
    wp_localize_script('bionic-chatbot-js', 'bionicChatbot', array(
        'ajaxUrl' => admin_url('admin-ajax.php')
    ));
}
add_action('wp_enqueue_scripts', 'bionic_chatbot_enqueue_assets');

function bionic_chatbot_add_html() {
    echo '<div id="chat-container">
            <div id="chat-output"></div>
            <div class="input-row">
                <input type="text" id="chat-input" placeholder="Type your message...">
                <button id="send-button">Send</button>
            </div>
          </div>';
}
add_action('wp_footer', 'bionic_chatbot_add_html');

function bionic_gpt_proxy() {
    $raw_post_data = file_get_contents('php://input');
    error_log('Bionic GPT: Raw POST input - ' . $raw_post_data);
    $post_data = json_decode($raw_post_data, true);
    $message = isset($post_data['message']) ? sanitize_text_field($post_data['message']) : '';
    error_log('Bionic GPT: Parsed message - ' . $message);
    if (empty($message)) {
        error_log('Bionic GPT: No message provided in POST request');
        wp_send_json_error(get_option('bionic_chatbot_error_message', 'No message provided.'));
        wp_die();
    }
    $api_key = get_option('bionic_chatbot_api_key', '');
    if (!$api_key) {
        error_log('Bionic GPT: No API key configured');
        wp_send_json_error(get_option('bionic_chatbot_error_message', 'API key not configured.'));
        wp_die();
    }
    $body = json_encode([
        'model' => get_option('bionic_chatbot_model', 'llama-3.3-70b-versatile'),
        'messages' => [['role' => 'user', 'content' => $message]],
        'temperature' => floatval(get_option('bionic_chatbot_temperature', '0.6')),
        'stream' => true
    ]);
    error_log('Bionic GPT Request Body: ' . $body);

    $response = wp_remote_post(get_option('bionic_chatbot_api_url', 'https://sparkpoint-ai.app/v1/chat/completions'), [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        ],
        'body' => $body,
        'timeout' => 30,
        'stream' => true,
        'filename' => tempnam(sys_get_temp_dir(), 'bionic_gpt_stream')
    ]);

    $response_code = wp_remote_retrieve_response_code($response);
    error_log('Bionic GPT Response Code: ' . $response_code);

    if (is_wp_error($response) || $response_code !== 200) {
        $error_message = is_wp_error($response) ? $response->get_error_message() : 'API returned status ' . $response_code;
        error_log('Bionic GPT Error: ' . $error_message);
        wp_send_json_error(get_option('bionic_chatbot_error_message', 'I did not quite understand, can you explain?'));
        wp_die();
    }

    $response_body = file_get_contents($response['filename']);
    unlink($response['filename']);
    error_log('Bionic GPT Raw Response Body: ' . $response_body);

    $full_content = '';
    $lines = explode("\n", trim($response_body));
    foreach ($lines as $line) {
        if (strpos($line, 'data: ') === 0 && $line !== 'data: [DONE]') {
            $json = json_decode(trim(substr($line, 6)), true);
            if (isset($json['choices'][0]['delta']['content'])) {
                $full_content .= $json['choices'][0]['delta']['content'];
            }
        }
    }

    if (empty($full_content)) {
        error_log('Bionic GPT: No content extracted from stream');
        wp_send_json_error(get_option('bionic_chatbot_error_message', 'No response from server.'));
        wp_die();
    }

    $response_json = [
        'object' => 'chat.completion',
        'model' => get_option('bionic_chatbot_model', 'llama-3.3-70b-versatile'),
        'choices' => [
            [
                'index' => 0,
                'finish_reason' => 'stop',
                'message' => [
                    'role' => 'assistant',
                    'content' => $full_content
                ]
            ]
        ]
    ];
    error_log('Bionic GPT Processed Response: ' . json_encode($response_json));
    wp_send_json($response_json);
    wp_die();
}
add_action('wp_ajax_bionic_gpt', 'bionic_gpt_proxy');
add_action('wp_ajax_nopriv_bionic_gpt', 'bionic_gpt_proxy');

// Admin Settings
function bionic_chatbot_admin_menu() {
    add_options_page(
        'SparkBot Settings',
        'SparkBot',
        'manage_options',
        'bionic-chatbot-settings',
        'bionic_chatbot_settings_page'
    );
}
add_action('admin_menu', 'bionic_chatbot_admin_menu');

function bionic_chatbot_settings_init() {
    register_setting('bionic_chatbot_settings', 'bionic_chatbot_api_key');
    register_setting('bionic_chatbot_settings', 'bionic_chatbot_error_message');
    register_setting('bionic_chatbot_settings', 'bionic_chatbot_model');
    register_setting('bionic_chatbot_settings', 'bionic_chatbot_temperature');
    register_setting('bionic_chatbot_settings', 'bionic_chatbot_api_url');

    add_settings_section(
        'bionic_chatbot_section',
        'SparkBot Configuration',
        null,
        'bionic_chatbot_settings'
    );

    add_settings_field(
        'bionic_chatbot_api_key',
        'API Key',
        'bionic_chatbot_api_key_callback',
        'bionic_chatbot_settings',
        'bionic_chatbot_section'
    );
    add_settings_field(
        'bionic_chatbot_error_message',
        'Error Message',
        'bionic_chatbot_error_message_callback',
        'bionic_chatbot_settings',
        'bionic_chatbot_section'
    );
    add_settings_field(
        'bionic_chatbot_model',
        'Model',
        'bionic_chatbot_model_callback',
        'bionic_chatbot_settings',
        'bionic_chatbot_section'
    );
    add_settings_field(
        'bionic_chatbot_temperature',
        'Temperature',
        'bionic_chatbot_temperature_callback',
        'bionic_chatbot_settings',
        'bionic_chatbot_section'
    );
    add_settings_field(
        'bionic_chatbot_api_url',
        'API URL',
        'bionic_chatbot_api_url_callback',
        'bionic_chatbot_settings',
        'bionic_chatbot_section'
    );
}
add_action('admin_init', 'bionic_chatbot_settings_init');

function bionic_chatbot_settings_page() {
    ?>
    <div class="wrap">
        <h1>SparkBot Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('bionic_chatbot_settings');
            do_settings_sections('bionic_chatbot_settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

function bionic_chatbot_api_key_callback() {
    $value = get_option('bionic_chatbot_api_key', '');
    echo '<input type="text" name="bionic_chatbot_api_key" value="' . esc_attr($value) . '" size="50" />';
}

function bionic_chatbot_error_message_callback() {
    $value = get_option('bionic_chatbot_error_message', 'I did not quite understand, can you explain?');
    echo '<input type="text" name="bionic_chatbot_error_message" value="' . esc_attr($value) . '" size="50" />';
}

function bionic_chatbot_model_callback() {
    $value = get_option('bionic_chatbot_model', 'llama-3.3-70b-versatile');
    echo '<input type="text" name="bionic_chatbot_model" value="' . esc_attr($value) . '" size="50" />';
}

function bionic_chatbot_temperature_callback() {
    $value = get_option('bionic_chatbot_temperature', '0.6');
    echo '<input type="number" step="0.1" min="0" max="1" name="bionic_chatbot_temperature" value="' . esc_attr($value) . '" />';
}

function bionic_chatbot_api_url_callback() {
    $value = get_option('bionic_chatbot_api_url', 'https://sparkpoint-ai.app/v1/chat/completions');
    echo '<input type="text" name="bionic_chatbot_api_url" value="' . esc_attr($value) . '" size="50" />';
}
?>