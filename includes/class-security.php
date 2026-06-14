<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Security helpers: capability checks and recursive sanitization of the
 * builder node tree. The tree is the only large, untrusted blob the editor
 * sends us, so it gets the strictest treatment.
 */
class Security {

	/** Capability required to use the builder on a given post. */
	public static function can_edit( int $post_id ): bool {
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Sanitize page settings from the editor.
	 *
	 * @param array $in       Raw incoming settings.
	 * @param bool  $allow_js Whether the current user may store/run custom JS
	 *                        (mirrors the unfiltered_html capability).
	 */
	public static function sanitize_page_settings( array $in, bool $allow_js ): array {
		$layouts = [ 'default', 'full', 'boxed', 'canvas' ];
		$layout  = (string) ( $in['layout'] ?? 'default' );

		$out = [
			'layout'       => in_array( $layout, $layouts, true ) ? $layout : 'default',
			'hide_title'   => ! empty( $in['hide_title'] ),
			'content_width'=> self::sanitize_css_value( (string) ( $in['content_width'] ?? '' ) ),
			'background'   => self::sanitize_color( (string) ( $in['background'] ?? '' ) ),
			'body_classes' => self::sanitize_class_list( (string) ( $in['body_classes'] ?? '' ) ),
			'custom_css'   => self::sanitize_custom_css( (string) ( $in['custom_css'] ?? '' ) ),
			'custom_js'    => '',
			'js_allowed'   => false,
		];

		// Custom JS is only stored (and later emitted) for capable users.
		if ( $allow_js && ! empty( $in['custom_js'] ) ) {
			$js = (string) $in['custom_js'];
			if ( strlen( $js ) > 50000 ) {
				$js = substr( $js, 0, 50000 );
			}
			// Strip a closing script tag so the value can't break out of <script>.
			$js = str_ireplace( '</script', '<\/script', $js );
			$out['custom_js']  = $js;
			$out['js_allowed'] = true;
		}

		return $out;
	}

	/** Capability required to manage global settings / forms. */
	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Recursively sanitize a node tree coming from the editor.
	 *
	 * Unknown keys are dropped. Content fields are sanitized by their declared
	 * widget control type. Style values are restricted to a safe CSS subset.
	 *
	 * @param array $nodes   Raw decoded nodes.
	 * @param int   $depth   Recursion guard.
	 * @return array
	 */
	public static function sanitize_tree( array $nodes, int $depth = 0 ): array {
		if ( $depth > 50 ) {
			return [];
		}

		$clean = [];
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) || empty( $node['type'] ) ) {
				continue;
			}

			$type = sanitize_key( $node['type'] );
			if ( ! Widgets::is_registered( $type ) ) {
				continue;
			}

			$clean_node = [
				'id'       => isset( $node['id'] ) ? self::sanitize_id( $node['id'] ) : self::generate_id(),
				'type'     => $type,
				'settings' => self::sanitize_settings( $node['settings'] ?? [], $type ),
				'children' => [],
			];

