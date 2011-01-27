jQuery(function($) {
	jQuery("div#posthaste-form input#tags").suggest(
		phAjaxUrl + "?action=posthaste_ajax_tag_search", 
		{ delay: 350, minchars: 2, multiple: true, multipleSep: ", " } 
	);

	if (jQuery.suggest)
		jQuery("ul.ac_results").css("display", "none"); // Hide tag suggestion box if displayed
	
	jQuery("#post-submit").click(function(e) {
		if (jQuery.trim(jQuery("#post_content").val()).length < 1) {
			e.preventDefault();
			alert("There must be some content");
			jQuery("#post_content").css("border-color", "#333");
			jQuery("#post_content").focus();
		}
	});
	
	// On document ready actions:
	jQuery(document).ready(function() {
		
		// show/hide labels for title input and content textarea
		jQuery("#post_title, #post_content").focus(function(e) {
			jQuery("label[for='"+jQuery(this).attr("name")+"']").css("visibility", "hidden");
		}).blur(function(e) {
			if (jQuery(this).val().length < 1)
				jQuery("label[for='"+jQuery(this).attr("name")+"']").css("visibility", "visible");
		});
		
		// select visual (tinyMCE) editor, if applicable
		if (typeof switchEditors != 'undefined') {
			switchEditors.go('post_content', 'tinymce');
		}
	
	});
	
});
