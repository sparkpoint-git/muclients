<?php
/**
 * @package   Gantry 5 Theme
 * @author    RocketTheme http://www.rockettheme.com
 * @copyright Copyright (C) 2007 - 2017 RocketTheme, LLC
 * @license   GNU/GPLv2 and later
 *
 * http://www.gnu.org/licenses/gpl-2.0.html
 */

defined('ABSPATH') or die;

// Note: This file must be PHP 5.2 compatible.

// Check min. required version of Gantry 5
$requiredGantryVersion = '5.4.0';

// Bootstrap Gantry framework or fail gracefully.
$gantry_include = get_stylesheet_directory() . '/includes/gantry.php';
if (!file_exists($gantry_include)) {
    $gantry_include = get_template_directory() . '/includes/gantry.php';
}
$gantry = include_once $gantry_include;

if (!$gantry) {
    return;
}

if (!$gantry->isCompatible($requiredGantryVersion)) {
    $current_theme = wp_get_theme();
    $error = sprintf(__('Please upgrade Gantry 5 Framework to v%s (or later) before using %s theme!', 'g5_hydrogen'), strtoupper($requiredGantryVersion), $current_theme->get('Name'));

    if(is_admin()) {
        add_action('admin_notices', function () use ($error) {
            echo '<div class="error"><p>' . $error . '</p></div>';
        });
    } else {
        wp_die($error);
    }
}

/** @var \Gantry\Framework\Theme $theme */
$theme = $gantry['theme'];

// Theme helper files that can contain useful methods or filters
$helpers = array(
    'includes/helper.php', // General helper file
);

foreach ($helpers as $file) {
    if (!$filepath = locate_template($file)) {
        trigger_error(sprintf(__('Error locating %s for inclusion', 'g5_hydrogen'), $file), E_USER_ERROR);
    }

    require $filepath;
}

add_filter( 'bp_login_redirect', 'bpdev_redirect_to_profile', 11, 3 );
 
function bpdev_redirect_to_profile( $redirect_to_calculated, $redirect_url_specified, $user ){

	if( empty( $redirect_to_calculated ) )
		$redirect_to_calculated = admin_url();
 
	//if the user is not site admin,redirect to his/her profile

	if( isset( $user->ID) && ! is_super_admin( $user->ID ) )
		return bp_core_get_user_domain( $user->ID );
	else
		return $redirect_to_calculated; /*if site admin or not logged in,do not do anything much*/
 
}

/** Removing Others Content when on the BackEnd **/

add_action('pre_get_posts', 'query_set_only_author' );

function query_set_only_author( $wp_query ) {

 global $current_user;

 if( is_admin() && !current_user_can('edit_others_posts') ) {

    $wp_query->set( 'author', $current_user->ID );

    add_filter('views_edit-post', 'fix_post_counts');

    add_filter('views_upload', 'fix_media_counts');

 }

}

function fix_post_counts($views) {

 global $current_user, $wp_query;

 unset($views['mine']);

 $types = array(

    array( 'status' =>  NULL ),

    array( 'status' => 'publish' ),

    array( 'status' => 'draft' ),

    array( 'status' => 'pending' ),

    array( 'status' => 'trash' )

 );

 foreach( $types as $type ) {

    $query = array(

        'author'   => $current_user->ID,

        'post_type'   => 'post',

        'post_status' => $type['status']

    );

    $result = new WP_Query($query);

    if( $type['status'] == NULL ):

        $class = ($wp_query->query_vars['post_status'] == NULL) ? ' class="current"' : '';

        $views['all'] = sprintf(__('<a href="/%s"'. $class .'>All <span class="count">(%d)</span></a>', 'all'),

            admin_url('edit.php?post_type=post'),

            $result->found_posts);

    elseif( $type['status'] == 'publish' ):

        $class = ($wp_query->query_vars['post_status'] == 'publish') ? ' class="current"' : '';

        $views['publish'] = sprintf(__('<a href="/%s"'. $class .'>Published <span class="count">(%d)</span></a>', 'publish'),

               admin_url('edit.php?post_status=publish&post_type=post'),

            $result->found_posts);

    elseif( $type['status'] == 'draft' ):

        $class = ($wp_query->query_vars['post_status'] == 'draft') ? ' class="current"' : '';

        $views['draft'] = sprintf(__('<a href="/%s"'. $class .'>Draft'. ((sizeof($result->posts) > 1) ? "s" : "") .' <span class="count">(%d)</span></a>', 'draft'),

            admin_url('edit.php?post_status=draft&post_type=post'),

            $result->found_posts);

    elseif( $type['status'] == 'pending' ):

        $class = ($wp_query->query_vars['post_status'] == 'pending') ? ' class="current"' : '';

        $views['pending'] = sprintf(__('<a href="/%s"'. $class .'>Pending <span class="count">(%d)</span></a>', 'pending'),

               admin_url('edit.php?post_status=pending&post_type=post'),

            $result->found_posts);

    elseif( $type['status'] == 'trash' ):

        $class = ($wp_query->query_vars['post_status'] == 'trash') ? ' class="current"' : '';

        $views['trash'] = sprintf(__('<a href="/%s"'. $class .'>Trash <span class="count">(%d)</span></a>', 'trash'),

            admin_url('edit.php?post_status=trash&post_type=post'),

            $result->found_posts);

    endif;

 }

 return $views;

}

