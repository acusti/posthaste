<?php 
/*
Plugin Name: Posthaste
Plugin URI: http://wordpress.org/extend/plugins/posthaste/
Description: Adds a post box (originally from the Prologue theme, then modified by Jon Smajda to include a Title field, Category dropdown and a Save as Draft option) to any page, for any post type (including custom post types), and including any custom taxonomies as well as the default elements. Requires WordPress v 2.7 or higher
** TODO:
* Give a preview step in which it displays what the 'post' will look like for user to confirm (option in settings)
* Support for validation / error handling
* improving admin settings: put post types and taxonomy selects in two columns (can use -moz-column-count:2; -webkit-columns:2; -moz-column-gap:30px; -webkit-column-gap:30px;)
* separate frontend and backend functionality into 2 different files
Version: 2.0.0
Author: Jon Smajda / Andrew Patton
Author URI: http://jon.smajda.com
License: GPL
*/

/*
 * Copyright 2009 Jon Smajda (email: jon@smajda.com)
 * Forked & modified 2011 by Andrew Patton (email: andrew@acusti.ca)
 *
 * This plugin reuses code from the Prologue and P2 Themes,
 * Copyright Joseph Scott, Matt Thomas, Noel Jackson, and Automattic
 * according to the terms of the GNU General Public License.
 * http://wordpress.org/extend/themes/prologue/
 * http://wordpress.org/extend/themes/p2/
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.	 See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 * http://www.gnu.org/licenses/gpl.html
 * */

/***********
 * VARIABLES 
 ***********/
$ph_vars = array(
		'post_array' => '', // store values of a post in case of error to repopulate form
		'version' => '2.0.0',
		'prospress' => false
	);



/************
 * FUNCTIONS 
 ************/

// When to display form
function ph_display_check() {
	
	if ( !is_admin() ) {
		if ( !$display = get_option( 'posthaste_display' ))
			$display = 'front';
		
		switch ( $display ) {
			case 'front':
				if ( is_home() )
					return true;
				break;
			case 'archive':
				if ( is_archive() || is_home() )
					return true;
				break;
			case 'everywhere':
				return true;
				break;
			case ( (int) $display != 0 ):
				if ( is_category( $display ) )
					return true;
				break;
			default:
				return false;
		}
	}
	else {
		return false;
	}

}

// Check capabilities and post type
function ph_custom_check() {

	// TODO: figure out all this 'where to display' logic;
	// for now, we'll just show it on a user's profile page
	
	// check post types to see if they match settings
	
	// get post types (if empty, fill in defaults & then get them)
	if ( ! $options = get_option( 'posthaste_post_types' ) ) {
		ph_add_default_post_types();
		$options = get_option( 'posthaste_post_types' );
	}
	
	// how to do this:
	// 1. check if user is logged in
	if ( is_user_logged_in() ) {
	
		// 2. check if it's the prospress index
		if ( is_page('auctions') && isset( $options['auctions'] ) ) {
			return 'auctions';
		}
				
		// 3. check get_post_type(); if it gives us something usable, use it	
		$post_type = get_post_type();
		
		if ( $post_type ) {
		 	if ( isset( $options[$post_type] ) ) {
				// TODO: check user's permissions
				return $post_type;
			}
		}
		elseif ( current_user_can( 'publish_posts' ) ) {
			return 'post';
		}
	}	
	return false;
}

// Which category to use
function ph_cat_check() {
	if ( is_category() )
		return get_cat_ID( single_cat_title( '', false ) );
	else 
		return get_option( 'default_category', 1 );
}

// Which tag to use
function ph_tag_check() {
	if ( is_tag() ) {
		$taxarray = get_term_by( 'name', single_tag_title( '', false), 'post_tag', ARRAY_A );
		return $taxarray['name'];
	} else {
		return '';
	}
}

// Include prospress helper file, if necessary
function ph_load_prospress() {
	global $ph_vars;
	if ( ! $ph_vars['prospress'] ) {
		// include prospress post helper file:
		require_once( WP_PLUGIN_DIR . '/prospress/pp-posts.php' );
		$ph_vars['prospress'] = true;
	}
	return $ph_vars['prospress'];
}

