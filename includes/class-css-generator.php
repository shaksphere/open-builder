<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Compiles a node tree into a single CSS string. Each node's styles are scoped
 * to its unique `.ob-{id}` class, with desktop as the base and tablet/mobile
 * emitted inside max-width media queries (mobile-friendly, breakpoint-aware).
 */
class Css_Generator {

	/** Default breakpoint max-widths in px. Desktop is the unqueried base. */
	const BREAKPOINTS = [
		'tablet' => 1024,
		'mobile' => 767,
	];

	/** Option storing the site's configured breakpoints. */
	const OPTION_BREAKPOINTS = 'openb_breakpoints';

	/** @var Global_Styles */
	private $globals;

	/** @var array{tablet:int,mobile:int} Resolved breakpoints for this instance. */
	private $bp;

	public function __construct( Global_Styles $globals ) {
		$this->globals = $globals;
		$this->bp      = self::breakpoints();
	}

	/**
	 * Site breakpoints, read from the option and clamped to sane ranges with
	 * mobile strictly below tablet. Falls back to the defaults.
	 *
	 * @return array{tablet:int,mobile:int}
	 */
	public static function breakpoints(): array {
		$saved = get_option( self::OPTION_BREAKPOINTS, [] );
		return self::sanitize_breakpoints( is_array( $saved ) ? $saved : [] );
	}

	/**
	 * Clamp incoming breakpoint values. tablet 480–1600, mobile 320–1400, and
	 * mobile always at least 40px below tablet so the bands never collide.
	 *
	 * @return array{tablet:int,mobile:int}
	 */
	public static function sanitize_breakpoints( array $in ): array {
		$tablet = isset( $in['tablet'] ) && is_numeric( $in['tablet'] ) ? (int) $in['tablet'] : self::BREAKPOINTS['tablet'];
		$mobile = isset( $in['mobile'] ) && is_numeric( $in['mobile'] ) ? (int) $in['mobile'] : self::BREAKPOINTS['mobile'];

		$tablet = max( 480, min( 1600, $tablet ) );
		$mobile = max( 320, min( 1400, $mobile ) );
		if ( $mobile >= $tablet ) {
			$mobile = max( 320, $tablet - 40 );
		}
		return [ 'tablet' => $tablet, 'mobile' => $mobile ];
	}

	/**
	 * @param array $tree Sanitized node tree.
	 * @return string Compiled CSS (without the global :root vars).
	 */
	public function compile( array $tree ): string {
		$base   = '';
		$tablet = '';
		$mobile = '';

		$this->walk( $tree, $base, $tablet, $mobile );

		$css = $base;
		if ( '' !== $tablet ) {
			$css .= sprintf( '@media(max-width:%dpx){%s}', $this->bp['tablet'], $tablet );
		}
		if ( '' !== $mobile ) {
			$css .= sprintf( '@media(max-width:%dpx){%s}', $this->bp['mobile'], $mobile );
		}
		return $css;
	}

	private function walk( array $nodes, string &$base, string &$tablet, string &$mobile ): void {
		foreach ( $nodes as $node ) {
			$id       = $node['id'] ?? '';
			$type     = $node['type'] ?? '';
			$settings = $node['settings'] ?? [];
			if ( '' === $id ) {
				continue;
			}
			$selector = '.ob-' . $id;

			// Structural defaults per layout widget.
			$base .= $this->structural_css( $selector, $type, $settings['content'] ?? [] );

			// User style maps per breakpoint.
			$style = $settings['style'] ?? [];
			$base   .= $this->rules( $selector, $style['desktop'] ?? [] );
			$tablet .= $this->rules( $selector, $style['tablet'] ?? [] );
			$mobile .= $this->rules( $selector, $style['mobile'] ?? [] );

			// Background (separate from the style map so url() can be controlled).
			$bg = $settings['background'] ?? [];
			$base   .= $this->rules( $selector, $this->background_declarations( $bg['desktop'] ?? [] ) );
			$tablet .= $this->rules( $selector, $this->background_declarations( $bg['tablet'] ?? [] ) );
			$mobile .= $this->rules( $selector, $this->background_declarations( $bg['mobile'] ?? [] ) );

			// Scoped custom CSS: replace `selector` token with the node selector.
			$custom = $settings['advanced']['custom_css'] ?? '';
			if ( '' !== $custom ) {
				$base .= str_replace( 'selector', $selector, $custom );
			}

			// Responsive visibility. Non-overlapping ranges so each toggle is
			// independent: desktop >1024, tablet 768–1024, mobile <768.
			$adv = $settings['advanced'] ?? [];
			if ( ! empty( $adv['hide_desktop'] ) ) {
				$base .= sprintf( '@media(min-width:%dpx){%s{display:none!important;}}', $this->bp['tablet'] + 1, $selector );
			}
			if ( ! empty( $adv['hide_tablet'] ) ) {
				$base .= sprintf( '@media(min-width:%dpx) and (max-width:%dpx){%s{display:none!important;}}', $this->bp['mobile'] + 1, $this->bp['tablet'], $selector );
			}
			if ( ! empty( $adv['hide_mobile'] ) ) {
				$base .= sprintf( '@media(max-width:%dpx){%s{display:none!important;}}', $this->bp['mobile'], $selector );
			}

			if ( ! empty( $node['children'] ) ) {
				$this->walk( $node['children'], $base, $tablet, $mobile );
			}
		}
	}

