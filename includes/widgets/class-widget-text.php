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
			'link_color' => [
				'type'    => 'color',
				'label'   => __( 'Link Color', 'open-builder' ),
				'default' => '',
				'hint'    => __( 'Links inside this text are unstyled (no underline) and colored with your brand primary by default. Set a color here to override just this block.', 'open-builder' ),
				'group'   => 'content',
			],
		];
	}

	public function render( array $content, string $inner_html, array $node ): string {
		// Already passed through wp_kses_post by the sanitizer.
		$text = $this->val( $content, 'text', '' );

		$link_color = Security::sanitize_color( (string) $this->val( $content, 'link_color', '' ) );
		$style = '' !== $link_color ? sprintf( ' style="--ob-text-link-color:%s"', esc_attr( $link_color ) ) : '';

		return sprintf( '<div class="ob-text__content"%s>%s</div>', $style, $text );
	}
}
