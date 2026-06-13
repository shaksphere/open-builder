<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

class Widget_Star_Rating extends Abstract_Widget {

	public function type(): string { return 'star_rating'; }
	public function title(): string { return __( 'Star Rating', 'open-builder' ); }
	public function category(): string { return 'Marketing'; }
	public function icon(): string { return 'star'; }

	public function controls(): array {
		return [
			'rating' => [
				'type'    => 'number',
				'label'   => __( 'Rating (0–5)', 'open-builder' ),
				'default' => 5,
				'group'   => 'content',
			],
			'color' => [
				'type'    => 'color',
				'label'   => __( 'Star Color', 'open-builder' ),
				'default' => 'var(--ob-color-accent)',
				'group'   => 'content',
			],
			'size' => [
				'type'    => 'text',
				'label'   => __( 'Size', 'open-builder' ),
				'default' => '24px',
				'group'   => 'content',
			],
		];
	}

	public function render( array $content, string $inner_html, array $node ): string {
		$rating = (float) ( $content['rating'] ?? 5 );
		$rating = max( 0, min( 5, $rating ) );
		$color  = Security::sanitize_color( (string) ( $content['color'] ?? '' ) ) ?: '#f59e0b';
		$size   = Security::sanitize_css_value( (string) ( $content['size'] ?? '24px' ) ) ?: '24px';

		$star_path = '<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/>';
		$full      = (int) floor( $rating );
		$has_half  = ( $rating - $full ) >= 0.5;

		$stars = '';
		for ( $i = 1; $i <= 5; $i++ ) {
			$fill = ( $i <= $full ) ? $color : ( ( $i === $full + 1 && $has_half ) ? 'url(#ob-half-' . $i . ')' : 'transparent' );
			$defs = '';
			if ( $i === $full + 1 && $has_half ) {
				$defs = sprintf(
					'<defs><linearGradient id="ob-half-%1$d"><stop offset="50%%" stop-color="%2$s"/><stop offset="50%%" stop-color="transparent"/></linearGradient></defs>',
					$i,
					esc_attr( $color )
				);
			}
			$stars .= sprintf(
				'<svg class="ob-stars__star" width="%1$s" height="%1$s" viewBox="0 0 24 24" fill="%2$s" stroke="%3$s" stroke-width="1.5" aria-hidden="true">%4$s%5$s</svg>',
				esc_attr( $size ),
				$fill,
				esc_attr( $color ),
				$defs,
				$star_path
			);
		}

		return sprintf(
			'<div class="ob-stars" role="img" aria-label="%s">%s</div>',
			esc_attr( sprintf( /* translators: %s: rating value */ __( 'Rated %s out of 5', 'open-builder' ), $rating ) ),
			$stars
		);
	}
}
