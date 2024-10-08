<?php

if ( !function_exists( 'bp_is_root_blog' ) ) :
	/**
	 * Is this BP_ROOT_BLOG?
	 *
	 * Provides backward compatibility with pre-1.5 BP installs
	 *
	 * @since 1.0.4
	 *
	 * @return bool $is_root_blog Returns true if this is BP_ROOT_BLOG. Always true on non-MS
	 */
	function bp_is_root_blog() {
		global $wpdb;

		$is_root_blog = true;

		if ( is_multisite() && $wpdb->blogid != BP_ROOT_BLOG )
			$is_root_blog = false;

		return apply_filters( 'bp_is_root_blog', $is_root_blog );
	}
endif;

/**
 * Initiates a BuddyPress Docs query
 *
 * @since 1.2
 */
function bp_docs_has_docs( $args = array() ) {
	global $bp, $wp_query, $wp_rewrite;

	// The if-empty is because, like with WP itself, we use bp_docs_has_docs() both for the
	// initial 'if' of the loop, as well as for the 'while' iterator. Don't want infinite
	// queries
	if ( empty( $bp->bp_docs->doc_query ) ) {
		// Build some intelligent defaults

		// Default to current group id, if available
		if ( bp_is_active( 'groups' ) && bp_is_group() ) {
			$d_group_id = bp_get_current_group_id();
		} else if ( ! empty( $_REQUEST['group_id'] ) ) {
			// This is useful for the AJAX request for folder contents.
			$d_group_id = $_REQUEST['group_id'];
		} else if ( bp_docs_is_mygroups_directory() ) {
			$my_groups = groups_get_user_groups( bp_loggedin_user_id() );
			$d_group_id = ! empty( $my_groups['total'] ) ? $my_groups['groups'] : array( 0 );
		} else {
			$d_group_id = null;
		}

		// If this is a Started By tab, set the author ID
		$d_author_id = bp_docs_is_started_by() ? bp_displayed_user_id() : array();

		// If this is an Edited By tab, set the edited_by id
		$d_edited_by_id = bp_docs_is_edited_by() ? bp_displayed_user_id() : array();

		// Default to the tags in the URL string, if available
		$d_tags = isset( $_REQUEST['bpd_tag'] ) ? explode( ',', urldecode( $_REQUEST['bpd_tag'] ) ) : array();

		// Order and orderby arguments
		$d_orderby = !empty( $_GET['orderby'] ) ? urldecode( $_GET['orderby'] ) : apply_filters( 'bp_docs_default_sort_order', 'modified' );

		if ( empty( $_GET['order'] ) ) {
			// If no order is explicitly stated, we must provide one.
			// It'll be different for date fields (should be DESC)
			if ( 'modified' == $d_orderby || 'date' == $d_orderby )
				$d_order = 'DESC';
			else
				$d_order = 'ASC';
		} else {
			$d_order = $_GET['order'];
		}

		// Search
		$d_search_terms = !empty( $_GET['s'] ) ? urldecode( $_GET['s'] ) : '';

		// Parent id
		$d_parent_id = !empty( $_REQUEST['parent_doc'] ) ? (int)$_REQUEST['parent_doc'] : '';

		// Folder id
		$d_folder_id = null;
		if ( ! empty( $_GET['folder'] ) ) {
			$d_folder_id = intval( $_GET['folder'] );
		} else if ( bp_docs_enable_folders_for_current_context() ) {
			/*
			 * 0 means we exclude docs that are in a folder.
			 * So we only want this to be set in folder-friendly contexts.
			 */
			$d_folder_id = 0;
		}

		// Page number, posts per page
		$d_paged = 1;
		if ( ! empty( $_GET['paged'] ) ) {
			$d_paged = absint( $_GET['paged'] );
		} else if ( bp_docs_is_global_directory() && is_a( $wp_query, 'WP_Query' ) && 1 < $wp_query->get( 'paged' ) ) {
			$d_paged = absint( $wp_query->get( 'paged' ) );
		} else if ( ! bp_docs_is_global_directory() ) {
			// For group and member docs directories, use the BP action variable.
			if ( ! empty( $bp->action_variables[0] ) && $wp_rewrite->pagination_base === $bp->action_variables[0] && ! empty( $bp->action_variables[1] ) ) {
				$page = absint( $bp->action_variables[1] );
				// Get the right page of docs.
				$d_paged = $page;
				// Set query var for the pagination function.
				set_query_var( 'paged', $page );
			} else {
				$d_paged = absint( $wp_query->get( 'paged', 1 ) );
			}
		} else {
			$d_paged = absint( $wp_query->get( 'paged', 1 ) );
		}

		// Use the calculated posts_per_page number from $wp_query->query_vars.
		// If that value isn't set, we assume 10 posts per page.
		$d_posts_per_page = absint( $wp_query->get( 'posts_per_page', 10 ) );

		// doc_slug
		$d_doc_slug = !empty( $bp->bp_docs->query->doc_slug ) ? $bp->bp_docs->query->doc_slug : '';

		$defaults = array(
			'doc_id'         => array(),      // Array or comma-separated string
			'doc_slug'       => $d_doc_slug,  // String (post_name/slug)
			'group_id'       => $d_group_id,  // Array or comma-separated string
			'parent_id'      => $d_parent_id, // int
			'folder_id'      => $d_folder_id, // array or comma-separated string
			'author_id'      => $d_author_id, // Array or comma-separated string
			'edited_by_id'   => $d_edited_by_id, // Array or comma-separated string
			'tags'           => $d_tags,      // Array or comma-separated string
			'order'          => $d_order,        // ASC or DESC
			'orderby'        => $d_orderby,   // 'modified', 'title', 'author', 'created'
			'paged'	         => $d_paged,
			'posts_per_page' => $d_posts_per_page,
			'search_terms'   => $d_search_terms,
			'update_attachment_cache' => false,
		);

		if ( function_exists( 'bp_parse_args' ) ) {
			$r = bp_parse_args( $args, $defaults, 'bp_docs_has_docs' );
		} else {
			$r = wp_parse_args( $args, $defaults );
		}

		$doc_query_builder      = new BP_Docs_Query( $r );
		$bp->bp_docs->doc_query = $doc_query_builder->get_wp_query();

		if ( $r['update_attachment_cache'] ) {
			$doc_ids = wp_list_pluck( $bp->bp_docs->doc_query->posts, 'ID' );
			$att_hash = array_fill_keys( $doc_ids, array() );
			if ( $doc_ids ) {
				/**
				 * Filter the arguments passed to get_posts() when populating
				 * the attachment cache.
				 *
				 * @since 2.0.0
				 *
				 * @param array $doc_ids An array of the doc IDs shown on the
				 *                       current page of the loop.
				 */
				$attachment_args = apply_filters( 'bp_docs_update_attachment_cache_args', array(
					'post_type' => 'attachment',
					'post_parent__in' => $doc_ids,
					'update_post_term_cache' => false,
					'posts_per_page' => -1,
					'post_status' => 'inherit'
				), $doc_ids );

				$atts_query = new WP_Query( $attachment_args );

				foreach ( $atts_query->posts as $a ) {
					$att_hash[ $a->post_parent ][] = $a;
				}

				foreach ( $att_hash as $doc_id => $doc_atts ) {
					wp_cache_set( 'bp_docs_attachments:' . $doc_id, $doc_atts, 'bp_docs_nonpersistent' );
				}
			}
		}
	}

	return $bp->bp_docs->doc_query->have_posts();
}

/**
 * Part of the bp_docs_has_docs() loop
 *
 * @since 1.2
 */
function bp_docs_the_doc() {
	global $bp;

	return $bp->bp_docs->doc_query->the_post();
}

/**
 * Determine whether you are viewing a BuddyPress Docs page
 *
 * @since 1.0-beta
 *
 * @return bool
 */
function bp_docs_is_bp_docs_page() {
	global $bp, $post;

	$is_bp_docs_page = false;

	// This is intentionally ambiguous and generous, to account for BP Docs is different
	// components. Probably should be cleaned up at some point
	if ( isset( $bp->bp_docs->slug ) && $bp->bp_docs->slug == bp_current_component()
	     ||
	     isset( $bp->bp_docs->slug ) && $bp->bp_docs->slug == bp_current_action()
	     ||
	     isset( $post->post_type ) && bp_docs_get_post_type_name() == $post->post_type
	     ||
	     is_post_type_archive( bp_docs_get_post_type_name() )
	   )
		$is_bp_docs_page = true;

	return apply_filters( 'bp_docs_is_bp_docs_page', $is_bp_docs_page );
}


/**
 * Returns true if the current page is a BP Docs edit or create page (used to load JS)
 *
 * @since 1.0-beta
 *
 * @returns bool
 */
function bp_docs_is_wiki_edit_page() {
	global $bp;

	$item_type = BP_Docs_Query::get_item_type();
	$current_view = BP_Docs_Query::get_current_view( $item_type );

	return apply_filters( 'bp_docs_is_wiki_edit_page', $is_wiki_edit_page );
}


/**
 * Echoes the output of bp_docs_get_info_header()
 *
 * @since 1.0-beta
 */
function bp_docs_info_header() {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo bp_docs_get_info_header();
}
	/**
	 * Get the info header for a list of docs
	 *
	 * Contains things like tag filters
	 *
	 * @since 1.0-beta
	 *
	 * @param int $doc_id optional The post_id of the doc
	 * @return str Permalink for the group doc
	 */
	function bp_docs_get_info_header() {
		do_action( 'bp_docs_before_info_header' );

		$filters = bp_docs_get_current_filters();

		// Set the message based on the current filters
		if ( empty( $filters ) ) {
			$message = __( 'You are viewing <strong>all</strong> docs.', 'buddypress-docs' );
		} else {
			$message = array();

			$message = apply_filters( 'bp_docs_info_header_message', $message, $filters );

			$message = implode( "<br />", $message );

			// We are viewing a subset of docs, so we'll add a link to clear filters
			// Figure out what the possible filter query args are.
			$filter_args = apply_filters( 'bp_docs_filter_types', array() );
			$filter_args = wp_list_pluck( $filter_args, 'query_arg' );
			$filter_args = array_merge( $filter_args, array( 'search_submit', 'folder' ) );

			$view_all_url = remove_query_arg( $filter_args );

			// Try to remove any pagination arguments.
			$view_all_url = remove_query_arg( 'p', $view_all_url );
			$view_all_url = preg_replace( '|page/[0-9]+/|', '', $view_all_url );

			$message .= ' - ' . sprintf( __( '<strong><a href="%s" title="View All Docs">View All Docs</a></strong>', 'buddypress-docs' ), esc_url( $view_all_url ) );
		}

		?>

		<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<p class="currently-viewing"><?php echo $message ?></p>

		<?php if ( $filter_titles = bp_docs_filter_titles() ) : ?>
			<div class="docs-filters">
				<p id="docs-filter-meta">
					<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php printf( __( 'Filter by: %s', 'buddypress-docs' ), $filter_titles ) ?>
				</p>

				<div id="docs-filter-sections">
					<?php do_action( 'bp_docs_filter_sections' ) ?>
				</div>
			</div>

			<div class="clear"> </div>
		<?php endif ?>
		<?php
	}

