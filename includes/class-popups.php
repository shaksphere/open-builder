<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Popups: design a popup visually (openb_popup CPT, edited with Open Builder),
 * choose a trigger (load / exit-intent / scroll depth / click selector /
 * inactivity), assign it to parts of the site with the shared display-conditions
 * engine, and cap how often it shows. On the front end, matching popups render
 * as accessible, hidden ARIA dialogs and a small loader script wires the
 * triggers, frequency capping and open/close (focus trap, ESC, overlay click).
 */
class Popups {

	const META_TRIGGER   = '_openb_popup_trigger';
	const META_FREQUENCY = '_openb_popup_frequency';
	const META_DISPLAY   = '_openb_popup_display';

	/** @var array<int,\WP_Post>|null resolved matching popups for this request */
	private $matched = null;

	public function register_hooks(): void {
		add_action( 'add_meta_boxes_' . Post_Types::CPT_POPUP, [ $this, 'meta_boxes' ] );
		add_action( 'save_post_' . Post_Types::CPT_POPUP, [ $this, 'save' ], 10, 1 );

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
		add_action( 'wp_footer', [ $this, 'render' ], 100 );
	}

	/* --------------------------------------------------------------------- *
	 * Trigger / frequency / display options
	 * --------------------------------------------------------------------- */

	public static function trigger_types(): array {
		return [
			'load'   => __( 'On page load (after a delay)', 'open-builder' ),
			'exit'   => __( 'Exit intent (mouse leaves the window)', 'open-builder' ),
			'scroll' => __( 'After scrolling down a percentage', 'open-builder' ),
			'click'  => __( 'Click a CSS selector', 'open-builder' ),
			'idle'   => __( 'After a period of inactivity', 'open-builder' ),
		];
	}

	public static function get_trigger( int $post_id ): array {
		$t = get_post_meta( $post_id, self::META_TRIGGER, true );
		return wp_parse_args( is_array( $t ) ? $t : [], [
			'type'     => 'load',
			'delay'    => 3,    // seconds (load)
			'scroll'   => 50,   // percent (scroll)
			'selector' => '',   // CSS selector (click)
			'idle'     => 20,   // seconds (idle)
		] );
	}

	public static function get_frequency( int $post_id ): array {
		$f = get_post_meta( $post_id, self::META_FREQUENCY, true );
		return wp_parse_args( is_array( $f ) ? $f : [], [
			'mode' => 'session', // always | session | days
			'days' => 7,
		] );
	}

	public static function get_display( int $post_id ): array {
		$d = get_post_meta( $post_id, self::META_DISPLAY, true );
		return wp_parse_args( is_array( $d ) ? $d : [], [
			'position'      => 'center', // center | top | bottom
			'width'         => '560px',
			'overlay_close' => true,
			'show_close'    => true,
		] );
	}

	/* --------------------------------------------------------------------- *
	 * Admin metaboxes
	 * --------------------------------------------------------------------- */

	public function meta_boxes(): void {
		add_meta_box( 'openb-popup-trigger', __( 'Trigger', 'open-builder' ), [ $this, 'render_trigger_box' ], Post_Types::CPT_POPUP, 'normal', 'high' );
		add_meta_box( 'openb-popup-conditions', __( 'Display Conditions', 'open-builder' ), [ $this, 'render_conditions_box' ], Post_Types::CPT_POPUP, 'normal', 'high' );
		add_meta_box( 'openb-popup-display', __( 'Appearance & Frequency', 'open-builder' ), [ $this, 'render_display_box' ], Post_Types::CPT_POPUP, 'side', 'default' );
	}

