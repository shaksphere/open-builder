<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

class Widget_Gallery extends Abstract_Widget {

	public function type(): string { return 'gallery'; }
	public function title(): string { return __( 'Gallery', 'open-builder' ); }
	public function category(): string { return 'Media'; }
	public function icon(): string { return 'image'; }

	public function controls(): array {
		return [
			'images' => [
				'type'    => 'gallery',
				'label'   => __( 'Images', 'open-builder' ),
				'default' => [],
				'group'   => 'content',
			],
			'columns' => [
				'type'    => 'select',
				'label'   => __( 'Columns', 'open-builder' ),
				'choices' => [ '2' => '2', '3' => '3', '4' => '4', '5' => '5' ],
				'default' => '3',
				'group'   => 'content',
			],
			'layout' => [
				'type'    => 'select',
				'label'   => __( 'Layout', 'open-builder' ),
				'choices' => [ 'grid' => 'Grid (uniform)', 'masonry' => 'Masonry' ],
				'default' => 'grid',
				'group'   => 'content',
			],
			'gap' => [
				'type'    => 'text',
				'label'   => __( 'Gap', 'open-builder' ),
				'default' => '12px',
				'group'   => 'content',
			],
			'lightbox' => [
				'type'    => 'toggle',
				'label'   => __( 'Open in lightbox', 'open-builder' ),
				'default' => true,
				'hint'    => __( 'Click opens the full image in an overlay (falls back to a normal link without JS).', 'open-builder' ),
				'group'   => 'content',
			],
		];
	}

	public function render( array $content, string $inner_html, array $node ): string {
		$images  = is_array( $content['images'] ?? null ) ? $content['images'] : [];
		$columns = (int) ( $content['columns'] ?? 3 );
		$columns = max( 2, min( 5, $columns ) );
		$masonry = ( $content['layout'] ?? 'grid' ) === 'masonry';
		$gap     = Security::sanitize_css_value( (string) ( $content['gap'] ?? '12px' ) ) ?: '12px';
		$link    = ! empty( $content['lightbox'] );

		if ( empty( $images ) ) {
			return '<div class="ob-image__placeholder">' . esc_html__( 'No images selected', 'open-builder' ) . '</div>';
		}

		$items = '';
		foreach ( $images as $img ) {
			$id  = absint( $img['id'] ?? 0 );
			$url = (string) ( $img['url'] ?? '' );
			if ( $id ) {
				$thumb = wp_get_attachment_image( $id, 'medium_large', false, [ 'class' => 'ob-gallery__img', 'loading' => 'lazy', 'decoding' => 'async' ] );
				$full  = wp_get_attachment_image_url( $id, 'full' ) ?: $url;
			} elseif ( '' !== $url ) {
				$thumb = sprintf( '<img class="ob-gallery__img" src="%s" alt="" loading="lazy" decoding="async">', esc_url( $url ) );
				$full  = $url;
			} else {
				continue;
			}
			// A real anchor either way, so the "open full size" behaviour degrades
			// gracefully to a plain link when JS is off; the lightbox JS upgrades it.
			$items .= $link
				? sprintf( '<a class="ob-gallery__item" href="%s">%s</a>', esc_url( $full ), $thumb )
				: sprintf( '<div class="ob-gallery__item">%s</div>', $thumb );
		}

		return sprintf(
			'<div class="ob-gallery%1$s" style="--ob-gallery-cols:%2$d;--ob-gallery-gap:%3$s"%4$s>%5$s</div>',
			$masonry ? ' ob-gallery--masonry' : '',
			$columns,
			esc_attr( $gap ),
			$link ? ' data-ob-lightbox="1"' : '',
			$items
		);
	}
}
