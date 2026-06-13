<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

class Widget_Divider extends Abstract_Widget {

	public function type(): string { return 'divider'; }
	public function title(): string { return __( 'Divider', 'open-builder' ); }
	public function category(): string { return 'Layout'; }
	public function icon(): string { return 'divider'; }

	public function controls(): array {
		return [
			'color' => [
				'type'    => 'color',
				'label'   => __( 'Color', 'open-builder' ),
				'default' => 'var(--ob-color-muted)',
				'group'   => 'content',
			],
			'thickness' => [
				'type'    => 'text',
				'label'   => __( 'Thickness', 'open-builder' ),
				'default' => '1px',
				'group'   => 'content',
			],
		];
	}

	public function render( array $content, string $inner_html, array $node ): string {
		$color = Security::sanitize_color( (string) $this->val( $content, 'color', '' ) );
		$thick = Security::sanitize_css_value( (string) $this->val( $content, 'thickness', '1px' ) );
		$color = '' !== $color ? $color : 'currentColor';
		$thick = '' !== $thick ? $thick : '1px';
		return sprintf(
			'<hr class="ob-divider" style="border:none;border-top:%s solid %s" />',
			esc_attr( $thick ),
			esc_attr( $color )
		);
	}
}
