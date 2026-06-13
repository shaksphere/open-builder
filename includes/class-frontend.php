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