/**
 * Links/Titles for the filter types
 *
 * @since 1.4
 */
function bp_docs_filter_titles() {
	$filter_types = apply_filters( 'bp_docs_filter_types', array() );
	$links = array();
	foreach ( $filter_types as $filter_type ) {
		$current = isset( $_GET[ $filter_type['query_arg'] ] ) ? ' current' : '';
		$links[] = sprintf(
			'<a href="#" class="docs-filter-title%s" id="docs-filter-title-%s">%s</a>',
			esc_attr( apply_filters( 'bp_docs_filter_title_class', $current, $filter_type ) ),
			esc_attr( $filter_type['slug'] ),
			esc_html( $filter_type['title'] )
		);
	}

	return implode( '', $links );
}

/**
 * Get the breadcrumb separator character.
 *
 * @since 1.9.0
 *
 * @param string $context 'doc' or 'directory'
 */
function bp_docs_get_breadcrumb_separator( $context = 'doc' ) {
	// Default value is a right-facing triangle
	return apply_filters( 'bp_docs_breadcrumb_separator', '&#9656;', $context );
}

/**
 * Echoes the breadcrumb of a Doc.
 *
 * @since 1.9.0
 */
function bp_docs_the_breadcrumb( $args = array() ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo bp_docs_get_the_breadcrumb( $args );
}
	/**
	 * Returns the breadcrumb of a Doc.
	 */
	function bp_docs_get_the_breadcrumb( $args = array() ) {
		$d_doc_id = 0;
		if ( bp_docs_is_existing_doc() ) {
			$d_doc_id = get_queried_object_id();
		}

		$r = wp_parse_args( $args, array(
			'include_doc' => true,
			'doc_id'      => $d_doc_id,
		) );

		$crumbs = array();

		$doc = get_post( $r['doc_id'] );

		if ( $r['include_doc'] ) {
			$crumbs[] = sprintf(
				'<span class="breadcrumb-current">%s%s</span>',
				bp_docs_get_genericon( 'document', $r['doc_id'] ),
				esc_html( $doc->post_title )
			);
		}

		$crumbs = apply_filters( 'bp_docs_doc_breadcrumbs', $crumbs, $doc );

		$sep = bp_docs_get_breadcrumb_separator( 'doc' );

		return implode( ' <span class="directory-breadcrumb-separator">' . $sep . '</span> ', $crumbs );
	}

/**
 * Echoes the content of a Doc
 *
 * @since 1.3
 */
function bp_docs_the_content() {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo bp_docs_get_the_content();
}
	/**
	 * Returns the content of a Doc
	 *
	 * We need to use this special function, because the BP theme compat
	 * layer messes with the filters on the_content, and we can't rely on
	 * using that theme function within the context of a Doc
	 *
	 * @since 1.3
	 *
	 * @return string $content
	 */
	function bp_docs_get_the_content() {
		if ( function_exists( 'bp_restore_all_filters' ) ) {
			bp_restore_all_filters( 'the_content' );
		}

		$content = apply_filters( 'the_content', get_queried_object()->post_content );

		if ( function_exists( 'bp_remove_all_filters' ) ) {
			bp_remove_all_filters( 'the_content' );
		}

		return apply_filters( 'bp_docs_get_the_content', $content );
	}

/**
 * 'action' URL for directory filter forms.
 *
 * @since 1.9.0
 *
 * @return string
 */
function bp_docs_directory_filter_form_action() {
	$form_action = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	$keeper_keys = array( 'folder' );
	foreach ( $_GET as $k => $v ) {
		if ( ! in_array( $k, $keeper_keys ) ) {
			$form_action = remove_query_arg( $k, $form_action );
		}
	}

	return $form_action;
}

/**
 * Filters the output of the doc list header for search terms
 *
 * @since 1.0-beta
 *
 * @return array $filters
 */
function bp_docs_search_term_filter_text( $message, $filters ) {
	if ( !empty( $filters['search_terms'] ) ) {
		$message[] = sprintf( __( 'You are searching for docs containing the term <em>%s</em>', 'buddypress-docs' ), esc_html( $filters['search_terms'] ) );
	}

	return $message;
}
add_filter( 'bp_docs_info_header_message', 'bp_docs_search_term_filter_text', 10, 2 );

/**
 * Get the filters currently being applied to the doc list
 *
 * @since 1.0-beta
 *
 * @return array $filters
 */
function bp_docs_get_current_filters() {
	$filters = array();

	// First check for tag filters
	if ( !empty( $_REQUEST['bpd_tag'] ) ) {
		// The bpd_tag argument may be comma-separated
		$tags = explode( ',', urldecode( $_REQUEST['bpd_tag'] ) );

		foreach ( $tags as $tag ) {
			$filters['tags'][] = $tag;
		}
	}

	// Now, check for search terms
	if ( !empty( $_REQUEST['s'] ) ) {
		$filters['search_terms'] = urldecode( $_REQUEST['s'] );
	}

	return apply_filters( 'bp_docs_get_current_filters', $filters );
}

/**
 * Echoes the output of bp_docs_get_doc_link()
 *
 * @since 1.0-beta
 */
function bp_docs_doc_link( $doc_id = false ) {
	echo esc_url( bp_docs_get_doc_link( $doc_id ) );
}
	/**
	 * Get the doc's permalink
	 *
	 * @since 1.0-beta
	 *
	 * @param int $doc_id
	 * @return str URL of the doc
	 */
	function bp_docs_get_doc_link( $doc_id = false ) {
		if ( false === $doc_id ) {
			$doc = bp_docs_get_current_doc();
			if ( $doc ) {
				$doc_id = $doc->ID;
			}
		}

		return apply_filters( 'bp_docs_get_doc_link', trailingslashit( get_permalink( $doc_id ) ), $doc_id );
	}

/**
 * Echoes the output of bp_docs_get_doc_edit_link()
 *
 * @since 1.2
 */
function bp_docs_doc_edit_link( $doc_id = false ) {
	echo esc_url( bp_docs_get_doc_edit_link( $doc_id ) );
}
	/**
	 * Get the edit link for a doc
	 *
	 * @since 1.2
	 *
	 * @param int $doc_id
	 * @return str URL of the edit page for the doc
	 */
	function bp_docs_get_doc_edit_link( $doc_id = false ) {
		return apply_filters( 'bp_docs_get_doc_edit_link', trailingslashit( bp_docs_get_doc_link( $doc_id ) . BP_DOCS_EDIT_SLUG ) );
	}

/**
 * Echoes the output of bp_docs_get_archive_link()
 *
 * @since 1.2
 */
function bp_docs_archive_link() {
	echo esc_url( bp_docs_get_archive_link() );
}
	/**
         * Get the link to the main site Docs archive
         *
         * @since 1.2
         */
	function bp_docs_get_archive_link() {
		return apply_filters( 'bp_docs_get_archive_link', trailingslashit( get_post_type_archive_link( bp_docs_get_post_type_name() ) ) );
	}

/**
 * Echoes the output of bp_docs_get_mygroups_link()
 *
 * @since 1.2
 */
function bp_docs_mygroups_link() {
	echo esc_url( bp_docs_get_mygroups_link() );
}
	/**
         * Get the link the My Groups tab of the Docs archive
         *
         * @since 1.2
         */
	function bp_docs_get_mygroups_link() {
		return apply_filters( 'bp_docs_get_mygroups_link', trailingslashit( bp_docs_get_archive_link() . BP_DOCS_MY_GROUPS_SLUG ) );
	}

/**
 * Gets the URL of a user's Docs tab.
 *
 * @since 2.2.0
 *
 * @param int $user_id ID of the user.
 * @return string
 */
function bp_docs_get_user_docs_url( $user_id ) {
	return bp_members_get_user_url(
		$user_id,
		bp_members_get_path_chunks( array( bp_docs_get_slug() ) )
	);
}

/**
 * Echoes the output of bp_docs_get_mydocs_link()
 *
 * @since 1.2
 */
function bp_docs_mydocs_link() {
	echo esc_url( bp_docs_get_mydocs_link() );
}
	/**
	 * Get the link to the My Docs tab of the logged in user
	 *
	 * @since 1.2
	 *
	 * @return string
	 */
	function bp_docs_get_mydocs_link() {
		return apply_filters( 'bp_docs_get_mydocs_link', trailingslashit( bp_docs_get_user_docs_url( bp_loggedin_user_id() ) ) );
	}

/**
 * Echoes the output of bp_docs_get_mydocs_started_link()
 *
 * @since 1.2
 */
function bp_docs_mydocs_started_link() {
	echo esc_url( bp_docs_get_mydocs_started_link() );
}
	/**
	 * Get the link to the Started By Me tab of the logged in user
	 *
	 * @since 1.2
	 *
	 * @return string
	 */
	function bp_docs_get_mydocs_started_link() {
		return apply_filters( 'bp_docs_get_mydocs_started_link', trailingslashit( bp_docs_get_mydocs_link() . BP_DOCS_STARTED_SLUG ) );
	}

/**
 * Echoes the output of bp_docs_get_mydocs_edited_link()
 *
 * @since 1.2
 */
function bp_docs_mydocs_edited_link() {
	echo esc_url( bp_docs_get_mydocs_edited_link() );
}
	/**
	 * Get the link to the Edited By Me tab of the logged in user
	 *
	 * @since 1.2
	 *
	 * @return string
	 */
	function bp_docs_get_mydocs_edited_link() {
		return apply_filters( 'bp_docs_get_mydocs_edited_link', trailingslashit( bp_docs_get_mydocs_link() . BP_DOCS_EDITED_SLUG ) );
	}

/**
 * Echoes the output of bp_docs_get_displayed_user_docs_started_link()
 *
 * @since 1.9
 */
function bp_docs_displayed_user_docs_started_link() {
	echo esc_url( bp_docs_get_displayed_user_docs_started_link() );
}
	/**
     * Get the link to the Started By tab of the displayed user
     *
     * @since 1.9
     */
	function bp_docs_get_displayed_user_docs_started_link() {
		return apply_filters( 'bp_docs_get_displayed_user_docs_started_link', user_trailingslashit( trailingslashit( bp_docs_get_user_docs_url( bp_displayed_user_id() ) ) . BP_DOCS_STARTED_SLUG ) );
	}

/**
 * Echoes the output of bp_docs_get_displayed_user_docs_edited_link()
 *
 * @since 1.9
 */