// Create new post if it is the posthaste form submit
function ph_process_post() {
	global $ph_vars;
	if ( 'POST' == $_SERVER['REQUEST_METHOD']
		&& !empty( $_POST['action'])
		&& $_POST['action'] == 'post'
		&& ph_display_check() ) { // !is_admin() will get it on all pages
		
		// check capabilities (ignore post type that is returned)
		if ( !ph_custom_check() ) {
			wp_redirect( get_bloginfo( 'url' ) . '/' );
			exit;
		}
		
		check_admin_referer( 'new-post' ); // check for valid nonce field
		
		global $current_user;

		// TODO: clean up and prep $_POST['tags_input']
		//	add any other custom validation we want to do here
		
		// if title was kept empty, trim content for title 
		// & add to 'asides' category if it exists (unless another
		// category was explicitly chosen in form)
		if ( empty($_POST['post_title']) ) {
			$_POST['post_title'] = strip_tags( $_POST['post_content'] );	
			if ( strlen( $_POST['post_title'] ) > 40 ) {
				$_POST['post_title'] = substr( $_POST['post_title'], 0, 40 ) . ' … ';
			}
			
			if ( ! $asides_cat = get_option( 'posthaste_asides' ) )
				$asides_cat = 'asides';
			// if "asides" category exists & no category was specified, put in asides category
			if ( ( ! isset($_POST['post_category']) || ! $_POST['post_category'] )
				&& $asides_catid = get_cat_id($asides_cat) ) {
				$_POST['post_category'] = $asides_catid;
			}
		} 
		
		// create that post (wp_insert_post() does sanitizing, prepping of variables, everything!):
		$post_id = wp_insert_post( $_POST );
		
		// include auction specific logic, if appropriate
		if ( $_POST['post_type'] == 'auctions' && ph_load_prospress() ) {
			
			// starting price and buy now price
			$price_names = array( 'start_price', 'buy_now_price' );
			foreach ( $price_names as $price_name ) {
				$price = preg_replace( '/[^\d.]/', '', $_POST[$price_name] );
				$price = number_format( $price, 2, '.', '' );
				update_post_meta( $post_id, $price_name, $price );
			}
			
			if ( true ) { /* necessary for pp_schedule_end_post() */
				$yye = $_POST['yye'];
				$mme = $_POST['mme'];
				$dde = $_POST['dde'];
				$hhe = $_POST['hhe'];
				$mne = $_POST['mne'];
				$sse = $_POST['sse'];	
				$yye = ( $yye <= 0 ) ? date('Y' ) : $yye;
				$mme = ( $mme <= 0 ) ? date('n' ) : $mme;
				$dde = ( $dde > 31 ) ? 31 : $dde;
				$dde = ( $dde <= 0 ) ? date('j' ) : $dde;
				$hhe = ( $hhe > 23 ) ? $hhe -24 : $hhe;
				$mne = ( $mne > 59 ) ? $mne -60 : $mne;
				$sse = ( $sse > 59 ) ? $sse -60 : $sse;
				$post_end_date = sprintf( '%04d-%02d-%02d %02d:%02d:%02d', $yye, $mme, $dde, $hhe, $mne, $sse );
			
				$post_end_date_gmt = get_gmt_from_date( $post_end_date );
				
				pp_schedule_end_post( $post_id, strtotime( $post_end_date_gmt ) );// defined in /prospress/pp-posts.php
				update_post_meta( $post_id, 'post_end_date', $post_end_date );
				update_post_meta( $post_id, 'post_end_date_gmt', $post_end_date_gmt );
			}
			
		}
		// process post thumbnail
		if ( get_option('posthaste_post_thumbnail') && current_theme_supports('post-thumbnails', $_POST['post_type']) && post_type_supports($_POST['post_type'], 'thumbnail') && ! is_multisite() ) {
			if ( ! empty( $_FILES ) && isset($_FILES['post_thumbnail']) && ! $_FILES['error'] ) {
			
				if ( ( ($_FILES['post_thumbnail']['type'] == 'image/gif')
						|| ($_FILES['post_thumbnail']['type'] == 'image/jpeg')
						|| ($_FILES['post_thumbnail']['type'] == 'image/png' ) )
					&& ($_FILES['post_thumbnail']['size'] < 409600) ) {
				
					// code from http://goldenapplesdesign.com/2010/07/03/front-end-file-uploads-in-wordpress/
					require_once( ABSPATH . '/wp-admin/includes/image.php' );
					require_once( ABSPATH . '/wp-admin/includes/file.php' );
					require_once( ABSPATH . '/wp-admin/includes/media.php' );
				
					$attach_id = media_handle_upload( 'post_thumbnail', $post_id );
					
					// set as post thumbnail:
					update_post_meta( $post_id, '_thumbnail_id', $attach_id );
					
				}
				else {
					echo '<h5>Images must be either JPEG, GIF, or PNG and less than 400 KB</h5>';
				}
			}
			else {
				echo '<h5>There was an error uploading your featured image. Please try again.</h5>';
			}
		}
		
		// finishing up
		$returnUrl = $_POST['posthaste_url'];
		$urlGlue = ( strpos( $returnUrl, '?' ) === false ? '?' : '&' );
		
		// now redirect back to blog
		if ( $post_status == 'draft' ) { 
			$postresult =  $urlGlue . 'posthaste=draft';
		} else { 
			$postresult = $urlGlue . 'posthaste=' . $post_id; 
		}
		wp_redirect( $returnUrl . $postresult );
		exit;
	}
}
add_action( 'get_header', ph_process_post );


