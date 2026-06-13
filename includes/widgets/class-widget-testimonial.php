<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

class Widget_Testimonial extends Abstract_Widget {

	public function type(): string { return 'testimonial'; }
	public function title(): string { return __( 'Testimonial', 'open-builder' ); }
	public function category(): string { return 'Marketing'; }
	public function icon(): string { return 'quote'; }

	public function controls(): array {
		return [
			'quote' => [
				'type'    => 'textarea',
				'label'   => __( 'Quote', 'open-builder' ),
				'default' => 'This product completely changed how our team works. I can\'t recommend it enough.',
				'group'   => 'content',
			],
			'name' => [
				'type'    => 'text',
				'label'   => __( 'Name', 'open-builder' ),
				'default' => 'Jane Doe',
				'group'   => 'content',
			],
			'role' => [
				'type'    => 'text',
				'label'   => __( 'Role / Company', 'open-builder' ),
				'default' => 'CEO, Acme Inc.',
				'group'   => 'content',
			],
			'image' => [
				'type'    => 'image',
				'label'   => __( 'Avatar', 'open-builder' ),
				'default' => [ 'id' => 0, 'url' => '' ],
				'group'   => 'content',
			],
			'rating' => [
				'type'    => 'number',
				'label'   => __( 'Rating (0–5, 0 to hide)', 'open-builder' ),
				'default' => 5,
				'group'   => 'content',
			],
		];
	}

	public function render( array $content, string $inner_html, array $node ): string {
		$quote = esc_html( $content['quote'] ?? '' );
		$name  = esc_html( $content['name'] ?? '' );
		$role  = esc_html( $content['role'] ?? '' );
		$img   = $content['image'] ?? [];
		$id    = is_array( $img ) ? absint( $img['id'] ?? 0 ) : 0;
		$url   = is_array( $img ) ? (string) ( $img['url'] ?? '' ) : '';
		$rating = (float) ( $content['rating'] ?? 0 );

		$avatar = '';
		if ( $id ) {
			$avatar = wp_get_attachment_image( $id, 'thumbnail', false, [ 'class' => 'ob-testimonial__avatar', 'loading' => 'lazy' ] );
		} elseif ( '' !== $url ) {
			$avatar = sprintf( '<img class="ob-testimonial__avatar" src="%s" alt="%s" loading="lazy">', esc_url( $url ), $name );
		}

		$stars = '';
		if ( $rating > 0 ) {
			$full = (int) round( $rating );
			$star = '<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/>';
			$out  = '';
			for ( $i = 1; $i <= 5; $i++ ) {
				$fill = $i <= $full ? 'var(--ob-color-accent,#f59e0b)' : 'transparent';
				$out .= sprintf( '<svg width="18" height="18" viewBox="0 0 24 24" fill="%s" stroke="var(--ob-color-accent,#f59e0b)" stroke-width="1.5" aria-hidden="true">%s</svg>', $fill, $star );
			}
			$stars = '<div class="ob-testimonial__stars">' . $out . '</div>';
		}

		return sprintf(
			'<figure class="ob-testimonial">%1$s<blockquote class="ob-testimonial__quote">%2$s</blockquote>'
			. '<figcaption class="ob-testimonial__person">%3$s<div class="ob-testimonial__meta"><span class="ob-testimonial__name">%4$s</span><span class="ob-testimonial__role">%5$s</span></div></figcaption></figure>',
			$stars,
			$quote,
			$avatar,
			$name,
			$role
		);
	}
}