function bp_docs_displayed_user_docs_edited_link() {
	echo esc_url( bp_docs_get_displayed_user_docs_edited_link() );
}
	/**
     * Get the link to the Edited By tab of the displayed user
     *
     * @since 1.9
	 *
	 * @return string
     */
	function bp_docs_get_displayed_user_docs_edited_link() {
		return apply_filters( 'bp_docs_get_displayed_user_docs_edited_link', user_trailingslashit( trailingslashit( bp_docs_get_user_docs_url( bp_displayed_user_id() ) ) . BP_DOCS_EDITED_SLUG ) );
	}

/**
 * Echoes the output of bp_docs_get_create_link()
 *
 * @since 1.2
 */
function bp_docs_create_link() {
	echo esc_url( bp_docs_get_create_link() );
}
	/**
	 * Get the link to create a Doc
	 *
	 * @since 1.2
	 *
	 * @return string
	 */
	function bp_docs_get_create_link() {
		return apply_filters( 'bp_docs_get_create_link', trailingslashit( bp_docs_get_archive_link() . BP_DOCS_CREATE_SLUG ) );
	}

/**
 * Echoes the output of bp_docs_get_item_docs_link()
 *
 * @since 1.0-beta
 * @deprecated 2.2.0
 */
function bp_docs_item_docs_link() {
	echo esc_url( bp_docs_get_item_docs_link() );
}
	/**
	 * Get the link to the docs section of an item
	 *
	 * @since 1.0-beta
	 * @deprecated 2.2.0
	 *
	 * @return array $args
	 */
	function bp_docs_get_item_docs_link( $args = array() ) {
		_deprecated_function( __FUNCTION__, '2.2.0', 'bp_docs_get_group_docs_url()' );
		return '';
	}

/**
 * Output the breadcrumb for use in directories.
 *
 * @since 1.9.0
 */
function bp_docs_directory_breadcrumb() {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo bp_docs_get_directory_breadcrumb();
}
	/**
	 * Generate a breadcrumb for use in directories.
	 *
	 * @since 1.9.0
	 *
	 * @return string
	 */
	function bp_docs_get_directory_breadcrumb() {
		$crumbs = array();

		$crumbs = apply_filters( 'bp_docs_directory_breadcrumb', $crumbs );

		if ( ! $crumbs ) {
			return '';
		}

		// Last item is the "current" item
		$last = array_pop( $crumbs );
		$last = strip_tags( $last, '<i>' );
		$last = '<span class="breadcrumb-current">' . $last . '</a>';
		$crumbs[] = $last;

		$sep = bp_docs_get_breadcrumb_separator( 'directory' );

		return implode( ' <span class="directory-breadcrumb-separator">' . $sep . '</span> ', $crumbs );
	}

/**
 * Get the sort order for sortable column links
 *
 * Detects the current sort order and returns the opposite
 *
 * @since 1.0-beta
 *
 * @return str $new_order Either desc or asc
 */
function bp_docs_get_sort_order( $orderby = 'modified' ) {

	$new_order	= false;

	// We only want a non-default order if we are currently ordered by this $orderby
	// The default order is Last Edited, so we must account for that
	$current_orderby	= !empty( $_GET['orderby'] ) ? $_GET['orderby'] : apply_filters( 'bp_docs_default_sort_order', 'modified' );

	if ( $orderby == $current_orderby ) {
		// Default sort orders are different for different fields
		if ( empty( $_GET['order'] ) ) {
			// If no order is explicitly stated, we must provide one.
			// It'll be different for date fields (should be DESC)
			if ( 'modified' == $current_orderby || 'date' == $current_orderby )
				$current_order = 'DESC';
			else
				$current_order = 'ASC';
		} else {
			$current_order = $_GET['order'];
		}

		$new_order = 'ASC' == $current_order ? 'DESC' : 'ASC';
	}

	return apply_filters( 'bp_docs_get_sort_order', $new_order );
}

/**
 * Echoes the output of bp_docs_get_order_by_link()
 *
 * @since 1.0-beta
 *
 * @param str $orderby The order_by item: title, author, created, edited, etc
 */
function bp_docs_order_by_link( $orderby = 'modified' ) {
	echo esc_url( bp_docs_get_order_by_link( $orderby ) );
}
	/**
	 * Get the URL for the sortable column header links
	 *
	 * @since 1.0-beta
	 *
	 * @param str $orderby The order_by item: title, author, created, modified, etc
	 * @return str The URL with args attached
	 */
	function bp_docs_get_order_by_link( $orderby = 'modified' ) {
		$args = array(
			'orderby' => $orderby,
			'order'	  => bp_docs_get_sort_order( $orderby )
		);

		return apply_filters( 'bp_docs_get_order_by_link', add_query_arg( $args ), $orderby, $args );
	}

/**
 * Echoes current-orderby and order classes for the column currently being ordered by
 *
 * @since 1.0-beta
 *
 * @param str $orderby The order_by item: title, author, created, modified, etc
 */
function bp_docs_is_current_orderby_class( $orderby = 'modified' ) {
	// Get the current orderby column
	$current_orderby = !empty( $_GET['orderby'] ) ? $_GET['orderby'] : apply_filters( 'bp_docs_default_sort_order', 'modified' );

	// Does the current orderby match the $orderby parameter?
	$is_current_orderby = $current_orderby == $orderby ? true : false;

	$class = '';

	// If this is indeed the current orderby, we need to get the asc/desc class as well
	if ( $is_current_orderby ) {
		$class = ' current-orderby';

		if ( empty( $_GET['order'] ) ) {
			// If no order is explicitly stated, we must provide one.
			// It'll be different for date fields (should be DESC)
			if ( 'modified' == $current_orderby || 'date' == $current_orderby )
				$class .= ' desc';
			else
				$class .= ' asc';
		} else {
			$class .= 'DESC' == $_GET['order'] ? ' desc' : ' asc';
		}
	}

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo apply_filters( 'bp_docs_is_current_orderby', $class, $is_current_orderby, $current_orderby );
}

/**
 * Prints the inline toggle setup script
 *
 * Ideally, I would put this into an external document; but the fact that it is supposed to hide
 * content immediately on pageload means that I didn't want to wait for an external script to
 * load, much less for document.ready. Sorry.
 *
 * @since 1.0-beta
 */
function bp_docs_inline_toggle_js() {
	?>
	<script type="text/javascript">
		/* Swap toggle text with a dummy link and hide toggleable content on load */
		var togs = jQuery('.toggleable');

		jQuery(togs).each(function(){
			var ts = jQuery(this).children('.toggle-switch');

			/* Get a unique identifier for the toggle */
			var tsid = jQuery(ts).attr('id').split('-');
			var type = tsid[0];

			/* Append the static toggle text with a '+' sign and linkify */
			var toggleid = type + '-toggle-link';
			var plus = '<span class="show-pane plus-or-minus"></span>';

			jQuery(ts).html('<a href="#" id="' + toggleid + '" class="toggle-link">' + plus + jQuery(ts).html() + '</a>');
		});

	</script>
	<?php
}

/**
 * Get a dropdown of associable groups for the current user.
 *
 * @since 1.8
 */
function bp_docs_associated_group_dropdown( $args = array() ) {
	if ( ! bp_is_active( 'groups' ) ) {
		return;
	}
	$r = wp_parse_args( $args, array(
		'name'         => 'associated_group_id',
		'id'           => 'associated_group_id',
		'selected'     => null,
		'options_only' => false,
		'echo'         => true,
		'null_option'  => true,
		'include'      => null,
	) );

	$groups_args = array(
		'per_page' => false,
		'populate_extras' => false,
		'type' => 'alphabetical',
		'update_meta_cache' => false,
	);

	if ( ! bp_current_user_can( 'bp_moderate' ) ) {
		$groups_args['user_id'] = bp_loggedin_user_id();
	}

	if ( ! is_null( $r['include'] ) ) {
		$groups_args['include'] = wp_parse_id_list( $r['include'] );
	}

	ksort( $groups_args );
	ksort( $r );
	$cache_key = 'bp_docs_associated_group_dropdown:' . md5( serialize( $groups_args ) . serialize( $r ) );
	$cached = wp_cache_get( $cache_key, 'bp_docs_nonpersistent' );
	if ( false !== $cached ) {
		return $cached;
	}

	// Populate the $groups_template global, but stash the old one
	// This ensures we don't mess anything up inside the group
	global $groups_template;
	$old_groups_template = $groups_template;

	bp_has_groups( $groups_args );

	// Filter out the groups where associate_with permissions forbid
	$removed = 0;
	foreach ( $groups_template->groups as $gtg_key => $gtg ) {
		if ( ! current_user_can( 'bp_docs_associate_with_group', $gtg->id ) ) {
			unset( $groups_template->groups[ $gtg_key ] );
			$removed++;
		}
	}

	// cleanup, if necessary from filter above
	if ( $removed ) {
		$groups_template->groups = array_values( $groups_template->groups );
		$groups_template->group_count = $groups_template->group_count - $removed;
		$groups_template->total_group_count = $groups_template->total_group_count - $removed;
	}

	$html = '';

	if ( ! $r['options_only'] ) {
		$html .= sprintf( '<select name="%s" id="%s">', esc_attr( $r['name'] ), esc_attr( $r['id'] ) );
	}

	if ( $r['null_option'] ) {
		$html .= '<option value="">' . __( 'None', 'buddypress-docs' ) . '</option>';
	}

	foreach ( $groups_template->groups as $g ) {
		$html .= sprintf(
			'<option value="%s" %s>%s</option>',
			esc_attr( $g->id ),
			selected( $r['selected'], $g->id, false ),
			esc_html( stripslashes( $g->name ) )
		);
	}

	if ( ! $r['options_only'] ) {
		$html .= '</select>';
	}

	$groups_template = $old_groups_template;

	wp_cache_set( $cache_key, $html, 'bp_docs_nonpersistent' );

	if ( false === $r['echo'] ) {
		return $html;
	} else {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $html;
	}
}

/**
 * Outputs the markup for the Associated Group settings section
 *
 * @since 1.2
 */
