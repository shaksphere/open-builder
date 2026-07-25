<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Feature Grid: a repeater of icon + heading + text cells laid out in a
 * responsive grid. Fills the gap the single Icon Box leaves for feature rows
 * wider than three items (build a 4- or 6-up grid from one widget instead of
 * hand-assembling columns).
 */
class Widget_Feature_Grid extends Abstract_Widget {

	public function type(): string { return 'feature_grid'; }
	public function title(): string { return __( 'Feature Grid', 'open-builder' ); }
	public function category(): string { return 'Basic'; }
	public function icon(): string { return 'grid'; }

	public function controls(): array {
		return [
			'features' => [
				'type'   => 'repeater',
				'label'  => __( 'Features', 'open-builder' ),
				'fields' => [
					'icon'  => [ 'type' => 'icon', 'label' => 'Icon', 'choices' => array_keys( Widget_Icon::set() ), 'default' => 'check' ],
					'title' => [ 'type' => 'text', 'label' => 'Title', 'default' => 'Feature' ],
					'text'  => [ 'type' => 'textarea', 'label' => 'Text', 'default' => 'Describe the benefit in a sentence.' ],
					'link'  => [ 'type' => 'url', 'label' => 'Link', 'default' => '' ],
				],
				'default' => [
					[ 'icon' => 'bolt', 'title' => 'Fast', 'text' => 'Quick turnaround without cutting corners.', 'link' => '' ],
					[ 'icon' => 'shield', 'title' => 'Reliable', 'text' => 'Rock-solid and dependable, every time.', 'link' => '' ],
					[ 'icon' => 'heart', 'title' => 'Friendly', 'text' => 'Real people who genuinely want to help.', 'link' => '' ],
					[ 'icon' => 'award', 'title' => 'Proven', 'text' => 'A track record you can count on.', 'link' => '' ],
				],
				'group' => 'content',
			],
			'columns' => [
				'type'    => 'select',
				'label'   => __( 'Columns', 'open-builder' ),
				'choices' => [ '2' => '2', '3' => '3', '4' => '4' ],
				'default' => '4',
				'group'   => 'content',
			],
			'align' => [
				'type'    => 'select',
				'label'   => __( 'Alignment', 'open-builder' ),
				'choices' => [ 'center' => 'Center', 'left' => 'Left' ],
				'default' => 'center',
				'group'   => 'content',
			],
			'color' => [
				'type'    => 'color',
				'label'   => __( 'Icon Color', 'open-builder' ),
				'default' => 'var(--ob-color-primary)',
				'group'   => 'content',
			],
		];
	}

	public function render( array $content, string $inner_html, array $node ): string {
		$features = is_array( $content['features'] ?? null ) ? $content['features'] : [];
		$columns  = max( 2, min( 4, (int) ( $content['columns'] ?? 4 ) ) );
		$align    = ( $content['align'] ?? 'center' ) === 'left' ? 'left' : 'center';
		$color    = Security::sanitize_color( (string) ( $content['color'] ?? '' ) ) ?: 'currentColor';
		$set      = Widget_Icon::set();

		if ( empty( $features ) ) {
			return '<div class="ob-image__placeholder">' . esc_html__( 'Add features in the settings panel.', 'open-builder' ) . '</div>';
		}

		$cells = '';
		foreach ( $features as $f ) {
			$name  = (string) ( $f['icon'] ?? 'check' );
			$path  = $set[ $name ] ?? $set['check'];
			$title = esc_html( (string) ( $f['title'] ?? '' ) );
			$text  = esc_html( (string) ( $f['text'] ?? '' ) );
			$link  = (string) ( $f['link'] ?? '' );

			$svg = sprintf(
				'<svg class="ob-fgrid__icon" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="%s" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>',
				esc_attr( $color ),
				$path
			);
			$title_html = ( '' !== $link )
				? sprintf( '<a href="%s">%s</a>', esc_url( $link ), $title )
				: $title;

			$cells .= sprintf(
				'<div class="ob-fgrid__cell">%s<h3 class="ob-fgrid__title">%s</h3><p class="ob-fgrid__text">%s</p></div>',
				$svg,
				$title_html,
				$text
			);
		}

		return sprintf(
			'<div class="ob-fgrid ob-fgrid--%1$s" style="--ob-fgrid-cols:%2$d">%3$s</div>',
			esc_attr( $align ),
			$columns,
			$cells
		);
	}
}
