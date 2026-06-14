<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Front-end output. When a post is built with Open Builder we replace its
 * content with the rendered tree and print the compiled (cached) CSS in the
 * head. Form JS is enqueued only when needed.
 */
class Frontend {

	/** @var Renderer */
	private $renderer;

	/** @var int[] posts whose CSS we still need to print */
	private $queued_css = [];

	public function __construct( Renderer $renderer ) {
		$this->renderer = $renderer;
	}

	public function register_hooks(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
		add_filter( 'the_content', [ $this, 'filter_content' ], 9 );
		add_action( 'wp_head', [ $this, 'print_css' ], 20 );

		// Page settings: body classes, page CSS/background, and gated custom JS.
		add_filter( 'body_class', [ $this, 'page_body_classes' ] );
		add_action( 'wp_head', [ $this, 'print_page_css' ], 21 );
		add_action( 'wp_footer', [ $this, 'print_page_js' ], 99 );
	}

	/** Page settings for the current singular request, or [] if none. */
	private function current_page_settings(): array {
		if ( ! is_singular() ) {
			return [];
		}
		return Post_Types::get_page_settings( get_queried_object_id() );
	}

	public function page_body_classes( array $classes ): array {
		$page = $this->current_page_settings();
		if ( empty( $page ) ) {
			return $classes;
		}
		if ( ! empty( $page['layout'] ) && 'default' !== $page['layout'] ) {
			$classes[] = 'openb-layout-' . sanitize_html_class( $page['layout'] );
		}
		if ( ! empty( $page['hide_title'] ) ) {
			$classes[] = 'openb-hide-title';
		}
		if ( ! empty( $page['body_classes'] ) ) {
			foreach ( preg_split( '/\s+/', $page['body_classes'] ) as $c ) {
				$classes[] = sanitize_html_class( $c );
			}
		}
		return array_filter( $classes );
	}

	public function print_page_css(): void {
		$page = $this->current_page_settings();
		if ( empty( $page ) ) {
			return;
		}
		$post_id = get_queried_object_id();
		$css = '';

		if ( ! empty( $page['background'] ) ) {
			$css .= sprintf( 'body.postid-%d{background:%s;}', (int) $post_id, $page['background'] );
		}
		if ( ! empty( $page['content_width'] ) ) {
			$css .= sprintf( '.postid-%d .ob-root{--ob-container:%s;}', (int) $post_id, $page['content_width'] );
		}
		if ( ! empty( $page['hide_title'] ) ) {
			// Best-effort across common themes; CSS-only so it never errors.
			$css .= '.openb-hide-title .entry-title,.openb-hide-title .page-title,.openb-hide-title .wp-block-post-title{display:none!important;}';
		}
		// Page custom CSS (already sanitized at save time).
		if ( ! empty( $page['custom_css'] ) ) {
			$css .= $page['custom_css'];
		}

		if ( '' !== $css ) {
			printf( "<style id=\"openb-page-%d\">%s</style>\n", (int) $post_id, $css );
		}
	}

	public function print_page_js(): void {
		$page = $this->current_page_settings();
		// Only emit JS that was saved by a user with unfiltered_html.
		if ( empty( $page['custom_js'] ) || empty( $page['js_allowed'] ) ) {
			return;
		}
		printf( "<script id=\"openb-page-js-%d\">%s</script>\n", (int) get_queried_object_id(), $page['custom_js'] );
	}

	public function enqueue(): void {
		wp_register_style( 'openb-frontend', OPENB_URL . 'assets/css/frontend.css', [], OPENB_VERSION );
		wp_register_script( 'openb-frontend', OPENB_URL . 'assets/js/frontend.js', [], OPENB_VERSION, true );

		$singular_enabled = is_singular() && Post_Types::is_enabled( get_queried_object_id() );
		$theme_builder    = Plugin::instance()->theme_builder && Plugin::instance()->theme_builder->applies_to_request();

		if ( $singular_enabled || $theme_builder ) {
			wp_enqueue_style( 'openb-frontend' );
			wp_enqueue_script( 'openb-frontend' );
			wp_localize_script( 'openb-frontend', 'OPENB_FRONT', [
				'restUrl' => esc_url_raw( rest_url( Rest::NS ) ),
				'postId'  => is_singular() ? get_queried_object_id() : 0,
			] );
		}
	}

	public function filter_content( string $content ): string {
		if ( ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$post_id = get_the_ID();
		if ( ! $post_id || ! Post_Types::is_enabled( $post_id ) ) {
			return $content;
		}

		$tree = Post_Types::get_tree( $post_id );
		if ( empty( $tree ) ) {
			return $content;
		}

		$this->queued_css[] = $post_id;
		// Renderer output is built from sanitized data and escaped per widget.
		return $this->renderer->render_tree( $tree );
	}

	/**
	 * Print compiled CSS. Prefer the value cached at save time; recompile as a
	 * fallback if it's missing (e.g. content imported without going through save).
	 */
	public function print_css(): void {
		if ( ! is_singular() ) {
			return;
		}
		$post_id = get_queried_object_id();
		if ( ! $post_id || ! Post_Types::is_enabled( $post_id ) ) {
			return;
		}

		$css = get_post_meta( $post_id, Post_Types::META_CSS, true );
		if ( '' === $css ) {
			$tree = Post_Types::get_tree( $post_id );
			$css  = $this->renderer->compile_css( $tree, Plugin::instance()->global_styles );
		}

		if ( '' !== $css ) {
			// $css is compiled exclusively from sanitized style values.
			printf( "<style id=\"openb-styles-%d\">%s</style>\n", (int) $post_id, $css );
		}
	}
}