function bp_docs_doc_associated_group_markup() {
	global $groups_template;

	$old_gt = $groups_template;

	// First, try to set the preselected group by looking at the URL params
	$selected_group_slug = isset( $_GET['group'] ) ? $_GET['group'] : '';

	// Support for BP Group Hierarchy
	if ( false !== $slash = strrpos( $selected_group_slug, '/' ) ) {
		$selected_group_slug = substr( $selected_group_slug, $slash + 1 );
	}

	$selected_group = BP_Groups_Group::get_id_from_slug( $selected_group_slug );
	if ( $selected_group && ! current_user_can( 'bp_docs_associate_with_group', $selected_group ) ) {
		$selected_group = 0;
	}

	// If the selected group is still 0, see if there's something in the db
	if ( ! $selected_group && is_singular() ) {
		$selected_group = bp_docs_get_associated_group_id( get_the_ID() );
	}

	// Last check: if this is a second attempt at a newly created Doc,
	// there may be a previously submitted value
	$associated_group_id = isset( buddypress()->bp_docs->submitted_data->group_id ) ? buddypress()->bp_docs->submitted_data->group_id : null;
	if ( empty( $selected_group ) && ! empty( $associated_group_id ) ) {
		$selected_group = $associated_group_id;
	}

	$selected_group = intval( $selected_group );

	?>
	<tr>
		<td class="desc-column">
			<label for="associated_group_id"><?php _e( 'Which group should this Doc be associated with?', 'buddypress-docs' ) ?></label>
			<span class="description"><?php _e( '(Optional) Note that the Access settings available for this Doc may be limited by the privacy settings of the group you choose.', 'buddypress-docs' ) ?></span>
		</td>

		<td class="content-column">
			<?php bp_docs_associated_group_dropdown( array(
				'name' => 'associated_group_id',
				'id' => 'associated_group_id',
				'selected' => $selected_group,
			) ) ?>

			<div id="associated_group_summary">
				<?php bp_docs_associated_group_summary() ?>
			</div>
		</td>
	</tr>
	<?php

	$groups_template = $old_gt;
}

/**
 * Display a summary of the associated group
 *
 * @since 1.2
 *
 * @param int $group_id
 */
function bp_docs_associated_group_summary( $group_id = 0 ) {
	$html = '';

	if ( ! $group_id ) {
		if ( isset( $_GET['group'] ) ) {
			$group_slug = $_GET['group'];
			$group_id   = BP_Groups_Group::get_id_from_slug( $group_slug );
		} else {
			$doc_id = is_singular() ? get_the_ID() : 0;
			$group_id = bp_docs_get_associated_group_id( $doc_id );
		}
	}

	$group_id = intval( $group_id );
	if ( $group_id ) {
		$group = groups_get_group( array( 'group_id' => $group_id ) );

		if ( ! empty( $group->name ) ) {
			$group_link = bp_get_group_url( $group );

			$group_avatar = bp_core_fetch_avatar( array(
				'item_id' => $group_id,
				'object' => 'group',
				'type' => 'thumb',
				'width' => '40',
				'height' => '40',
			) );
			$_count = (int) groups_get_groupmeta( $group_id, 'total_member_count' );
			if ( 1 === $_count ) {
				// Using sprintf() to avoid creating another string.
				$group_member_count = sprintf( __( '%s member', 'buddypress-docs', $_count ), number_format_i18n( $_count ) );
			} else {
				$group_member_count = sprintf( _n( '%s member', '%s members', $_count, 'buddypress-docs' ), number_format_i18n( $_count ) );
			}

			switch ( $group->status ) {
				case 'public' :
					$group_type_string = __( 'Public Group', 'buddypress-docs' );
					break;

				case 'private' :
					$group_type_string = __( 'Private Group', 'buddypress-docs' );
					break;

				case 'hidden' :
					$group_type_string = __( 'Hidden Group', 'buddypress-docs' );
					break;

				default :
					$group_type_string = '';
					break;
			}

			$html .= '<a href="' . esc_url( $group_link ) . '">' . $group_avatar . '</a>';

			$html .= '<div class="item">';
			$html .= '<a href="' . esc_url( $group_link ) . '">' . esc_html( $group->name ) . '</a>';
			$html .= '<div class="meta">' . esc_html( $group_type_string ) . ' / ' . esc_html( $group_member_count ) . '</div>';
			$html .= '</div>';
		}

	}

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $html;
}

/**
 * A hook for intergration pieces to insert their settings markup
 *
 * @since 1.0-beta
 */
function bp_docs_doc_settings_markup( $doc_id = 0, $group_id = 0 ) {
	global $bp;

	if ( ! $doc_id ) {
		$doc = bp_docs_get_current_doc();
		if ( $doc ) {
			$doc_id = $doc->ID;
		}
	}

	$doc_settings = bp_docs_get_doc_settings( $doc_id, 'default', $group_id );

	$settings_fields = array(
		'read' => array(
			'name'  => 'read',
			'label' => __( 'Who can read this doc?', 'buddypress-docs' )
		),
		'edit' => array(
			'name'  => 'edit',
			'label' => __( 'Who can edit this doc?', 'buddypress-docs' )
		),
		'read_comments' => array(
			'name'  => 'read_comments',
			'label' => __( 'Who can read comments on this doc?', 'buddypress-docs' )
		),
		'post_comments' => array(
			'name'  => 'post_comments',
			'label' => __( 'Who can post comments on this doc?', 'buddypress-docs' )
		),
		'view_history' => array(
			'name'  => 'view_history',
			'label' => __( 'Who can view the history of this doc?', 'buddypress-docs' )
		)
	);

	foreach ( $settings_fields as $settings_field ) {
		bp_docs_access_options_helper( $settings_field, $doc_id, $group_id );
	}

	// Hand off the creation of additional settings to individual integration pieces
	do_action( 'bp_docs_doc_settings_markup', $doc_settings );
}

function bp_docs_access_options_helper( $settings_field, $doc_id = 0, $group_id = 0 ) {
	if ( $group_id ) {
		$settings_type = 'raw';
	} else {
		$settings_type = 'default';
	}

	$doc_settings = bp_docs_get_doc_settings( $doc_id, $settings_type, $group_id );

	// If this is a failed form submission, check the submitted values first
	$field_name = isset( buddypress()->bp_docs->submitted_data->settings->{$settings_field['name']} ) ? buddypress()->bp_docs->submitted_data->settings->{$settings_field['name']} : null;
	if ( ! empty( $field_name ) ) {
		$setting = $field_name;
	} else {
		$setting = isset( $doc_settings[ $settings_field['name'] ] ) ? $doc_settings[ $settings_field['name'] ] : '';
	}

	?>
	<tr class="bp-docs-access-row bp-docs-access-row-<?php echo esc_attr( $settings_field['name'] ) ?>">
		<td class="desc-column">
			<label for="settings-<?php echo esc_attr( $settings_field['name'] ) ?>"><?php echo esc_html( $settings_field['label'] ) ?></label>
		</td>

		<td class="content-column">
			<select name="settings[<?php echo esc_attr( $settings_field['name'] ) ?>]" id="settings-<?php echo esc_attr( $settings_field['name'] ) ?>">
				<?php $access_options = bp_docs_get_access_options( $settings_field['name'], $doc_id, $group_id ) ?>
				<?php foreach ( $access_options as $key => $option ) : ?>
					<?php
					$selected = selected( $setting, $option['name'], false );
					if ( empty( $setting ) && ! empty( $option['default'] ) ) {
						$selected = selected( 1, 1, false );
					}
					?>
					<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<option value="<?php echo esc_attr( $option['name'] ) ?>" <?php echo $selected ?>><?php echo esc_attr( $option['label'] ) ?></option>
				<?php endforeach ?>
			</select>
		</td>
	</tr>

	<?php
}

/**
 * Outputs the links that appear under each Doc in the Doc listing
 *
 */
function bp_docs_doc_action_links() {
	$links = array();

	$links[] = '<a href="' . esc_url( bp_docs_get_doc_link() ) . '">' . esc_html__( 'Read', 'buddypress-docs' ) . '</a>';

	if ( current_user_can( 'bp_docs_edit', get_the_ID() ) ) {
		$links[] = '<a href="' . esc_url( bp_docs_get_doc_edit_link() ). '">' . esc_html__( 'Edit', 'buddypress-docs' ) . '</a>';
	}

	if ( current_user_can( 'bp_docs_view_history', get_the_ID() ) && defined( 'WP_POST_REVISIONS' ) && WP_POST_REVISIONS ) {
		$links[] = '<a href="' . esc_url( bp_docs_get_doc_link() . BP_DOCS_HISTORY_SLUG ) . '">' . esc_html__( 'History', 'buddypress-docs' ) . '</a>';
	}

	if ( current_user_can( 'manage', get_the_ID() ) && bp_docs_is_doc_trashed( get_the_ID() ) ) {
		$links[] = '<a href="' . esc_url( bp_docs_get_remove_from_trash_link( get_the_ID() ) ) . '" class="delete confirm">' . esc_html__( 'Untrash', 'buddypress-docs' ) . '</a>';
	}

	$links = apply_filters( 'bp_docs_doc_action_links', $links, get_the_ID() );

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo implode( ' &#124; ', $links );
}

function bp_docs_current_group_is_public() {
	global $bp;

	if ( !empty( $bp->groups->current_group->status ) && 'public' == $bp->groups->current_group->status )
		return true;

	return false;
}

/**
 * Echoes the output of bp_docs_get_delete_doc_link()
 *
 * @since 1.0.1
 */
function bp_docs_delete_doc_link( $force_delete = false ) {
	echo esc_url( bp_docs_get_delete_doc_link( $force_delete ) );
}
	/**
	 * Get the URL to delete the current doc
	 *
	 * @since 1.0.1
	 *
	 * @param bool force_delete Whether to add the force_delete query arg.
	 *
	 * @return string $delete_link href for the delete doc link
	 */
	function bp_docs_get_delete_doc_link( $force_delete = false ) {
		$doc_permalink = bp_docs_get_doc_link();
		$query_args = array( BP_DOCS_DELETE_SLUG => 1 );

		if ( $force_delete ) {
			$query_args['force_delete'] = 1;
		}

		$delete_link = wp_nonce_url( add_query_arg( $query_args, $doc_permalink ), 'bp_docs_delete' );

		return apply_filters( 'bp_docs_get_delete_doc_link', $delete_link, $doc_permalink );
	}


/**
 * Echo the URL to remove a Doc from the Trash.
 *
 * @since 1.5.5
 */
function bp_docs_remove_from_trash_link( $doc_id = false ) {
	echo esc_url( bp_docs_get_remove_from_trash_link( $doc_id ) );
}
	/**
	 * Get the URL for removing a Doc from the Trash.
	 *
	 * @since 1.5.5
	 *
	 * @param $doc_id ID of the Doc.
	 * @return string URL for Doc untrashing.
	 */
	function bp_docs_get_remove_from_trash_link( $doc_id ) {
		$doc_permalink = bp_docs_get_doc_link( $doc_id );

		$untrash_link = wp_nonce_url( add_query_arg( array(
			BP_DOCS_UNTRASH_SLUG => '1',
			'doc_id' => intval( $doc_id ),
		), $doc_permalink ), 'bp_docs_untrash' );

		return apply_filters( 'bp_docs_get_remove_from_trash_link', $untrash_link, $doc_permalink );
	}

