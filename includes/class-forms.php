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

		foreach ( $schema['fields'] as $field ) {
			$name = sanitize_key( $field['name'] ?? '' );
			if ( '' === $name ) {
				continue;
			}
			$raw  = isset( $fields[ $name ] ) ? wp_unslash( $fields[ $name ] ) : '';
			$type = $field['type'] ?? 'text';

			$value = ( 'email' === $type )
				? sanitize_email( (string) $raw )
				: ( 'textarea' === $type ? sanitize_textarea_field( (string) $raw ) : sanitize_text_field( (string) $raw ) );

			if ( ! empty( $field['required'] ) && '' === trim( (string) $value ) ) {
				$missing[] = $field['label'] ?? $name;
			}
			if ( 'email' === $type && '' !== $value && ! is_email( $value ) ) {
				$missing[] = $field['label'] ?? $name;
			}

			$clean[ $name ] = [ 'label' => $field['label'] ?? $name, 'value' => $value ];
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
			'fields'          => is_array( $content['fields'] ?? null ) ? $content['fields'] : [],
			'submit_label'    => (string) ( $content['submit_label'] ?? '' ),
			'success_message' => (string) ( $content['success_message'] ?? '' ),
			'email_to'        => (string) ( $content['email_to'] ?? '' ),
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
