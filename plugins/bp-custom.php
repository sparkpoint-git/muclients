<?php
function wps_redirect_bp_login() {
  if( is_user_logged_in() && bp_is_register_page() ) {
    bp_core_redirect( get_option('home') . '/the-slug/' );
  }
}
add_action( 'template_redirect', 'wps_redirect_bp_login', 1 );
?>