/**
 * Echo the Delete/Untrash link for use on single Doc pages.
 *
 * @since 1.5.5
 *
 * @param int $doc_id Optional. Default: current Doc.
 */
function bp_docs_delete_doc_button( $doc_id = false ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo bp_docs_get_delete_doc_button( $doc_id );
}
	/**
	 * Get HTML for the Delete/Untrash link used on single Doc pages.
	 *
	 * @since 1.5.5
	 *
	 * @param int $doc_id Optional. Default: ID of current Doc.
	 * @return string HTML of Delete/Remove from Trash link.
	 */
	function bp_docs_get_delete_doc_button( $doc_id = false ) {
		if ( ! $doc_id ) {
			$doc_id = bp_docs_is_existing_doc() ? get_queried_object_id() : get_the_ID();
		}

		if ( bp_docs_is_doc_trashed( $doc_id ) ) {
			// A button to remove the doc from the trash...
			$button = ' <a class="delete-doc-button untrash-doc-button confirm" href="' . esc_url( bp_docs_get_remove_from_trash_link( $doc_id ) ) . '">' . esc_html__( 'Remove from Trash', 'buddypress-docs' ) . '</a>';
			// and a button to permanently delete the doc.
			$button .= '<a class="delete-doc-button confirm" href="' . esc_url( bp_docs_get_delete_doc_link() ) . '">' . esc_html__( 'Permanently Delete', 'buddypress-docs' ) . '</a>';
		} else {
			// A button to move the doc to the trash...
			$button = '<a class="delete-doc-button confirm" href="' . esc_url( bp_docs_get_delete_doc_link() ) . '">' . esc_html__( 'Move to Trash', 'buddypress-docs' ) . '</a>';
			// and a button to permanently delete the doc.
			$button .= '<a class="delete-doc-button confirm" href="' . esc_url( bp_docs_get_delete_doc_link( true ) ) . '">' . esc_html__( 'Permanently Delete', 'buddypress-docs' ) . '</a>';
		}

		return $button;
	}

/**
 * Get a directory link appropriate for this item.
 *
 * @since 1.9
 *
 * @param string $item_type 'global', 'group', 'user'. Default: 'global'.
 * @param int $item_id If $item_type is not 'global', the ID of the item.
 * @return string
 */
function bp_docs_get_directory_url( $item_type = 'global', $item_id = 0 ) {
	switch ( $item_type ) {
		case 'user' :
			$url = bp_docs_get_user_docs_url( $item_id );
			break;

		case 'group' :
			if ( bp_is_active( 'groups' ) ) {
				$url = bp_docs_get_group_docs_url( $item_id );
				break;
			}
			// otherwise fall through

		case 'global' :
		default :
			$url = bp_docs_get_archive_link();
			break;
	}

	return $url;
}

/**
 * Echo the pagination links for the doc list view
 *
 * @since 1.0-beta-2
 */
function bp_docs_paginate_links() {
	global $bp, $wp_query, $wp_rewrite;

	$page_links_total = $bp->bp_docs->doc_query->max_num_pages;

	$pagination_args = array(
		'base' 		=> add_query_arg( 'paged', '%#%' ),
		'format' 	=> '',
		'prev_text' 	=> __( '&laquo;', 'buddypress-docs' ),
		'next_text' 	=> __( '&raquo;', 'buddypress-docs' ),
		'total' 	=> $page_links_total,
		'end_size'  => 2,
	);

	if ( $wp_rewrite->using_permalinks() ) {
		$pagination_args['base'] = apply_filters( 'bp_docs_page_links_base_url', user_trailingslashit( trailingslashit( bp_docs_get_archive_link() ) . $wp_rewrite->pagination_base . '/%#%/', 'bp-docs-directory' ), $wp_rewrite->pagination_base );
	}

	$page_links = paginate_links( $pagination_args );

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo apply_filters( 'bp_docs_paginate_links', $page_links );
}

/**
 * Get the start number for the current docs view (ie "Viewing *5* - 8 of 12")
 *
 * Here's the math: Subtract one from the current page number; multiply times posts_per_page to get
 * the last post on the previous page; add one to get the start for this page.
 *
 * @since 1.0-beta-2
 *
 * @return int $start The start number
 */
function bp_docs_get_current_docs_start() {
	global $bp;

	$paged = !empty( $bp->bp_docs->doc_query->query_vars['paged'] ) ? $bp->bp_docs->doc_query->query_vars['paged'] : 1;

	$posts_per_page = !empty( $bp->bp_docs->doc_query->query_vars['posts_per_page'] ) ? $bp->bp_docs->doc_query->query_vars['posts_per_page'] : 10;

	$start = ( ( $paged - 1 ) * $posts_per_page ) + 1;

	return apply_filters( 'bp_docs_get_current_docs_start', $start );
}

/**
 * Get the end number for the current docs view (ie "Viewing 5 - *8* of 12")
 *
 * Here's the math: Multiply the posts_per_page by the current page number. If it's the last page
 * (ie if the result is greater than the total number of docs), just use the total doc count
 *
 * @since 1.0-beta-2
 *
 * @return int $end The start number
 */
function bp_docs_get_current_docs_end() {
	global $bp;

	$paged = !empty( $bp->bp_docs->doc_query->query_vars['paged'] ) ? $bp->bp_docs->doc_query->query_vars['paged'] : 1;

	$posts_per_page = !empty( $bp->bp_docs->doc_query->query_vars['posts_per_page'] ) ? $bp->bp_docs->doc_query->query_vars['posts_per_page'] : 10;

	$end = $paged * $posts_per_page;

	if ( $end > bp_docs_get_total_docs_num() )
		$end = bp_docs_get_total_docs_num();

	return apply_filters( 'bp_docs_get_current_docs_end', $end );
}

/**
 * Get the total number of found docs out of $wp_query
 *
 * @since 1.0-beta-2
 *
 * @return int $total_doc_count The start number
 */
function bp_docs_get_total_docs_num() {
	global $bp;

	$total_doc_count = !empty( $bp->bp_docs->doc_query->found_posts ) ? $bp->bp_docs->doc_query->found_posts : 0;

	return apply_filters( 'bp_docs_get_total_docs_num', $total_doc_count );
}

/**
 * Display a Doc's comments
 *
 * This function was introduced to make sure that the comment display callback function can be
 * filtered by site admins. Originally, wp_list_comments() was called directly from the template
 * with the callback bp_dtheme_blog_comments, but this caused problems for sites not running a
 * child theme of bp-default.
 *
 * Filter bp_docs_list_comments_args to provide your own comment-formatting function.
 *
 * @since 1.0.5
 */
function bp_docs_list_comments() {
	$args = array();

	if ( function_exists( 'bp_dtheme_blog_comments' ) )
		$args['callback'] = 'bp_dtheme_blog_comments';

	$args = apply_filters( 'bp_docs_list_comments_args', $args );

	wp_list_comments( $args );
}

/**
 * Are we looking at an existing doc?
 *
 * @since 1.0-beta
 *
 * @return bool True if it's an existing doc
 */
function bp_docs_is_existing_doc() {
	global $wp_query;

	$is_existing_doc = false;

	if ( isset( $wp_query ) && $wp_query instanceof WP_Query ) {
		$post_obj = get_queried_object();
		if ( isset( $post_obj->post_type ) && is_singular( bp_docs_get_post_type_name() ) ) {
			$is_existing_doc = true;
		}
	}

	return apply_filters( 'bp_docs_is_existing_doc', $is_existing_doc );
}

/**
 * What's the current view?
 *
 * @since 1.1
 *
 * @return str $current_view The current view
 */
function bp_docs_current_view() {
	global $bp;

	$view = !empty( $bp->bp_docs->current_view ) ? $bp->bp_docs->current_view : false;

	return apply_filters( 'bp_docs_current_view', $view );
}

/**
 * Todo: Make less hackish
 */
function bp_docs_doc_permalink() {
	if ( bp_is_active( 'groups' ) && bp_is_group() ) {
		bp_docs_group_doc_permalink();
	} else {
		the_permalink();
	}
}

function bp_docs_slug() {
	echo esc_html( bp_docs_get_slug() );
}
	function bp_docs_get_slug() {
		global $bp;
		return apply_filters( 'bp_docs_get_slug', $bp->bp_docs->slug );
	}

function bp_docs_get_docs_slug() {
	global $bp;

	if ( defined( 'BP_DOCS_SLUG' ) ) {
		$slug = BP_DOCS_SLUG;
		$is_in_wp_config = true;
	} else {
		$slug = bp_get_option( 'bp-docs-slug' );
		if ( empty( $slug ) ) {
			$slug = 'docs';
		}

		// for backward compatibility
		define( 'BP_DOCS_SLUG', $slug );
		$is_in_wp_config = false;
	}

	// For the settings page
	if ( ! isset( $bp->bp_docs->slug_defined_in_wp_config['slug'] ) ) {
		$bp->bp_docs->slug_defined_in_wp_config['slug'] = (int) $is_in_wp_config;
	}

	return apply_filters( 'bp_docs_get_docs_slug', $slug );
}

/**
 * Outputs the tabs at the top of the Docs view (All Docs, New Doc, etc)
 *
 * At the moment, the group-specific stuff is hard coded in here.
 * @todo Get the group stuff out
 */
function bp_docs_tabs( $show_create_button = true ) {
	$theme_package = bp_get_theme_package_id();

	switch ( bp_get_theme_package_id() ) {
		case 'nouveau' :
			$template = 'tabs-nouveau.php';
		break;

		default :
			$template = 'tabs-legacy.php';
		break;
	}

	// Calling `include` here so `$show_create_button` is in template scope.
	$located = bp_docs_locate_template( $template );
	include( $located );
}

/**
 * Echoes the Create A Doc button
 *
 * @since 1.2
 */
