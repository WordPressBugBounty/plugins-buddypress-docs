<?php
$bp_docs_do_theme_compat = is_buddypress() && bp_docs_do_theme_compat( 'single/history.php' );
if ( ! $bp_docs_do_theme_compat ) : ?>
<div id="buddypress" class="<?php bp_docs_buddypress_container_class(); ?>">
<?php endif; ?>

<div class="<?php bp_docs_container_class(); ?>">

	<?php include( apply_filters( 'bp_docs_header_template', bp_docs_locate_template( 'docs-header.php' ) ) ) ?>

	<div class="doc-content">

	<?php if ( bp_docs_history_is_latest() ) : ?>

		<p><?php _e( "Click on a revision date from the list below to view that revision.", 'buddypress-docs' ) ?></p>

		<p><?php _e( "Alternatively, you can compare two revisions by selecting them in the 'Old' and 'New' columns, and clicking 'Compare Revisions'.", 'buddypress-docs' ) ?></p>

	<?php endif ?>

	<table class="form-table ie-fixed">
		<col class="th" />

		<?php if ( 'diff' == bp_docs_history_action() ) : ?>
			<tr id="revision">
				<th scope="row"></th>
				<th scope="col" class="th-full">
					<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span class="alignleft"><?php printf( __( 'Older: %s', 'buddypress-docs' ), bp_docs_history_post_revision_field( 'left', 'post_title' ) ); ?></span>
					<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span class="alignright"><?php printf( __( 'Newer: %s', 'buddypress-docs' ), bp_docs_history_post_revision_field( 'right', 'post_title' ) ); ?></span>
				</th>
			</tr>
		<?php elseif ( !bp_docs_history_is_latest() ) : ?>
			<tr id="revision">
				<th scope="row"></th>
				<th scope="col" class="th-full">
					<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span class="alignleft"><?php printf( __( 'You are currently viewing a revision titled "%1$s", saved on %2$s by %3$s', 'buddypress-docs' ), bp_docs_history_post_revision_field( false, 'post_title' ), esc_html( bp_format_time( strtotime( bp_docs_history_post_revision_field( false, 'post_date' ) ) ) ), bp_core_get_userlink( bp_docs_history_post_revision_field( false, 'post_author' ) ) ); ?></span>
				</th>
			</tr>
		<?php endif ?>

		<?php foreach ( _wp_post_revision_fields() as $field => $field_title ) : ?>
			<?php if ( 'diff' == bp_docs_history_action() ) : ?>
				<tr id="revision-field-<?php echo esc_attr( $field ); ?>">
					<th scope="row"><?php echo esc_html( $field_title ); ?></th>
					<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<td><div class="pre"><?php echo wp_text_diff( bp_docs_history_post_revision_field( 'left', $field ), bp_docs_history_post_revision_field( 'right', $field ) ) ?></div></td>
				</tr>
			<?php elseif ( !bp_docs_history_is_latest() ) : ?>
				<tr id="revision-field-<?php echo esc_attr( $field ); ?>">
					<th scope="row"><?php echo esc_html( $field_title ); ?></th>
					<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<td><div class="pre"><?php echo bp_docs_history_post_revision_field( false, $field ) ?></div></td>
				</tr>

			<?php endif ?>

		<?php endforeach ?>

		<?php do_action( 'bp_docs_revisions_comparisons' ) ?>

		<?php if ( 'diff' == bp_docs_history_action() && bp_docs_history_revisions_are_identical() ) : ?>
			<tr><td colspan="2"><div class="updated"><p><?php _e( 'These revisions are identical.', 'buddypress-docs' ); ?></p></div></td></tr>
		<?php endif ?>

	</table>

	<br class="clear" />

	<?php bp_docs_list_post_revisions( get_the_ID() ) ?>

	</div>

</div><!-- .bp-docs -->

<?php if ( ! $bp_docs_do_theme_compat ) : ?>
</div><!-- /#buddypress -->
<?php endif; ?>
