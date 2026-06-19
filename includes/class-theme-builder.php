<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Theme builder: lets users design headers, footers and templates (single,
 * archive, 404, search) visually and assign them to parts of the site via a
 * display-conditions engine. When a template applies to the current request we
 * take over the theme's output with our own canvas template.
 */
class Theme_Builder {

	const META_TYPE       = '_openb_template_type';
	const META_CONDITIONS = '_openb_conditions';

	/** Template types and their human labels. */
	public static function types(): array {
		return [
			'header'  => __( 'Header', 'open-builder' ),
			'footer'  => __( 'Footer', 'open-builder' ),
			'single'  => __( 'Single', 'open-builder' ),
			'archive' => __( 'Archive', 'open-builder' ),
			'search'  => __( 'Search Results', 'open-builder' ),
			'404'     => __( '404 Page', 'open-builder' ),
		];
	}

	/** @var array<string,int|null> resolved template ids for this request */
	private $resolved = [];

	public function register_hooks(): void {
		add_action( 'init', [ $this, 'register_meta' ] );
		add_action( 'add_meta_boxes_' . Post_Types::CPT_TEMPLATE, [ $this, 'meta_boxes' ] );
		add_action( 'save_post_' . Post_Types::CPT_TEMPLATE, [ $this, 'save' ], 10, 2 );

		// Take over the theme when a template applies. Late priority so other
		// template plugins resolve first.
		add_filter( 'template_include', [ $this, 'maybe_take_over' ], 99 );
	}

	public function register_meta(): void {
		register_post_meta( Post_Types::CPT_TEMPLATE, self::META_TYPE, [
			'type'          => 'string',
			'single'        => true,
			'show_in_rest'  => false,
			'auth_callback' => function ( $a, $k, $post_id ) { return Security::can_edit( (int) $post_id ); },
		] );
	}

	/* --------------------------------------------------------------------- *
	 * Admin: type + conditions metaboxes on the template edit screen
	 * --------------------------------------------------------------------- */
	public function meta_boxes(): void {
		add_meta_box( 'openb-template-type', __( 'Template Type', 'open-builder' ), [ $this, 'render_type_box' ], Post_Types::CPT_TEMPLATE, 'side', 'high' );
		add_meta_box( 'openb-template-conditions', __( 'Display Conditions', 'open-builder' ), [ $this, 'render_conditions_box' ], Post_Types::CPT_TEMPLATE, 'normal', 'high' );
	}

