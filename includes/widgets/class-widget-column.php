<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

class Widget_Column extends Abstract_Widget {

	public function type(): string { return 'column'; }
	public function title(): string { return __( 'Column', 'open-builder' ); }
	public function category(): string { return 'Layout'; }
	public function icon(): string { return 'column'; }
	public function is_container(): bool { return true; }
	public function accepts(): array { return [ 'heading', 'text', 'button', 'image', 'spacer', 'divider', 'icon', 'html', 'form', 'columns' ]; }

	public function controls(): array {
		return [];
	}

	public function render( array $content, string $inner_html, array $node ): string {
		return $inner_html;
	}
}
