<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Form submissions: a custom DB table for storage plus a REST submit handler
 * that validates the per-form nonce, sanitizes input, stores the entry and
 * optionally emails a notification.
 */
class Forms {

	const TABLE = 'openb_form_entries';

	public function register_hooks(): void {
		add_action( 'admin_post_nopriv_openb_noop', '__return_false' );
	}

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/** Create the entries table. Called on activation. */
	public static function install_table(): void {
		global $wpdb;
		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id VARCHAR(64) NOT NULL DEFAULT '',
			post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			data LONGTEXT NOT NULL,
			ip VARCHAR(64) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY form_id (form_id),
			KEY post_id (post_id)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Handle a submission coming from the REST endpoint.
	 *
	 * @param string $form_id Form node id.
	 * @param int    $post_id Post the form lives on.
	 * @param array  $fields  Raw $_POST field map.
	 * @return array{success:bool,message:string}
	 */
	public function handle_submission( string $form_id, int $post_id, array $fields ): array {
		// Re-derive the field schema from the saved tree so we only accept the
		// fields this form actually defines — never trust the client's field list.
		$schema = $this->find_form_fields( $post_id, $form_id );
		if ( null === $schema ) {
			return [ 'success' => false, 'message' => __( 'Form not found.', 'open-builder' ) ];
		}

		$clean   = [];
		$missing = [];
		$email_to = '';
		$submitter_email = '';

		foreach ( $schema['fields'] as $field ) {
			$name = sanitize_key( $field['name'] ?? '' );
			if ( '' === $name ) {
				continue;
			}
			$type    = $field['type'] ?? 'text';
			$label   = $field['label'] ?? $name;
			$raw     = isset( $fields[ $name ] ) ? wp_unslash( $fields[ $name ] ) : '';
			$options = in_array( $type, Widget_Form::OPTION_TYPES, true ) ? Widget_Form::parse_options( (string) ( $field['options'] ?? '' ) ) : [];

			if ( 'checkbox' === $type ) {
				// Multi-value. Accept only values that are declared options.
				$picked = is_array( $raw ) ? $raw : ( '' === $raw ? [] : [ $raw ] );
				$picked = array_values( array_intersect( array_map( 'sanitize_text_field', $picked ), $options ) );
				if ( ! empty( $field['required'] ) && empty( $picked ) ) {
					$missing[] = $label;
				}
				$clean[ $name ] = [ 'label' => $label, 'value' => implode( ', ', $picked ) ];
				continue;
			}

			switch ( $type ) {
				case 'email':
					$value = sanitize_email( (string) $raw );
					break;
				case 'textarea':
					$value = sanitize_textarea_field( (string) $raw );
					break;
				case 'number':
					$value = is_numeric( $raw ) ? (string) ( $raw + 0 ) : '';
					break;
				default:
					$value = sanitize_text_field( (string) $raw );
			}

			// Choice fields: reject anything not in the declared option list.
			if ( in_array( $type, [ 'select', 'radio' ], true ) && '' !== $value && ! in_array( $value, $options, true ) ) {
				$value = '';
			}

			if ( ! empty( $field['required'] ) && '' === trim( $value ) ) {
				$missing[] = $label;
			}
			if ( 'email' === $type && '' !== $value && ! is_email( $value ) ) {
				$missing[] = $label;
			}
			if ( 'email' === $type && '' === $submitter_email && '' !== $value && is_email( $value ) ) {
				$submitter_email = $value;
			}

			$clean[ $name ] = [ 'label' => $label, 'value' => $value ];
		}

		if ( ! empty( $missing ) ) {
			return [
				'success' => false,
				'message' => sprintf(
					/* translators: %s: comma-separated field labels */
					__( 'Please complete: %s', 'open-builder' ),
					implode( ', ', array_map( 'sanitize_text_field', $missing ) )
				),
			];
		}

		$this->store( $form_id, $post_id, $clean );

		$email_to = $schema['email_to'] ?: get_option( 'admin_email' );
		if ( $email_to && is_email( $email_to ) ) {
			$this->notify( $email_to, $post_id, $clean );
		}

		// Action: POST the entry to an external webhook (best-effort, non-blocking).
		$webhook = esc_url_raw( (string) ( $schema['webhook_url'] ?? '' ) );
		if ( '' !== $webhook ) {
			$this->send_webhook( $webhook, $form_id, $post_id, $clean );
		}

		// Action: auto-reply to the submitter.
		if ( ! empty( $schema['autoresponder'] ) && '' !== $submitter_email ) {
			$this->autoresponder( $submitter_email, $schema['autoresponder_subject'] ?? '', $schema['autoresponder_message'] ?? '' );
		}

		$message = $schema['success_message'] ?: __( 'Thank you. Your submission was received.', 'open-builder' );
		return [ 'success' => true, 'message' => sanitize_text_field( $message ) ];
	}

	private function store( string $form_id, int $post_id, array $data ): void {
		global $wpdb;
		$wpdb->insert(
			self::table_name(),
			[
				'form_id'    => $form_id,
				'post_id'    => $post_id,
				'data'       => wp_json_encode( $data ),
				'ip'         => $this->anonymized_ip(),
				'created_at' => current_time( 'mysql' ),
			],
			[ '%s', '%d', '%s', '%s', '%s' ]
		);
	}

	private function notify( string $to, int $post_id, array $data ): void {
		$lines = [];
		foreach ( $data as $entry ) {
			$lines[] = sprintf( '%s: %s', $entry['label'], $entry['value'] );
		}
		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] New form submission', 'open-builder' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);
		$body = implode( "\n", $lines ) . "\n\n" . sprintf(
			/* translators: %s: post URL */
			__( 'Submitted from: %s', 'open-builder' ),
			get_permalink( $post_id )
		);
		wp_mail( $to, $subject, $body );
	}

