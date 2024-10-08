<?php

/**
 * This file contains the functions used to enable hierarchical docs.
 * Separated into this file so that the feature can be turned off.
 *
 * @package BuddyPressDocs
 */

class BP_Docs_Hierarchy {
	var $parent;
	var $children;

	/**
	 * PHP 5 constructor
	 *
	 * @since 1.0-beta
	 */
	function __construct() {
		add_action( 'parse_query', array( $this, 'parse_query' ) );

		// Make sure that the bp_docs post type supports our post taxonomies
		add_filter( 'bp_docs_post_type_args', array( $this, 'register_with_post_type' ) );

		// Set parent_id for posts saved from the front end
		add_filter( 'bp_docs_get_parent_id_via_post', array( $this, 'set_parent_id_via_post' ) );

		// Display a doc's parent on its single doc page
		add_action( 'bp_docs_single_doc_meta', array( $this, 'show_parent' ) );

		// Display a doc's children on its single doc page
		add_action( 'bp_docs_single_doc_meta', array( $this, 'show_children' ) );

		// When a Doc is deleted, mark its children as parentless
		add_action( 'bp_docs_doc_deleted', array( $this, 'deleted_parent' ) );
	}

	/**
	 * Ensure that child Docs have the query parsed correctly.
	 *
	 * get_page_by_path() is mangling the parse, because I've filtered out
	 * the ancestors' slugs from the URL. What I'm doing below is pretty
	 * hackish.
	 *
	 * @since 1.8.5
	 */
	public function parse_query( $q ) {
		$pt = $q->get( 'post_type' );
		if ( ! $pt || bp_docs_get_post_type_name() !== $pt ) {
			return;
		}

		$post_name = $q->get( 'name' );
		if ( ! $post_name ) {
			return;
		}

		// Bypass get_post_by_path().
		global $wpdb;
		$sql = $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE post_name = %s AND post_type = %s AND post_parent != 0", $post_name, $pt );
		$post_id = $wpdb->get_var( $sql );

		if ( ! empty( $post_id ) ) {
			$q->set( 'p', $post_id );
			$q->set( 'name', '' );
			$q->set( 'pagename', '' );
			$q->set( bp_docs_get_post_type_name(), '' );
		}
	}

	/**
	 * Registers the post taxonomies with the bp_docs post type
	 *
	 * @since 1.0-beta
	 *
	 * @param array The $bp_docs_post_type_args array created in BP_Docs::register_post_type()
	 * @return array $args The modified parameters
	 */
	function register_with_post_type( $args ) {
		$args['hierarchical'] = true;

		return $args;
	}

	/**
	 * Set parent_id for posts saved from the front end
	 *
	 * @since 1.0-beta
	 *
	 * @param int $parent_id The parent ID from BP_Docs_Query->save()
	 * @return int $parent_id Returns the doc's parent_id on success
	 */
	function set_parent_id_via_post( $parent_id ) {
		if ( ! empty( $_POST['parent_id'] ) ) {
			$parent_id = $_POST['parent_id'];
		}

		return $parent_id;
	}

	/**
	 * Display a link to the doc's parent
	 *
	 * @since 1.0-beta
	 */
	function show_parent() {
		global $post, $wp_query;

		$html = '';
		$parent = false;

		if ( ! empty( $post->post_parent ) ) {
			$parent = get_post( $post->post_parent );
			if ( !empty( $parent->ID ) ) {
				$parent_url = bp_docs_get_doc_link( $parent->ID );
				$parent_title = $parent->post_title;

				$html = '<p>' . esc_html__( 'Parent: ', 'buddypress-docs' ) . sprintf( '<a href="%s" title="%s">%s</a>', esc_url( $parent_url ), esc_attr( $parent_title ), esc_html( $parent_title ) );
			}
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo apply_filters( 'bp_docs_hierarchy_show_parent', $html, $parent );
	}

	/**
	  * Display links to the doc's children
	  *
	  * @since 1.0
	  */
	function show_children() {
		global $bp, $wp_query, $post;

		// Get the child posts
		$child_posts_args = array(
			'post_type'	=> $bp->bp_docs->post_type_name,
			'post_parent'	=> get_the_ID()
		);

		$child_posts = new WP_Query( $child_posts_args );

		// Workaround for WP funniness
		$wp_query_stash = $wp_query;
		$post_stash	= $post;

		// Assemble the link data out of the query
		$child_data = array();
		if ( $child_posts->have_posts() ) {
			while ( $child_posts->have_posts() ) {
				$child_posts->the_post();

				$child_id = get_the_ID();
				$child_data[$child_id] = array(
					'post_name' => get_the_title(),
					'post_link' => bp_docs_get_doc_link( $child_id )
				);
			}
		}

		// Workaround for WP funniness
		$wp_query = $wp_query_stash;
		$post     = $post_stash;

		$child_data = apply_filters( 'bp_docs_hierarchy_child_data', $child_data );

		// Create the HTML
		$html = '';
		if ( !empty( $child_data ) ) {
			$html .= '<p>' . esc_html__( 'Children: ', 'buddypress-docs' );

			$children_html = array();
			foreach( $child_data as $child ) {
				$children_html[] = '<a href="' . esc_url( $child['post_link'] ) . '">' . esc_html( $child['post_name'] ) . '</a>';
			}

			$html .= implode( ', ', $children_html );

			$html .= '</p>';
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo apply_filters( 'bp_docs_hierarchy_show_children', $html, $child_data );
	}

	/**
	 * When a Doc is deleted, mark its children as having no parent
	 *
	 * @since 1.5
	 * @param array $args The $delete_args array from bp_docs_trash_dac()
	 */
	public function deleted_parent( $args ) {
		if ( ! isset( $args['ID'] ) ) {
			return;
		}

		$children = get_children( array(
			'post_parent' => $args['ID'],
			'post_type' => bp_docs_get_post_type_name(),
		), ARRAY_A );

		foreach ( $children as $cid => $child ) {
			wp_update_post( array(
				'ID' => $child['ID'],
				'post_parent' => 0,
			) );
		}
	}
}
