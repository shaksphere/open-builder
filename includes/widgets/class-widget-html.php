<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Custom HTML widget. Output is filtered through wp_kses with the post ruleset,
 * so it permits rich markup but strips scripts/event handlers for editors who
 * lack unfiltered_html. Users with unfiltered_html (admins on single sites)
 * get their raw markup, matching core WordPress behaviour.
 */
class Widget_Html extends Abstract_Widget {

	public function type(): string { return 'html'; }
	public function title(): string { return __( 'Custom HTML', 'open-builder' ); }
	public function category(): string { return 'Advanced'; }
	public function icon(): string { return 'code'; }

	public function controls(): array {
		return [
			'html' => [
				'type'    => 'html',
				'label'   => __( 'HTML', 'open-builder' ),
				'default' => '<div>Custom HTML</div>',
				'group'   => 'content',
			],
		];
	}

	public function render( array $content, string $inner_html, array $node ): string {
		$html = (string) $this->val( $content, 'html', '' );
		if ( current_user_can( 'unfiltered_html' ) ) {
			return $html;
		}
		return wp_kses_post( $html );
	}
}
