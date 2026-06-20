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
	public function handle_submission( string $form_id, int $post_id, array $fields, array $files = [] ): array {
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
		$uploaded_paths  = []; // absolute paths of moved uploads, for cleanup/attachments

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

			if ( 'file' === $type ) {
				$file = $files[ $name ] ?? null;
				$has  = is_array( $file ) && ! empty( $file['name'] ) && (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) !== UPLOAD_ERR_NO_FILE;
				if ( ! $has ) {
					if ( ! empty( $field['required'] ) ) {
						$missing[] = $label;
					}
					$clean[ $name ] = [ 'label' => $label, 'value' => '' ];
					continue;
				}
				$up = $this->handle_upload( $file );
				if ( is_wp_error( $up ) ) {
					$missing[] = $label . ' (' . $up->get_error_message() . ')';
					$clean[ $name ] = [ 'label' => $label, 'value' => '' ];
					continue;
				}
				$uploaded_paths[] = $up['file'];
				$clean[ $name ]   = [ 'label' => $label, 'value' => $up['url'] ];
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
			// Don't leave orphaned uploads behind when the submission is rejected.
			foreach ( $uploaded_paths as $p ) {
				wp_delete_file( $p );
			}
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
			$this->notify( $email_to, $post_id, $clean, $uploaded_paths );
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

	private function notify( string $to, int $post_id, array $data, array $attachments = [] ): void {
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
		wp_mail( $to, $subject, $body, '', array_values( array_filter( $attachments, 'file_exists' ) ) );
	}

	/**
	 * Validate and move an uploaded file into the WordPress uploads directory.
	 * Restricted to a small extension/MIME allowlist and a size cap; the real
	 * file contents are checked (not just the client-supplied name).
	 *
	 * @param array $file A single $_FILES-style entry.
	 * @return array{url:string,file:string}|\WP_Error
	 */
	private function handle_upload( array $file ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$allowed = array_map( 'strtolower', (array) apply_filters( 'openb_form_allowed_ext', Widget_Form::ALLOWED_FILE_EXT ) );
		$max     = (int) apply_filters( 'openb_form_max_upload', Widget_Form::MAX_FILE_BYTES );

		if ( (int) ( $file['size'] ?? 0 ) > $max ) {
			return new \WP_Error( 'too_big', __( 'file too large', 'open-builder' ) );
		}

		// Build a MIME map limited to the allowed extensions.
		$mimes = [];
		foreach ( wp_get_mime_types() as $exts => $mime ) {
			foreach ( explode( '|', $exts ) as $e ) {
				if ( in_array( $e, $allowed, true ) ) {
					$mimes[ $exts ] = $mime;
					break;
				}
			}
		}

		// Verify the actual file type against the allowlist (defends against a
		// disguised extension).
		$checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $mimes );
		if ( empty( $checked['ext'] ) || empty( $checked['type'] ) || ! in_array( strtolower( $checked['ext'] ), $allowed, true ) ) {
			return new \WP_Error( 'bad_type', __( 'file type not allowed', 'open-builder' ) );
		}

		$moved = wp_handle_upload( $file, [ 'test_form' => false, 'mimes' => $mimes ] );
		if ( ! is_array( $moved ) || isset( $moved['error'] ) ) {
			return new \WP_Error( 'upload_failed', is_array( $moved ) ? $moved['error'] : __( 'upload failed', 'open-builder' ) );
		}

		return [ 'url' => $moved['url'], 'file' => $moved['file'] ];
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