// the post form
function ph_display_form() {	
	// check if we should display form and get post type
	if ( ph_display_check() && $post_type = ph_custom_check() ) { 
		
		// ph_load_rich_editor() ties into this hook
		do_action( 'ph_before_form_display' );
			
		// get fields (if empty, fill in defaults & then get them)
		if ( !$fields = get_option( 'posthaste_fields' ) ) {
			ph_add_default_post_fields(); 
			$fields = get_option( 'posthaste_fields' );
		}
		// get taxonomies (if empty, fill in defaults & then get them)
		if ( !$taxonomies = get_option( 'posthaste_taxonomies' ) ) {
			ph_add_default_post_taxonomies();
			$taxonomies = get_option( 'posthaste_taxonomies' );
		}
		// get info for current post type:
		// TODO: modify to use get_post_type()
		$post_types = get_post_types( '', 'objects' );
		$post_type_name = $post_types[$post_type]->labels->singular_name;
		
		// set up posthaste_url:
		$posthaste_url = $_SERVER['REQUEST_URI'];
		
		// Have we just successfully posted something?
		if ( isset( $_GET['posthaste'] ) ) : ?>
			<div class="posthaste-notice">
			<?php if ( $_GET['posthaste'] == 'draft' ) : ?>
			<?php echo $post_type_name ?> saved as draft. <a href="<?php echo get_bloginfo( 'wpurl' ) ?>/wp-admin/edit.php?post_status=draft">View drafts</a>.
			<?php else : ?>
			<?php echo $post_type_name ?> successfully published. <a href="<?php echo get_permalink( (int) $_GET['posthaste'] ) ?>">View it here</a>.
			<?php endif; ?>
			</div>
			<?php // prepare posthaste_url
			if ( ( $phKey = strpos( $posthaste_url, 'posthaste=' ) ) !== false ) {
				$replace = substr( $posthaste_url, $phKey-1 );
				$phKeyEnd = strpos( $replace, '&', 1 );
				if ( $phKeyEnd ) $replace = substr( $replace, 1, $phKeyEnd );
				$posthaste_url = str_replace( $replace, '', $posthaste_url );
			}
			?>
		<?php endif; ?>

	<div id="posthaste-form">
		<?php
		global $current_user;
		$user = get_userdata( $current_user->ID );
		$nickname = attribute_escape( $user->nickname ); ?>
		
		<form id="new-post" name="new-post" method="post" enctype="multipart/form-data">
			<input type="hidden" name="action" value="post" />
			
			<?php wp_nonce_field( 'new-post' ); ?>
			
			<input type="hidden" name="post_type" value="<?php echo $post_type ?>" />
			
			<?php if ( $fields['gravatar'] || $fields['greeting and links'] ) : ?>
			<div id="posthaste-intro">

			<?php if ( $fields['gravatar'] && function_exists( 'get_avatar' ) ) :
					global $current_user;
					echo get_avatar( $current_user->ID, 40 ); 
				endif; ?>

			<?php if ( $fields['greeting and links'] ) : ?>
			<b>Hello, <?php echo $nickname; ?>!</b> <a href="<?php bloginfo( 'wpurl' );	?>/wp-admin/post-new.php" title="Go to the full WordPress editor">Write a new post</a>, <a href="<?php bloginfo( 'wpurl' );	 ?>/wp-admin/" title="Manage the blog">Manage the blog</a>, or <?php wp_loginout(); ?>.
			<?php endif; ?>

			</div>
			<?php endif; ?>

			<?php if ( $fields['title'] ) : ?>
			<div class="title-wrap"><label for="post_title" class="hide-if-no-js">Enter title here</label>
			<input type="text" name="post_title" id="post_title" />
			</div>
			<?php endif; ?>
			<div class="<?php echo user_can_richedit() ? 'rich-edit ' : '' ?>content-wrap">
				<label for="post_content" class="hide-if-no-js">Enter details here</label>
			<?php
			if ( user_can_richedit() ) :
				the_editor( '<p>Enter details here</p>', $id='post_content', $prev_id='switch', $media_buttons = false );
			else : ?>
				<textarea name="post_content" id="post_content" class="post_content"></textarea>
			<?php endif; ?>
			</div>
			
			<div class="general-fields">
				<?php if ( $fields['tags'] ) : ?>
				<div class="field-wrap tags-wrap"><label for="tags_input" class="tags-label">Tags:</label>
				<input type="text" name="tags_input" id="tags_input" value="<?php echo ph_tag_check(); ?>" autocomplete="off" />
				</div>
				<?php else :
					$tagselect = ph_tag_check();
					echo '<input type="hidden" value="'
							.$tagselect.'" name="tags_input" id="tags_input">';
				endif; ?>
				
				<?php if ( $fields['categories'] ) : ?>
				<div class="field-wrap cats-wrap"><label for="post_category" class="cats-label">Category:</label>
				<?php
					$catselect = ph_cat_check();
					wp_dropdown_categories( array(
						'hide_empty' => 0,
						'name' => 'post_category',
						'orderby' => 'name',
						'class' => 'taxonomy-select',
						'hierarchical' => 1,
						'selected' => $catselect/*,
						'tab_index' => 4*/
						)
					); ?>
				</div>
				<?php elseif ( count( $taxonomies ) <= 1 ) : // only use a default category if no custom taxonomies will be used
					$catselect = ph_cat_check(); ?>
				<input type="hidden" value="<?php echo $catselect ?>" name="post_category" id="post_category">
				
				<?php endif; ?>
	
				<?php if ( $fields['draft'] ) : ?>
				<div class="field-wrap status-wrap"><input type="checkbox" name="post_status" value="draft" id="post_status">
				<label for="post_status">Draft</label></div>
				<?php else : ?>
				<input type="hidden" name="post_status" value="publish">
				<?php endif; ?>
				
				<?php if ( count( $taxonomies ) > 1 ) : // there will always be one for the hidden value
				$taxs_obj = get_taxonomies( '', 'object' );
					foreach ( $taxonomies as $name => $value ) :
						$taxonomy = $taxs_obj[$name];
						if ( !$taxonomy ) continue;
						$display_tax = is_object_in_taxonomy( $post_type, $name );
	/*					foreach ( $taxonomy->object_type as $obj_type ) {
							if ( $post_type == $obj_type ) { // the taxonomy applies to the current post type
								$display_tax = true;
								break;
							}
						} */
						if ( $display_tax ) : ?>
				
				<div class="field-wrap <?php echo $taxonomy->name ?>-wrap"><label for="tax_input[<?php echo $taxonomy->name ?>]" class="taxonomy-label"><?php echo $taxonomy->labels->singular_label ?>:</label>
				<?php
					wp_dropdown_categories( array(
						'hide_empty' => 0,
						'name' => 'tax_input['.$taxonomy->name.']',
						'orderby' => 'name',
						'class' => 'taxonomy-select',
						'hierarchical' => 1,
						'taxonomy' => $taxonomy->name,
						'hide_if_empty' => 1
						)
					); ?>
				</div>
					<?php endif;
					endforeach;
				endif; ?>
			</div>
			
			<?php if ( $post_type == 'auctions' ) : // include auction specific elements
			
			// end date (default to 1 week from now)
			$end_date = date_i18n( $datef, strtotime( gmdate( 'Y-m-d H:i:s', ( time() + 604800 + ( get_option( 'gmt_offset' ) * 3600 ) ) ) ) );
			
			$end_stamp = __('End on: <b>%1$s</b>', 'prospress' );
	?>
			<div class="prospress-fields">
				<div class="field-wrap curtime">
					<span id="endtimestamp">
					<?php printf( $end_stamp, $end_date); ?></span>
					<a href="#edit_endtimestamp" class="edit-endtimestamp hide-if-no-js" tabindex='4'><?php ('completed' != $post->post_status) ? _e('Edit', 'prospress' ) : _e('Extend', 'prospress' ); ?></a>
					<div id="endtimestampdiv" class="hide-if-js">
						<?php pp_touch_end_time( ( $action == 'edit' ), 5 ); ?>
					</div>
				</div>
				<div class="field-wrap price-wrap">
					<label for="start_price" class="taxonomy-label price-label">Starting Price: $</label>
					<input type="text" name="start_price" id="start_price" value="1.00" autocomplete="off" />
				</div>
				<div class="field-wrap buy-now-wrap">
					<label for="buy_now_price" class="taxonomy-label price-label">Buy Now Price: $</label>
					<input type="text" name="buy_now_price" id="buy_now_price" value="0.00" autocomplete="off" />
				</div>
			</div>
			
			<?php endif; ?>

			<?php if ( get_option('posthaste_post_thumbnail') && current_theme_supports('post-thumbnails', $post_type) && post_type_supports($post_type, 'thumbnail') && ! is_multisite() ) : ?>
			<div class="image-fields">
			<?php // with a max file size of 400 KB: ?>
				<div class="field-wrap">
					<label for="post_thumbnail" class="taxonomy-label upload-label">Featured image (must be under 400 KB):</label>
					<input type="hidden" name="MAX_FILE_SIZE" value="409600" />
					<input type="file" name="post_thumbnail" id="post_thumbnail" />
				</div>
			</div>
			<?php endif; ?>
			
			<input type="hidden" value="<?php echo $posthaste_url ?>" name="posthaste_url" >

			<input id="post-submit" type="submit" value="Create <?php echo strtolower( $post_type_name ) ?>" />

			 
		</form>
		<?php
		echo '</div> <!-- close ph_display_form -->'."\n";
	}
}
// add form at user defined hook or start of loop
// get option:
$action = get_option( 'posthaste_action' );
if ( !$action )
	$action = 'loop_start';
