<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

class Widget_Post_Title extends Abstract_Widget {

	public function type(): string { return 'post_title'; }
	public function title(): string { return __( 'Post Title', 'open-builder' ); }
	public function category(): string { return 'Dynamic'; }
	public function icon(): string { return 'heading'; }

	public function controls(): array {
		return [
			'tag' => [
				'type'    => 'select',
				'label'   => __( 'Tag', 'open-builder' ),
				'choices' => [ 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'div' => 'div' ],
				'default' => 'h1',
				'group'   => 'content',
			],
			'link' => [
				'type'    => 'toggle',
				'label'   => __( 'Link to post', 'open-builder' ),
				'default' => false,
				'group'   => 'content',
			],
		];
	}

	public function render( array $content, string $inner_html, array $node ): string {
		$tag = in_array( $content['tag'] ?? 'h1', [ 'h1', 'h2', 'h3', 'div' ], true ) ? $content['tag'] : 'h1';

		if ( Render_Context::is_editor() ) {
			$title = esc_html__( 'Post Title', 'open-builder' );
			return "<{$tag} class=\"ob-heading__text ob-dynamic\">{$title}</{$tag}>";
		}

		$title = esc_html( get_the_title() );
		if ( ! empty( $content['link'] ) ) {
			$title = sprintf( '<a href="%s">%s</a>', esc_url( get_permalink() ), $title );
		}
		return "<{$tag} class=\"ob-heading__text\">{$title}</{$tag}>";
	}
}
