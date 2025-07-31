<?php

// Defines
define( 'FL_CHILD_THEME_DIR', get_stylesheet_directory() );
define( 'FL_CHILD_THEME_URL', get_stylesheet_directory_uri() );

// Classes
require_once 'classes/class-fl-child-theme.php';

// Actions
add_action( 'wp_enqueue_scripts', 'FLChildTheme::enqueue_scripts', 1000 );
add_filter('mwai_openai_api_url', function($url) {
    error_log('API URL overridden to: https://your-bionic-gpt-instance/v1/chat/completions');
    return 'https://sparkpoint-ai.app/v1/chat/completions';
});

add_filter( 'forminator_pdf_templates', function( $templates ) {
    $template_path = WP_PLUGIN_DIR . '/forminator-addons-pdf/core/templates/template-fhcorp-pdf.php';
    
    if ( file_exists( $template_path ) ) {
        require_once $template_path;
        $templates['fhcorp_pdf'] = new Forminator_Template_FHCorp_PDF();
    } else {
        error_log( 'FHCorp PDF Template file not found at: ' . $template_path );
    }
    
    return $templates;
} );