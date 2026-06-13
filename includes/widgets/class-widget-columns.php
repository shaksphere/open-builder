<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

class Widget_Columns extends Abstract_Widget {

	public function type(): string { return 'columns'; }
	public function title(): string { return __( 'Columns', 'open-builder' ); }
	public function category(): string { return 'Layout'; }
	public function icon(): string { return 'columns'; }
	public function is_container(): bool { return true; }
	public function accepts(): array { return [ 'column' ]; }

	public function controls(): array {
		return [
			'gap' => [
				'type'    => 'text',
				'label'   => __( 'Gap', 'open-builder' ),
				'default' => '20px',
				'group'   => 'content',
			],
		];
	}

	public function render( array $content, string $inner_html, array $node ): string {
		return $inner_html;
	}
}
