<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

class Widget_Text extends Abstract_Widget {

	public function type(): string { return 'text'; }
	public function title(): string { return __( 'Text', 'open-builder' ); }
	public function category(): string { return 'Basic'; }
	public function icon(): string { return 'text'; }

	public function controls(): array {
		return [
			'text' => [
				'type'    => 'richtext',
				'label'   => __( 'Content', 'open-builder' ),
				'default' => '<p>Add your text here. Click to edit this paragraph and write something compelling for your visitors.</p>',
				'group'   => 'content',
			],
		];
	}

	public function render( array $content, string $inner_html, array $node ): string {
		// Already passed through wp_kses_post by the sanitizer.
		return '<div class="ob-text__content">' . $this->val( $content, 'text', '' ) . '</div>';
	}
}
