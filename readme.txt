=== Posthaste ===
Contributors: smajda, acustica
Author URI: http://jon.smajda.com
Plugin URI: http://wordpress.org/extend/plugins/posthaste/
Tags: post, frontend, custom post type, asides
Requires at least: 2.7
Tested up to: 3.0.4
Stable tag: 2.0.0

Allows you to create posts (of any built-in or custom post type) from the frontend in any theme. Features include support for custom taxonomies and post thumbnails, flexibility of placement, and a rich text editor,

== Description ==

Allows you to create posts (of any built-in or custom post type) from the frontend in any theme.

This plugin reuses code from the [Prologue](http://wordpress.org/extend/themes/prologue/) theme according to the terms of the GNU General Public License. So a big thanks to the authors of Prologue, Joseph Scott and Matt Thomas.

A few notes about the plugin's behavior: 

* You can control many aspects of the plugin's behaviour from its admin settings, which are located in “Settings -> Writing -> Posthaste Settings”.
* If you leave the “Title:” field blank, it takes the first 40 characters of the post content and makes that the title.
* If you leave the “Category:” box at its default setting (“Category...”) it posts to your default category. _However..._
* If you have a category named “asides”, it will put posts with empty titles into the ‘asides’ category even if you do not explicitly specify the ‘asides’ category in the dropdown. You can then [style them as asides](http://codex.wordpress.org/Adding_Asides).
* The included CSS is deliberately simple. If you want to customize the appearance of the form, just customize your own css files.

== Installation ==

Just upload the `posthaste` directory to `/wp-content/plugins/` and activate. To further customize how it works, see “Settings -> Writing -> Posthaste Settings”

== Frequently Asked Questions ==

= Can I customize the automatic “asides” behavior? =

If you call your “asides” category something other than “asides”, you can make Posthaste work with that. Go to “Settings -> Writing -> Posthaste Settings” and specify the slug of your category under “‘Asides’ category slug”. Or, if you just want to make sure this behaviour never happens, specifiy anything that _is not_ the name of a category on your blog.

= Help! I activated the plugin but the form isn’t showing up! =

It’s possible your theme has `get_sidebar()` placed _before_ the loop at the top of your theme (Most themes call `get_sidebar()` after the loop, but some do it before). This plugin attaches the form at the start of the loop by default, which usually works fine. In order to prevent the “Recent Posts” widget (or any other widgets which call [multiple loops](http://codex.wordpress.org/The_Loop#Multiple_Loops)) from _also_ causing the form to display, the plugin deactives the form once `get_sidebar()` is called. So if `get_sidebar()` runs first, the form won't appear in the “real loop” either.

There are a number of ways you can fix this. The simplest is to choose a different hook for when to load the form. You can specify a different hook in “Settings -> Writing -> Posthaste Settings”, where it says “Specify hook to trigger display”. This gives you complete flexibility both in when, during the loading of a page, the form is displayed, and, if you wish, on what pages the form is displayed. If, for example, you choose to use a custom hook, such as `ph_display_form`, you just need to add:

`<?php do_action( 'ph_display_form' ); ?>`

in any template file(s), at whichever point you want the Posthaste form to load.

== Screenshots ==

1. This is what the form looks like when being used to create a new page (not post) on the default Twenty Ten theme.
2. The form being used with [Prospress](http://prospress.org/) to create an auction.
3. Posthaste Settings: customize how the form is displayed. In Settings -> Writing.

== Changelog ==

= 2.0.0 =
* Added rich text editor
* Added support for custom post types (specifiable in the settings), with special support for the [Prospress](http://prospress.org/) marketplace plugin
* Added support for custom taxonomies (also specifiable in the settings)
* Added support to specify the hook for displaying the Posthaste form
* Added support for the user to be able to specify an “Asides” category from the settings
* Added support for uploading a post thumbnail (featured image)
* Refactored post creation process to rely heavily on WordPress’s `wp_insert_post()` function

= 1.3.2 =
* Bug fix: hierarchical categories now work properly (thanks: @twocolddogs)

= 1.3.1 =
* Bug fix: pages now redirect properly after posting with Draft checkbox disabled.

= 1.3 =
* You can now choose where you want the form to appear in Settings > Writing > Posthaste Settings. You can display the form on your Front Page (default), Front Page and all archive pages, all pages or only on the archive pages for a specific category.
* You can also now choose whether or not to display the “Hello” greeting and admin links.
* Category selection works a little different now. By default, the category dropdown selects your default category, unless you’re showing the form on a category archive page, in which case it selects the category for that page. If you aren’t displaying the category dropdown at all, it will post to your default category *unless* you're posting from a category archive page, then it will post to the category of the category archive page you’re on.
* Tag selection is similar: if you show the form on a tag archive page, that tag is auto-filled in the tag field (if it's visible) or auto-added to a new post from that page (if the tag field is not visible).

= 1.2.1 =
* Fixed gravatars 

= 1.2 = 
* Added auto-suggest feature to Tags field 
* Optional avatar display. 

= 1.1 =
* You can now choose which fields you want to show up under Settings -> Writing -> Posthaste Settings (WP 2.7 only). 
* Also adds a checkbox to save your post as a Draft. 

= 1.0.1 =
* Filters HTML out of title field. Just a one-line change. For blogs with a small, private groups of trusted authors who don't care about this, feel free to skip this update.

= 1.0 = 
* First release.
