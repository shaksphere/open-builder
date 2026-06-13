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