			if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
				$clean_node['children'] = self::sanitize_tree( $node['children'], $depth + 1 );
			}

			$clean[] = $clean_node;
		}

		return $clean;
	}

	/**
	 * Sanitize a node's settings against its widget control schema.
	 */
	private static function sanitize_settings( $settings, string $type ): array {
		if ( ! is_array( $settings ) ) {
			return [ 'content' => [], 'style' => [] ];
		}

		$schema  = Widgets::get_controls( $type );
		$content = [];

		foreach ( $schema as $key => $control ) {
			if ( ! array_key_exists( $key, (array) ( $settings['content'] ?? [] ) ) ) {
				continue;
			}
			$content[ $key ] = self::sanitize_control_value(
				$settings['content'][ $key ],
				$control['type'] ?? 'text',
				$control
			);
		}

		$style = self::sanitize_style( $settings['style'] ?? [] );

		$advanced = [
			'css_id'      => isset( $settings['advanced']['css_id'] ) ? sanitize_html_class( $settings['advanced']['css_id'] ) : '',
			'css_classes' => isset( $settings['advanced']['css_classes'] ) ? self::sanitize_class_list( $settings['advanced']['css_classes'] ) : '',
			'custom_css'  => isset( $settings['advanced']['custom_css'] ) ? self::sanitize_custom_css( $settings['advanced']['custom_css'] ) : '',
		];

		return [
			'content'    => $content,
			'style'      => $style,
			'background' => self::sanitize_background( $settings['background'] ?? [] ),
			'advanced'   => $advanced,
		];
	}

	/**
	 * Sanitize per-breakpoint background settings. Kept separate from the free
	 * style map because background-image needs a controlled url() (which the
	 * general CSS-value sanitizer deliberately rejects).
	 */
	public static function sanitize_background( $bg ): array {
		if ( ! is_array( $bg ) ) {
			return [];
		}
		$out         = [];
		$breakpoints = [ 'desktop', 'tablet', 'mobile' ];
		$positions   = [ 'center center', 'top left', 'top center', 'top right', 'center left', 'center right', 'bottom left', 'bottom center', 'bottom right' ];
		$sizes       = [ 'cover', 'contain', 'auto' ];
		$repeats     = [ 'no-repeat', 'repeat', 'repeat-x', 'repeat-y' ];
		$types       = [ 'none', 'color', 'image', 'gradient' ];

		foreach ( $breakpoints as $bp ) {
			if ( empty( $bg[ $bp ] ) || ! is_array( $bg[ $bp ] ) ) {
				continue;
			}
			$in       = $bg[ $bp ];
			$type_in  = (string) ( $in['type'] ?? 'none' );
			$type     = in_array( $type_in, $types, true ) ? $type_in : 'none';
			if ( 'none' === $type ) {
				continue;
			}
			$clean = [ 'type' => $type ];

			if ( 'color' === $type ) {
				$clean['color'] = self::sanitize_color( (string) ( $in['color'] ?? '' ) );
			} elseif ( 'image' === $type ) {
				$img = $in['image'] ?? [];
				$clean['image'] = [
					'id'  => isset( $img['id'] ) ? absint( $img['id'] ) : 0,
					'url' => isset( $img['url'] ) ? esc_url_raw( (string) $img['url'] ) : '',
				];
				$position = (string) ( $in['position'] ?? 'center center' );
				$size     = (string) ( $in['size'] ?? 'cover' );
				$repeat   = (string) ( $in['repeat'] ?? 'no-repeat' );
				$clean['position'] = in_array( $position, $positions, true ) ? $position : 'center center';
				$clean['size']     = in_array( $size, $sizes, true ) ? $size : 'cover';
				$clean['repeat']   = in_array( $repeat, $repeats, true ) ? $repeat : 'no-repeat';
				$clean['color']    = self::sanitize_color( (string) ( $in['color'] ?? '' ) ); // overlay/fallback
			} elseif ( 'gradient' === $type ) {
				$clean['from']  = self::sanitize_color( (string) ( $in['from'] ?? '' ) );
				$clean['to']    = self::sanitize_color( (string) ( $in['to'] ?? '' ) );
				$clean['angle'] = isset( $in['angle'] ) ? max( 0, min( 360, (int) $in['angle'] ) ) : 135;
			}

			$out[ $bp ] = $clean;
		}

		return $out;
	}

	/** Sanitize one content control value by its control type. */
	public static function sanitize_control_value( $value, string $control_type, array $control = [] ) {
		switch ( $control_type ) {
			case 'select':
				// Restrict to the control's declared choices when present.
				$value   = sanitize_text_field( (string) $value );
				$choices = isset( $control['choices'] ) && is_array( $control['choices'] ) ? array_keys( $control['choices'] ) : null;
				if ( null !== $choices && ! in_array( $value, $choices, true ) ) {
					return isset( $control['default'] ) ? $control['default'] : ( $choices[0] ?? '' );
				}
				return $value;
			case 'icon':
				$value   = sanitize_text_field( (string) $value );
				$choices = isset( $control['choices'] ) && is_array( $control['choices'] ) ? $control['choices'] : null;
				if ( null !== $choices && ! in_array( $value, $choices, true ) ) {
					return isset( $control['default'] ) ? $control['default'] : ( $choices[0] ?? '' );
				}
				return $value;
			case 'richtext':
				return wp_kses_post( (string) $value );
			case 'html':
				// Custom HTML widget: editors with unfiltered_html keep raw markup
				// (mirrors core), everyone else is filtered to the post ruleset.
				$value = (string) $value;
				return current_user_can( 'unfiltered_html' ) ? $value : wp_kses_post( $value );
			case 'textarea':
				return sanitize_textarea_field( (string) $value );
			case 'url':
				return esc_url_raw( (string) $value );
			case 'number':
				return is_numeric( $value ) ? $value + 0 : '';
			case 'toggle':
				return (bool) $value;
			case 'color':
				return self::sanitize_color( (string) $value );
			case 'image':
				$id  = isset( $value['id'] ) ? absint( $value['id'] ) : 0;
				$url = isset( $value['url'] ) ? esc_url_raw( $value['url'] ) : '';
				return [ 'id' => $id, 'url' => $url ];
			case 'gallery':
				if ( ! is_array( $value ) ) {
					return [];
				}
				$items = [];
				foreach ( array_slice( $value, 0, 100 ) as $img ) {
					if ( ! is_array( $img ) ) {
						continue;
					}
					$items[] = [
						'id'  => isset( $img['id'] ) ? absint( $img['id'] ) : 0,
						'url' => isset( $img['url'] ) ? esc_url_raw( $img['url'] ) : '',
					];
				}
				return $items;
			case 'repeater':
				return is_array( $value ) ? self::sanitize_repeater( $value ) : [];
			case 'text':
			default:
				return sanitize_text_field( (string) $value );
		}
	}

	private static function sanitize_repeater( array $rows ): array {
		$out = [];
		foreach ( array_slice( $rows, 0, 100 ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$clean = [];
			foreach ( $row as $k => $v ) {
				$clean[ sanitize_key( $k ) ] = is_array( $v )
					? array_map( 'sanitize_text_field', wp_unslash( $v ) )
					: sanitize_text_field( (string) $v );
			}
			$out[] = $clean;
		}
		return $out;
	}

	/**
	 * Sanitize per-breakpoint style maps. Only whitelisted CSS properties pass,
	 * and each value is filtered to a safe CSS token set (no url(), no
	 * expression(), no semicolons/braces).
	 */
	public static function sanitize_style( $style ): array {
		if ( ! is_array( $style ) ) {
			return [];
		}

		$allowed_props = self::allowed_css_properties();
		$breakpoints   = [ 'desktop', 'tablet', 'mobile' ];
		$out           = [];

		foreach ( $breakpoints as $bp ) {
			if ( empty( $style[ $bp ] ) || ! is_array( $style[ $bp ] ) ) {
				continue;
			}
			foreach ( $style[ $bp ] as $prop => $val ) {
				$prop = strtolower( trim( (string) $prop ) );
				if ( ! in_array( $prop, $allowed_props, true ) ) {
					continue;
				}
				$safe = self::sanitize_css_value( (string) $val );
				if ( '' !== $safe ) {
					$out[ $bp ][ $prop ] = $safe;
				}
			}
		}

		return $out;
	}

	/** Whitelist of CSS properties the style controls may set. */
	public static function allowed_css_properties(): array {
		return [
			'color', 'background-color', 'background-image', 'background-size',
			'background-position', 'background-repeat',
			'font-size', 'font-weight', 'font-family', 'font-style', 'line-height',
			'letter-spacing', 'text-align', 'text-transform', 'text-decoration',
			'width', 'max-width', 'min-width', 'height', 'max-height', 'min-height',
			'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
			'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
			'border-width', 'border-style', 'border-color', 'border-radius',
			'box-shadow', 'opacity', 'display', 'flex-direction', 'flex-wrap',
			'justify-content', 'align-items', 'gap', 'grid-template-columns',
			'object-fit', 'overflow', 'z-index', 'position', 'top', 'right', 'bottom', 'left',
		];
	}

	/**
	 * Allow a value through only if it looks like a benign CSS token list.
	 * Blocks url(), expression(), javascript:, and CSS-injection metacharacters.
	 */
	public static function sanitize_css_value( string $value ): string {
		$value = trim( $value );
		if ( '' === $value || strlen( $value ) > 800 ) {
			return '';
		}
		$lower = strtolower( $value );
		foreach ( [ 'expression(', 'javascript:', '</', '<script', '@import', 'behavior:' ] as $bad ) {
			if ( false !== strpos( $lower, $bad ) ) {
				return '';
			}
		}
		// Permit var(--...) and gradients/rgba but forbid the structural CSS chars.
		if ( preg_match( '/[;{}<>]/', $value ) ) {
			return '';
		}
		// url() is only allowed for the WP-controlled background-image we build ourselves,
		// so reject any url() that arrives from the client here.
		if ( false !== stripos( $value, 'url(' ) ) {
			return '';
		}
		return $value;
	}

	public static function sanitize_color( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '/^#([a-f0-9]{3}|[a-f0-9]{4}|[a-f0-9]{6}|[a-f0-9]{8})$/i', $value ) ) {
			return $value;
		}
		if ( preg_match( '/^(rgb|rgba|hsl|hsla)\([0-9.,%\s\/]+\)$/i', $value ) ) {
			return $value;
		}
		if ( preg_match( '/^var\(--[a-z0-9\-_]+\)$/i', $value ) ) {
			return $value;
		}
		return '';
	}

	public static function sanitize_custom_css( string $css ): string {
		// Scoped, but still strip the obvious script-injection vectors.
		$css = (string) $css;
		if ( strlen( $css ) > 20000 ) {
			$css = substr( $css, 0, 20000 );
		}
		$lower = strtolower( $css );
		foreach ( [ '</style', '<script', 'javascript:', 'expression(', 'behavior:' ] as $bad ) {
			if ( false !== strpos( $lower, $bad ) ) {
				return '';
			}
		}
		return $css;
	}

	public static function sanitize_class_list( string $classes ): string {
		$parts = preg_split( '/\s+/', trim( $classes ) );
		$parts = array_filter( array_map( 'sanitize_html_class', $parts ) );
		return implode( ' ', $parts );
	}

	public static function sanitize_id( $id ): string {
		$id = preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $id );
		return $id !== '' ? $id : self::generate_id();
	}

	public static function generate_id(): string {
		return 'ob' . substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 10 );
	}
}
