<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Serves the full-screen editor app and the same-origin preview iframe skeleton
 * the canvas renders into. Both are gated behind edit_post.
 */
class Editor {

	const QV_EDITOR  = 'openb_editor';
	const QV_PREVIEW = 'openb_preview';

	public function register_hooks(): void {
		add_action( 'init', [ $this, 'add_query_vars' ] );
		add_action( 'template_redirect', [ $this, 'maybe_render' ], 1 );
		add_action( 'admin_bar_menu', [ $this, 'admin_bar_link' ], 100 );
	}

	public function add_query_vars(): void {
		add_rewrite_tag( '%' . self::QV_EDITOR . '%', '([0-9]+)' );
		add_rewrite_tag( '%' . self::QV_PREVIEW . '%', '([0-9]+)' );
	}

	public static function edit_url( int $post_id ): string {
		return add_query_arg( self::QV_EDITOR, $post_id, home_url( '/' ) );
	}

	public function admin_bar_link( $bar ): void {
		if ( ! is_admin() && is_singular() ) {
			$post_id = get_queried_object_id();
			if ( $post_id && Security::can_edit( $post_id ) && in_array( get_post_type( $post_id ), Post_Types::builder_post_types(), true ) ) {
				$bar->add_node( [
					'id'    => 'openb-edit',
					'title' => __( 'Edit with Open Builder', 'open-builder' ),
					'href'  => self::edit_url( $post_id ),
				] );
			}
		}
	}

	public function maybe_render(): void {
		$editor_id  = isset( $_GET[ self::QV_EDITOR ] ) ? absint( $_GET[ self::QV_EDITOR ] ) : 0;
		$preview_id = isset( $_GET[ self::QV_PREVIEW ] ) ? absint( $_GET[ self::QV_PREVIEW ] ) : 0;

		if ( $editor_id ) {
			$this->guard( $editor_id );
			$this->render_editor( $editor_id );
			exit;
		}
		if ( $preview_id ) {
			$this->guard( $preview_id );
			$this->render_preview( $preview_id );
			exit;
		}
	}

	private function guard( int $post_id ): void {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		if ( ! Security::can_edit( $post_id ) || ! get_post( $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to edit this content.', 'open-builder' ), 403 );
		}
	}

	private function render_editor( int $post_id ): void {
		$plugin = Plugin::instance();
		$tree   = Post_Types::get_tree( $post_id );

		$boot = [
			'restUrl'     => esc_url_raw( rest_url( Rest::NS ) ),
			'restNonce'   => wp_create_nonce( 'wp_rest' ),
			'postId'      => $post_id,
			'postTitle'   => get_the_title( $post_id ),
			'previewUrl'  => add_query_arg( self::QV_PREVIEW, $post_id, home_url( '/' ) ),
			'homeUrl'     => esc_url_raw( home_url( '/' ) ),
			'editQv'      => self::QV_EDITOR,
			'exitUrl'     => get_edit_post_link( $post_id, 'raw' ) ?: admin_url(),
			'viewUrl'     => get_permalink( $post_id ),
			'widgets'     => $plugin->widgets->schema_for_editor(),
			'globals'     => $plugin->global_styles->get(),
			'tree'         => $tree,
			'icons'        => Widget_Icon::set(),
			'canManage'    => Security::can_manage(),
			'isTemplate'   => self::is_chromeless( $post_id ),
			'pageSettings' => Post_Types::get_page_settings( $post_id ),
			'canCustomJs'  => current_user_can( 'unfiltered_html' ),
			'globalCss'    => $plugin->global_styles->get_custom_css(),
			'seoActive'    => Frontend::seo_plugin_active(),
			'dynamicSources' => Dynamic_Tags::sources_for_editor(),
		];

		// Make the WordPress media library available inside our custom document
		// so the Image control can use it. Degrades to URL paste if unavailable.
		wp_enqueue_media();

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );

		$css_url = OPENB_URL . 'editor/css/editor.css?v=' . OPENB_VERSION;
		$js_url  = OPENB_URL . 'editor/js/editor.js?v=' . OPENB_VERSION;
		$title   = esc_html( get_the_title( $post_id ) );
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex,nofollow">
	<title><?php printf( esc_html__( 'Open Builder — %s', 'open-builder' ), $title ); ?></title>
	<link rel="stylesheet" href="<?php echo esc_url( $css_url ); ?>">
	<?php wp_print_styles(); ?>
	<script>window.OPENB_BOOT = <?php echo wp_json_encode( $boot ); ?>;</script>
</head>
<body class="openb-editor-body">
	<div id="openb-app"></div>
	<?php
	// Print the enqueued WP scripts (jQuery, media views) and media templates,
	// then our editor bundle so wp.media is ready when it runs.
	wp_print_scripts();
	if ( function_exists( 'wp_print_media_templates' ) ) {
		wp_print_media_templates();
	}
	if ( function_exists( 'wp_print_footer_scripts' ) ) {
		wp_print_footer_scripts();
	}
	?>
	<script src="<?php echo esc_url( $js_url ); ?>"></script>
</body>
</html>
		<?php
	}