function bp_docs_create_button() {
	if ( ! bp_docs_is_doc_create() && current_user_can( 'bp_docs_create' ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo apply_filters( 'bp_docs_create_button', '<a class="button" id="bp-create-doc-button" href="' . esc_url( bp_docs_get_create_link() ) . '">' . esc_html__( "Create New Doc", 'buddypress-docs' ) . '</a>' );
	}
}

/**
 * Puts a Create A Doc button on the members nav of member doc lists
 *
 * @since 1.2.1
 */
function bp_docs_member_create_button() {
	if ( bp_docs_is_docs_component() ) { ?>
		<?php bp_docs_create_button(); ?>
	<?php
	}
}
add_action( 'bp_member_plugin_options_nav', 'bp_docs_member_create_button' );

/**
 * Markup for the Doc Permissions snapshot
 *
 * Markup is built inline. Someday I may abstract it. In the meantime, suck a lemon
 *
 * @since 1.2
 */
function bp_docs_doc_permissions_snapshot( $args = array() ) {
	$html = '';

	$defaults = array(
		'summary_before_content' => '',
		'summary_after_content' => ''
	);

	$args = wp_parse_args( $args, $defaults );
	extract( $args, EXTR_SKIP );

	if ( bp_is_active( 'groups' ) ) {
		$doc_group_ids = bp_docs_get_associated_group_id( get_the_ID(), false, true );
		$doc_groups = array();
		foreach( $doc_group_ids as $dgid ) {
			$maybe_group = groups_get_group( array( 'group_id' => $dgid ) );

			// Don't show hidden groups if the
			// current user is not a member
			if ( isset( $maybe_group->status ) && 'hidden' === $maybe_group->status ) {
				// @todo this is slow
				if ( ! current_user_can( 'bp_moderate' ) && ! groups_is_user_member( bp_loggedin_user_id(), $dgid ) ) {
					continue;
				}
			}

			if ( !empty( $maybe_group->name ) ) {
				$doc_groups[] = $maybe_group;
			}
		}

		// First set up the Group snapshot, if there is one
		if ( ! empty( $doc_groups ) ) {
			$group_link = bp_get_group_url( $doc_groups[0] );

			$html .= '<div id="doc-group-summary">';

			$html .= $summary_before_content ;
			$html .= '<span>' . esc_html( 'Group: ', 'buddypress-docs' ) . '</span>';

			$html .= sprintf( __( ' %s', 'buddypress-docs' ), '<a href="' . esc_url( $group_link ) . '">' . bp_core_fetch_avatar( 'item_id=' . $doc_groups[0]->id . '&object=group&type=thumb&width=25&height=25' ) . '</a> ' . '<a href="' . esc_url( $group_link ) . '">' . esc_html( $doc_groups[0]->name ) . '</a>' );

			$html .= $summary_after_content;

			$html .= '</div>';
		}

		// we'll need a list of comma-separated group names
		$group_names = implode( ', ', wp_list_pluck( $doc_groups, 'name' ) );
	}

	$levels = array(
		'anyone'        => __( 'Anyone', 'buddypress-docs' ),
		'loggedin'      => __( 'Logged-in Users', 'buddypress-docs' ),
		'friends'       => __( 'My Friends', 'buddypress-docs' ),
		'creator'       => __( 'The Doc author only', 'buddypress-docs' ),
		'no-one'        => __( 'Just Me', 'buddypress-docs' )
	);

	if ( bp_is_active( 'groups' ) ) {
		$levels['group-members'] = sprintf( __( 'Members of: %s', 'buddypress-docs' ), $group_names );
		$levels['admins-mods'] = sprintf( __( 'Admins and mods of the group %s', 'buddypress-docs' ), $group_names );
	}

	if ( get_the_author_meta( 'ID' ) == bp_loggedin_user_id() ) {
		$levels['creator'] = __( 'The Doc author only (that\'s you!)', 'buddypress-docs' );
	}

	$settings = bp_docs_get_doc_settings();

	// Read
	$read_class = bp_docs_get_permissions_css_class( $settings['read'] );
	$read_text  = sprintf( __( 'This Doc can be read by: <strong>%s</strong>', 'buddypress-docs' ), esc_html( $levels[ $settings['read'] ] ) );

	// Edit
	$edit_class = bp_docs_get_permissions_css_class( $settings['edit'] );
	$edit_text  = sprintf( __( 'This Doc can be edited by: <strong>%s</strong>', 'buddypress-docs' ), esc_html( $levels[ $settings['edit'] ] ) );

	// Read Comments
	$read_comments_class = bp_docs_get_permissions_css_class( $settings['read_comments'] );
	$read_comments_text  = sprintf( __( 'Comments are visible to: <strong>%s</strong>', 'buddypress-docs' ), esc_html( $levels[ $settings['read_comments'] ] ) );

	// Post Comments
	$post_comments_class = bp_docs_get_permissions_css_class( $settings['post_comments'] );
	$post_comments_text  = sprintf( __( 'Comments can be posted by: <strong>%s</strong>', 'buddypress-docs' ), esc_html( $levels[ $settings['post_comments'] ] ) );

	// View History
	$view_history_class = bp_docs_get_permissions_css_class( $settings['view_history'] );
	$view_history_text  = sprintf( __( 'History can be viewed by: <strong>%s</strong>', 'buddypress-docs' ), esc_html( $levels[ $settings['view_history'] ] ) );

	// Calculate summary
	// Summary works like this:
	//  'public'  - all read_ items set to 'anyone', all others to 'anyone' or 'loggedin'
	//  'private' - everything set to 'admins-mods', 'creator', 'no-one', 'friends', or 'group-members' where the associated group is non-public
	//  'limited' - everything else
	$anyone_count  = 0;
	$private_count = 0;
	$public_settings = array(
		'read'          => 'anyone',
		'edit'          => 'loggedin',
		'read_comments' => 'anyone',
		'post_comments' => 'loggedin',
		'view_history'  => 'anyone'
	);

	foreach ( $settings as $l => $v ) {
		if ( 'anyone' == $v || ( isset( $public_settings[ $l ] ) && $public_settings[ $l ] == $v ) ) {

			$anyone_count++;

		} else if ( in_array( $v, array( 'admins-mods', 'creator', 'no-one', 'friends', 'group-members' ) ) ) {

			if ( 'group-members' == $v ) {
				if ( ! isset( $group_status ) ) {
					$group_status = 'foo'; // todo
				}

				if ( 'public' != $group_status ) {
					$private_count++;
				}
			} else {
				$private_count++;
			}

		}
	}

	$settings_count = count( $public_settings );
	if ( $settings_count == $private_count ) {
		$summary       = 'private';
		$summary_label = __( 'Private', 'buddypress-docs' );
	} else if ( $settings_count == $anyone_count ) {
		$summary       = 'public';
		$summary_label = __( 'Public', 'buddypress-docs' );
	} else {
		$summary       = 'limited';
		$summary_label = __( 'Limited', 'buddypress-docs' );
	}

	$html .= '<div id="doc-permissions-summary" class="doc-' . $summary . '">';
	$html .= $summary_before_content;
 $html .=   sprintf( __( 'Access: <strong>%s</strong>', 'buddypress-docs' ), esc_html( $summary_label ) );
	$html .=   '<a href="#" class="doc-permissions-toggle" id="doc-permissions-more">' . esc_html__( 'Show Details', 'buddypress-docs' ) . '</a>';
	$html .= $summary_after_content;
 $html .= '</div>';

	$html .= '<div id="doc-permissions-details">';
	$html .=   '<ul>';
	$html .=     '<li class="bp-docs-can-read ' . esc_attr( $read_class ) . '"><span class="bp-docs-level-icon"></span>' . '<span class="perms-text">' . $read_text . '</span></li>';
	$html .=     '<li class="bp-docs-can-edit ' . esc_attr( $edit_class ) . '"><span class="bp-docs-level-icon"></span>' . '<span class="perms-text">' . $edit_text . '</span></li>';
	$html .=     '<li class="bp-docs-can-read_comments ' . esc_attr( $read_comments_class ) . '"><span class="bp-docs-level-icon"></span>' . '<span class="perms-text">' . $read_comments_text . '</span></li>';
	$html .=     '<li class="bp-docs-can-post_comments ' . esc_attr( $post_comments_class ) . '"><span class="bp-docs-level-icon"></span>' . '<span class="perms-text">' . $post_comments_text . '</span></li>';
	$html .=     '<li class="bp-docs-can-view_history ' . esc_attr( $view_history_class ) . '"><span class="bp-docs-level-icon"></span>' . '<span class="perms-text">' . $view_history_text . '</span></li>';
	$html .=   '</ul>';

	if ( current_user_can( 'bp_docs_manage' ) )
		$html .=   '<a href="' . esc_url( bp_docs_get_doc_edit_link() ) . '#doc-settings" id="doc-permissions-edit">' . esc_html__( 'Edit', 'buddypress-docs' ) . '</a>';

	$html .=   '<a href="#" class="doc-permissions-toggle" id="doc-permissions-less">' . esc_html__( 'Hide Details', 'buddypress-docs' ) . '</a>';
	$html .= '</div>';

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $html;
}

function bp_docs_get_permissions_css_class( $level ) {
	return apply_filters( 'bp_docs_get_permissions_css_class', 'bp-docs-level-' . $level );
}

/**
 * Blasts any previous queries stashed in the BP global
 *
 * @since 1.2
 */
function bp_docs_reset_query() {
	global $bp;

	if ( isset( $bp->bp_docs->doc_query ) ) {
		unset( $bp->bp_docs->doc_query );
	}
}

/**
 * Get a total doc count, for a user, a group, or the whole site
 *
 * @since 1.2
 * @todo Total sitewide doc count
 *
 * @param int $item_id The id of the item (user or group)
 * @param str $item_type 'user' or 'group'
 * @return int
 */
function bp_docs_get_doc_count( $item_id = 0, $item_type = '' ) {
	$doc_count = 0;

	switch ( $item_type ) {
		case 'user' :
			$doc_count = get_user_meta( $item_id, 'bp_docs_count', true );

			if ( '' === $doc_count ) {
				$doc_count = bp_docs_update_doc_count( $item_id, 'user' );
			}

			break;
		case 'group' :
			$doc_count = groups_get_groupmeta( $item_id, 'bp-docs-count' );

			if ( '' === $doc_count ) {
				$doc_count = bp_docs_update_doc_count( $item_id, 'group' );
			}
			break;
	}

	return apply_filters( 'bp_docs_get_doc_count', (int)$doc_count, $item_id, $item_type );
}

/**
 * Is the current page a single Doc?
 *
 * @since 1.2
 * @return bool
 */
function bp_docs_is_single_doc() {
	global $wp_query;

	$is_single_doc = false;

	// There's an odd bug in WP_Query that causes errors when attempting to access
	// get_queried_object() too early. The check for $wp_query->post is a workaround
	if ( is_singular() && ! empty( $wp_query->post ) ) {
		$post = get_queried_object();

		if ( isset( $post->post_type ) && bp_docs_get_post_type_name() == $post->post_type ) {
			$is_single_doc = true;
		}
	}

	return apply_filters( 'bp_docs_is_single_doc', $is_single_doc );
}

/**
 * Is the current page a single Doc 'read' view?
 *
 * By process of elimination.
 *
 * @since 1.2
 * @return bool
 */
function bp_docs_is_doc_read() {
	$is_doc_read = false;

	if ( bp_docs_is_single_doc() &&
	     ! bp_docs_is_doc_edit() &&
	     ( !function_exists( 'bp_docs_is_doc_history' ) || !bp_docs_is_doc_history() )
	   ) {
	 	$is_doc_read = true;
	}

	return apply_filters( 'bp_docs_is_doc_read', $is_doc_read );
}


/**
 * Is the current page a doc edit?
 *
 * @since 1.2
 * @return bool
 */
function bp_docs_is_doc_edit() {
	$is_doc_edit = false;

	if ( bp_docs_is_single_doc() && 1 == get_query_var( BP_DOCS_EDIT_SLUG ) ) {
		$is_doc_edit = true;
	}

	return apply_filters( 'bp_docs_is_doc_edit', $is_doc_edit );
}

/**
 * Is this the Docs create screen?
 *
 * @since 1.2
 * @return bool
 */
function bp_docs_is_doc_create() {
	$is_doc_create = false;

	if ( is_post_type_archive( bp_docs_get_post_type_name() ) && 1 == get_query_var( BP_DOCS_CREATE_SLUG ) ) {
		$is_doc_create = true;
	}

	return apply_filters( 'bp_docs_is_doc_create', $is_doc_create );
}

/**
 * Is this the My Groups tab of the Docs archive?
 *
 * @since 1.2
 * @return bool
 */
function bp_docs_is_mygroups_docs() {
	$is_mygroups_docs = false;

	if ( is_post_type_archive( bp_docs_get_post_type_name() ) && 1 == get_query_var( BP_DOCS_MY_GROUPS_SLUG ) ) {
		$is_mygroups_docs = true;
	}

	return apply_filters( 'bp_docs_is_mygroups_docs', $is_mygroups_docs );
}

/**
 * Is this the History tab?
 *
 * @since 1.2
 * @return bool
 */
function bp_docs_is_doc_history() {
	$is_doc_history = false;

	if ( bp_docs_is_single_doc() && 1 == get_query_var( BP_DOCS_HISTORY_SLUG ) ) {
		$is_doc_history = true;
	}

	return apply_filters( 'bp_docs_is_doc_history', $is_doc_history );
}

/**
 * Is this the Docs tab of a user profile?
 *
 * @since 1.2
 * @return bool
 */
function bp_docs_is_user_docs() {
	$is_user_docs = false;

	if ( bp_is_user() && bp_docs_is_docs_component() ) {
		$is_user_docs = true;
	}

	return apply_filters( 'bp_docs_is_user_docs', $is_user_docs );
}

/**
 * Is this the Started By tab of a user profile?
 *
 * @since 1.2
 * @return bool
 */
function bp_docs_is_started_by() {
	$is_started_by = false;

	if ( bp_docs_is_user_docs() && bp_is_current_action( BP_DOCS_STARTED_SLUG ) ) {
		$is_started_by = true;
	}

	return apply_filters( 'bp_docs_is_started_by', $is_started_by );
}

/**
 * Is this the Edited By tab of a user profile?
 *
 * @since 1.2
 * @return bool
 */
function bp_docs_is_edited_by() {
	$is_edited_by = false;

	if ( bp_docs_is_user_docs() && bp_is_current_action( BP_DOCS_EDITED_SLUG ) ) {
		$is_edited_by = true;
	}

	return apply_filters( 'bp_docs_is_edited_by', $is_edited_by );
}

/**
 * Is this the global Docs directory?
 */
function bp_docs_is_global_directory() {
	$is_global_directory = false;

	if ( is_post_type_archive( bp_docs_get_post_type_name() ) && ! get_query_var( BP_DOCS_MY_GROUPS_SLUG ) && ! get_query_var( BP_DOCS_CREATE_SLUG ) ) {
		$is_global_directory = true;
	}

	return apply_filters( 'bp_docs_is_global_directory', $is_global_directory );
}

/**
 * Is this a single group's Docs tab?
 *
 * @since 1.9
 * @return bool
 */
function bp_docs_is_group_docs() {
	$is_directory = false;

	if ( bp_is_active( 'groups' ) && bp_is_group() && bp_docs_is_docs_component() ) {
		$is_directory = true;
	}

	return apply_filters( 'bp_docs_is_group_docs', $is_directory );
}

/**
 * Is this the My Groups directory?
 *
 * @since 1.5
 * @return bool
 */
function bp_docs_is_mygroups_directory() {
	$is_mygroups_directory = false;

	if ( is_post_type_archive( bp_docs_get_post_type_name() ) && get_query_var( BP_DOCS_MY_GROUPS_SLUG ) && ! get_query_var( BP_DOCS_CREATE_SLUG ) ) {
		$is_mygroups_directory = true;
	}

	return apply_filters( 'bp_docs_is_mygroups_directory', $is_mygroups_directory );
}

function bp_docs_get_sidebar() {
	if ( $template = apply_filters( 'bp_docs_sidebar_template', '' ) ) {
		load_template( $template );
	} else {
		get_sidebar( 'buddypress' );
	}
}

/**
 * Renders the Permissions Snapshot
 *
 * @since 1.3
 */
function bp_docs_render_permissions_snapshot() {
	$show_snapshot = is_user_logged_in();

	if ( apply_filters( 'bp_docs_allow_access_settings', $show_snapshot ) )  {
		?>
		<div class="doc-permissions">
			<?php bp_docs_doc_permissions_snapshot() ?>
		</div>
		<?php
	}
}
add_action( 'bp_docs_single_doc_header_fields', 'bp_docs_render_permissions_snapshot' );

/**
 * Renders the Add Files button area
 *
 * @since 1.4
 */
function bp_docs_media_buttons( $editor_id ) {
	if ( bp_docs_is_existing_doc() && ! current_user_can( 'bp_docs_edit' ) ) {
		return;
	}

	$post = get_post();
	if ( ! $post && ! empty( $GLOBALS['post_ID'] ) )
		$post = $GLOBALS['post_ID'];

	wp_enqueue_media( array(
		'post' => $post
	) );

	$img = '<span class="wp-media-buttons-icon"></span> ';

	?>
	<div class="add-files-button">
		<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<button id="insert-media-button" class="button add-attachment add_media" data-editor="<?php echo esc_attr( $editor_id ); ?>" title="<?php esc_attr_e( 'Add Files', 'buddypress-docs' ); ?>"><?php echo $img; ?><?php esc_html_e( 'Add Files', 'buddypress-docs' ); ?></button>
	</div>
	<?php
}

/**
 * Fetch the attachments for a Doc
 *
 * @since 1.4
 * @return array
 */
function bp_docs_get_doc_attachments( $doc_id = null ) {

	$cache_key = 'bp_docs_attachments:' . $doc_id;
	$cached = wp_cache_get( $cache_key, 'bp_docs_nonpersistent' );
	if ( false !== $cached ) {
		return $cached;
	}

	if ( is_null( $doc_id ) ) {
		$doc = get_post();
		if ( ! empty( $doc->ID ) ) {
			$doc_id = $doc->ID;
		}
	}

	if ( empty( $doc_id ) ) {
		return array();
	}

	/**
	 * Filter the arguments passed to get_posts() when fetching
	 * the attachments for a specific doc.
	 *
	 * @since 1.5
	 *
	 * @param int $doc_id The current doc ID.
	 */
	$atts_args = apply_filters( 'bp_docs_get_doc_attachments_args', array(
		'post_type' => 'attachment',
		'post_parent' => $doc_id,
		'update_post_meta_cache' => true,
		'update_post_term_cache' => false,
		'posts_per_page' => -1,
		'post_status' => 'inherit',
	), $doc_id );

	$atts_query = new WP_Query( $atts_args );
	$atts = apply_filters( 'bp_docs_get_doc_attachments', $atts_query->posts, $doc_id );

	wp_cache_set( $cache_key, $atts, 'bp_docs_nonpersistent' );

	return $atts;
}

/**
 * Get the URL for an attachment download.
 *
 * Is sensitive to whether Docs can be directly downloaded.
 *
 * @param int $attachment_id
 */
function bp_docs_attachment_url( $attachment_id ) {
	echo esc_url( bp_docs_get_attachment_url( $attachment_id ) );
}
	/**
	 * Get the URL for an attachment download.
	 *
	 * Is sensitive to whether Docs can be directly downloaded.
	 *
	 * @param int $attachment_id
	 */
	function bp_docs_get_attachment_url( $attachment_id ) {
		$attachment = get_post( $attachment_id );

		if ( bp_docs_attachment_protection() ) {
			$attachment = get_post( $attachment_id );
			$att_base   = wp_basename( get_attached_file( $attachment_id ) );
			$doc_url    = bp_docs_get_doc_link( $attachment->post_parent );
			$att_url    = add_query_arg( 'bp-attachment', $att_base, $doc_url );
		} else {
			$att_url = wp_get_attachment_url( $attachment_id );
		}

		// Backward compatibility: fix IIS URLs that were broken by a
		// previous implementation
		$att_url = preg_replace( '|bp\-attachments([0-9])|', 'bp-attachments/$1', $att_url );

		return apply_filters( 'bp_docs_attachment_url_base', $att_url, $attachment );
	}


// @todo make <li> optional?
function bp_docs_attachment_item_markup( $attachment_id, $format = 'full' ) {
	$markup = '';

	$att_url    = bp_docs_get_attachment_url( $attachment_id );

	$attachment = get_post( $attachment_id );
	$att_base   = wp_basename( get_attached_file( $attachment_id ) );
	$doc_url    = bp_docs_get_doc_link( $attachment->post_parent );

	$attachment_ext         = preg_replace( '/^.+?\.([^.]+)$/', '$1', $att_url );
	$attachment_delete_html = '';

	if ( 'full' === $format ) {
		if ( current_user_can( 'bp_docs_edit' ) && ( bp_docs_is_doc_edit() || bp_docs_is_doc_create() ) ) {
			$attachment_delete_url = wp_nonce_url( $doc_url, 'bp_docs_delete_attachment_' . $attachment_id );
			$attachment_delete_url = add_query_arg( array(
				'delete_attachment' => $attachment_id,
			), $attachment_delete_url );
			$attachment_delete_html = sprintf(
				'<a href="%s" class="doc-attachment-delete confirm button">%s</a> ',
				esc_url( $attachment_delete_url ),
				esc_html__( 'Delete', 'buddypress-docs' )
			);
		}

		$markup = sprintf(
			'<li id="doc-attachment-%d"><span class="doc-attachment-mime-icon doc-attachment-mime-%s"></span><a href="%s" title="%s">%s</a>%s</li>',
			esc_attr( $attachment_id ),
			esc_attr( $attachment_ext ),
			esc_url( $att_url ),
			esc_attr( $att_base ),
			esc_html( $att_base ),
			$attachment_delete_html
		);
	} else {
		$markup = sprintf(
			'<li id="doc-attachment-%d"><span class="doc-attachment-mime-icon doc-attachment-mime-%s"></span><a href="%s" title="%s">%s</a></li>',
			esc_attr( $attachment_id ),
			esc_attr( $attachment_ext ),
			esc_url( $att_url ),
			esc_attr( $att_base ),
			esc_html( $att_base )
		);
	}

	$filter_args = array(
		'format'      => $format,
		'att_id'      => $attachment_id,
		'att_ext'     => $attachment_ext,
		'att_url'     => $att_url,
		'title_attr'  => esc_attr( $att_base ),
		'link_text'   => esc_html( $att_base ),
		'delete_link' => $attachment_delete_html
	);
	/**
	 * Filters attachment list item output.
	 *
	 * @since 2.2.0
	 *
	 * @param string $markup HTML markup of list item.
	 * @param array  $screen Arguments used to create markup.
	 */
	return apply_filters( 'bp_docs_attachment_item_markup', $markup, $filter_args );
}

/**
 * Does this doc have attachments?
 *
 * @since 1.4
 * @return bool
 */
function bp_docs_doc_has_attachments( $doc_id = null ) {
	if ( is_null( $doc_id ) ) {
		$doc_id = get_the_ID();
	}

	$atts = bp_docs_get_doc_attachments( $doc_id );

	return ! empty( $atts );
}

/**
 * Gets the markup for the paperclip icon in directories
 *
 * @since 1.4
 */
function bp_docs_attachment_icon() {
	$atts = bp_docs_get_doc_attachments( get_the_ID() );

	if ( empty( $atts ) ) {
		return;
	}

	// $pc = plugins_url( BP_DOCS_PLUGIN_SLUG . '/includes/images/paperclip.png' );

	$html = '<a class="bp-docs-attachment-clip" id="bp-docs-attachment-clip-' . esc_attr( get_the_ID() ) . '">' . bp_docs_get_genericon( 'attachment', get_the_ID() ) . '</a>';

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $html;
}

/**
 * Builds the markup for the attachment drawer in directories
 *
 * @since 1.4
 */
function bp_docs_doc_attachment_drawer() {
	$atts = bp_docs_get_doc_attachments( get_the_ID() );
	$html = '';

	if ( ! empty( $atts ) ) {
		$html .= '<ul>';
		$html .= '<h4>' . esc_html__( 'Attachments', 'buddypress-docs' ) . '</h4>';

		foreach ( $atts as $att ) {
			$html .= bp_docs_attachment_item_markup( $att->ID, 'simple' );
		}

		$html .= '</ul>';
	}

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $html;
}

/**
 * Echo the classes for the bp-docs container element.
 *
 * All Docs content appears in a div.bp-docs. Classes are also included for current theme/parent theme, eg
 * 'bp-docs-theme-twentytwelve'.
 *
 * @since 1.9.0
 */
function bp_docs_container_class() {
	echo esc_attr( bp_docs_get_container_class() );
}

/**
 * Generate the classes for the bp-docs container element.
 *
 * All Docs content appears in a div.bp-docs. Classes are also included for current theme/parent theme, eg
 * 'bp-docs-theme-twentytwelve'.
 *
 * @since 1.9.0
 */
function bp_docs_get_container_class() {
	$classes = array();

	$classes[] = 'bp-docs';
	$classes[] = 'bp-docs-container';
	$classes[] = 'bp-docs-theme-' . get_stylesheet();
	$classes[] = 'bp-docs-theme-' . get_template();

	/**
	 * Filter the classes for the bp-docs container element.
	 *
	 * @since 1.9.0
	 *
	 * @param array $classes Array of classes.
	 */
	$classes = apply_filters( 'bp_docs_get_container_classes', $classes );

	$classes = array_unique( array_map( 'sanitize_html_class', $classes ) );

	return implode( ' ', $classes );
}

/**
 * Echo the classes for the bp-docs BuddyPress container element.
 *
 *
 * @since 2.2.1
 */
function bp_docs_buddypress_container_class() {
	echo esc_attr( bp_docs_get_buddypress_container_class() );
}

/**
 * Generate the classes for the bp-docs BuddyPress container element.
 *
 * All Docs content appears in a div.bp-docs. Classes are also included for current theme/parent theme, eg
 * 'bp-docs-theme-twentytwelve'.
 *
 * @since 2.2.1
 */
function bp_docs_get_buddypress_container_class() {
	$classes = array( get_template() );

	if ( bp_docs_theme_supports_wide_layout() ) {
		$classes[] = 'alignwide';
	}

	/**
	 * Filter the classes for the bp-docs BuddyPress container element.
	 *
	 * @since 2.2.1
	 *
	 * @param array $classes Array of classes.
	 */
	$classes = apply_filters( 'bp_docs_get_buddypress_container_class', $classes );

	$classes = array_unique( array_map( 'sanitize_html_class', $classes ) );

	return implode( ' ',  $classes );
}

/**
 * Add classes to a row in the document list table.
 *
 * Currently supports: bp-doc-trashed-doc
 *
 * @since 1.5.5
 */
function bp_docs_doc_row_classes() {
	$classes = array();

	$status = get_post_status( get_the_ID() );
	if ( 'trash' == $status ) {
		$classes[] = 'bp-doc-trashed-doc';
	} elseif ( 'bp_docs_pending' == $status ) {
		$classes[] = 'bp-doc-pending-doc';
	}

	// Pass the classes out as an array for easy unsetting or adding new elements
	$classes = apply_filters( 'bp_docs_doc_row_classes', $classes );

	if ( ! empty( $classes ) ) {
		$classes = implode( ' ', $classes );
		echo ' class="' . esc_attr( $classes ) . '"';
	}
}

/**
 * Add "Trash" notice next to deleted Docs.
 *
 * @since 1.5.5
 */
function bp_docs_doc_trash_notice() {
	$status = get_post_status( get_the_ID() );
	if ( 'trash' == $status ) {
		echo ' <span title="' . esc_attr__( 'This Doc is in the Trash', 'buddypress-docs' ) . '" class="bp-docs-trashed-doc-notice">' . esc_html__( 'Trash', 'buddypress-docs' ) . '</span>';
	} elseif ( 'bp_docs_pending' == $status  ) {
		echo ' <span title="' . esc_attr__( 'This Doc is awaiting moderation', 'buddypress-docs' ) . '" class="bp-docs-pending-doc-notice">' . esc_html__( 'Awaiting Moderation', 'buddypress-docs' ) . '</span>';
	}
}

/**
 * Is the given Doc trashed?
 *
 * @since 1.5.5
 *
 * @param int $doc_id Optional. ID of the doc. Default: current doc.
 * @return bool True if doc is trashed, otherwise false.
 */
function bp_docs_is_doc_trashed( $doc_id = false ) {
	if ( ! $doc_id ) {
		$doc = get_queried_object();
	} else {
		$doc = get_post( $doc_id );
	}

	return isset( $doc->post_status ) && 'trash' == $doc->post_status;
}

/**
 * Output 'toggle-open' or 'toggle-closed' class for toggleable div.
 *
 * @since 1.8
 * @since 2.1 Added $context parameter
 */
function bp_docs_toggleable_open_or_closed_class( $context = 'unknown' ) {
	if ( bp_docs_is_doc_create() ) {
		$class = 'toggle-open';
	} else {
		$class = 'toggle-closed';
	}

	/**
	 * Filters the open/closed class used for toggleable divs.
	 *
	 * @since 2.1.0
	 *
	 * @param string $class   'toggle-open' or 'toggle-closed'.
	 * @param string $context In what context is this function being called.
	 */
	echo esc_attr( apply_filters( 'bp_docs_toggleable_open_or_closed_class', $class, $context ) );
}

/**
 * Output data for JS access on directories.
 *
 * @since 1.9
 */
function bp_docs_ajax_value_inputs() {
	// Store the group ID in a hidden input.
	if ( bp_docs_is_group_docs() ) {
		$group_id = bp_get_current_group_id();
	} else {
		// Having the value always set makes JS easier.
		$group_id = 0;
	}
	?>
	<input type="hidden" id="directory-group-id" value="<?php echo esc_attr( $group_id ); ?>">
	<?php
	// Store the user ID in a hidden input.
	$user_id = bp_displayed_user_id();
	?>
	<input type="hidden" id="directory-user-id" value="<?php echo esc_attr( $user_id ); ?>">
	<?php
	// Allow other plugins to add inputs.
	do_action( 'bp_docs_ajax_value_inputs', $group_id, $user_id );
}

/**
 * Is the current directory view filtered?
 *
 * @since 1.9.0
 *
 * @param array $exclude Filter types to ignore.
 *
 * @return bool
 */
function bp_docs_is_directory_view_filtered( $exclude = array() ) {
	/*
	 * If a string has been passed instead of an array, use it to create an array.
	 */
	if ( ! is_array( $exclude ) ) {
		$exclude = preg_split( '#\s+#', $exclude );
	}

	/**
	 * Other BP Docs components and plugins can hook in here to
	 * declare whether the current view is filtered.
	 * See BP_Docs_Taxonomy::is_directory_view_filtered for example usage.
	 *
	 * @since 1.9.0
	 *
	 * @param bool  $is_filtered Is the current view filtered?
	 * @param array $exclude Array of filter types to ignore.
	 */
	return apply_filters( 'bp_docs_is_directory_view_filtered', false, $exclude );
}

/**
 * Output a genericon-compatible <i> element for displaying icons.
 *
 * @since 1.9
 *
 * @param string $glyph_name The genericon id of the icon.
 * @param string $object_id The ID of the object we're genericoning.
 *
 * @return string HTML representing icon element.
 */
function bp_docs_genericon( $glyph_name, $object_id = null ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo bp_docs_get_genericon( $glyph_name, $object_id );
}
	function bp_docs_get_genericon( $glyph_name, $object_id = null ) {
		if ( empty( $glyph_name ) ) {
			$glyph_name = 'document';
		}
		if ( empty( $object_id ) ) {
			$object_id = get_the_ID();
		}
		$icon_markup = '<i class="genericon genericon-' . esc_attr( $glyph_name ) . '"></i>';
		return apply_filters( 'bp_docs_get_genericon', $icon_markup, $glyph_name, $object_id );
	}

/**
 * Retuns the theme layout available widths.
 * Idea comes directly from the BP Nouveau template pack.
 *
 * @since 2.2.1
 *
 * @return bool $go_wide Whether the theme supports wide content width.
 */
function bp_docs_theme_supports_wide_layout() {
	$go_wide = false;

	if ( current_theme_supports( 'align-wide' ) ) {
		$go_wide = true;
	} else if ( function_exists( 'wp_get_global_settings' ) ) {
		$theme_layouts = wp_get_global_settings( array( 'layout' ) );

		if ( isset( $theme_layouts['wideSize'] ) && $theme_layouts['wideSize'] ) {
			$go_wide = true;
		}
	}

	/**
	 * Filter here to edit whether we should allow a wide layout or not.
	 *
	 * @since 2.2.1
	 *
	 * @param bool $go_wide Whether the theme supports wide content width.
	 */
	return apply_filters( 'bp_docs_theme_supports_wide_layout', $go_wide );
}
