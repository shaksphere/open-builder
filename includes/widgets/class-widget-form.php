<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Form builder widget. Fields are defined as a repeater. Submissions are
 * stored in the plugin's DB table and optionally emailed. The front-end
 * submit is handled by Forms via a nonce-protected REST endpoint.
 */
class Widget_Form extends Abstract_Widget {

	/** Field types this widget can render. Kept in sync with Forms validation. */
	const FIELD_TYPES = [ 'text', 'email', 'tel', 'number', 'textarea', 'select', 'radio', 'checkbox', 'date', 'file', 'hidden' ];

	/** Allowed upload extensions for file fields. Filterable. */
	const ALLOWED_FILE_EXT = [ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'txt', 'csv' ];

	/** Max upload size in bytes (5 MB). Filterable via openb_form_max_upload. */
	const MAX_FILE_BYTES = 5242880;

	/** Types whose value is chosen from a comma-separated option list. */
	const OPTION_TYPES = [ 'select', 'radio', 'checkbox' ];

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
					'label'       => [ 'type' => 'text', 'label' => 'Label', 'default' => 'Name' ],
					'name'        => [ 'type' => 'text', 'label' => 'Key', 'default' => 'name' ],
					'type'        => [ 'type' => 'select', 'label' => 'Type', 'choices' => [
						'text'     => 'Text',
						'email'    => 'Email',
						'tel'      => 'Phone',
						'number'   => 'Number',
						'textarea' => 'Textarea',
						'select'   => 'Dropdown',
						'radio'    => 'Radio buttons',
						'checkbox' => 'Checkboxes',
						'date'     => 'Date',
						'file'     => 'File upload',
						'hidden'   => 'Hidden',
					], 'default' => 'text' ],
					'options'     => [ 'type' => 'text', 'label' => 'Options (comma-separated)', 'default' => '' ],
					'placeholder' => [ 'type' => 'text', 'label' => 'Placeholder', 'default' => '' ],
					'value'       => [ 'type' => 'text', 'label' => 'Default value', 'default' => '' ],
					'required'    => [ 'type' => 'toggle', 'label' => 'Required', 'default' => false ],
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
			'redirect_url' => [
				'type'    => 'text',
				'label'   => __( 'Redirect URL after submit', 'open-builder' ),
				'default' => '',
				'hint'    => __( 'Leave blank to show the success message instead.', 'open-builder' ),
				'group'   => 'content',
			],
			'webhook_url' => [
				'type'    => 'text',
				'label'   => __( 'Webhook URL', 'open-builder' ),
				'default' => '',
				'hint'    => __( 'On submit, the entry is POSTed as JSON to this URL.', 'open-builder' ),
				'group'   => 'content',
			],
			'autoresponder' => [
				'type'    => 'toggle',
				'label'   => __( 'Send auto-reply to submitter', 'open-builder' ),
				'default' => false,
				'hint'    => __( 'Replies to the first email field on the form.', 'open-builder' ),
				'group'   => 'content',
			],
			'autoresponder_subject' => [
				'type'    => 'text',
				'label'   => __( 'Auto-reply subject', 'open-builder' ),
				'default' => 'Thanks for your message',
				'group'   => 'content',
			],
			'autoresponder_message' => [
				'type'    => 'textarea',
				'label'   => __( 'Auto-reply message', 'open-builder' ),
				'default' => 'Thanks — we received your message and will be in touch soon.',
				'group'   => 'content',
			],
		];
	}

	/** Split a comma-separated option string into clean values. */
	public static function parse_options( string $raw ): array {
		$out = [];
		foreach ( explode( ',', $raw ) as $opt ) {
			$opt = trim( $opt );
			if ( '' !== $opt ) {
				$out[] = $opt;
			}
		}
		return $out;
	}

	public function render( array $content, string $inner_html, array $node ): string {
		$fields  = $this->val( $content, 'fields', [] );
		$fields  = is_array( $fields ) ? $fields : [];
		$form_id = $node['id'] ?? Security::generate_id();
		$submit  = esc_html( $this->val( $content, 'submit_label', 'Submit' ) );

		$fields_html = '';
		foreach ( $fields as $field ) {
			$fields_html .= $this->render_field( $field, $form_id );
		}

		// Per-form nonce so submissions are CSRF-protected.
		$nonce = wp_create_nonce( 'openb_form_' . $form_id );

		// Optional client-side redirect after a successful submit.
		$redirect      = trim( (string) $this->val( $content, 'redirect_url', '' ) );
		$redirect_attr = '' !== $redirect ? sprintf( ' data-redirect="%s"', esc_url( $redirect ) ) : '';

		return sprintf(
			'<form class="ob-form" data-ob-form="%1$s" data-nonce="%2$s"%5$s>
				%3$s
				<div class="ob-form__actions"><button type="submit" class="ob-button ob-button--primary">%4$s</button></div>
				<div class="ob-form__message" role="status" aria-live="polite"></div>
			</form>',
			esc_attr( $form_id ),
			esc_attr( $nonce ),
			$fields_html,
			$submit,
			$redirect_attr
		);
	}

	/** Render a single field row to HTML. */
	private function render_field( array $field, string $form_id ): string {
		$label = esc_html( $field['label'] ?? '' );
		$name  = sanitize_key( $field['name'] ?? '' );
		$ftype = in_array( $field['type'] ?? 'text', self::FIELD_TYPES, true ) ? $field['type'] : 'text';
		$req   = ! empty( $field['required'] );
		$ph    = (string) ( $field['placeholder'] ?? '' );
		$dval  = (string) ( $field['value'] ?? '' );
		if ( '' === $name ) {
			return '';
		}

		$fid      = esc_attr( $form_id . '-' . $name );
		$req_attr = $req ? ' required' : '';
		$ph_attr  = '' !== $ph ? sprintf( ' placeholder="%s"', esc_attr( $ph ) ) : '';
		$options  = in_array( $ftype, self::OPTION_TYPES, true ) ? self::parse_options( (string) ( $field['options'] ?? '' ) ) : [];

		// Hidden fields carry their default value and have no visible wrapper.
		if ( 'hidden' === $ftype ) {
			return sprintf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( $name ), esc_attr( $dval ) );
		}

		$req_mark = $req ? ' <span class="ob-form__req">*</span>' : '';

		switch ( $ftype ) {
			case 'textarea':
				$input = sprintf( '<textarea id="%1$s" name="%2$s" rows="4"%3$s%4$s>%5$s</textarea>', $fid, esc_attr( $name ), $req_attr, $ph_attr, esc_textarea( $dval ) );
				break;

			case 'select':
				$opts = '<option value="">' . esc_html( '' !== $ph ? $ph : __( 'Choose…', 'open-builder' ) ) . '</option>';
				foreach ( $options as $opt ) {
					$opts .= sprintf( '<option value="%1$s"%2$s>%1$s</option>', esc_attr( $opt ), selected( $dval, $opt, false ) );
				}
				$input = sprintf( '<select id="%1$s" name="%2$s"%3$s>%4$s</select>', $fid, esc_attr( $name ), $req_attr, $opts );
				break;

			case 'radio':
			case 'checkbox':
				$is_check  = 'checkbox' === $ftype;
				$in_type   = $is_check ? 'checkbox' : 'radio';
				$in_name   = $is_check ? $name . '[]' : $name;
				$defaults  = $is_check ? array_map( 'trim', explode( ',', $dval ) ) : [ $dval ];
				$group_req = $req ? ' data-ob-required="1"' : '';
				$choices   = '';
				foreach ( $options as $i => $opt ) {
					$oid      = $fid . '-' . $i;
					$checked  = in_array( $opt, $defaults, true ) ? ' checked' : '';
					$choices .= sprintf(
						'<label class="ob-form__choice" for="%1$s"><input type="%2$s" id="%1$s" name="%3$s" value="%4$s"%5$s /> <span>%4$s</span></label>',
						esc_attr( $oid ),
						esc_attr( $in_type ),
						esc_attr( $in_name ),
						esc_attr( $opt ),
						$checked
					);
				}
				return sprintf(
					'<div class="ob-form__field ob-form__field--%6$s"><span class="ob-form__grouplabel">%1$s%2$s</span><div class="ob-form__choices" role="group" aria-label="%3$s"%4$s>%5$s</div></div>',
					$label,
					$req_mark,
					esc_attr( wp_strip_all_tags( $field['label'] ?? '' ) ),
					$group_req,
					$choices,
					esc_attr( $ftype )
				);

			case 'date':
				$input = sprintf( '<input type="date" id="%1$s" name="%2$s" value="%4$s"%3$s />', $fid, esc_attr( $name ), $req_attr, esc_attr( $dval ) );
				break;

			case 'number':
				$input = sprintf( '<input type="number" id="%1$s" name="%2$s" value="%4$s"%3$s%5$s />', $fid, esc_attr( $name ), $req_attr, esc_attr( $dval ), $ph_attr );
				break;

			case 'file':
				$exts   = (array) apply_filters( 'openb_form_allowed_ext', self::ALLOWED_FILE_EXT );
				$accept = implode( ',', array_map( function ( $e ) { return '.' . ltrim( $e, '.' ); }, $exts ) );
				$input  = sprintf( '<input type="file" id="%1$s" name="%2$s" accept="%4$s"%3$s />', $fid, esc_attr( $name ), $req_attr, esc_attr( $accept ) );
				break;

			default: // text, email, tel
				$input = sprintf( '<input type="%5$s" id="%1$s" name="%2$s" value="%4$s"%3$s%6$s />', $fid, esc_attr( $name ), $req_attr, esc_attr( $dval ), esc_attr( $ftype ), $ph_attr );
		}

		return sprintf(
			'<div class="ob-form__field"><label for="%1$s">%2$s%3$s</label>%4$s</div>',
			$fid,
			$label,
			$req_mark,
			$input
		);
	}
}
