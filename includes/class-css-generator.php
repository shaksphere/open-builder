<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Compiles a node tree into a single CSS string. Each node's styles are scoped
 * to its unique `.ob-{id}` class, with desktop as the base and tablet/mobile
 * emitted inside max-width media queries (mobile-friendly, breakpoint-aware).
 */
class Css_Generator {

	/** Breakpoint max-widths in px. Desktop is the unqueried base. */
	const BREAKPOINTS = [
		'tablet' => 1024,
		'mobile' => 767,
	];

	/** @var Global_Styles */
	private $globals;

	public function __construct( Global_Styles $globals ) {
		$this->globals = $globals;
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
			$css .= sprintf( '@media(max-width:%dpx){%s}', self::BREAKPOINTS['tablet'], $tablet );
		}
		if ( '' !== $mobile ) {
			$css .= sprintf( '@media(max-width:%dpx){%s}', self::BREAKPOINTS['mobile'], $mobile );
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

			// Scoped custom CSS: replace `selector` token with the node selector.
			$custom = $settings['advanced']['custom_css'] ?? '';
			if ( '' !== $custom ) {
				$base .= str_replace( 'selector', $selector, $custom );
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
			self::BREAKPOINTS['mobile']
		);
	}
}
