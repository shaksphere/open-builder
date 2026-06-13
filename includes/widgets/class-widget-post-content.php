<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

class Widget_Post_Content extends Abstract_Widget {

	public function type(): string { return 'post_content'; }
	public function title(): string { return __( 'Post Content', 'open-builder' ); }
	public function category(): string { return 'Dynamic'; }
	public function icon(): string { return 'text'; }

	public function render( array $content, string $inner_html, array $node ): string {
		if ( Render_Context::is_editor() ) {
			return '<div class="ob-text__content ob-dynamic"><p>' . esc_html__( 'The post content will display here on the front end.', 'open-builder' ) . '</p></div>';
		}

		// Guard against infinite recursion: a Single template rendering the post
		// content of a builder-enabled post would re-enter the builder. Use the
		// raw content filters but suppress our own the_content takeover.
		$post_id = get_the_ID();
		$raw     = get_post_field( 'post_content', $post_id );
		$out     = apply_filters( 'the_content', $raw );

		return '<div class="ob-text__content">' . $out . '</div>';
	}
}