function fix_media_counts($views) {

 global $wpdb, $current_user, $post_mime_types, $avail_post_mime_types;

 $views = array();

 $_num_posts = array();

 $count = $wpdb->get_results( "

    SELECT post_mime_type, COUNT( * ) AS num_posts

    FROM $wpdb->posts

    WHERE post_type = 'attachment'

    AND post_author = $current_user->ID

    AND post_status != 'trash'

    GROUP BY post_mime_type

 ", ARRAY_A );

 foreach( $count as $row )

    $_num_posts[$row['post_mime_type']] = $row['num_posts'];

 $_total_posts = array_sum($_num_posts);

 $detached = isset( $_REQUEST['detached'] ) || isset( $_REQUEST['find_detached'] );

 if ( !isset( $total_orphans ) )

    $total_orphans = $wpdb->get_var("

        SELECT COUNT( * )

        FROM $wpdb->posts

        WHERE post_type = 'attachment'

        AND post_author = $current_user->ID

        AND post_status != 'trash'

        AND post_parent < 1

    ");

 $matches = wp_match_mime_types(array_keys($post_mime_types), array_keys($_num_posts));

 foreach ( $matches as $type => $reals )

    foreach ( $reals as $real )

        $num_posts[$type] = ( isset( $num_posts[$type] ) ) ? $num_posts[$type] + $_num_posts[$real] : $_num_posts[$real];

 $class = ( empty($_GET['post_mime_type']) && !$detached && !isset($_GET['status']) ) ? ' class="current"' : '';

 $views['all'] = "<a href='upload.php'$class>" . sprintf( __('All <span class="count">(%s)</span>', 'uploaded files' ), number_format_i18n( $_total_posts )) . '</a>';

 foreach ( $post_mime_types as $mime_type => $label ) {

    $class = '';

    if ( !wp_match_mime_types($mime_type, $avail_post_mime_types) )

        continue;

    if ( !empty($_GET['post_mime_type']) && wp_match_mime_types($mime_type, $_GET['post_mime_type']) )

        $class = ' class="current"';

    if ( !empty( $num_posts[$mime_type] ) )

        $views[$mime_type] = "<a href='upload.php?post_mime_type=$mime_type'$class>" . sprintf( translate_nooped_plural( $label[2], $num_posts[$mime_type] ), $num_posts[$mime_type] ) . '</a>';

 }

 $views['detached'] = '<a href="/upload.php?detached=1"' . ( $detached ? ' class="current"' : '' ) . '>' . sprintf( __( 'Unattached <span class="count">(%s)</span>', 'detached files' ), $total_orphans ) . '</a>';

 return $views;

}

/** Adding Filtering of Custom Fields **/

add_filter( 'parse_query', 'ba_admin_posts_filter' );
add_action( 'restrict_manage_posts', 'ba_admin_posts_filter_restrict_manage_posts' );
 
function ba_admin_posts_filter( $query )
{
    global $pagenow;
    if ( is_admin() && $pagenow=='edit.php' && isset($_GET['ADMIN_FILTER_FIELD_NAME']) && $_GET['ADMIN_FILTER_FIELD_NAME'] != '') {
        $query->query_vars['meta_key'] = $_GET['ADMIN_FILTER_FIELD_NAME'];
    if (isset($_GET['ADMIN_FILTER_FIELD_VALUE']) && $_GET['ADMIN_FILTER_FIELD_VALUE'] != '')
        $query->query_vars['meta_value'] = $_GET['ADMIN_FILTER_FIELD_VALUE'];
    }
}
 
function ba_admin_posts_filter_restrict_manage_posts()
{
    global $wpdb;
    $sql = 'SELECT DISTINCT meta_key FROM '.$wpdb->postmeta.' ORDER BY 1';
    $fields = $wpdb->get_results($sql, ARRAY_N);
?>
<select name="ADMIN_FILTER_FIELD_NAME">
<option value=""><?php _e('Filter By Custom Fields', 'baapf'); ?></option>
<?php
    $current = isset($_GET['ADMIN_FILTER_FIELD_NAME'])? $_GET['ADMIN_FILTER_FIELD_NAME']:'';
    $current_v = isset($_GET['ADMIN_FILTER_FIELD_VALUE'])? $_GET['ADMIN_FILTER_FIELD_VALUE']:'';
    foreach ($fields as $field) {
        if (substr($field[0],0,1) != "_"){
        printf
            (
                '<option value="%s"%s>%s</option>',
                $field[0],
                $field[0] == $current? ' selected="selected"':'',
                $field[0]
            );
        }
    }
?>
</select> <?php _e('Value:', 'baapf'); ?><input type="TEXT" name="ADMIN_FILTER_FIELD_VALUE" value="<?php echo $current_v; ?>" />
<?php
}