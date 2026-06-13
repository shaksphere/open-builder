<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

class Widget_Button extends Abstract_Widget {

	public function type(): string { return 'button'; }
	public function title(): string { return __( 'Button', 'open-builder' ); }
	public function category(): string { return 'Basic'; }
	public function icon(): string { return 'button'; }

	public function controls(): array {
		return [
			'text' => [
				'type'    => 'text',
				'label'   => __( 'Label', 'open-builder' ),
				'default' => 'Click Here',
				'group'   => 'content',
			],
			'url' => [
				'type'    => 'url',
				'label'   => __( 'Link', 'open-builder' ),
				'default' => '#',
				'group'   => 'content',
			],
			'target' => [
				'type'    => 'toggle',
				'label'   => __( 'Open in new tab', 'open-builder' ),
				'default' => false,
				'group'   => 'content',
			],
			'style' => [
				'type'    => 'select',
				'label'   => __( 'Style', 'open-builder' ),
				'choices' => [ 'primary' => 'Primary', 'secondary' => 'Secondary', 'outline' => 'Outline' ],
				'default' => 'primary',
				'group'   => 'content',
			],
		];
	}

	public function render( array $content, string $inner_html, array $node ): string {
		$label   = esc_html( $this->val( $content, 'text', '' ) );
		$url     = (string) $this->val( $content, 'url', '#' );
		$target  = $this->val( $content, 'target', false ) ? '_blank' : '';
		$variant = $this->val( $content, 'style', 'primary' );
		$variant = in_array( $variant, [ 'primary', 'secondary', 'outline' ], true ) ? $variant : 'primary';

		$rel = '_blank' === $target ? ' target="_blank" rel="noopener noreferrer"' : '';

		return sprintf(
			'<a class="ob-button ob-button--%1$s" href="%2$s"%3$s>%4$s</a>',
			esc_attr( $variant ),
			esc_url( $url ),
			$rel,
			$label
		);
	}
}
