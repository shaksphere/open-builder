<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

class Widget_Site_Logo extends Abstract_Widget {

	public function type(): string { return 'site_logo'; }
	public function title(): string { return __( 'Site Logo', 'open-builder' ); }
	public function category(): string { return 'Dynamic'; }
	public function icon(): string { return 'image'; }

	public function controls(): array {
		return [
			'source' => [
				'type'    => 'select',
				'label'   => __( 'Source', 'open-builder' ),
				'choices' => [ 'site' => 'Site Logo / Title', 'custom' => 'Custom Image' ],
				'default' => 'site',
				'group'   => 'content',
			],
			'image' => [
				'type'    => 'image',
				'label'   => __( 'Custom Image', 'open-builder' ),
				'default' => [ 'id' => 0, 'url' => '' ],
				'group'   => 'content',
			],
			'width' => [
				'type'    => 'text',
				'label'   => __( 'Max Width', 'open-builder' ),
				'default' => '160px',
				'group'   => 'content',
			],
		];
	}

	public function render( array $content, string $inner_html, array $node ): string {
		$width = Security::sanitize_css_value( (string) ( $content['width'] ?? '160px' ) ) ?: '160px';
		$home  = esc_url( home_url( '/' ) );
		$style = sprintf( 'max-width:%s;height:auto', esc_attr( $width ) );

		if ( ( $content['source'] ?? 'site' ) === 'custom' ) {
			$img = $content['image'] ?? [];
			$id  = is_array( $img ) ? absint( $img['id'] ?? 0 ) : 0;
			$url = is_array( $img ) ? (string) ( $img['url'] ?? '' ) : '';
			if ( $id ) {
				$logo = wp_get_attachment_image( $id, 'medium', false, [ 'style' => $style, 'alt' => get_bloginfo( 'name' ) ] );
			} elseif ( $url ) {
				$logo = sprintf( '<img src="%s" alt="%s" style="%s">', esc_url( $url ), esc_attr( get_bloginfo( 'name' ) ), $style );
			} else {
				$logo = esc_html( get_bloginfo( 'name' ) );
			}
			return sprintf( '<a class="ob-logo" href="%s">%s</a>', $home, $logo );
		}

		// Use the theme's custom logo if set, otherwise the site title.
		$custom_logo_id = get_theme_mod( 'custom_logo' );
		if ( $custom_logo_id ) {
			$logo = wp_get_attachment_image( $custom_logo_id, 'medium', false, [ 'style' => $style, 'alt' => get_bloginfo( 'name' ) ] );
			return sprintf( '<a class="ob-logo" href="%s">%s</a>', $home, $logo );
		}

		return sprintf( '<a class="ob-logo ob-logo--text" href="%s">%s</a>', $home, esc_html( get_bloginfo( 'name' ) ) );
	}
}