	/**
	 * Resolve the header/footer templates that would apply when viewing the
	 * given post on the front end, so the editor can render the real site chrome
	 * in context. We temporarily swap the global query to a query for this post
	 * so the conditions engine's conditional tags (is_singular, is_front_page…)
	 * reflect the edited post rather than the preview route's home request.
	 *
	 * @return array{header:int,footer:int}
	 */
	/** Post types edited bare in the builder (no theme header/footer chrome). */
	private static function is_chromeless( int $post_id ): bool {
		return in_array( get_post_type( $post_id ), [ Post_Types::CPT_TEMPLATE, Post_Types::CPT_POPUP ], true );
	}

	private function resolve_chrome_for( int $post_id ): array {
		$tb = Plugin::instance()->theme_builder;
		if ( ! $tb ) {
			return [ 'header' => 0, 'footer' => 0 ];
		}

		global $wp_query, $wp_the_query;
		$post_type = get_post_type( $post_id );
		$args      = ( 'page' === $post_type )
			? [ 'page_id' => $post_id ]
			: [ 'p' => $post_id, 'post_type' => $post_type ];

		$saved_query     = $wp_query;
		$saved_the_query = $wp_the_query;

		$sim          = new \WP_Query( $args );
		$wp_query     = $sim;       // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		$wp_the_query = $sim;       // phpcs:ignore WordPress.WP.GlobalVariablesOverride

		$chrome = [
			'header' => $tb->resolve( 'header' ),
			'footer' => $tb->resolve( 'footer' ),
		];

		$wp_query     = $saved_query;     // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		$wp_the_query = $saved_the_query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		wp_reset_postdata();

		return $chrome;
	}

	/**
	 * Minimal same-origin document the canvas iframe loads. The parent editor
	 * injects rendered HTML/CSS into it directly (same origin).
	 *
	 * For pages/posts we also render the resolved theme header above and footer
	 * below the editable canvas, OUTSIDE #openb-canvas, so the page's live
	 * re-renders never touch them. Templates themselves are edited bare (no
	 * chrome wrapped around chrome).
	 */
	private function render_preview( int $post_id ): void {
		$plugin = Plugin::instance();
		// Editor preview: dynamic widgets render placeholders, not live data.
		Render_Context::set( Render_Context::MODE_EDITOR );
		$tree   = Post_Types::get_tree( $post_id );
		$html   = $plugin->renderer->render_tree( $tree );
		$css    = $plugin->renderer->compile_css( $tree, $plugin->global_styles );

		$is_template  = self::is_chromeless( $post_id );
		$chrome       = $is_template ? [ 'header' => 0, 'footer' => 0 ] : $this->resolve_chrome_for( $post_id );
		$header_id    = (int) $chrome['header'];
		$footer_id    = (int) $chrome['footer'];
		$header_html  = $header_id ? Theme_Builder::render_template( $header_id ) : '';
		$footer_html  = $footer_id ? Theme_Builder::render_template( $footer_id ) : '';
		$show_chrome  = ! $is_template;

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );

		$frontend_css = OPENB_URL . 'assets/css/frontend.css?v=' . OPENB_VERSION;
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex,nofollow">
	<link rel="stylesheet" href="<?php echo esc_url( $frontend_css ); ?>">
	<style id="openb-dynamic-css"><?php echo $css; // Compiled from sanitized values. ?></style>
	<style id="openb-global-custom-css"><?php echo $plugin->global_styles->get_custom_css(); // Sanitized at save time. ?></style>
	<style>
		.openb-canvas-empty{padding:80px 20px;text-align:center;color:#9ca3af;font-family:system-ui,sans-serif;border:2px dashed #e4e6eb;border-radius:8px;margin:20px;}
		.openb-editable{position:relative;cursor:pointer;outline:1px solid transparent;transition:outline-color .1s;}
		.openb-editable:hover{outline:1px dashed #93c5fd;}
		.openb-selected{outline:2px solid #2563eb !important;outline-offset:-1px;}
		.openb-drop-before{box-shadow:inset 0 3px 0 0 #2563eb;}
		.openb-drop-after{box-shadow:inset 0 -3px 0 0 #2563eb;}
		.openb-drop-inside{outline:2px dashed #2563eb !important;background:rgba(37,99,235,.04);}
		.openb-inline-editable{cursor:text;}
		.openb-editing{outline:2px solid #2563eb !important;outline-offset:2px;cursor:text;background:rgba(37,99,235,.03);border-radius:2px;}
		[contenteditable="true"]{min-width:8px;}
		body.openb-preview-body{margin:0;}
		.ob-empty-dropzone{display:flex;align-items:center;justify-content:center;min-height:80px;width:100%;border:2px dashed #cbd5e1;border-radius:8px;color:#94a3b8;font-family:system-ui,sans-serif;font-size:13px;background:rgba(148,163,184,.05);}
		/* Theme regions: shown for context, not inline-editable in the page. */
		.openb-region{position:relative;}
		.openb-region::after{content:"";position:absolute;inset:0;pointer-events:none;outline:1px dashed transparent;transition:outline-color .1s,background-color .1s;}
		.openb-region:hover::after{outline-color:#a855f7;background:rgba(168,85,247,.04);}
		.openb-region__badge{position:absolute;top:8px;right:8px;z-index:9;display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:6px;border:none;cursor:pointer;background:#7c3aed;color:#fff;font:600 12px/1 system-ui,sans-serif;box-shadow:0 1px 4px rgba(0,0,0,.25);opacity:0;transition:opacity .12s;}
		.openb-region:hover .openb-region__badge{opacity:1;}
		.openb-region__badge:hover{background:#6d28d9;}
		.openb-region--footer .openb-region__badge{top:auto;bottom:8px;}
		.openb-region-add{display:flex;align-items:center;justify-content:center;gap:8px;min-height:60px;margin:0;padding:16px 20px;cursor:pointer;border:2px dashed #c4b5fd;background:rgba(124,58,237,.04);color:#7c3aed;font:600 14px/1 system-ui,sans-serif;}
		.openb-region-add:hover{background:rgba(124,58,237,.09);border-color:#a855f7;}
		body.openb-hide-chrome .openb-region,body.openb-hide-chrome .openb-region-add{display:none !important;}
	</style>
</head>
<body class="openb-preview-body<?php echo $show_chrome ? '' : ' openb-no-chrome'; ?>">
	<?php if ( $show_chrome ) : ?>
		<?php if ( '' !== $header_html ) : ?>
			<div class="openb-region openb-region--header" data-ob-region="header" data-ob-template="<?php echo esc_attr( $header_id ); ?>">
				<button type="button" class="openb-region__badge" data-ob-edit-region="header"><?php esc_html_e( '✎ Edit Header', 'open-builder' ); ?></button>
				<?php echo $header_html; // Trusted renderer + compiled CSS. ?>
			</div>
		<?php else : ?>
			<div class="openb-region-add" data-ob-add="header"><?php esc_html_e( '+ Add Header', 'open-builder' ); ?></div>
		<?php endif; ?>
	<?php endif; ?>

	<div id="openb-canvas"><?php echo $html; // Built by trusted Renderer from sanitized tree. ?></div>

	<?php if ( $show_chrome ) : ?>
		<?php if ( '' !== $footer_html ) : ?>
			<div class="openb-region openb-region--footer" data-ob-region="footer" data-ob-template="<?php echo esc_attr( $footer_id ); ?>">
				<button type="button" class="openb-region__badge" data-ob-edit-region="footer"><?php esc_html_e( '✎ Edit Footer', 'open-builder' ); ?></button>
				<?php echo $footer_html; // Trusted renderer + compiled CSS. ?>
			</div>
		<?php else : ?>
			<div class="openb-region-add" data-ob-add="footer"><?php esc_html_e( '+ Add Footer', 'open-builder' ); ?></div>
		<?php endif; ?>
	<?php endif; ?>
</body>
</html>
		<?php
	}
}