	/** Emit `selector{prop:val;...}` for one declaration map. */
	private function rules( string $selector, $declarations ): string {
		if ( empty( $declarations ) || ! is_array( $declarations ) ) {
			return '';
		}
		$out = '';
		foreach ( $declarations as $prop => $val ) {
			$out .= sprintf( '%s:%s;', $prop, $val );
		}
		return '' !== $out ? sprintf( '%s{%s}', $selector, $out ) : '';
	}

	/**
	 * Build CSS declarations for a sanitized background config. The image URL is
	 * the only place we emit url(), and it comes from esc_url_raw at save time.
	 */
	private function background_declarations( $bg ): array {
		if ( empty( $bg ) || ! is_array( $bg ) || empty( $bg['type'] ) ) {
			return [];
		}
		$decls = [];
		switch ( $bg['type'] ) {
			case 'color':
				if ( ! empty( $bg['color'] ) ) {
					$decls['background-color'] = $bg['color'];
				}
				break;
			case 'image':
				$url = isset( $bg['image']['url'] ) ? (string) $bg['image']['url'] : '';
				if ( '' !== $url ) {
					// esc_url for CSS context; wrap in quotes to be safe.
					$decls['background-image']    = "url('" . esc_url( $url ) . "')";
					$decls['background-size']     = $bg['size'] ?? 'cover';
					$decls['background-position'] = $bg['position'] ?? 'center center';
					$decls['background-repeat']   = $bg['repeat'] ?? 'no-repeat';
				}
				if ( ! empty( $bg['color'] ) ) {
					$decls['background-color'] = $bg['color'];
				}
				break;
			case 'gradient':
				$from  = $bg['from'] ?? '';
				$to    = $bg['to'] ?? '';
				$angle = isset( $bg['angle'] ) ? (int) $bg['angle'] : 135;
				if ( '' !== $from && '' !== $to ) {
					$decls['background-image'] = sprintf( 'linear-gradient(%ddeg,%s,%s)', $angle, $from, $to );
				}
				break;
		}
		return $decls;
	}

	/** Built-in layout behaviour that isn't expressed through style controls. */
	private function structural_css( string $selector, string $type, array $content ): string {
		switch ( $type ) {
			case 'columns':
				$gap = Security::sanitize_css_value( (string) ( $content['gap'] ?? '20px' ) ) ?: '20px';
				return sprintf(
					'%1$s{display:flex;flex-wrap:wrap;gap:%2$s;}%1$s>.ob-node{flex:1 1 0;min-width:0;}',
					$selector,
					$gap
				);
			case 'column':
				return sprintf( '%s{display:flex;flex-direction:column;}', $selector );
			default:
				return '';
		}
	}

	/** Mobile stacking rule for columns, appended globally once. */
	public function responsive_base(): string {
		return sprintf(
			'@media(max-width:%dpx){.ob-columns{flex-direction:column;}.ob-columns>.ob-node{flex-basis:100%%;}}',
			$this->bp['mobile']
		);
	}
}
