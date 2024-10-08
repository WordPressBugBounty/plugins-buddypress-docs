<?php bp_docs_inline_toggle_js() ?>

<?php if ( bp_docs_has_docs() ) : ?>
	<table class="doctable">

	<thead>
		<tr valign="bottom">
			<?php if ( bp_docs_enable_attachments() ) : ?>
				<th scope="column" class="attachment-clip-cell">&nbsp;<span class="screen-reader-text"><?php esc_html_e( 'Has attachment', 'buddypress-docs' ); ?></span></th>
			<?php endif ?>

			<th scope="column" class="title-cell<?php bp_docs_is_current_orderby_class( 'title' ) ?>">
				<a href="<?php bp_docs_order_by_link( 'title' ) ?>"><?php _e( 'Title', 'buddypress-docs' ); ?></a>
			</th>

			<th scope="column" class="author-cell<?php bp_docs_is_current_orderby_class( 'author' ) ?>">
				<a href="<?php bp_docs_order_by_link( 'author' ) ?>"><?php _e( 'Author', 'buddypress-docs' ); ?></a>
			</th>

			<th scope="column" class="created-date-cell<?php bp_docs_is_current_orderby_class( 'created' ) ?>">
				<a href="<?php bp_docs_order_by_link( 'created' ) ?>"><?php _e( 'Created', 'buddypress-docs' ); ?></a>
			</th>

			<th scope="column" class="edited-date-cell<?php bp_docs_is_current_orderby_class( 'modified' ) ?>">
				<a href="<?php bp_docs_order_by_link( 'modified' ) ?>"><?php _e( 'Last Edited', 'buddypress-docs' ); ?></a>
			</th>

			<?php do_action( 'bp_docs_loop_additional_th' ) ?>
		</tr>
        </thead>

        <tbody>
	<?php while ( bp_docs_has_docs() ) : bp_docs_the_doc() ?>
 		<tr<?php bp_docs_doc_row_classes(); ?>>
			<?php if ( bp_docs_enable_attachments() ) : ?>
				<td class="attachment-clip-cell">
					<?php bp_docs_attachment_icon() ?>
				</td>
			<?php endif ?>

			<td class="title-cell">
				<a href="<?php bp_docs_doc_link() ?>"><?php the_title() ?></a> <?php bp_docs_doc_trash_notice(); ?>

				<?php if ( bp_docs_get_excerpt_length() ) : ?>
					<?php the_excerpt() ?>
				<?php endif ?>

				<div class="row-actions">
					<?php bp_docs_doc_action_links() ?>
				</div>

				<div class="bp-docs-attachment-drawer" id="bp-docs-attachment-drawer-<?php echo esc_attr( get_the_ID() ); ?>">
					<?php bp_docs_doc_attachment_drawer() ?>
				</div>
			</td>

			<td class="author-cell">
				<a href="<?php echo esc_url( bp_members_get_user_url( get_the_author_meta( 'ID' ) ) ); ?>" title="<?php echo esc_attr( bp_core_get_user_displayname( get_the_author_meta( 'ID' ) ) ); ?>"><?php echo esc_html( bp_core_get_user_displayname( get_the_author_meta( 'ID' ) ) ); ?></a>
			</td>

			<td class="date-cell created-date-cell">
				<?php echo esc_html( get_the_date() ); ?>
			</td>

			<td class="date-cell edited-date-cell">
				<?php echo esc_html( get_the_modified_date() ); ?>
			</td>

			<?php do_action( 'bp_docs_loop_additional_td' ) ?>
		</tr>
	<?php endwhile ?>
        </tbody>

	</table>

	<div id="bp-docs-pagination">
		<div id="bp-docs-pagination-count">
			<?php echo esc_html( sprintf( __( 'Viewing %1$s-%2$s of %3$s docs', 'buddypress-docs' ), bp_docs_get_current_docs_start(), bp_docs_get_current_docs_end(), bp_docs_get_total_docs_num() ) ); ?>
		</div>

		<div id="bp-docs-paginate-links">
			<?php bp_docs_paginate_links() ?>
		</div>
	</div>

<?php else: ?>

        <?php if ( bp_docs_current_user_can_create_in_context() ) : ?>
			<p class="no-docs"><?php echo wp_kses_post( sprintf( __( 'There are no docs for this view. Why not <a href="%s">create one</a>?', 'buddypress-docs' ), esc_url( bp_docs_get_create_link() ) ) ); ?>
	<?php else : ?>
		<p class="no-docs"><?php _e( 'There are no docs for this view.', 'buddypress-docs' ) ?></p>
        <?php endif ?>

<?php endif ?>
