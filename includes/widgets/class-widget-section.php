<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

class Widget_Section extends Abstract_Widget {

	public function type(): string { return 'section'; }
	public function title(): string { return __( 'Section', 'open-builder' ); }
	public function category(): string { return 'Layout'; }
	public function icon(): string { return 'layout'; }
	public function is_container(): bool { return true; }
	public function accepts(): array { return [ 'columns', 'heading', 'text', 'button', 'image', 'spacer', 'divider', 'icon', 'html', 'form' ]; }

	public function controls(): array {
		return [
			'content_width' => [
				'type'    => 'select',
				'label'   => __( 'Content Width', 'open-builder' ),
				'choices' => [ 'boxed' => 'Boxed', 'full' => 'Full Width' ],
				'default' => 'boxed',
				'group'   => 'content',
			],
			'tag' => [
				'type'    => 'select',
				'label'   => __( 'HTML Tag', 'open-builder' ),
				'choices' => [ 'section' => 'section', 'div' => 'div', 'header' => 'header', 'footer' => 'footer', 'main' => 'main' ],
				'default' => 'section',
				'group'   => 'content',
			],
		];
	}

	public function render( array $content, string $inner_html, array $node ): string {
		$boxed = 'full' !== $this->val( $content, 'content_width', 'boxed' );
		$inner = $boxed
			? '<div class="ob-section__inner">' . $inner_html . '</div>'
			: $inner_html;
		return $inner;
	}
}
