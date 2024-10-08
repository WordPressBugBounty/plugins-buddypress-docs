jQuery(document).ready(function($){
	var doc_id = $( '#existing-doc-id' ).val();

	// BP should do this, but sometimes doesn't
	$( 'body' ).removeClass( 'no-js' ).addClass( 'js' );

	/* Unhide JS content */
	$('.hide-if-no-js').show();

	// If this is an edit page, set the lock
	if ( $( 'body' ).hasClass( 'bp-docs-edit' ) ) {
		var lock_data = {
			action: 'add_edit_lock',
			doc_id: doc_id
		};

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: lock_data,
			success: function(response){
				return true;
			}
		});
	}

	$( '.doctable' ).on('click', '.bp-docs-attachment-clip', function(e) {
		var att_doc_id = $(e.target).closest('.bp-docs-attachment-clip').attr('id').split('-').pop();
		var att_doc_drawer = $('#bp-docs-attachment-drawer-'+att_doc_id);
		att_doc_drawer.slideToggle( 400 );
	});

	$('#doc-attachments-ul > li').each( function( i ) {
		$(this).addClass( (i + 1) % 2 ? 'odd' : 'even' );
	});

	// Fix the wonky tabindex on Text mode
	$('input#doc-permalink').on('keydown',function(e){
		focus_in_content_area(e);
	});

	// When a Doc is created new, there is no Permalink input
	$('input#doc-title').on('keydown',function(e){
		if ( ! document.getElementById( 'doc-permalink' ) ) {
			focus_in_content_area(e);
		}
	});

	/* When a toggle is clicked, show the toggle-content */
	$('.toggle-link').click(function(){
		// Traverse for some items
		var $toggleable = $( this ).parents( '.toggleable' );
		var $tc = $toggleable.find( '.toggle-content' );
		var $ts = $toggleable.find( '.toggle-switch' );
		var $pom = $( this ).find( '.plus-or-minus' );

		if ( $toggleable.hasClass( 'toggle-open' ) ) {
			$toggleable.removeClass( 'toggle-open' ).addClass( 'toggle-closed' );
		} else {
			$toggleable.removeClass( 'toggle-closed' ).addClass( 'toggle-open' );
		}

		return false;
	});

	var $group_enable_toggle = $( '#bp-docs-group-enable' );
	$group_enable_toggle.click(function(){
		if ( $group_enable_toggle.is( ':checked' ) ) {
			$('#group-doc-options').show();
		} else {
			$('#group-doc-options').hide();
		}
	});

	/* Permissions snapshot toggle */
	var thisaction, showing, hidden;
	$('#doc-permissions-summary').show();
	$('#doc-permissions-details').hide();
	var dpt = $('.doc-permissions-toggle');
	$(dpt).on('click',function(e){
		e.preventDefault();
		thisaction = $(e.target).attr('id').split('-').pop();
		showing = 'more' == thisaction ? 'summary' : 'details';
		hidden = 'summary' == showing ? 'details' : 'summary';
		$('#doc-permissions-' + showing).slideUp(100, function(){
			$('#doc-permissions-' + hidden).slideDown(100);
		});
	});

	/** Directory filters ************************************************/

	var hidden_tag_counter = 0,
		tag_button_action,
		$dfsection,
		$dfsection_tags = $( '#docs-filter-section-tags' ),
		$dfsection_tags_list = $dfsection_tags.find( 'ul#tags-list' ),
		$dfsection_tags_items = $dfsection_tags_list.children( 'li' );

	// Set up filter sections
	// - hide if necessary
	$('.docs-filter-section').each(function(){
		$dfsection = $(this);
		// Open sections:
		if ( ! $dfsection.hasClass( 'docs-filter-section-open' ) ) {
			$dfsection.hide();
		}
	});

	// Collapse the Tags filter if it contains greater than 10 items
	if ( $dfsection_tags_items.length > 10 ) {
		tags_section_collapse( $dfsection_tags );
	}

	$dfsection_tags.on( 'click', 'a.tags-action-button', function( e ) {
		$dfsection_tags.slideUp( 300, function() {
			tag_button_action = $( e.target ).hasClass( 'tags-unhide' ) ? 'expand' : 'collapse';

			if ( 'expand' == tag_button_action ) {
				tags_section_expand( $dfsection_tags );
			} else if ( 'collapse' == tag_button_action ) {
				tags_section_collapse( $dfsection_tags );
			}

			$dfsection_tags.slideDown();
		} );
		return false;
	} );

	$('.docs-filter-title').on('click',function(e){
		var filter_title = $(this);
		var filter_title_id = filter_title.attr('id');
		var filter_id = filter_title_id.replace('docs-filter-title-', '');
		var filter_to_show_id = 'docs-filter-section-' + filter_id;
		var showing_filter_id = $('.docs-filter-section-open').attr('id');

		$('.docs-filter-title').removeClass( 'current' );
		filter_title.addClass( 'current' );

		var filter_sections = $('.docs-filter-section');
		filter_sections.removeClass( 'docs-filter-section-open' );

		var all_section_slideup = function() {
			$('.docs-filter-section').slideUp(100);
		}

		$.when( all_section_slideup() ).done(function(){
			if ( filter_to_show_id != showing_filter_id ) {
				$('#' + filter_to_show_id).fadeIn().addClass( 'docs-filter-section-open' );
			}
		});
		return false;
	});

	/* Docs search highlighting */
	var searchTerm = bpdocs_get_query_var( 's' );
	if ( searchTerm ) {
		// escape for use in regex
		var searchRegExpPattern = '(<?\\w*(' + searchTerm.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&') + ')\\w*)'
		var searchRegExp = new RegExp( searchRegExpPattern );
		$('.doctable tbody .title-cell > a').html(function(index,html){
			return html.replace(searchTerm, '<span class="search-term-match">' + searchTerm + '</span>');
		});

		$('.bp-docs-attachment-drawer li a').html(function(index,html){
			var newHtml = html.replace(searchRegExp, function( match, a, b, c ) {
				// Yikes. Skip if the match is on a 'span'.
				if ( '<span' === a ) {
					return match;
				}

				return match.replace( b, '<span class="search-term-match">' + b + '</span>' );
			});

			// Open the drawer.
			if ( newHtml !== html ) {
				var $thisDrawer = $(this).closest('.bp-docs-attachment-drawer');
				if ( ! $thisDrawer.is(':visible') ) {
					var drawerDocId = $thisDrawer.attr('id').substr(26);
					if ( drawerDocId ) {
						$('#bp-docs-attachment-clip-' + drawerDocId).trigger('click');
					}
				}
			}

			return newHtml;
		});
	}

	// Set the interval and the namespace event
	if ( typeof wp != 'undefined' && typeof wp.heartbeat != 'undefined' && typeof bp_docs.pulse != 'undefined' ) {

		wp.heartbeat.interval( Number( bp_docs.pulse ) );

		$.fn.extend({
			'heartbeat-send': function() {
			return this.bind( 'heartbeat-send.buddypress-docs' );
	        },
	    });
	}

	$( document ).on( 'heartbeat-send.buddypress-docs', function( e, data ) {
		data['doc_id'] = $('#existing-doc-id').val();
	});

	// Increment newest_activities and activity_last_id if data has been returned
	$( document ).on( 'heartbeat-tick', function( e, data ) {
		if ( ! data['bp_docs_bounce'] ) {
			return;
		}

		window.location = data['bp_docs_bounce'];
	});
	/**
	 * Collapse the Tags filter section
	 */
	function tags_section_collapse( $section ) {
		$section.find( 'a.tags-hide' ).remove();

		var hide_counter = 0;
		$dfsection_tags_items.each( function( k, v ) {
			hide_counter++;
			if ( hide_counter > bpDocsConfig.tagCloudCount ) {
				$( v ).addClass( 'hidden-tag' );
				hidden_tag_counter++;
			}
		} );

		// Add an ellipses item
		var st = '<span class="and-x-more">&hellip; <a href="#" class="tags-unhide tags-action-button">' + bp_docs.and_x_more + '</a></span>';
		st = st.replace( /%d/, hidden_tag_counter );

		$dfsection_tags_list.append( '<li class="tags-ellipses">' + st + '</li>' );

		$dfsection_tags.prepend( '<a class="tags-unhide tags-action-button tags-spanning-button" href="#">' + bp_docs.show_all_tags + '</a>' );
	}

	/**
	 * Expand the Tags filter section
	 */
	function tags_section_expand( $section ) {
		$section.find( 'a.tags-unhide' ).remove();
		$section.find( '.tags-ellipses' ).remove();
		$dfsection_tags_items.removeClass( 'hidden-tag' );
		$dfsection_tags.prepend( '<a class="tags-hide tags-action-button tags-spanning-button" href="#">' + bp_docs.show_fewer_tags + '</a>' );
		hidden_tag_counter = 0;
	}

	function focus_in_content_area(e){
		var code = e.keyCode || e.which;
		if ( code == 9 ) {
			$doc_content = $('textarea#doc_content');
			if ( $doc_content.is(':visible') ) {
				var doccontent = $doc_content.val();
				$doc_content.val('');
				$doc_content.focus();
				$doc_content.val(doccontent);
				return false;
			}
		}
	}
},(jQuery));

