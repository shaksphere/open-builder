<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Google Map embed. Uses the keyless Google Maps embed query, so no API key
 * is required. The address is URL-encoded and the iframe is lazy-loaded.
 */
class Widget_Google_Map extends Abstract_Widget {

	public function type(): string { return 'google_map'; }
	public function title(): string { return __( 'Google Map', 'open-builder' ); }
	public function category(): string { return 'Media'; }
	public function icon(): string { return 'map'; }

	public function controls(): array {
		return [
			'address' => [
				'type'    => 'text',
				'label'   => __( 'Address or Place', 'open-builder' ),
				'default' => 'Sydney Opera House, Australia',
				'group'   => 'content',
			],
			'zoom' => [
				'type'    => 'number',
				'label'   => __( 'Zoom (1–20)', 'open-builder' ),
				'default' => 14,
				'group'   => 'content',
			],
			'height' => [
				'type'    => 'text',
				'label'   => __( 'Height', 'open-builder' ),
				'default' => '400px',
				'group'   => 'content',
			],
		];
	}

	public function render( array $content, string $inner_html, array $node ): string {
		$address = trim( (string) ( $content['address'] ?? '' ) );
		$zoom    = (int) ( $content['zoom'] ?? 14 );
		$zoom    = max( 1, min( 20, $zoom ) );
		$height  = Security::sanitize_css_value( (string) ( $content['height'] ?? '400px' ) ) ?: '400px';

		if ( '' === $address ) {
			return '<div class="ob-image__placeholder">' . esc_html__( 'Enter an address', 'open-builder' ) . '</div>';
		}

		$src = 'https://maps.google.com/maps?q=' . rawurlencode( $address ) . '&z=' . $zoom . '&output=embed';

		return sprintf(
			'<div class="ob-map" style="height:%1$s"><iframe class="ob-map__frame" src="%2$s" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="%3$s" style="border:0;width:100%%;height:100%%" allowfullscreen></iframe></div>',
			esc_attr( $height ),
			esc_url( $src ),
			esc_attr( $address )
		);
	}
}
