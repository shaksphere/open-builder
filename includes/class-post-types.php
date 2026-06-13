<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Registers builder-owned post types (reusable templates, popups) and the
 * post meta keys used to store the node tree on any editable post.
 */
class Post_Types {

	const META_TREE     = '_openb_tree';
	const META_ENABLED  = '_openb_enabled';
	const META_CSS      = '_openb_compiled_css';
	const CPT_TEMPLATE  = 'openb_template';
	const CPT_POPUP     = 'openb_popup';

	private static $instance = null;

	public static function instance(): Post_Types {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks(): void {
		add_action( 'init', [ $this, 'register_post_types' ] );
		add_action( 'init', [ $this, 'register_meta' ] );
	}

	public function register_post_types(): void {
		register_post_type( self::CPT_TEMPLATE, [
			'labels'       => [
				'name'          => __( 'Templates', 'open-builder' ),
				'singular_name' => __( 'Template', 'open-builder' ),
			],
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => false,
			'supports'     => [ 'title', 'editor', 'revisions' ],
			'rewrite'      => false,
			'capability_type' => 'post',
		] );

		register_post_type( self::CPT_POPUP, [
			'labels'       => [
				'name'          => __( 'Popups', 'open-builder' ),
				'singular_name' => __( 'Popup', 'open-builder' ),
			],
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => false,
			'supports'     => [ 'title', 'revisions' ],
			'rewrite'      => false,
			'capability_type' => 'post',
		] );
	}

	public function register_meta(): void {
		// Stored as a JSON string; never auto-exposed in REST to avoid leaking
		// unsanitized output. The builder reads/writes it through our own
		// nonce-guarded REST routes.
		register_post_meta( '', self::META_ENABLED, [
			'type'              => 'boolean',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => 'rest_sanitize_boolean',
			'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
				return Security::can_edit( (int) $post_id );
			},
		] );
	}

	/** Post types that may be edited with the builder. */
	public static function builder_post_types(): array {
		$types = array_merge(
			[ 'page', 'post', self::CPT_TEMPLATE, self::CPT_POPUP ],
			array_keys( get_post_types( [ 'public' => true, '_builtin' => false ] ) )
		);
		return array_values( array_unique( apply_filters( 'open_builder_post_types', $types ) ) );
	}

	public static function is_enabled( int $post_id ): bool {
		return (bool) get_post_meta( $post_id, self::META_ENABLED, true );
	}

	public static function get_tree( int $post_id ): array {
		$raw = get_post_meta( $post_id, self::META_TREE, true );
		if ( empty( $raw ) ) {
			return [];
		}
		$tree = is_array( $raw ) ? $raw : json_decode( $raw, true );
		return is_array( $tree ) ? $tree : [];
	}

	public static function save_tree( int $post_id, array $tree, string $compiled_css ): void {
		// wp_slash because update_post_meta runs wp_unslash internally.
		update_post_meta( $post_id, self::META_TREE, wp_slash( wp_json_encode( $tree ) ) );
		update_post_meta( $post_id, self::META_CSS, wp_slash( $compiled_css ) );
		update_post_meta( $post_id, self::META_ENABLED, ! empty( $tree ) );
	}
}
