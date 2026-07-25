<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Bridge widget for the separate Open Form Builder plugin. Lets a page pick
 * one of its forms (booking/multi-step/priced forms included) from a real
 * dropdown instead of requiring the author to know the `[open_form id="…"]`
 * shortcode syntax and type it into a generic Shortcode widget.
 *
 * Always registered (so a page that already uses it keeps working even if the
 * other plugin is later deactivated — see Widgets::is_registered()), but
 * hidden from the "add new" panel by Widgets::schema_for_editor() while
 * OFB_Forms isn't loaded. That check happens per-request at editor render
 * time, not at plugin-boot time, so it's safe regardless of which of the two
 * plugins finishes booting first on `plugins_loaded`.
 */
class Widget_Ofb_Form extends Abstract_Widget {

	public function type(): string { return 'ofb_form'; }
	public function title(): string { return __( 'Booking / Advanced Form', 'open-builder' ); }
	public function category(): string { return 'Marketing'; }
	public function icon(): string { return 'form'; }

	public function controls(): array {
		$active  = class_exists( 'OFB_Forms' );
		$choices = [ '' => __( '— Select a form —', 'open-builder' ) ];

		if ( $active ) {
			foreach ( \OFB_Forms::all() as $form ) {
				$name               = '' !== $form['name'] ? $form['name'] : sprintf( '#%d', $form['id'] );
				$choices[ (string) $form['id'] ] = $name;
			}
		}

		return [
			'form_id' => [
				'type'    => 'select',
				'label'   => __( 'Form', 'open-builder' ),
				'choices' => $choices,
				'default' => '',
				'group'   => 'content',
				'hint'    => $active
					? __( 'Built with Open Form Builder — supports multi-step, conditional logic, date/time and priced fields.', 'open-builder' )
					: __( 'Install and activate the Open Form Builder plugin to select a form.', 'open-builder' ),
			],
		];
	}

	public function render( array $content, string $inner_html, array $node ): string {
		if ( ! class_exists( 'OFB_Forms' ) ) {
			return Render_Context::is_editor()
				? '<div class="openb-ofb-missing">' . esc_html__( 'Open Form Builder is not active. Install and activate it to display this form.', 'open-builder' ) . '</div>'
				: '';
		}

		$id = trim( (string) $this->val( $content, 'form_id', '' ) );
		if ( '' === $id ) {
			return Render_Context::is_editor()
				? '<div class="openb-ofb-missing">' . esc_html__( 'Choose a form in the settings panel.', 'open-builder' ) . '</div>'
				: '';
		}

		return do_shortcode( sprintf( '[open_form id="%d"]', absint( $id ) ) );
	}
}
