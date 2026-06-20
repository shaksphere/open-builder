<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode widget. Lets a page embed any registered WordPress shortcode
 * (e.g. [open_form id="2"], [contact-form-7 id="x"], gallery, etc.).
 *
 * The stored value is sanitized as plain text on input (sanitize_textarea_field
 * keeps the [bracketed] syntax and attributes but strips HTML). On output it is
 * run through do_shortcode() so the registered handler renders in place, on both
 * the front end and the same-origin editor canvas (true WYSIWYG).
 */
class Widget_Shortcode extends Abstract_Widget {

	public function type(): string { return 'shortcode'; }
	public function title(): string { return __( 'Shortcode', 'open-builder' ); }
	public function category(): string { return 'Advanced'; }
	public function icon(): string { return 'code'; }

	public function controls(): array {
		return [
			'shortcode' => [
				'type'        => 'textarea',
				'label'       => __( 'Shortcode', 'open-builder' ),
				'default'     => '[open_form id="2"]',
				'placeholder' => '[open_form id="2"]',
				'group'       => 'content',
			],
		];
	}

	public function render( array $content, string $inner_html, array $node ): string {
		$code = trim( (string) $this->val( $content, 'shortcode', '' ) );
		if ( '' === $code ) {
			return '<span class="openb-shortcode-empty">' . esc_html__( 'Add a shortcode in the settings panel.', 'open-builder' ) . '</span>';
		}
		return do_shortcode( $code );
	}
}
