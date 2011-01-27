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
Author: Andrew Patton
Author URI: http://www.acusti.ca
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
$phVars = array(
		'asidesCatName' => 'asides',
		'postValues' => '', // store values of a post in case of error to repopulate form
		'version' => '2.0.0'
	);



/************
 * FUNCTIONS 
 ************/

// When to display form
function posthasteDisplayCheck() {
	
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
function posthasteCustomCheck() {

	// TODO: figure out all this 'where to display' logic;
	// for now, we'll just show it on a user's profile page
	
	// check post types to see if they match settings
	
	// get post types (if empty, fill in defaults & then get them)
	if ( !$post_types = get_option( 'posthaste_post_types' ) ) {
		posthasteAddDefaultPostTypes();
		$post_types = get_option( 'posthaste_post_types' );
	}
	// how to do this:
	// 1. check if single; in that case, just use get_post_type
	
	if ( is_single() ) {
		$post_type = get_post_type();
		if ( $post_type && isset( $options[$post_type] ) ) {
			// TODO: check user's permissions
			return $post_type;
		}
	}
	else { // don't need to check if user is logged in; it's already been done
		// hack to display form and set it to post_type = auction if user has permission and global $bp->current_component == 'profile' && $bp->current_action == 'public' OR if it's the auction index page
		global $bp;
		if ( ( $bp->current_component == 'profile' && $bp->current_action == 'public' )
			|| ( $bp->current_component == 'activity' )
			|| ( is_page( 'auctions') ) ) {
			return 'auctions';
		}
		elseif ( current_user_can( 'publish_posts' ) ) {
			return 'post';
		}
		else {
			return false;
		}
	}

}

// Which category to use
function posthasteCatCheck() {
	if ( is_category() )
		return get_cat_ID ( single_cat_title( '', false ) );
	else 
		return get_option ( 'default_category', 1 );
}

// Which tag to use
function posthasteTagCheck() {
	if ( is_tag() ) {
		$taxarray = get_term_by( 'name', single_tag_title( '', false), 'post_tag', ARRAY_A );
		return $taxarray['name'];
	} else {
		return '';
	}
}

// Add to header
function posthasteHeader() {
	global $phVars;
	if ( 'POST' == $_SERVER['REQUEST_METHOD']
		&& !empty( $_POST['action'])
		&& $_POST['action'] == 'post'
		&& posthasteDisplayCheck() ) { // !is_admin() will get it on all pages

		if ( !is_user_logged_in() ) {
			wp_redirect( get_bloginfo( 'url' ) . '/' );
			exit;
		}
		
		// check capabilities (ignore post type that is returned)
		if ( !posthasteCustomCheck() ) {
			wp_redirect( get_bloginfo( 'url' ) . '/' );
			exit;
		}

		check_admin_referer( 'new-post' ); // check for valid nonce field
		
		global $current_user;
		
		// some special post title logic:		
		$post_title = trim (strip_tags( $_POST['post-title'] ) );
		if ( empty( $post_title ) ) {
			// title is required, so we will trim content for title 
			// & add to asides category if it exists (unless another
			// category was explicitly chosen in form)
			$post_title = strip_tags( $post_content );	   
			if( strlen( $post_title ) > 40 ) {
				$post_title = substr( $post_title, 0, 40 ) . ' … ';
			}	 
			// if "asides" category exists & title is empty, add to asides:
			if ( $asidesCatID = get_cat_id( $phVars['asidesCatName'] ) ) {
				$post_category = array( $asidesCatID ); 
			}
		}

		$the_post = array();

		$the_post['post_author'] = $current_user->user_id;
		$the_post['post_content'] = $_POST['post-content'];
		$the_post['post_title'] = $post_title;
		$the_post['post_type'] = $_POST['post-type'];
		
		// set post_status 
		if ( $_POST['postStatus'] == 'draft' ) {
			$the_post['post_status'] = 'draft';
		} else {
			$the_post['post_status'] = 'publish';
		}

		// create the post
		$post_id = wp_insert_post( $the_post );
		// note: you can close the comments or open the comments by setting the value for 'comment_status' key

		// now, the terms / taxonomies
		// for these, check only for $_POST variables specified in posthaste settings
		$fields = get_option( 'posthaste_fields' );
		$taxonomies = get_option( 'posthaste_taxonomies' );
		
		if ( $fields['categories'] && $cats = (int) $_POST['cats-select'] ) {
			wp_set_post_terms( $post_id, $cats, 'category' );
		}
		
		if ( $fields['tags'] && $tags = trim( $_POST['tags'] ) ) {
			// TODO: add serious clean up, syntax validation, etc. here
			//	can either turn the tags into an array or a comma separated string
			wp_set_post_terms( $post_id, $tags, 'post_tag' );
		}
		
		if ( count( $taxonomies ) > 1 ) { // there will always be one for the hidden value
			foreach ( $taxonomies as $name => $value ) {
				$taxonomy = $_POST['tax_input['.$name.']'];
				if ( $taxonomy ) {
					wp_set_post_terms( $post_id, $taxonomy, $name );
				}
			}
		}
		
		// include auction specific logic, if appropriate
		if ( $post_type == 'auctions' ) {
		
			// include prospress post helper file:
			require_once( WP_PLUGIN_DIR . '/prospress/pp-posts.php' );
			
			// TODO: fix these so they come from $_POST[]
			// Set end date to 2 weeks from now
			$post_end_date = date( 'Y-m-d H:i:s', current_time( 'timestamp', 0 ) + 60 * 60 * 24 * 14 );
			$post_end_date_gmt = get_gmt_from_date( $post_end_date );
			
			pp_schedule_end_post( $post_id, strtotime( $post_end_date_gmt ) ); // defined in prospress/pp-posts.php
			update_post_meta( $post_id, 'post_end_date', $post_end_date );
			update_post_meta( $post_id, 'post_end_date_gmt', $post_end_date_gmt );
			update_post_meta( $post_id, 'start_price', 2.71 );
		}
		
		$returnUrl = $_POST['posthasteUrl'];
		
		// now redirect back to blog
		if ( $post_status == 'draft' ) { 
			$postresult = "?posthaste=draft";
		} else { 
			$postresult = '?posthaste=' . $post_id; 
		}
		wp_redirect( $returnUrl . $postresult );
		exit;
	}
}

// the post form
function posthasteForm() {	
	// check if we should display form and get post type
	if ( is_user_logged_in() && posthasteDisplayCheck() && $post_type = posthasteCustomCheck() ) { 
	
		// get fields (if empty, fill in defaults & then get them)
		if ( !$fields = get_option( 'posthaste_fields' ) ) {
			posthasteAddDefaultFields(); 
			$fields = get_option( 'posthaste_fields' );
		}
		// get taxonomies (if empty, fill in defaults & then get them)
		if ( !$taxonomies = get_option( 'posthaste_taxonomies' ) ) {
			posthasteAddDefaultTaxonomies();
			$taxonomies = get_option( 'posthaste_taxonomies' );
		}
		// get info for current post type:
		$post_types = get_post_types( '', 'objects' );
		$post_type_name = $post_types[$post_type]->labels->singular_name;
		
		// Have we just successfully posted something?
		if ( isset( $_GET['posthaste'] ) ) : ?>
			<div class="posthaste-notice">
			<?php if ( $_GET['posthaste'] == 'draft' ) : ?>
			<?php echo $post_type_name ?> saved as draft. <a href="<?php echo get_bloginfo( 'wpurl' ) ?>/wp-admin/edit.php?post_status=draft">View drafts</a>.
			<?php else : ?>
			<?php echo $post_type_name ?> successfully published. <a href="<?php echo get_permalink( (int) $_GET['posthaste'] ) ?>">View it here</a>.
			<?php endif; ?>
			</div>
		<?php endif; ?>

	<div id="posthaste-form">
		<?php
		global $current_user;
		$user = get_userdata( $current_user->ID );
		$nickname = attribute_escape( $user->nickname ); ?>
		
		<form id="new-post" name="new-post" method="post">
			<input type="hidden" name="action" value="post" />
			
			<?php wp_nonce_field( 'new-post' ); ?>
			
			<input type="hidden" name="post-type" value="<?php echo $post_type ?>" />
			
			<?php if ( $fields['gravatar'] || $fields['greeting and links'] ) : ?>
			<div id="posthaste-intro">

			<?php if ( $fields['gravatar'] && function_exists( 'get_avatar' ) ) :
					global $current_user;
					echo get_avatar( $current_user->ID, 40 ); 
				endif; ?>

			<?php if ( $fields['greeting and links'] ) : ?>
			<b>Hello, <?php echo $nickname; ?>!</b> <a href="<?php bloginfo( 'wpurl' );  ?>/wp-admin/post-new.php" title="Go to the full WordPress editor">Write a new post</a>, <a href="<?php bloginfo( 'wpurl' );  ?>/wp-admin/" title="Manage the blog">Manage the blog</a>, or <?php wp_loginout(); ?>.
			<?php endif; ?>

			</div>
			<?php endif; ?>

			<?php if ( $fields['title'] ) : ?>
			<div class="title-wrap"><label for="post-title" class="hide-if-no-js">Enter title here</label>
			<input type="text" name="post-title" id="post-title" tabindex="1" />
			</div>
			<?php endif; ?>
			<div class="<?php echo user_can_richedit() ? 'rich-edit ' : '' ?>content-wrap">
				<label for="post-content" id="postLabel" class="hide-if-no-js">Enter details here</label>
			<?php
			// TODO: test to make sure everything still works with normal textarea (if (!user_can_richedit()))
			if ( user_can_richedit() ) :
				the_editor( '<p>Enter details here</p>', $id='post-content', $prev_id='switch', $media_buttons = false, $tab_index = 2 );
			else : ?>
				<textarea name="post-content" id="post-content" class="post-content" tabindex="2" ></textarea>
			<?php endif; ?>
			</div>

			<?php if ( $fields['tags'] ) : ?>
			<div class="tags-wrap"><label for="tags" class="tags-label">Tags:</label>
			<input type="text" name="tags" id="tags" tabindex="3" value="<?php echo posthasteTagCheck(); ?>" autocomplete="off" />
			</div>
			<?php else :
				$tagselect = posthasteTagCheck();
				echo '<input type="hidden" value="'
					  .$tagselect.'" name="tags" id="tags">';
			endif; ?>
			
			<?php if ( $fields['categories'] ) : ?>
			<div class="cats-wrap"></div><label for="cats" class="cats-label">Category:</label>
			<?php
				$catselect = posthasteCatCheck();
				wp_dropdown_categories( array(
					'hide_empty' => 0,
					'name' => 'cats-select',
					'orderby' => 'name',
					'class' => 'taxonomy-select',
					'hierarchical' => 1,
					'selected' => $catselect,
					'tab_index' => 3
					)
				); ?>
			</div>
			<?php elseif ( count( $taxonomies ) <= 1 ) : // only use a default category if no custom taxonomies will be used
				$catselect = posthasteCatCheck(); ?>
			<input type="hidden" value="<?php echo $catselect ?>" name="cats-select" id="cats-select">
			
			<?php endif; ?>

			<?php if ( $fields['draft'] ) : ?>
			<div class="draft-wrap"><input type="checkbox" name="postStatus" value="draft" id="postStatus">
			<label for="postStatus" id="postStatusLabel">Draft</label></div>
			<?php endif; ?>
			
			<?php if ( count( $taxonomies ) > 1 ) : // there will always be one for the hidden value
			$taxs_obj = get_taxonomies( '', 'object' );
				foreach ( $taxonomies as $name => $value ) :
					$taxonomy = $taxs_obj[$name];
					if ( !$taxonomy ) continue;
					$display_tax = false;
					foreach ( $taxonomy->object_type as $obj_type ) {
						if ( $post_type == $obj_type ) { // the taxonomy applies to the current post type
							$display_tax = true;
							break;
						}
					}
					if ( $display_tax ) : ?>
			
			<div class="<?php echo $taxonomy->name ?>-wrap"><label for="<?php echo $taxonomy->name ?>" class="taxonomy-label"><?php echo $taxonomy->labels->singular_label ?>:</label>
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
			
			<?php if ( $post_type == 'auctions' ) : // include auction specific elements ?>
			
			
			<?php endif; ?>
			
			<input type="hidden" value="<?php echo $_SERVER['REQUEST_URI']; ?>" name="posthasteUrl" >

			<input id="post-submit" type="submit" value="Create <?php echo strtolower( $post_type_name ) ?>" />

		   
		</form>
		<?php
		echo '</div> <!-- close posthasteForm -->'."\n";
	}
}


// remove action if loop is in sidebar, i.e. recent posts widget
function removePosthasteInSidebar() {
	remove_action( 'loop_start', posthasteForm );
}


// add css
function addPosthasteStylesheet() {
	if ( is_user_logged_in() && posthasteDisplayCheck() && posthasteCustomCheck() ) {	
	
		// Set url to stylesheet
		$pluginStyleURL = WP_PLUGIN_URL.'/'.basename( dirname( __FILE__ ) ).'/style.css';

		echo "\n".'<link rel="stylesheet" type="text/css" media="screen" href="'.$pluginStyleURL.'">'."\n";
				
	}
}


// add posthaste.js and dependencies
function addPosthasteJs() {
	if ( is_user_logged_in() && posthasteDisplayCheck() && posthasteCustomCheck() ) {
		global $phVars;
		
		wp_enqueue_script(
			'posthaste',  // script name
			plugins_url( '/posthaste.js', __FILE__ ), // url
			array( 	'jquery',
					'suggest' ), // dependencies
			$phVars['version']
		);
		//if (function_exists('add_thickbox')) add_thickbox();
		if ( user_can_richedit() ) {
			wp_enqueue_script( 'common' );
			wp_enqueue_script( 'utils' );
			wp_enqueue_script( 'post' );
			wp_enqueue_script( 'media-upload' );
			wp_enqueue_script( 'jquery-ui-core' );
			wp_enqueue_script( 'jquery-ui-tabs' );
			wp_enqueue_script( 'jquery-color' );
			wp_enqueue_script( 'tiny_mce' );
			wp_enqueue_script( 'editor' );
			wp_enqueue_script( 'editor-functions' );
			
			require_once( ABSPATH . '/wp-admin/includes/post.php' );
			
			wp_tiny_mce( true , // true makes the editor "teeny"
				array(
					'editor_selector' => 'post-content',
					'height' => '200px'
				)
			);
			
			remove_all_filters( 'mce_external_plugins' );
		}
	}
}


// Blatant copying from p2 here
function posthaste_ajax_tag_search() {
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


// pass wpurl from php to js
function posthaste_jsvars() {
	?><script>
		var phAjaxUrl = "<?php echo js_escape( get_bloginfo( 'wpurl' ) . '/wp-admin/admin-ajax.php' ); ?>";
	</script><?php
}


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
function posthasteAddDefaultPostTypes() {
	
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
function posthasteAddDefaultFields() {
	
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

// add default fields to db if db is empty
function posthasteAddDefaultTaxonomies() {
	
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

// add_settings_field
function posthasteSettingsInit() {

	// add the section
	add_settings_section(
		'posthaste_settings_section', 
		'Posthaste Settings', 
		'posthasteSettingsSectionCallback', 
		'writing'
	);

	// add 'display on' option
	add_settings_field(
		'posthaste_display', 
		'Display Posthaste on…',
		'posthasteDisplayCallback',
		'writing',
		'posthaste_settings_section'
	);
	register_setting( 'writing','posthaste_display' );
	
	// add 'display hook' option
	add_settings_field(
		'posthaste_action', 
		'Specify hook to trigger display',
		'posthasteActionCallback',
		'writing',
		'posthaste_settings_section'
	);
	register_setting( 'writing','posthaste_action' );
	
	// add post types selection
	add_settings_field(
		'posthaste_post_types', 
		'Post types to enable for frontend editing',
		'posthastePostTypesCallback',
		'writing',
		'posthaste_settings_section'
	);
	register_setting( 'writing','posthaste_post_types' );

	// add fields selection
	add_settings_field(
		'posthaste_fields', 
		'General elements to include in form',
		'posthasteFieldsCallback',
		'writing',
		'posthaste_settings_section'
	);
	register_setting( 'writing','posthaste_fields' );
	
	// add taxonomies selection
	add_settings_field(
		'posthaste_taxonomies', 
		'Specific taxonomies to include in form',
		'posthasteTaxonomiesCallback',
		'writing',
		'posthaste_settings_section'
	);
	register_setting( 'writing','posthaste_taxonomies' );
	
}

// prints the section description in Posthaste settings
function posthasteSettingsSectionCallback() {
	$post_types = get_post_types( array(), 'names' );
	$taxonomies = get_taxonomies( array(), 'names' );
	echo '<p>The settings below affect the behavior of the '
		.'<a href="http://wordpress.org/extend/plugins/posthaste/">Posthaste</a> '
		.'plugin.</p>';
			
}

function posthasteDisplayCallback() {
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

function posthasteActionCallback() {
	
	if ( !$action = get_option( 'posthaste_action' ) )
		$action = 'loop_start';
	
	echo "<input name=\"posthaste_action\" id=\"posthaste_action\" value=\"$action\" "
		. 'class="regular-text code" type="text" cols="25" />';
	echo '<span class="description">You can leave this as the default or use it customize both the placement and page type on which the form is displayed. For example, if you specify “ph_display_form” here, you could then add “do_action( \'ph_display_form\' );” wherever you want it displayed in your template.</span>';
	
}

// prints the post types and taxonomies lists in Posthaste settings
function posthastePostTypesCallback() {

	$post_types = get_post_types( '', 'objects' );
	
	$options = get_option( 'posthaste_post_types' );
	
	$update = ( !$options || isset( $options['reset'] ) );
	
	echo "<fieldset>\n";
	
	// update post types list / create from new (if applicable) & output html
	foreach ( $post_types as $post_type ) {
	
		// if there is nothing set for this post type, set it as on
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
		 . 'id="posthaste_post_types[hidden]">'
		 . "\n" . '<label for="posthaste_post_types[hidden]">&nbsp;<b>Reset this list</b>'
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
function posthasteFieldsCallback() {
	// fields you want in the form
	$fields = array( 'title', 'tags', 'categories', 'draft', 'gravatar', 'greeting and links' ); 

	// get options (if empty, fill in defaults & then get options)
	if ( !$options = get_option( 'posthaste_fields' ) ) { 
		posthasteAddDefaultFields(); 
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
function posthasteTaxonomiesCallback() {

	$post_types = get_post_types( '', 'objects' );
	
	$taxonomies = get_taxonomies( '', 'objects' );
	
	$options = get_option( 'posthaste_taxonomies' );
	
	$update = ( !$options || isset( $options['reset'] ) );
	
	/*
echo "<fieldset>\n";
	
	// update taxonomies list / create from new (if applicable) & add to post_types array
	foreach ( $taxonomies as $taxonomy ) {
		
		// 1. use $taxonomy->labels->singular_label for displayed name
		
		// if there is nothing set for this taxonomy, set it as on
		if ( $update ) {
			$options[ $taxonomy->name ] = 1;
		}
		
		$checked = $options[$taxonomy->name] ? ' checked="checked"' : '';

		$inputname = "posthaste_post_types[{$post_type->name}]";
		echo "<input{$checked} name=\"$inputname\" type=\"checkbox\" id=\"$inputname\">\n"
			. "<label for=\"$inputname\">&nbsp;" . $post_type->labels->name . "</label><br />\n";
		// for later use to display post types' labels in settings screen (will be returned) (doesn't make sense
		//$post_type_labels[ $post_type->name ] = $post_type->labels->name;
		
	}
	
	// add a reset option input (to make it possible to load new post types):
	echo '<input type="checkbox" value="1" name="posthaste_post_types[reset]" '
		 . 'id="posthaste_post_types[hidden]">'
		 . "\n" . '<label for="posthaste_post_types[hidden]">&nbsp;<b>Reset this list</b>'
		 . ' (use this if a custom post type didn’t show up in this list)</label>';		
	
	// add the hidden value too (stupid hack so "all off" will work, probably a better way)
	$options['hidden'] = 1;
	
	// …and output it
	echo "\n" . '<input type="hidden" value="1" '
		 .'name="posthaste_post_types[hidden]" id="posthaste_post_types[hidden]">';
	echo "\n" . '</fieldset>';

	// now add options to the db; update_option() adds options if they don’t exist or updates them if they do
	update_option( 'posthaste_post_types', $options );
*/
}

/************
 * ACTIONS 
 ************/
// add header content
add_action( 'get_header', posthasteHeader );
// add form at start of loop
// get option:
$action = get_option( 'posthaste_action' );
if ( !$action ) $action = 'loop_start';
add_action( $action, posthasteForm );
// don't display form in sidebar loop (i.e. 'recent posts')
add_action( 'get_sidebar', removePosthasteInSidebar );
// add the css
add_action( 'wp_head', addPosthasteStylesheet );
// add js
add_action( 'wp_print_scripts', addPosthasteJs );
// tell wp-admin.php about ajax tags function with wp_ajax_ action
add_action( 'wp_ajax_posthaste_ajax_tag_search', 'posthaste_ajax_tag_search' );
// load php vars for js
add_action( 'wp_head', 'posthaste_jsvars' );
// add options to "Writing" admin page in 2.7 and up
add_action( 'admin_init', posthasteSettingsInit );
