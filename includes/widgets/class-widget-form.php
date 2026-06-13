<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Form builder widget. Fields are defined as a repeater. Submissions are
 * stored in the plugin's DB table and optionally emailed. The front-end
 * submit is handled by Forms via a nonce-protected REST endpoint.
 */
class Widget_Form extends Abstract_Widget {

	public function type(): string { return 'form'; }
	public function title(): string { return __( 'Form', 'open-builder' ); }
	public function category(): string { return 'Marketing'; }
	public function icon(): string { return 'form'; }

	public function controls(): array {
		return [
			'fields' => [
				'type'    => 'repeater',
				'label'   => __( 'Fields', 'open-builder' ),
				'fields'  => [
					'label'    => [ 'type' => 'text', 'label' => 'Label', 'default' => 'Name' ],
					'name'     => [ 'type' => 'text', 'label' => 'Key', 'default' => 'name' ],
					'type'     => [ 'type' => 'select', 'label' => 'Type', 'choices' => [ 'text' => 'Text', 'email' => 'Email', 'tel' => 'Phone', 'textarea' => 'Textarea' ], 'default' => 'text' ],
					'required' => [ 'type' => 'toggle', 'label' => 'Required', 'default' => false ],
				],
				'default' => [
					[ 'label' => 'Name',    'name' => 'name',    'type' => 'text',     'required' => '1' ],
					[ 'label' => 'Email',   'name' => 'email',   'type' => 'email',    'required' => '1' ],
					[ 'label' => 'Message', 'name' => 'message', 'type' => 'textarea', 'required' => '' ],
				],
				'group' => 'content',
			],
			'submit_label' => [
				'type'    => 'text',
				'label'   => __( 'Submit Button', 'open-builder' ),
				'default' => 'Send Message',
				'group'   => 'content',
			],
			'success_message' => [
				'type'    => 'text',
				'label'   => __( 'Success Message', 'open-builder' ),
				'default' => 'Thanks! Your message has been sent.',
				'group'   => 'content',
			],
			'email_to' => [
				'type'    => 'text',
				'label'   => __( 'Notification Email', 'open-builder' ),
				'default' => '',
				'group'   => 'content',
			],
		];
	}

	public function render( array $content, string $inner_html, array $node ): string {
		$fields  = $this->val( $content, 'fields', [] );
		$fields  = is_array( $fields ) ? $fields : [];
		$form_id = $node['id'] ?? Security::generate_id();
		$submit  = esc_html( $this->val( $content, 'submit_label', 'Submit' ) );

		$fields_html = '';
		foreach ( $fields as $field ) {
			$label = esc_html( $field['label'] ?? '' );
			$name  = sanitize_key( $field['name'] ?? '' );
			$ftype = in_array( $field['type'] ?? 'text', [ 'text', 'email', 'tel', 'textarea' ], true ) ? $field['type'] : 'text';
			$req   = ! empty( $field['required'] ) ? ' required' : '';
			if ( '' === $name ) {
				continue;
			}
			$req_mark = $req ? ' <span class="ob-form__req">*</span>' : '';
			$input = ( 'textarea' === $ftype )
				? sprintf( '<textarea id="%1$s-%2$s" name="%2$s" rows="4"%3$s></textarea>', esc_attr( $form_id ), esc_attr( $name ), $req )
				: sprintf( '<input type="%4$s" id="%1$s-%2$s" name="%2$s"%3$s />', esc_attr( $form_id ), esc_attr( $name ), $req, esc_attr( $ftype ) );

			$fields_html .= sprintf(
				'<div class="ob-form__field"><label for="%1$s-%2$s">%3$s%4$s</label>%5$s</div>',
				esc_attr( $form_id ),
				esc_attr( $name ),
				$label,
				$req_mark,
				$input
			);
		}

		// Per-form nonce so submissions are CSRF-protected.
		$nonce = wp_create_nonce( 'openb_form_' . $form_id );

		return sprintf(
			'<form class="ob-form" data-ob-form="%1$s" data-nonce="%2$s">
				%3$s
				<div class="ob-form__actions"><button type="submit" class="ob-button ob-button--primary">%4$s</button></div>
				<div class="ob-form__message" role="status" aria-live="polite"></div>
			</form>',
			esc_attr( $form_id ),
			esc_attr( $nonce ),
			$fields_html,
			$submit
		);
	}
}
