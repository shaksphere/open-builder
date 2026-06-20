<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Sensible defaults so concrete widgets only override what they need.
 */
abstract class Abstract_Widget implements Widget_Interface {

	public function category(): string {
		return 'Basic';
	}

	public function is_container(): bool {
		return false;
	}

	public function is_loop(): bool {
		return false;
	}

	public function accepts(): array {
		return [];
	}

	public function controls(): array {
		return [];
	}

	public function default_content(): array {
		$defaults = [];
		foreach ( $this->controls() as $key => $control ) {
			if ( array_key_exists( 'default', $control ) ) {
				$defaults[ $key ] = $control['default'];
			}
		}
		return $defaults;
	}

	/** Helper: pull a content value with a fallback. */
	protected function val( array $content, string $key, $fallback = '' ) {
		return array_key_exists( $key, $content ) ? $content[ $key ] : $fallback;
	}

	/** Helper: build a safe link open/close pair from a url control value. */
	protected function link( string $url, string $target = '', string $rel = '' ): array {
		$url = trim( $url );
		if ( '' === $url ) {
			return [ '', '' ];
		}
		$attrs = sprintf( ' href="%s"', esc_url( $url ) );
		if ( '_blank' === $target ) {
			$attrs .= ' target="_blank" rel="noopener noreferrer"';
		} elseif ( '' !== $rel ) {
			$attrs .= sprintf( ' rel="%s"', esc_attr( $rel ) );
		}
		return [ "<a{$attrs}>", '</a>' ];
	}
}