	public function render_type_box( \WP_Post $post ): void {
		wp_nonce_field( 'openb_template_meta', 'openb_template_nonce' );
		$current = get_post_meta( $post->ID, self::META_TYPE, true ) ?: 'single';
		echo '<select name="openb_template_type" style="width:100%">';
		foreach ( self::types() as $key => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $key ), selected( $current, $key, false ), esc_html( $label ) );
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Choose what this template represents, then set where it appears below.', 'open-builder' ) . '</p>';
		printf(
			'<p><a href="%s" class="button button-primary" style="width:100%%;text-align:center">%s</a></p>',
			esc_url( Editor::edit_url( $post->ID ) ),
			esc_html__( 'Edit with Open Builder', 'open-builder' )
		);
	}

	public function render_conditions_box( \WP_Post $post ): void {
		self::render_conditions_ui( $post->ID );
	}

	/**
	 * Render the include/exclude display-conditions UI (table + add/remove JS).
	 * Shared by the template and popup edit screens. The caller is responsible
	 * for the surrounding metabox and its nonce; this only emits the rows for the
	 * `openb_conditions[]` field, read back by save_conditions_from_post().
	 */
	public static function render_conditions_ui( int $post_id ): void {
		$conditions = self::get_conditions( $post_id );
		if ( empty( $conditions ) ) {
			$conditions = [ [ 'relation' => 'include', 'rule' => 'entire_site' ] ];
		}
		$rules = self::rule_choices();
		?>
		<div id="openb-conditions">
			<p class="description"><?php esc_html_e( 'Include rules add this template to matching pages; exclude rules remove it. The most specific matching template wins.', 'open-builder' ); ?></p>
			<table class="widefat" style="margin-top:8px">
				<tbody id="openb-conditions-rows">
				<?php foreach ( $conditions as $i => $cond ) : ?>
					<tr class="openb-condition-row">
						<td style="width:120px">
							<select name="openb_conditions[<?php echo (int) $i; ?>][relation]">
								<option value="include" <?php selected( $cond['relation'], 'include' ); ?>><?php esc_html_e( 'Include', 'open-builder' ); ?></option>
								<option value="exclude" <?php selected( $cond['relation'], 'exclude' ); ?>><?php esc_html_e( 'Exclude', 'open-builder' ); ?></option>
							</select>
						</td>
						<td>
							<select name="openb_conditions[<?php echo (int) $i; ?>][rule]" style="min-width:260px">
								<?php foreach ( $rules as $val => $label ) : ?>
									<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $cond['rule'], $val ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td style="width:60px"><button type="button" class="button openb-remove-cond">&times;</button></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p><button type="button" class="button" id="openb-add-cond"><?php esc_html_e( '+ Add Condition', 'open-builder' ); ?></button></p>
		</div>
		<script>
		(function(){
			var idx = <?php echo count( $conditions ); ?>;
			var rules = <?php echo wp_json_encode( $rules ); ?>;
			document.getElementById('openb-add-cond').addEventListener('click', function(){
				var opts = Object.keys(rules).map(function(k){return '<option value="'+k+'">'+rules[k]+'</option>';}).join('');
				var tr = document.createElement('tr');
				tr.className = 'openb-condition-row';
				tr.innerHTML = '<td><select name="openb_conditions['+idx+'][relation]"><option value="include"><?php echo esc_js( __( 'Include', 'open-builder' ) ); ?></option><option value="exclude"><?php echo esc_js( __( 'Exclude', 'open-builder' ) ); ?></option></select></td>'+
					'<td><select name="openb_conditions['+idx+'][rule]" style="min-width:260px">'+opts+'</select></td>'+
					'<td><button type="button" class="button openb-remove-cond">&times;</button></td>';
				document.getElementById('openb-conditions-rows').appendChild(tr);
				idx++;
			});
			document.getElementById('openb-conditions').addEventListener('click', function(e){
				if (e.target.classList.contains('openb-remove-cond')) {
					var row = e.target.closest('tr');
					if (row) row.parentNode.removeChild(row);
				}
			});
		})();
		</script>
		<?php
	}

	/** Available display-condition rules. */
	public static function rule_choices(): array {
		$choices = [
			'entire_site' => __( 'Entire Site', 'open-builder' ),
			'front_page'  => __( 'Front Page', 'open-builder' ),
			'home'        => __( 'Blog Index', 'open-builder' ),
			'is_404'      => __( '404 Page', 'open-builder' ),
			'is_search'   => __( 'Search Results', 'open-builder' ),
			'any_archive' => __( 'Any Archive', 'open-builder' ),
			'any_single'  => __( 'Any Singular', 'open-builder' ),
		];
		foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $pt ) {
			$choices[ 'singular:' . $pt->name ] = sprintf( /* translators: %s: post type label */ __( 'Singular: %s', 'open-builder' ), $pt->labels->singular_name );
			$choices[ 'archive:' . $pt->name ]  = sprintf( /* translators: %s: post type label */ __( 'Archive: %s', 'open-builder' ), $pt->labels->name );
		}
		return $choices;
	}

	public function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['openb_template_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['openb_template_nonce'] ) ), 'openb_template_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! Security::can_edit( $post_id ) ) {
			return;
		}

		$type = isset( $_POST['openb_template_type'] ) ? sanitize_key( wp_unslash( $_POST['openb_template_type'] ) ) : 'single';
		if ( ! array_key_exists( $type, self::types() ) ) {
			$type = 'single';
		}
		update_post_meta( $post_id, self::META_TYPE, $type );

		self::save_conditions_from_post( $post_id );
	}

	/**
	 * Read, sanitize and store the `openb_conditions[]` POST field. Shared by the
	 * template and popup save handlers (each guards its own nonce/cap first).
	 */
	public static function save_conditions_from_post( int $post_id ): void {
		$conditions  = [];
		$valid_rules = array_keys( self::rule_choices() );
		if ( ! empty( $_POST['openb_conditions'] ) && is_array( $_POST['openb_conditions'] ) ) {
			foreach ( wp_unslash( $_POST['openb_conditions'] ) as $cond ) {
				if ( ! is_array( $cond ) ) {
					continue;
				}
				$relation = ( ( $cond['relation'] ?? 'include' ) === 'exclude' ) ? 'exclude' : 'include';
				$rule     = sanitize_text_field( $cond['rule'] ?? '' );
				if ( ! in_array( $rule, $valid_rules, true ) ) {
					continue;
				}
				$conditions[] = [ 'relation' => $relation, 'rule' => $rule ];
			}
		}
		update_post_meta( $post_id, self::META_CONDITIONS, $conditions );
	}

	public static function get_conditions( int $post_id ): array {
		$c = get_post_meta( $post_id, self::META_CONDITIONS, true );
		return is_array( $c ) ? $c : [];
	}

	/**
	 * Evaluate a set of include/exclude conditions against the current request.
	 * Any matching exclude wins; otherwise at least one include must match.
	 * Public so the popup loader can reuse the same engine as templates.
	 */
	public function conditions_match( array $conditions ): bool {
		if ( empty( $conditions ) ) {
			return false;
		}
		$included = false;
		foreach ( $conditions as $cond ) {
			if ( empty( $cond['rule'] ) || ! $this->rule_matches( $cond['rule'] ) ) {
				continue;
			}
			if ( 'exclude' === ( $cond['relation'] ?? 'include' ) ) {
				return false;
			}
			$included = true;
		}
		return $included;
	}

	/* --------------------------------------------------------------------- *
	 * Resolution: which template applies to the current request
	 * --------------------------------------------------------------------- */

	/** Weight of a rule for specificity (higher beats lower). */
	private function rule_weight( string $rule ): int {
		if ( 0 === strpos( $rule, 'singular:' ) ) {
			return 30;
		}
		if ( 0 === strpos( $rule, 'archive:' ) ) {
			return 30;
		}
		switch ( $rule ) {
			case 'front_page':
			case 'is_404':
			case 'is_search':
			case 'home':
				return 40;
			case 'any_single':
			case 'any_archive':
				return 20;
			case 'entire_site':
			default:
				return 1;
		}
	}

	/** Does a single rule match the current request? */
	private function rule_matches( string $rule ): bool {
		if ( 'entire_site' === $rule ) {
			return true;
		}
		if ( 'front_page' === $rule ) {
			return is_front_page();
		}
		if ( 'home' === $rule ) {
			return is_home();
		}
		if ( 'is_404' === $rule ) {
			return is_404();
		}
		if ( 'is_search' === $rule ) {
			return is_search();
		}
		if ( 'any_archive' === $rule ) {
			return is_archive() || is_home();
		}
		if ( 'any_single' === $rule ) {
			return is_singular();
		}
		if ( 0 === strpos( $rule, 'singular:' ) ) {
			return is_singular( substr( $rule, 9 ) );
		}
		if ( 0 === strpos( $rule, 'archive:' ) ) {
			$pt = substr( $rule, 8 );
			return is_post_type_archive( $pt ) || ( is_category() && 'post' === $pt ) || ( is_tag() && 'post' === $pt );
		}
		return false;
	}

	/**
	 * Resolve the best template of a given type for the current request.
	 * Returns the post id, or 0 if none applies.
	 */
	public function resolve( string $type ): int {
		if ( array_key_exists( $type, $this->resolved ) ) {
			return (int) $this->resolved[ $type ];
		}

		$templates = get_posts( [
			'post_type'      => Post_Types::CPT_TEMPLATE,
			'post_status'    => 'publish',
			'numberposts'    => 100,
			'meta_key'       => self::META_TYPE,
			'meta_value'     => $type,
			'suppress_filters' => false,
		] );

		$best_id = 0;
		$best_w  = -1;

		foreach ( $templates as $tpl ) {
			$conditions = self::get_conditions( $tpl->ID );
			if ( empty( $conditions ) ) {
				continue;
			}
			$include_w = -1;
			$excluded  = false;
			foreach ( $conditions as $cond ) {
				if ( ! $this->rule_matches( $cond['rule'] ) ) {
					continue;
				}
				if ( 'exclude' === $cond['relation'] ) {
					$excluded = true;
					break;
				}
				$include_w = max( $include_w, $this->rule_weight( $cond['rule'] ) );
			}
			if ( $excluded || $include_w < 0 ) {
				continue;
			}
			if ( $include_w > $best_w ) {
				$best_w  = $include_w;
				$best_id = $tpl->ID;
			}
		}

		$this->resolved[ $type ] = $best_id;
		return $best_id;
	}

	/** Whether the current request has any header/footer/body template. */
	private function body_type_for_request(): string {
		if ( is_404() ) {
			return '404';
		}
		if ( is_search() ) {
			return 'search';
		}
		if ( is_singular() ) {
			return 'single';
		}
		if ( is_archive() || is_home() ) {
			return 'archive';
		}
		return '';
	}

	/**
	 * Take over the theme output with our canvas when a header, footer or body
	 * template applies to this request.
	 */
	public function maybe_take_over( string $template ): string {
		// Don't interfere with our own editor/preview routes or the admin.
		if ( is_admin() || isset( $_GET[ Editor::QV_EDITOR ] ) || isset( $_GET[ Editor::QV_PREVIEW ] ) ) {
			return $template;
		}

		$header = $this->resolve( 'header' );
		$footer = $this->resolve( 'footer' );
		$body_type = $this->body_type_for_request();
		$body = $body_type ? $this->resolve( $body_type ) : 0;

		if ( ! $header && ! $footer && ! $body ) {
			return $template; // Nothing applies — leave the theme alone.
		}

		// Stash for the canvas template.
		$GLOBALS['openb_tb'] = [
			'header'    => $header,
			'footer'    => $footer,
			'body'      => $body,
			'body_type' => $body_type,
		];

		return OPENB_DIR . 'templates/canvas.php';
	}

	/** Whether any builder template (header/footer/body) applies to this request. */
	public function applies_to_request(): bool {
		if ( is_admin() ) {
			return false;
		}
		if ( $this->resolve( 'header' ) || $this->resolve( 'footer' ) ) {
			return true;
		}
		$body_type = $this->body_type_for_request();
		return $body_type ? (bool) $this->resolve( $body_type ) : false;
	}

	/** Render a template post's tree + print its CSS. Safe to call in the loop. */
	public static function render_template( int $template_id ): string {
		if ( ! $template_id ) {
			return '';
		}
		$plugin = Plugin::instance();
		$tree   = Post_Types::get_tree( $template_id );
		if ( empty( $tree ) ) {
			return '';
		}
		$css = get_post_meta( $template_id, Post_Types::META_CSS, true );
		if ( '' === $css ) {
			$css = $plugin->renderer->compile_css( $tree, $plugin->global_styles );
		}
		$html = $plugin->renderer->render_tree( $tree );
		// CSS is compiled from sanitized values only.
		return sprintf( "<style id=\"openb-tpl-%d\">%s</style>", (int) $template_id, $css ) . $html;
	}
}
