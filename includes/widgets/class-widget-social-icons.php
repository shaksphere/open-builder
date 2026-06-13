<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Social icons. Uses a small bundled set of brand glyphs (simple, royalty-free
 * path data) so there's no external icon font or third-party request.
 */
class Widget_Social_Icons extends Abstract_Widget {

	public function type(): string { return 'social_icons'; }
	public function title(): string { return __( 'Social Icons', 'open-builder' ); }
	public function category(): string { return 'Marketing'; }
	public function icon(): string { return 'share'; }

	public static function networks(): array {
		return [
			'facebook'  => '<path d="M14 9V7c0-1 .3-1.5 1.5-1.5H17V3h-2.5C12 3 11 4.4 11 6.6V9H9v2.5h2V21h3v-9.5h2.2L17.5 9z"/>',
			'twitter'   => '<path d="M22 5.9c-.7.3-1.5.5-2.3.6.8-.5 1.4-1.3 1.7-2.2-.8.4-1.6.8-2.5 1A4 4 0 0012 8.9c0 .3 0 .6.1.9-3.3-.2-6.3-1.8-8.3-4.2-.4.6-.5 1.3-.5 2 0 1.4.7 2.6 1.8 3.3-.7 0-1.3-.2-1.8-.5v.1c0 1.9 1.4 3.5 3.2 3.9-.6.1-1.2.2-1.8.1.5 1.6 2 2.7 3.7 2.7A8 8 0 012 19.5a11.3 11.3 0 006.1 1.8c7.3 0 11.3-6 11.3-11.3v-.5c.8-.6 1.4-1.3 2-2z"/>',
			'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/>',
			'linkedin'  => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 10v7M7 7v.01M11 17v-4a2 2 0 014 0v4M11 11v6"/>',
			'youtube'   => '<rect x="3" y="6" width="18" height="12" rx="3"/><path d="M10 9.5l5 2.5-5 2.5z" fill="currentColor" stroke="none"/>',
			'github'    => '<path d="M12 2a10 10 0 00-3.2 19.5c.5.1.7-.2.7-.5v-1.7c-2.8.6-3.4-1.3-3.4-1.3-.5-1.2-1.1-1.5-1.1-1.5-.9-.6.1-.6.1-.6 1 .1 1.5 1 1.5 1 .9 1.5 2.3 1.1 2.9.8.1-.6.3-1.1.6-1.3-2.2-.300-4.6-1.1-4.6-5 0-1.1.4-2 1-2.7-.1-.3-.4-1.3.1-2.6 0 0 .8-.3 2.7 1a9.4 9.4 0 015 0c1.9-1.3 2.7-1 2.7-1 .5 1.3.2 2.3.1 2.6.6.7 1 1.6 1 2.7 0 3.9-2.4 4.7-4.6 5 .3.3.6.9.6 1.8v2.7c0 .3.2.6.7.5A10 10 0 0012 2z"/>',
			'tiktok'    => '<path d="M16 3c.3 2 1.6 3.6 3.5 3.9V9c-1.3 0-2.5-.4-3.5-1v6.5a5.5 5.5 0 11-5.5-5.5c.3 0 .6 0 .9.1v2.3a3.2 3.2 0 102.3 3V3z"/>',
			'website'   => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 010 18 14 14 0 010-18z"/>',
		];
	}

	public function controls(): array {
		return [
			'items' => [
				'type'   => 'repeater',
				'label'  => __( 'Profiles', 'open-builder' ),
				'fields' => [
					'network' => [ 'type' => 'select', 'label' => 'Network', 'choices' => array_combine( array_keys( self::networks() ), array_map( 'ucfirst', array_keys( self::networks() ) ) ), 'default' => 'facebook' ],
					'url'     => [ 'type' => 'url', 'label' => 'URL', 'default' => '#' ],
				],
				'default' => [
					[ 'network' => 'facebook',  'url' => '#' ],
					[ 'network' => 'instagram', 'url' => '#' ],
					[ 'network' => 'linkedin',  'url' => '#' ],
				],
				'group' => 'content',
			],
			'shape' => [
				'type'    => 'select',
				'label'   => __( 'Shape', 'open-builder' ),
				'choices' => [ 'rounded' => 'Rounded', 'circle' => 'Circle', 'square' => 'Square', 'bare' => 'Icon only' ],
				'default' => 'rounded',
				'group'   => 'content',
			],
			'size' => [
				'type'    => 'text',
				'label'   => __( 'Icon Size', 'open-builder' ),
				'default' => '20px',
				'group'   => 'content',
			],
		];
	}

	public function render( array $content, string $inner_html, array $node ): string {
		$items = is_array( $content['items'] ?? null ) ? $content['items'] : [];
		$nets  = self::networks();
		$shape = in_array( $content['shape'] ?? 'rounded', [ 'rounded', 'circle', 'square', 'bare' ], true ) ? $content['shape'] : 'rounded';
		$size  = Security::sanitize_css_value( (string) ( $content['size'] ?? '20px' ) ) ?: '20px';

		if ( empty( $items ) ) {
			return '';
		}

		$out = sprintf( '<div class="ob-social ob-social--%s">', esc_attr( $shape ) );
		foreach ( $items as $item ) {
			$net = sanitize_key( $item['network'] ?? '' );
			if ( ! isset( $nets[ $net ] ) ) {
				continue;
			}
			$url = (string) ( $item['url'] ?? '#' );
			$out .= sprintf(
				'<a class="ob-social__link" href="%1$s" aria-label="%2$s" rel="noopener" target="_blank">'
				. '<svg width="%3$s" height="%3$s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%4$s</svg></a>',
				esc_url( $url ),
				esc_attr( ucfirst( $net ) ),
				esc_attr( $size ),
				$nets[ $net ]
			);
		}
		$out .= '</div>';
		return $out;
	}
}