	public function render_trigger_box( \WP_Post $post ): void {
		wp_nonce_field( 'openb_popup_meta', 'openb_popup_nonce' );
		$t = self::get_trigger( $post->ID );
		echo '<p><label><strong>' . esc_html__( 'Trigger', 'open-builder' ) . '</strong></label><br>';
		echo '<select name="openb_popup_trigger[type]" style="min-width:320px">';
		foreach ( self::trigger_types() as $key => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $key ), selected( $t['type'], $key, false ), esc_html( $label ) );
		}
		echo '</select></p>';

		printf(
			'<p><label>%s <input type="number" min="0" step="1" name="openb_popup_trigger[delay]" value="%d" style="width:80px"> %s</label></p>',
			esc_html__( 'Load delay:', 'open-builder' ), (int) $t['delay'], esc_html__( 'seconds', 'open-builder' )
		);
		printf(
			'<p><label>%s <input type="number" min="0" max="100" step="1" name="openb_popup_trigger[scroll]" value="%d" style="width:80px"> %%</label></p>',
			esc_html__( 'Scroll depth:', 'open-builder' ), (int) $t['scroll']
		);
		printf(
			'<p><label>%s <input type="text" name="openb_popup_trigger[selector]" value="%s" placeholder=".my-button, #cta" style="width:260px"></label></p>',
			esc_html__( 'Click selector:', 'open-builder' ), esc_attr( $t['selector'] )
		);
		printf(
			'<p><label>%s <input type="number" min="1" step="1" name="openb_popup_trigger[idle]" value="%d" style="width:80px"> %s</label></p>',
			esc_html__( 'Inactivity:', 'open-builder' ), (int) $t['idle'], esc_html__( 'seconds', 'open-builder' )
		);
		echo '<p class="description">' . esc_html__( 'Only the field matching the selected trigger is used.', 'open-builder' ) . '</p>';

		printf(
			'<p><a href="%s" class="button button-primary">%s</a></p>',
			esc_url( Editor::edit_url( $post->ID ) ),
			esc_html__( 'Design popup with Open Builder', 'open-builder' )
		);
	}

	public function render_conditions_box( \WP_Post $post ): void {
		Theme_Builder::render_conditions_ui( $post->ID );
	}

	public function render_display_box( \WP_Post $post ): void {
		$d = self::get_display( $post->ID );
		$f = self::get_frequency( $post->ID );

		echo '<p><label><strong>' . esc_html__( 'Position', 'open-builder' ) . '</strong></label><br>';
		echo '<select name="openb_popup_display[position]" style="width:100%">';
		foreach ( [ 'center' => __( 'Center', 'open-builder' ), 'top' => __( 'Top', 'open-builder' ), 'bottom' => __( 'Bottom', 'open-builder' ) ] as $k => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $d['position'], $k, false ), esc_html( $label ) );
		}
		echo '</select></p>';

		printf(
			'<p><label>%s<br><input type="text" name="openb_popup_display[width]" value="%s" style="width:100%%"></label></p>',
			esc_html__( 'Max width (e.g. 560px)', 'open-builder' ), esc_attr( $d['width'] )
		);
		printf(
			'<p><label><input type="checkbox" name="openb_popup_display[overlay_close]" value="1"%s> %s</label></p>',
			checked( ! empty( $d['overlay_close'] ), true, false ), esc_html__( 'Close on overlay click', 'open-builder' )
		);
		printf(
			'<p><label><input type="checkbox" name="openb_popup_display[show_close]" value="1"%s> %s</label></p>',
			checked( ! empty( $d['show_close'] ), true, false ), esc_html__( 'Show close (×) button', 'open-builder' )
		);

		echo '<hr><p><label><strong>' . esc_html__( 'Show frequency', 'open-builder' ) . '</strong></label><br>';
		echo '<select name="openb_popup_frequency[mode]" style="width:100%">';
		foreach ( [ 'always' => __( 'Every page view', 'open-builder' ), 'session' => __( 'Once per session', 'open-builder' ), 'days' => __( 'Once every N days', 'open-builder' ) ] as $k => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $f['mode'], $k, false ), esc_html( $label ) );
		}
		echo '</select></p>';
		printf(
			'<p><label>%s <input type="number" min="1" step="1" name="openb_popup_frequency[days]" value="%d" style="width:80px"></label></p>',
			esc_html__( 'Days:', 'open-builder' ), (int) $f['days']
		);
	}

	/* --------------------------------------------------------------------- *
	 * Save
	 * --------------------------------------------------------------------- */

	public function save( int $post_id ): void {
		if ( ! isset( $_POST['openb_popup_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['openb_popup_nonce'] ) ), 'openb_popup_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! Security::can_edit( $post_id ) ) {
			return;
		}

		// Trigger.
		$in  = isset( $_POST['openb_popup_trigger'] ) && is_array( $_POST['openb_popup_trigger'] ) ? wp_unslash( $_POST['openb_popup_trigger'] ) : [];
		$types = self::trigger_types();
		$trigger = [
			'type'     => isset( $in['type'] ) && isset( $types[ $in['type'] ] ) ? sanitize_key( $in['type'] ) : 'load',
			'delay'    => max( 0, (int) ( $in['delay'] ?? 3 ) ),
			'scroll'   => max( 0, min( 100, (int) ( $in['scroll'] ?? 50 ) ) ),
			'selector' => Security::sanitize_selector( (string) ( $in['selector'] ?? '' ) ),
			'idle'     => max( 1, (int) ( $in['idle'] ?? 20 ) ),
		];
		update_post_meta( $post_id, self::META_TRIGGER, $trigger );

		// Frequency.
		$fin  = isset( $_POST['openb_popup_frequency'] ) && is_array( $_POST['openb_popup_frequency'] ) ? wp_unslash( $_POST['openb_popup_frequency'] ) : [];
		$mode = in_array( $fin['mode'] ?? '', [ 'always', 'session', 'days' ], true ) ? $fin['mode'] : 'session';
		update_post_meta( $post_id, self::META_FREQUENCY, [
			'mode' => $mode,
			'days' => max( 1, (int) ( $fin['days'] ?? 7 ) ),
		] );

		// Display.
		$din = isset( $_POST['openb_popup_display'] ) && is_array( $_POST['openb_popup_display'] ) ? wp_unslash( $_POST['openb_popup_display'] ) : [];
		update_post_meta( $post_id, self::META_DISPLAY, [
			'position'      => in_array( $din['position'] ?? '', [ 'center', 'top', 'bottom' ], true ) ? $din['position'] : 'center',
			'width'         => Security::sanitize_css_value( (string) ( $din['width'] ?? '560px' ) ) ?: '560px',
			'overlay_close' => ! empty( $din['overlay_close'] ),
			'show_close'    => ! empty( $din['show_close'] ),
		] );

		// Conditions (shared engine).
		Theme_Builder::save_conditions_from_post( $post_id );
	}

	/* --------------------------------------------------------------------- *
	 * Front end
	 * --------------------------------------------------------------------- */

	/** Popups whose display conditions match the current request. */
	private function matching_popups(): array {
		if ( null !== $this->matched ) {
			return $this->matched;
		}
		$this->matched = [];

		if ( is_admin() || isset( $_GET[ Editor::QV_EDITOR ] ) || isset( $_GET[ Editor::QV_PREVIEW ] ) ) {
			return $this->matched;
		}

		$tb = Plugin::instance()->theme_builder;
		if ( ! $tb ) {
			return $this->matched;
		}

		$popups = get_posts( [
			'post_type'        => Post_Types::CPT_POPUP,
			'post_status'      => 'publish',
			'numberposts'      => 50,
			'suppress_filters' => false,
		] );

		foreach ( $popups as $popup ) {
			$tree = Post_Types::get_tree( $popup->ID );
			if ( empty( $tree ) ) {
				continue; // Nothing designed yet.
			}
			if ( $tb->conditions_match( Theme_Builder::get_conditions( $popup->ID ) ) ) {
				$this->matched[] = $popup;
			}
		}
		return $this->matched;
	}

	public function enqueue(): void {
		if ( empty( $this->matching_popups() ) ) {
			return;
		}
		// Reuse the shared front-end base + the cached global brand file.
		wp_enqueue_style( 'openb-frontend', OPENB_URL . 'assets/css/frontend.css', [], OPENB_VERSION );
		wp_enqueue_script( 'openb-frontend', OPENB_URL . 'assets/js/frontend.js', [], OPENB_VERSION, true );

		$global_url = Plugin::instance()->css_store->global_url();
		if ( '' !== $global_url ) {
			wp_enqueue_style( 'openb-global', $global_url, [ 'openb-frontend' ], null );
		}

		wp_enqueue_script( 'openb-popups', OPENB_URL . 'assets/js/popups.js', [], OPENB_VERSION, true );
	}

	/** Render the matching popups as hidden ARIA dialogs in the footer. */
	public function render(): void {
		$popups = $this->matching_popups();
		if ( empty( $popups ) ) {
			return;
		}

		foreach ( $popups as $popup ) {
			$id      = (int) $popup->ID;
			$trigger = self::get_trigger( $id );
			$freq    = self::get_frequency( $id );
			$display = self::get_display( $id );

			$attrs = [
				'id'                   => 'openb-popup-' . $id,
				'class'                => 'openb-popup openb-popup--' . sanitize_html_class( $display['position'] ),
				'role'                 => 'dialog',
				'aria-modal'           => 'true',
				'aria-hidden'          => 'true',
				'hidden'               => 'hidden',
				'data-openb-popup'     => $id,
				'data-trigger'         => $trigger['type'],
				'data-delay'           => (int) $trigger['delay'],
				'data-scroll'          => (int) $trigger['scroll'],
				'data-selector'        => $trigger['selector'],
				'data-idle'            => (int) $trigger['idle'],
				'data-freq'            => $freq['mode'],
				'data-days'            => (int) $freq['days'],
				'data-overlay-close'   => $display['overlay_close'] ? '1' : '0',
			];
			$title = get_the_title( $id );
			if ( '' !== $title ) {
				$attrs['aria-label'] = $title;
			}

			$attr_str = '';
			foreach ( $attrs as $k => $v ) {
				$attr_str .= sprintf( ' %s="%s"', $k, esc_attr( $v ) );
			}

			$close_btn = $display['show_close']
				? sprintf( '<button type="button" class="openb-popup__close" data-openb-close aria-label="%s">&times;</button>', esc_attr__( 'Close', 'open-builder' ) )
				: '';

			// render_template emits the popup's compiled <style> + sanitized tree.
			$content = Theme_Builder::render_template( $id );

			printf(
				'<div%1$s><div class="openb-popup__overlay" data-openb-close></div>'
				. '<div class="openb-popup__dialog" role="document" style="max-width:%2$s">%3$s<div class="openb-popup__content">%4$s</div></div></div>',
				$attr_str, // Built from sanitized values + esc_attr above.
				esc_attr( $display['width'] ),
				$close_btn, // Trusted markup.
				$content    // Trusted renderer.
			);
		}
	}
}
