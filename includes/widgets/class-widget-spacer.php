<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

class Widget_Spacer extends Abstract_Widget {

	public function type(): string { return 'spacer'; }
	public function title(): string { return __( 'Spacer', 'open-builder' ); }
	public function category(): string { return 'Layout'; }
	public function icon(): string { return 'spacer'; }

	public function controls(): array {
		return [
			'height' => [
				'type'    => 'text',
				'label'   => __( 'Height', 'open-builder' ),
				'default' => '40px',
				'group'   => 'content',
			],
		];
	}

	public function render( array $content, string $inner_html, array $node ): string {
		$height = Security::sanitize_css_value( (string) $this->val( $content, 'height', '40px' ) );
		$height = '' !== $height ? $height : '40px';
		return sprintf( '<div class="ob-spacer" style="height:%s" aria-hidden="true"></div>', esc_attr( $height ) );
	}
}
