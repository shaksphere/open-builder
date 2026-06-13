<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Icon widget backed by a small, bundled, royalty-free SVG set (no external
 * icon font, no third-party licensing concerns).
 */
class Widget_Icon extends Abstract_Widget {

	public function type(): string { return 'icon'; }
	public function title(): string { return __( 'Icon', 'open-builder' ); }
	public function category(): string { return 'Basic'; }
	public function icon(): string { return 'star'; }

	public static function set(): array {
		return [
			'star'   => '<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>',
			'check'  => '<path d="M20 6L9 17l-5-5"/>',
			'arrow'  => '<path d="M5 12h14M13 6l6 6-6 6"/>',
			'heart'  => '<path d="M20.8 4.6a5.5 5.5 0 00-7.8 0L12 5.6l-1-1a5.5 5.5 0 00-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 000-7.8z"/>',
			'bolt'   => '<path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>',
			'mail'   => '<path d="M4 4h16v16H4z"/><path d="M22 6l-10 7L2 6"/>',
			'phone'  => '<path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.13.96.36 1.9.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0122 16.92z"/>',
			'globe'  => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 010 20 15.3 15.3 0 010-20z"/>',
		];
	}

	public function controls(): array {
		return [
			'icon' => [
				'type'    => 'icon',
				'label'   => __( 'Icon', 'open-builder' ),
				'choices' => array_keys( self::set() ),
				'default' => 'star',
				'group'   => 'content',
			],
			'size' => [
				'type'    => 'text',
				'label'   => __( 'Size', 'open-builder' ),
				'default' => '40px',
				'group'   => 'content',
			],
			'color' => [
				'type'    => 'color',
				'label'   => __( 'Color', 'open-builder' ),
				'default' => 'var(--ob-color-primary)',
				'group'   => 'content',
			],
			'link' => [
				'type'    => 'url',
				'label'   => __( 'Link', 'open-builder' ),
				'default' => '',
				'group'   => 'content',
			],
		];
	}

	public function render( array $content, string $inner_html, array $node ): string {
		$set  = self::set();
		$name = (string) $this->val( $content, 'icon', 'star' );
		$path = $set[ $name ] ?? $set['star'];
		$size = Security::sanitize_css_value( (string) $this->val( $content, 'size', '40px' ) ) ?: '40px';
		$color = Security::sanitize_color( (string) $this->val( $content, 'color', '' ) ) ?: 'currentColor';

		$svg = sprintf(
			'<svg class="ob-icon__svg" width="%1$s" height="%1$s" viewBox="0 0 24 24" fill="none" stroke="%2$s" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%3$s</svg>',
			esc_attr( $size ),
			esc_attr( $color ),
			$path // From our own static, trusted set.
		);

		$link = (string) $this->val( $content, 'link', '' );
		if ( '' !== $link ) {
			$svg = sprintf( '<a href="%s">%s</a>', esc_url( $link ), $svg );
		}
		return $svg;
	}
}