add_action( $action, ph_display_form );


// remove action if loop is in sidebar, i.e. recent posts widget,
// and if posthaste is set to load at the start of the loop
function ph_remove_in_sidebar() {
	$action = get_option( 'posthaste_action' );
	// if posthaste is set to load at the start of the loop
	if ( ! $action || $action == 'loop_start' )
		remove_action( 'loop_start', ph_display_form );
}
add_action( 'get_sidebar', ph_remove_in_sidebar );


// add posthaste's style.css
function ph_load_styles() {
	if ( ph_display_check() && $post_type = ph_custom_check() ) {
		
		wp_enqueue_style( 'posthaste', plugins_url( '/style.css', __FILE__ ) );
		
		if ( $post_type == 'auctions' ) {
			wp_enqueue_style( 'prospress-post', get_bloginfo( 'wpurl' ) . '/wp-content/plugins/prospress/pp-posts/pp-post-admin.css' );
		}
		
		if ( user_can_richedit() ) {
			wp_enqueue_style( 'thickbox' );
		}				
	}
}
add_action( 'wp_print_styles', ph_load_styles );


// add posthaste.js and dependencies
function ph_load_js() {
	if ( ph_display_check() && $post_type = ph_custom_check() ) {
		global $ph_vars;
		
		wp_enqueue_script(
			'posthaste',	 // script name
			plugins_url( '/posthaste.js', __FILE__ ), // url
			array( 	'jquery',
					'suggest' ), // dependencies
			$ph_vars['version']
		);
				
		if ( $post_type == 'auctions' && ph_load_prospress()/* necessary for pp_touch_end_time(), which displays auction end dates */ ) {		
			wp_enqueue_script( 'prospress-post', get_bloginfo( 'wpurl' ) . '/wp-content/plugins/prospress/pp-posts/pp-post-admin.js' );
			wp_localize_script( 'prospress-post', 'ppPostL10n', array(
				'endedOn' => __( 'Ended on:', 'prospress' ),
				'endOn' => __( 'End on:', 'prospress' ),
				'end' => __( 'End', 'prospress' ),
				'update' => __( 'Update', 'prospress' ),
				'repost' => __( 'Repost', 'prospress' ),
			) );
		}
	}
}
add_action( 'wp_print_scripts', ph_load_js );


