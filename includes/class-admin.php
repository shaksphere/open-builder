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
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/** Register plugin settings (Settings API). */
	public function register_settings(): void {
		register_setting( 'openb_settings', Theme_Builder::OPTION_SITE_WIDE, [
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => false,
		] );
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
			__( 'Popups', 'open-builder' ),
			__( 'Popups', 'open-builder' ),
			'edit_pages',
			'edit.php?post_type=' . Post_Types::CPT_POPUP
		);

		add_submenu_page(
			'open-builder',
			__( 'Blocks', 'open-builder' ),
			__( 'Blocks', 'open-builder' ),
			'edit_pages',
			'edit.php?post_type=' . Post_Types::CPT_BLOCK
		);

		add_submenu_page(
			'open-builder',
			__( 'Form Entries', 'open-builder' ),
			__( 'Form Entries', 'open-builder' ),
			'edit_pages',
			'open-builder-entries',
			[ $this, 'entries_page' ]
		);

		add_submenu_page(
			'open-builder',
			__( 'Settings', 'open-builder' ),
			__( 'Settings', 'open-builder' ),
			'manage_options',
			'open-builder-settings',
			[ $this, 'settings_page' ]
		);
	}

	public function settings_page(): void {
		echo '<div class="wrap"><h1>' . esc_html__( 'Open Builder Settings', 'open-builder' ) . '</h1>';
		echo '<form method="post" action="options.php">';
		settings_fields( 'openb_settings' );

		$site_wide = Theme_Builder::site_wide_enabled();
		echo '<h2>' . esc_html__( 'Theme Templates', 'open-builder' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'Apply across the entire site', 'open-builder' ) . '</th><td>';
		printf(
			'<label><input type="checkbox" name="%s" value="1" %s> %s</label>',
			esc_attr( Theme_Builder::OPTION_SITE_WIDE ),
			checked( $site_wide, true, false ),
			esc_html__( 'Let my header/footer/templates take over the whole site', 'open-builder' )
		);
		echo '<p class="description">' . esc_html__( 'Off (recommended): Open Builder only controls pages you build with it, plus archive/search/404 pages that have a matching template. Pages built with your theme or another builder (e.g. Elementor) are never altered.', 'open-builder' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'On (advanced): your Open Builder header/footer and templates apply to every page, replacing the output of your theme and other builders. Only enable this once your whole site is built with Open Builder.', 'open-builder' ) . '</p>';
		echo '</td></tr>';
		echo '</tbody></table>';

		submit_button();
		echo '</form></div>';
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

		// Popups section.
		echo '<h2>' . esc_html__( 'Popups', 'open-builder' ) . '</h2>';
		echo '<p>' . esc_html__( 'Design popups, choose a trigger (load, exit-intent, scroll, click or inactivity), and assign them with display conditions.', 'open-builder' ) . '</p>';
		echo '<p>';
		printf( '<a class="button button-primary" href="%s">%s</a> ', esc_url( admin_url( 'post-new.php?post_type=' . Post_Types::CPT_POPUP ) ), esc_html__( 'New Popup', 'open-builder' ) );
		printf( '<a class="button" href="%s">%s</a>', esc_url( admin_url( 'edit.php?post_type=' . Post_Types::CPT_POPUP ) ), esc_html__( 'All Popups', 'open-builder' ) );
		echo '</p>';

		$popups = get_posts( [ 'post_type' => Post_Types::CPT_POPUP, 'numberposts' => 50 ] );
		if ( $popups ) {
			$tt = Popups::trigger_types();
			echo '<table class="widefat striped" style="max-width:760px"><thead><tr><th>' . esc_html__( 'Popup', 'open-builder' ) . '</th><th>' . esc_html__( 'Trigger', 'open-builder' ) . '</th><th></th></tr></thead><tbody>';
			foreach ( $popups as $pp ) {
				$trig = Popups::get_trigger( $pp->ID );
				printf(
					'<tr><td><strong>%s</strong></td><td>%s</td><td><a class="button button-small button-primary" href="%s">%s</a> <a class="button button-small" href="%s">%s</a></td></tr>',
					esc_html( get_the_title( $pp ) ),
					esc_html( $tt[ $trig['type'] ] ?? $trig['type'] ),
					esc_url( Editor::edit_url( $pp->ID ) ),
					esc_html__( 'Design', 'open-builder' ),
					esc_url( get_edit_post_link( $pp->ID ) ),
					esc_html__( 'Settings', 'open-builder' )
				);
			}
			echo '</tbody></table>';
		}

		// Saved Blocks section.
		echo '<h2>' . esc_html__( 'Saved Blocks', 'open-builder' ) . '</h2>';
		echo '<p>' . esc_html__( 'Design a block once and reuse it across pages with the Global Block widget. Editing a block updates it everywhere it is used.', 'open-builder' ) . '</p>';
		echo '<p>';
		printf( '<a class="button button-primary" href="%s">%s</a> ', esc_url( admin_url( 'post-new.php?post_type=' . Post_Types::CPT_BLOCK ) ), esc_html__( 'New Block', 'open-builder' ) );
		printf( '<a class="button" href="%s">%s</a>', esc_url( admin_url( 'edit.php?post_type=' . Post_Types::CPT_BLOCK ) ), esc_html__( 'All Blocks', 'open-builder' ) );
		echo '</p>';

		$blocks = get_posts( [ 'post_type' => Post_Types::CPT_BLOCK, 'numberposts' => 50 ] );
		if ( $blocks ) {
			echo '<table class="widefat striped" style="max-width:760px"><thead><tr><th>' . esc_html__( 'Block', 'open-builder' ) . '</th><th></th></tr></thead><tbody>';
			foreach ( $blocks as $bl ) {
				printf(
					'<tr><td><strong>%s</strong></td><td><a class="button button-small button-primary" href="%s">%s</a></td></tr>',
					esc_html( get_the_title( $bl ) ),
					esc_url( Editor::edit_url( $bl->ID ) ),
					esc_html__( 'Edit', 'open-builder' )
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