function bp_docs_tiny_mce_init(ed) {
	if ( typeof window.tinyMCE === 'undefined' || window.tinyMCE.activeEditor === null || typeof window.tinyMCE.activeEditor === 'undefined' ) {
		return;
	} else {
		bp_docs_load_idle();

		jQuery( window.tinyMCE.activeEditor.contentDocument.activeElement ).on( "mousemove keydown", function(evt) {
			_active(evt);
		});
	}
}

function bp_docs_load_idle() {
	if(jQuery('#doc-form').length != 0 && jQuery('#existing-doc-id').length != 0 ) {
		// For testing
		//setIdleTimeout(1000 * 3); // 25 minutes until the popup (ms * s * min)
		//setAwayTimeout(1000 * 10); // 30 minutes until the autosave

		/* Set away timeout for quasi-autosave */
		setIdleTimeout(1000 * 60 * 25); // 25 minutes until the popup (ms * s * min)
		setAwayTimeout(1000 * 60 * 30); // 30 minutes until the autosave
		document.onIdle = function() {
			jQuery.colorbox({
				inline: true,
				href: "#still_working_content",
				width: "50%",
				height: "50%"
			});
		}
		document.onAway = function() {
			jQuery.colorbox.close();
			var is_auto = '<input type="hidden" name="is_auto" value="1">';
			jQuery('#doc-form').append(is_auto);
			jQuery('#doc-edit-submit').click();
		}

		jQuery( window ).on( 'unload', function( event ){
			var doc_id = jQuery("#existing-doc-id").val();
			var data = {
				action: 'remove_edit_lock',
				doc_id: doc_id,
				nonce: jQuery( '#_wpnonce' ).val()
			};
			jQuery.ajax({
				url: ajaxurl,
				type: 'POST',
				async: false,
				timeout: 10000,
				dataType:'json',
				data: data,
				success: function(response){
					return true;
				},
				complete: function(){
					return true;
				}
			});
		});
	}
}

/**
 * Get a querystring parameter from a URL.
 *
 * @param {String} Query string parameter name.
 * @param {String} URL to parse. Defaults to current URL.
 */
function bpdocs_get_query_var( param, url ) {
	var qs = {};

	// Use current URL if no URL passed.
	if ( typeof url === 'undefined' ) {
		url = location.search.substr(1).split('&');
	} else {
		url = url.split('?')[1].split('&');
	}

	// Parse querystring into object props.
	// http://stackoverflow.com/a/21152762
	url.forEach(function(item) {
		qs[item.split('=')[0]] = item.split('=')[1] && decodeURIComponent( item.split('=')[1] );
	});

	if ( qs.hasOwnProperty( param ) && qs[param] != null ) {
		return qs[param];
	} else {
		return false;
	}
}
