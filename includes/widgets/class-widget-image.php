<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

class Widget_Image extends Abstract_Widget {

	public function type(): string { return 'image'; }
	public function title(): string { return __( 'Image', 'open-builder' ); }
	public function category(): string { return 'Basic'; }
	public function icon(): string { return 'image'; }

	public function controls(): array {
		return [
			'image' => [
				'type'    => 'image',
				'label'   => __( 'Image', 'open-builder' ),
				'default' => [ 'id' => 0, 'url' => '' ],
				'group'   => 'content',
			],
			'alt' => [
				'type'    => 'text',
				'label'   => __( 'Alt Text', 'open-builder' ),
				'default' => '',
				'group'   => 'content',
			],
			'link' => [
				'type'    => 'url',
				'label'   => __( 'Link', 'open-builder' ),
				'default' => '',
				'group'   => 'content',
			],
			'lazy' => [
				'type'    => 'toggle',
				'label'   => __( 'Lazy load', 'open-builder' ),
				'default' => true,
				'group'   => 'content',
			],
		];
	}

	public function render( array $content, string $inner_html, array $node ): string {
		$img = $this->val( $content, 'image', [] );
		$id  = is_array( $img ) ? absint( $img['id'] ?? 0 ) : 0;
		$url = is_array( $img ) ? (string) ( $img['url'] ?? '' ) : '';
		$alt = (string) $this->val( $content, 'alt', '' );
		$lazy = $this->val( $content, 'lazy', true );

		if ( $id > 0 ) {
			// Prefer WP's responsive markup (srcset/sizes) when we have an attachment.
			$html = wp_get_attachment_image( $id, 'large', false, [
				'class'    => 'ob-image__img',
				'alt'      => $alt,
				'loading'  => $lazy ? 'lazy' : 'eager',
				'decoding' => 'async',
			] );
		} elseif ( '' !== $url ) {
			$html = sprintf(
				'<img class="ob-image__img" src="%1$s" alt="%2$s" loading="%3$s" decoding="async" />',
				esc_url( $url ),
				esc_attr( $alt ),
				$lazy ? 'lazy' : 'eager'
			);
		} else {
			$html = '<div class="ob-image__placeholder" aria-hidden="true">' . esc_html__( 'No image selected', 'open-builder' ) . '</div>';
		}

		$link = (string) $this->val( $content, 'link', '' );
		if ( '' !== $link ) {
			$html = sprintf( '<a href="%s">%s</a>', esc_url( $link ), $html );
		}

		return $html;
	}
}
