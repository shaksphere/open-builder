<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

class Widget_Heading extends Abstract_Widget {

	public function type(): string { return 'heading'; }
	public function title(): string { return __( 'Heading', 'open-builder' ); }
	public function category(): string { return 'Basic'; }
	public function icon(): string { return 'heading'; }

	public function controls(): array {
		return [
			'text' => [
				'type'    => 'text',
				'label'   => __( 'Text', 'open-builder' ),
				'default' => 'Your Heading Here',
				'group'   => 'content',
			],
			'tag' => [
				'type'    => 'select',
				'label'   => __( 'Tag', 'open-builder' ),
				'choices' => [ 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6' ],
				'default' => 'h2',
				'group'   => 'content',
			],
			'link' => [
				'type'    => 'url',
				'label'   => __( 'Link', 'open-builder' ),
				'default' => '',
				'group'   => 'content',
			],
		];
	}

	public function render( array $content, string $inner_html, array $node ): string {
		$allowed = [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ];
		$tag     = $this->val( $content, 'tag', 'h2' );
		$tag     = in_array( $tag, $allowed, true ) ? $tag : 'h2';
		$text    = esc_html( $this->val( $content, 'text', '' ) );

		[ $open, $close ] = $this->link( (string) $this->val( $content, 'link', '' ) );
		$text = $open . $text . $close;

		return "<{$tag} class=\"ob-heading__text\">{$text}</{$tag}>";
	}
}
