<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Admin surface: top-level menu, "Edit with Open Builder" entry points on the
 * post screen and list tables, the form-entries viewer and a link to the
 * global styles editor (which lives in the builder app).
 */
class Admin {

	public function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'add_meta_boxes', [ $this, 'meta_box' ] );
		add_filter( 'page_row_actions', [ $this, 'row_action' ], 10, 2 );
		add_filter( 'post_row_actions', [ $this, 'row_action' ], 10, 2 );
		add_action( 'admin_post_openb_flush_css', [ $this, 'handle_flush_css' ] );
	}

	/** Flush all cached CSS files; they lazily rebuild on next visit/save. */
	public function handle_flush_css(): void {
		if ( ! current_user_can( 'edit_pages' ) || ! check_admin_referer( 'openb_flush_css' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'open-builder' ), 403 );
		}
		Plugin::instance()->css_store->flush_all();
		Plugin::instance()->css_store->rebuild_global();
		wp_safe_redirect( add_query_arg( 'openb_flushed', '1', admin_url( 'admin.php?page=open-builder' ) ) );
		exit;
	}

	public function menu(): void {
		add_menu_page(
			__( 'Open Builder', 'open-builder' ),
			__( 'Open Builder', 'open-builder' ),
			'edit_pages',
			'open-builder',
			[ $this, 'dashboard_page' ],
			'dashicons-layout',
			58
		);

		add_submenu_page(
			'open-builder',
			__( 'Templates', 'open-builder' ),
			__( 'Templates', 'open-builder' ),
			'edit_pages',
			'edit.php?post_type=' . Post_Types::CPT_TEMPLATE
		);

		add_submenu_page(
			'open-builder',
			__( 'Form Entries', 'open-builder' ),
			__( 'Form Entries', 'open-builder' ),
			'edit_pages',
			'open-builder-entries',
			[ $this, 'entries_page' ]
		);
	}

	public function dashboard_page(): void {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Open Builder', 'open-builder' ) . '</h1>';
		echo '<p>' . esc_html__( 'Edit any page or post with the visual builder, or manage your global brand settings inside the editor.', 'open-builder' ) . '</p>';

		if ( isset( $_GET['openb_flushed'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'CSS cache cleared. Files rebuild automatically on next visit.', 'open-builder' ) . '</p></div>';
		}

		$pages = get_posts( [ 'post_type' => 'page', 'numberposts' => 20, 'orderby' => 'modified', 'order' => 'DESC' ] );
		if ( $pages ) {
			echo '<h2>' . esc_html__( 'Recent Pages', 'open-builder' ) . '</h2><ul class="ob-admin-list">';
			foreach ( $pages as $p ) {
				printf(
					'<li><strong>%s</strong> — <a class="button button-primary button-small" href="%s">%s</a></li>',
					esc_html( get_the_title( $p ) ),
					esc_url( Editor::edit_url( $p->ID ) ),
					esc_html__( 'Edit with Open Builder', 'open-builder' )
				);
			}
			echo '</ul>';
		}

		// Theme Builder section.
		echo '<h2>' . esc_html__( 'Theme Builder', 'open-builder' ) . '</h2>';
		echo '<p>' . esc_html__( 'Design global headers, footers and templates, then assign them with display conditions.', 'open-builder' ) . '</p>';
		$new_url = admin_url( 'post-new.php?post_type=' . Post_Types::CPT_TEMPLATE );
		echo '<p>';
		printf( '<a class="button button-primary" href="%s">%s</a> ', esc_url( $new_url ), esc_html__( 'New Template', 'open-builder' ) );
		printf( '<a class="button" href="%s">%s</a>', esc_url( admin_url( 'edit.php?post_type=' . Post_Types::CPT_TEMPLATE ) ), esc_html__( 'All Templates', 'open-builder' ) );
		echo '</p>';

		$templates = get_posts( [ 'post_type' => Post_Types::CPT_TEMPLATE, 'numberposts' => 50 ] );
		if ( $templates ) {
			echo '<table class="widefat striped" style="max-width:760px"><thead><tr><th>' . esc_html__( 'Template', 'open-builder' ) . '</th><th>' . esc_html__( 'Type', 'open-builder' ) . '</th><th></th></tr></thead><tbody>';
			$types = Theme_Builder::types();
			foreach ( $templates as $tpl ) {
				$type = get_post_meta( $tpl->ID, Theme_Builder::META_TYPE, true );
				printf(
					'<tr><td><strong>%s</strong></td><td>%s</td><td><a class="button button-small button-primary" href="%s">%s</a> <a class="button button-small" href="%s">%s</a></td></tr>',
					esc_html( get_the_title( $tpl ) ),
					esc_html( $types[ $type ] ?? $type ),
					esc_url( Editor::edit_url( $tpl->ID ) ),
					esc_html__( 'Edit', 'open-builder' ),
					esc_url( get_edit_post_link( $tpl->ID ) ),
					esc_html__( 'Conditions', 'open-builder' )
				);
			}
			echo '</tbody></table>';
		}

		// Tools.
		echo '<h2>' . esc_html__( 'Tools', 'open-builder' ) . '</h2>';
		echo '<p>' . esc_html__( 'CSS is cached to files in your uploads folder for performance. Regenerate them after updating the plugin or theme.', 'open-builder' ) . '</p>';
		printf(
			'<form method="post" action="%s">%s<input type="hidden" name="action" value="openb_flush_css"><button type="submit" class="button">%s</button></form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			wp_nonce_field( 'openb_flush_css', '_wpnonce', true, false ),
			esc_html__( 'Regenerate CSS Cache', 'open-builder' )
		);

		echo '</div>';
	}

	public function entries_page(): void {
		$entries = Plugin::instance()->forms->get_entries( 200 );
		echo '<div class="wrap"><h1>' . esc_html__( 'Form Entries', 'open-builder' ) . '</h1>';
		if ( empty( $entries ) ) {
			echo '<p>' . esc_html__( 'No submissions yet.', 'open-builder' ) . '</p></div>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Date', 'open-builder' ) . '</th>';
		echo '<th>' . esc_html__( 'Source', 'open-builder' ) . '</th>';
		echo '<th>' . esc_html__( 'Data', 'open-builder' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $entries as $row ) {
			$data = json_decode( $row['data'], true );
			$pairs = [];
			if ( is_array( $data ) ) {
				foreach ( $data as $entry ) {
					$pairs[] = esc_html( ( $entry['label'] ?? '' ) . ': ' . ( $entry['value'] ?? '' ) );
				}
			}
			printf(
				'<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
				esc_html( $row['created_at'] ),
				esc_html( get_the_title( (int) $row['post_id'] ) ?: '#' . (int) $row['post_id'] ),
				implode( '<br>', $pairs )
			);
		}
		echo '</tbody></table></div>';
	}

	public function meta_box(): void {
		foreach ( Post_Types::builder_post_types() as $type ) {
			add_meta_box(
				'openb-launch',
				__( 'Open Builder', 'open-builder' ),
				[ $this, 'render_meta_box' ],
				$type,
				'side',
				'high'
			);
		}
	}

	public function render_meta_box( \WP_Post $post ): void {
		$enabled = Post_Types::is_enabled( $post->ID );
		echo '<p>';
		printf(
			'<a href="%s" class="button button-primary button-hero" style="width:100%%;text-align:center;">%s</a>',
			esc_url( Editor::edit_url( $post->ID ) ),
			esc_html__( 'Edit with Open Builder', 'open-builder' )
		);
		echo '</p>';
		if ( $enabled ) {
			echo '<p class="description">' . esc_html__( 'This content is built with Open Builder. Editing the WordPress content here will be overridden on the front end.', 'open-builder' ) . '</p>';
		}
	}

	public function row_action( array $actions, \WP_Post $post ): array {
		if ( Security::can_edit( $post->ID ) && in_array( $post->post_type, Post_Types::builder_post_types(), true ) ) {
			$actions['openb_edit'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( Editor::edit_url( $post->ID ) ),
				esc_html__( 'Open Builder', 'open-builder' )
			);
		}
		return $actions;
	}
}
