<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * REST routes for the editor (save/load/render/global-styles) and the public
 * form submit endpoint. Editor routes require an authenticated user with
 * edit_post and rely on the standard X-WP-Nonce cookie auth. The form route is
 * public but guarded by a per-form nonce.
 */
class Rest {

	const NS = 'openb/v1';

	private $widgets;
	private $renderer;
	private $globals;
	private $forms;

	public function __construct( Widgets $widgets, Renderer $renderer, Global_Styles $globals, Forms $forms ) {
		$this->widgets  = $widgets;
		$this->renderer = $renderer;
		$this->globals  = $globals;
		$this->forms    = $forms;
	}

	public function register_hooks(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		register_rest_route( self::NS, '/save', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'save' ],
			'permission_callback' => [ $this, 'can_edit_post' ],
			'args'                => [
				'post_id' => [ 'required' => true, 'type' => 'integer' ],
				'tree'    => [ 'required' => true ],
			],
		] );

		register_rest_route( self::NS, '/render', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'render' ],
			'permission_callback' => [ $this, 'can_edit_post' ],
			'args'                => [
				'post_id' => [ 'required' => true, 'type' => 'integer' ],
				'tree'    => [ 'required' => true ],
			],
		] );

		register_rest_route( self::NS, '/global-styles', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'save_global_styles' ],
			'permission_callback' => [ $this, 'can_manage' ],
		] );

		register_rest_route( self::NS, '/form', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'submit_form' ],
			'permission_callback' => '__return_true', // Public; guarded by per-form nonce.
			'args'                => [
				'form_id' => [ 'required' => true, 'type' => 'string' ],
				'post_id' => [ 'required' => true, 'type' => 'integer' ],
			],
		] );
	}

	/** Permission: caller may edit the target post. */
	public function can_edit_post( \WP_REST_Request $request ): bool {
		$post_id = absint( $request->get_param( 'post_id' ) );
		return $post_id > 0 && Security::can_edit( $post_id );
	}

	public function can_manage(): bool {
		return Security::can_manage();
	}

	/** Decode + sanitize the incoming tree param (accepts array or JSON string). */
	private function read_tree( \WP_REST_Request $request ): array {
		$raw = $request->get_param( 'tree' );
		if ( is_string( $raw ) ) {
			$raw = json_decode( $raw, true );
		}
		if ( ! is_array( $raw ) ) {
			return [];
		}
		return Security::sanitize_tree( $raw );
	}

	public function save( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = absint( $request->get_param( 'post_id' ) );
		$tree    = $this->read_tree( $request );
		$css     = $this->renderer->compile_css( $tree, $this->globals );

		Post_Types::save_tree( $post_id, $tree, $css );

		return new \WP_REST_Response( [
			'success' => true,
			'message' => __( 'Saved', 'open-builder' ),
			'tree'    => $tree,
		], 200 );
	}

	public function render( \WP_REST_Request $request ): \WP_REST_Response {
		$tree = $this->read_tree( $request );
		$html = $this->renderer->render_tree( $tree );
		$css  = $this->renderer->compile_css( $tree, $this->globals );

		return new \WP_REST_Response( [
			'html' => $html,
			'css'  => $css,
			'tree' => $tree,
		], 200 );
	}

	public function save_global_styles( \WP_REST_Request $request ): \WP_REST_Response {
		$incoming = $request->get_param( 'styles' );
		if ( is_string( $incoming ) ) {
			$incoming = json_decode( $incoming, true );
		}
		$saved = $this->globals->save( is_array( $incoming ) ? $incoming : [] );
		return new \WP_REST_Response( [ 'success' => true, 'styles' => $saved, 'css' => $this->globals->to_css() ], 200 );
	}

	public function submit_form( \WP_REST_Request $request ): \WP_REST_Response {
		$form_id = sanitize_text_field( (string) $request->get_param( 'form_id' ) );
		$post_id = absint( $request->get_param( 'post_id' ) );
		$nonce   = (string) $request->get_param( '_nonce' );

		if ( ! wp_verify_nonce( $nonce, 'openb_form_' . $form_id ) ) {
			return new \WP_REST_Response( [ 'success' => false, 'message' => __( 'Security check failed. Please reload and try again.', 'open-builder' ) ], 403 );
		}

		// Honeypot: bots fill hidden fields.
		if ( '' !== (string) $request->get_param( 'ob_hp' ) ) {
			return new \WP_REST_Response( [ 'success' => true, 'message' => __( 'Thank you.', 'open-builder' ) ], 200 );
		}

		$fields = (array) $request->get_param( 'fields' );
		$result = $this->forms->handle_submission( $form_id, $post_id, $fields );

		return new \WP_REST_Response( $result, $result['success'] ? 200 : 422 );
	}
}