// add rich text editor dependencies and code
function ph_load_rich_editor() {

	if ( ph_display_check() && ph_custom_check() && user_can_richedit() ) {
		
		// this is only called if the posthaste form actually gets loaded,
		// so it doesn't go in the <head>, hence why we need to use
		// wp_print_scripts() instead of wp_enqueue_script()
		wp_print_scripts( 'thickbox' );
		wp_print_scripts( 'common' );
		wp_print_scripts( 'utils' );
		wp_print_scripts( 'post' );
		//wp_print_scripts( 'media-upload' );
		wp_print_scripts( 'jquery-ui-core' );
		wp_print_scripts( 'jquery-ui-tabs' );
		wp_print_scripts( 'jquery-color' );
		wp_print_scripts( 'tiny_mce' );
		wp_print_scripts( 'editor' );
		wp_print_scripts( 'editor-functions' );
		
		// tiny MCE javascript helper function: ?>
<script>
	function phMceCustom(ed) {
		ed.onPostProcess.add(function(ed, o) {
			o.content = o.content.replace(/(<[^ >]+)[^>]*?( href="[^"]*")?[^>]*?(>)/g, "$1$2$3"); /* strip all attributes */
		});
	}
</script>
		<?php
		// necessary for wp_tiny_mce()
		require_once( ABSPATH . '/wp-admin/includes/post.php' );
		
		// TODO: need to test editor functionality; does it strip <img />, for example?
		wp_tiny_mce( true , // true makes the editor "teeny"
			array(
				'editor_selector' => 'post_content',
				'height' => '200px',
				'theme_advanced_buttons1' => 'bold,italic,underline,|,'/*.'justifyleft,justifycenter,justifyright,justifyfull,formatselect,'*/.'bullist,numlist,|,outdent,indent,|,link,unlink,|,undo,redo',
				'theme_advanced_buttons2' => '',
				'theme_advanced_buttons3' => '',
				'theme_advanced_buttons4' => '',
				'setup' => 'phMceCustom'
			)
		);
		
		remove_all_filters( 'mce_external_plugins' );
	}
}
// load all of these dependencies only when the form
// is actually being displayed; prevents js errors w. the editor js
add_action( 'ph_before_form_display', ph_load_rich_editor );


// Blatant copying from p2 here
function ph_ajax_tag_search() {
	global $wpdb;
	$s = $_GET['q'];
	if ( false !== strpos( $s, ',' ) ) {
		$s = explode( ',', $s );
		$s = $s[count( $s ) - 1];
	}
	$s = trim( $s );
	if ( strlen( $s ) < 2 )
		die; // require 2 chars for matching

	$results = $wpdb->get_col( "SELECT t.name 
		FROM $wpdb->term_taxonomy 
		AS tt INNER JOIN $wpdb->terms 
		AS t ON tt.term_id = t.term_id 
		WHERE tt.taxonomy = 'post_tag' AND t.name 
		LIKE ('%". like_escape( $wpdb->escape( $s )	 ) . "%')" );
	echo join( $results, "\n" );
	exit;
}
// tell wp-admin.php about ajax tags function with wp_ajax_ action
add_action( 'wp_ajax_ph_ajax_tag_search', 'ph_ajax_tag_search' );


// pass wpurl from php to js
function ph_load_js_vars() {
	if ( ph_display_check() && ph_custom_check() ) :
	?><script>
		var phAjaxUrl = "<?php echo js_escape( get_bloginfo( 'wpurl' ) . '/wp-admin/admin-ajax.php' ); ?>";
	</script><?php
	endif;
}
add_action( 'wp_print_scripts', 'ph_load_js_vars' );


/*
 * SETTINGS
 *
 * Modifiable in: Settings -> Writing -> Posthaste Settings
 *
 */

/**
 * Functions to populate the options if user has never touched posthaste settings 
 */
 
// add default post types to db if db is empty
function ph_add_default_post_types() {
	
	// TODO: test this
	$post_types = get_post_types( '', 'objects' );
	
	$options = array();
	
	// fill options from post types
	foreach ( $post_types as $post_type ) {	
		$options[ $post_type->name ] = 1;
	}
	
	// add the hidden value too
	$options['hidden'] = 1;
	
	// now add options to the db; update_option() adds options if they don’t exist or updates them if they do
	update_option( 'posthaste_post_types', $options, '', 'yes' );
}

// add default fields to db if db is empty
function ph_add_default_post_fields() {
	
	// fields that are on by default:
	$fields = array( 'title', 'tags', 'categories', 'draft', 'greeting and links' ); 

	// fill in options array with each field on
	$options = array();
	foreach ( $fields as $field ) {
		$options[$field] = 1;
	}

	// add the hidden value too
	$options['hidden'] = 1;

	// now add options to the db 
	add_option( 'posthaste_fields', $options, '', 'yes' );
}

// add default taxonomies to db if db is empty
function ph_add_default_post_taxonomies() {
	
	// fields that are on by default:
	$taxonomies = get_taxonomies( '', 'objects' );

	// fill in options array with each field on
	$options = array();
	foreach ( $taxonomies as $taxonomy ) {
		if ( $taxonomy->show_ui == 1 && !$taxonomy->_builtin ) {
			$options[$taxonomy->name] = 1;
		}
	}

	// add the hidden value too
	$options['hidden'] = 1;

	// now add options to the db 
	update_option( 'posthaste_taxonomies', $options, '', 'yes' );
}


/**
 * Functions for displaying admin settings
 */

// add settings options to "Writing" page
function ph_register_settings() {

	// add the section
	add_settings_section(
		'posthaste_settings_section', 
		'Posthaste Settings', 
		'ph_settings_description_callback', 
		'writing'
	);

	// add 'display on' option
	add_settings_field(
		'posthaste_display', 
		'Display Posthaste on…',
		'ph_settings_display_callback',
		'writing',
		'posthaste_settings_section'
	);
	register_setting( 'writing','posthaste_display' );
	
	// add 'display hook' option
	add_settings_field(
		'posthaste_action', 
		'Specify hook to trigger display',
		'ph_settings_action_callback',
		'writing',
		'posthaste_settings_section'
	);
	register_setting( 'writing','posthaste_action' );
	
	// add post types selection
	add_settings_field(
		'posthaste_post_types', 
		'Post types to enable for frontend editing',
		'ph_settings_post_types_callback',
		'writing',
		'posthaste_settings_section'
	);
	register_setting( 'writing','posthaste_post_types' );

	// add fields selection
	add_settings_field(
		'posthaste_fields', 
		'General elements to include in form',
		'ph_settings_fields_callback',
		'writing',
		'posthaste_settings_section'
	);
	register_setting( 'writing','posthaste_fields' );
	
	// add taxonomies selection
	add_settings_field(
		'posthaste_taxonomies', 
		'Specific taxonomies to include in form',
		'ph_settings_taxonomies_callback',
		'writing',
		'posthaste_settings_section'
	);
	register_setting( 'writing','posthaste_taxonomies' );
	
	// add featured image option
	add_settings_field(
		'posthaste_post_thumbnail', 
		'Post thumbnail',
		'ph_settings_post_thumbnail_callback',
		'writing',
		'posthaste_settings_section'
	);
	register_setting( 'writing','posthaste_post_thumbnail' );
	
	// add featured image option
	add_settings_field(
		'posthaste_asides', 
		'“Asides” category slug',
		'ph_settings_asides_callback',
		'writing',
		'posthaste_settings_section'
	);
	register_setting( 'writing','posthaste_asides' );	
}
add_action( 'admin_init', ph_register_settings );


// prints the section description in Posthaste settings
function ph_settings_description_callback() {
	$post_types = get_post_types( array(), 'names' );
	$taxonomies = get_taxonomies( array(), 'names' );
	echo '<p>The settings below affect the behavior of the '
		.'<a href="http://wordpress.org/extend/plugins/posthaste/">Posthaste</a> '
		.'plugin.</p>';
			
}

function ph_settings_display_callback() {
	// get current values
	if ( !$select = get_option( 'posthaste_display') )
		$select = 'front';

	$options = array(
				'front' => 'Front Page', 
				'archive' => 'Front and Archive Pages',
				'everywhere' => 'Everywhere (user is permitted)',
				'catheader' => 'Single Category Page:'
			);

	$cats = get_categories( array(
				'hide_empty' => 0,
				'hierarchical' => 0
			) );

	foreach ( $cats as $cat ) {
		$options[ $cat->cat_ID ] = $cat->cat_name;
	}


	// build the dropdown menu
	echo '<select name="posthaste_display" id="posthaste_display">';

	foreach ( $options as $key=>$value ) {
		if ( $select == $key )
			$selected = ' selected="selected"';
		if ( $key == 'catheader' )
			$disabled = ' disabled="disabled"';
		echo "<option value=\"$key\"$selected$disabled>$value</option>\n";
		unset( $selected, $disabled );
	}	

	echo '</select>';

}

// prints the textbox to specify Posthaste hook trigger
function ph_settings_action_callback() {
	
	if ( !$action = get_option( 'posthaste_action' ) )
		$action = 'loop_start';
	
	echo '<input name="posthaste_action" id="posthaste_action" value="' .  $action . '" class="code" type="text" />';
	echo '<span class="description">&nbsp;&nbsp;You can leave this as the default or use it customize both the placement and page type on which the form is displayed. For example, if you specify “ph_display_form” here, you could then add “do_action( \'ph_display_form\' );” wherever you want it displayed in your template.</span>';
	
}

// prints the post types list in Posthaste settings
function ph_settings_post_types_callback() {

	$post_types = get_post_types( '', 'objects' );
	
	$options = get_option( 'posthaste_post_types' );
	
	$update = ( !$options || isset( $options['reset'] ) );
	
	echo "<fieldset>\n";
	
	// update post types list / create from new (if applicable) & output html
	foreach ( $post_types as $post_type ) {
	
		// if it's an update, set it as on
		if ( $update ) {
			$options[ $post_type->name ] = 1;
		}
		
		$checked = $options[$post_type->name] ? ' checked="checked"' : '';
		
/*
		$inputname = "posthaste_post_types[$name]";
		echo "<input{$checked} name=\"$inputname\" type=\"checkbox\" id=\"$inputname\">\n"
			. "<label for=\"$inputname\">&nbsp;" . $name . "</label><br />\n";
*/
		$inputname = "posthaste_post_types[{$post_type->name}]";
		echo "<input{$checked} name=\"$inputname\" type=\"checkbox\" id=\"$inputname\">\n"
			. "<label for=\"$inputname\">&nbsp;" . $post_type->labels->name . "</label><br />\n";
		// for later use to display post types' labels in settings screen (will be returned) (doesn't make sense
		//$post_type_labels[ $post_type->name ] = $post_type->labels->name;
		
	}
	
	// add a reset option input (to make it possible to load new post types):
	echo '<input type="checkbox" value="1" name="posthaste_post_types[reset]" '
		 . 'id="posthaste_post_types[reset]">'
		 . "\n" . '<label for="posthaste_post_types[reset]">&nbsp;<b>Reset this list</b>'
		 . ' (use this if the post types list seems out of date)</label>';
	
	// add the hidden value too (stupid hack so "all off" will work, probably a better way)
	$options['hidden'] = 1;
	
	// …and output it
	echo "\n" . '<input type="hidden" value="1" '
		 .'name="posthaste_post_types[hidden]" id="posthaste_post_types[hidden]">';
	echo "\n" . '</fieldset>';

	// now add options to the db; update_option() adds options if they don’t exist or updates them if they do
	update_option( 'posthaste_post_types', $options );
}

// prints the fields selects in Posthaste settings
function ph_settings_fields_callback() {
	// fields you want in the form
	$fields = array( 'title', 'tags', 'categories', 'draft', 'gravatar', 'greeting and links' ); 

	// get options (if empty, fill in defaults & then get options)
	if ( !$options = get_option( 'posthaste_fields' ) ) { 
		ph_add_default_post_fields(); 
		$options = get_option( 'posthaste_fields' );
	}

	if ( !empty( $options ) ) {
		$options = get_option( 'posthaste_fields' );
		echo "<fieldset>\n";
		foreach ( $fields as $field ) {
			// see if it should be checked or not
			unset( $checked );
			if ( $options[$field] ) { $checked = ' checked="checked"';}

			// print the checkbox
			$fieldname = "posthaste_fields[$field]";
			echo "<input{$checked} name=\"$fieldname\" type=\"checkbox\" id=\"$fieldname\">\n"
				."<label for=\"$fieldname\">&nbsp;" . ucfirst( $field ) . "\n</label><br />\n";
		}
		// now the hidden input (stupid hack so "all off" will work, probably a better way)
		echo '<input type="hidden" value="1" '
			 .'name="posthaste_fields[hidden]" id="posthaste_fields[hidden]">';
		echo '</fieldset>';
	}
}

// prints the post types and taxonomies lists in Posthaste settings
function ph_settings_taxonomies_callback() {

	$post_types = get_post_types( '', 'objects' );
	
	$taxonomies = get_taxonomies( '', 'objects' );
	
	$options = get_option( 'posthaste_taxonomies' );
	
	$update = ( !$options || isset( $options['reset'] ) );
	
	echo "<fieldset>\n";
	
	// update taxonomies list / create from new (if applicable) & add to post_types array
	foreach ( $taxonomies as $taxonomy ) {

		if ( $taxonomy->show_ui == 1 && !$taxonomy->_builtin ) {
			
			// if it's an update, set it as on
			if ( $update ) {
				$options[ $taxonomy->name ] = 1;
			}
			
			$checked = $options[$taxonomy->name] ? ' checked="checked"' : '';
	
			$inputname = "posthaste_taxonomies[{$taxonomy->name}]";
			echo "<input{$checked} name=\"$inputname\" type=\"checkbox\" id=\"$inputname\">\n"
				. "<label for=\"$inputname\">&nbsp;" . $taxonomy->labels->singular_label . "</label><br />\n";
		}

	}
	
	// add a reset option input (to make it possible to load new post types):
	echo '<input type="checkbox" value="1" name="posthaste_taxonomies[reset]" '
		 . 'id="posthaste_taxonomies[reset]">'
		 . "\n" . '<label for="posthaste_taxonomies[reset]">&nbsp;<b>Reset this list</b>'
		 . ' (use this if a custom taxonomy didn’t show up in this list)</label>';
	
	// add the hidden value too (stupid hack so "all off" will work, probably a better way)
	$options['hidden'] = 1;
	
	// …and output it
	echo "\n" . '<input type="hidden" value="1" '
		.'name="posthaste_taxonomies[hidden]" id="posthaste_taxonomies[hidden]">';
	echo "\n" . '</fieldset>';

	// now add options to the db; update_option() adds options if they don’t exist or updates them if they do
	update_option( 'posthaste_taxonomies', $options );
}

// prints the checkbox for includng the post thumbnail element
function ph_settings_post_thumbnail_callback() {
	
	$post_thumbnail = get_option( 'posthaste_post_thumbnail' );
	
	echo '<input type="checkbox" value="1" name="posthaste_post_thumbnail"'
		. ( $post_thumbnail ? ' checked="checked"' : '' ) . ' id="posthaste_post_thumbnail">'
		. "\n" . '<label for="posthaste_post_thumbnail">&nbsp;Add post thumbnail field</label>';
	echo '<span class="description">&nbsp;&nbsp;This option adds a post thumbnail element with a file uploader to your form if it is supported by your theme. Your current theme <strong>'
		. ( current_theme_supports( 'post-thumbnails' ) ? 'does' : 'does not' )
		. '</strong> support post thumbnails.</span>';
			
}

// prints the textbox to specify Posthaste “asides” category
function ph_settings_asides_callback() {
	
	if ( ! $asides_cat = get_option( 'posthaste_asides' ) )
		$asides_cat = 'asides';
	
	echo '<input name="posthaste_asides" id="posthaste_asides" value="' . $asides_cat . '" type="text" />';
	echo '<span class="description">&nbsp;&nbsp;If this means nothing to you, just ignore it. Here, you can specify the slug of your <a href="http://codex.wordpress.org/Adding_Asides">“asides” category</a>, which the plugin will use automatically as a post’s category if no title or category is specified in the form (and the category exists).</span>';
	
}

// quick link on the plugin admin page for Posthaste meta
function ph_register_plugin_links( $links, $file ) {
	$base = plugin_basename( __FILE__ );

	if ( $file == $base ) {
		$links[] = '<a href="options-writing.php">Settings</a>';
	}
	return $links;
}
add_filter( 'plugin_row_meta', 'ph_register_plugin_links', 10, 2 );