	/**
	 * POST the submission to an external webhook as JSON. Best-effort: failures
	 * are logged but never block the submission. Short timeout so the visitor
	 * isn't kept waiting on a slow endpoint.
	 */
	private function send_webhook( string $url, string $form_id, int $post_id, array $data ): void {
		$flat = [];
		foreach ( $data as $name => $entry ) {
			$flat[ $name ] = $entry['value'];
		}
		$payload = [
			'form_id'      => $form_id,
			'post_id'      => $post_id,
			'permalink'    => get_permalink( $post_id ),
			'submitted_at' => current_time( 'mysql' ),
			'fields'       => $flat,
		];
		$resp = wp_remote_post( $url, [
			'timeout'     => 5,
			'blocking'    => true,
			'headers'     => [ 'Content-Type' => 'application/json' ],
			'body'        => wp_json_encode( $payload ),
			'user-agent'  => 'OpenBuilder/' . ( defined( 'OPENB_VERSION' ) ? OPENB_VERSION : '1.0' ),
		] );
		if ( is_wp_error( $resp ) ) {
			error_log( '[Open Builder] Webhook failed: ' . $resp->get_error_message() );
		}
	}

	/** Send an auto-reply to the person who submitted the form. */
	private function autoresponder( string $to, string $subject, string $message ): void {
		$subject = sanitize_text_field( $subject ) ?: __( 'Thanks for your message', 'open-builder' );
		$message = sanitize_textarea_field( $message );
		if ( '' === trim( $message ) ) {
			$message = __( 'Thanks — we received your message and will be in touch soon.', 'open-builder' );
		}
		$from_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$headers   = [ 'Content-Type: text/plain; charset=UTF-8' ];
		wp_mail( $to, sprintf( '[%s] %s', $from_name, $subject ), $message, $headers );
	}

	/** Store only a hashed/truncated IP to respect privacy by default. */
	private function anonymized_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( '' === $ip ) {
			return '';
		}
		return substr( wp_hash( $ip ), 0, 16 );
	}

	/**
	 * Locate a form node in a post's saved tree and return its content schema.
	 *
	 * @return array{fields:array,submit_label:string,success_message:string,email_to:string}|null
	 */
	private function find_form_fields( int $post_id, string $form_id ): ?array {
		$tree = Post_Types::get_tree( $post_id );
		$found = $this->search( $tree, $form_id );
		if ( null === $found ) {
			return null;
		}
		$content = $found['settings']['content'] ?? [];
		return [
			'fields'                => is_array( $content['fields'] ?? null ) ? $content['fields'] : [],
			'submit_label'          => (string) ( $content['submit_label'] ?? '' ),
			'success_message'       => (string) ( $content['success_message'] ?? '' ),
			'email_to'              => (string) ( $content['email_to'] ?? '' ),
			'webhook_url'           => (string) ( $content['webhook_url'] ?? '' ),
			'autoresponder'         => ! empty( $content['autoresponder'] ),
			'autoresponder_subject' => (string) ( $content['autoresponder_subject'] ?? '' ),
			'autoresponder_message' => (string) ( $content['autoresponder_message'] ?? '' ),
		];
	}

	private function search( array $nodes, string $form_id ): ?array {
		foreach ( $nodes as $node ) {
			if ( ( $node['type'] ?? '' ) === 'form' && ( $node['id'] ?? '' ) === $form_id ) {
				return $node;
			}
			if ( ! empty( $node['children'] ) ) {
				$hit = $this->search( $node['children'], $form_id );
				if ( null !== $hit ) {
					return $hit;
				}
			}
		}
		return null;
	}

	/** @return array list of entries for admin display */
	public function get_entries( int $limit = 100 ): array {
		global $wpdb;
		$table = self::table_name();
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", $limit ),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : [];
	}
}
