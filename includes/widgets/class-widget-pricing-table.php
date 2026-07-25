<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Pricing Table: a repeater of plans rendered as comparison cards, with one
 * optionally highlighted as "featured". Stops people faking pricing tables out
 * of raw columns + buttons (a common small-business page need).
 *
 * Each plan's feature list is a plain textarea, one feature per line — nested
 * repeaters aren't a thing here, and per-line is the least fiddly authoring UX.
 */
class Widget_Pricing_Table extends Abstract_Widget {

	public function type(): string { return 'pricing_table'; }
	public function title(): string { return __( 'Pricing Table', 'open-builder' ); }
	public function category(): string { return 'Marketing'; }
	public function icon(): string { return 'pricing'; }

	public function controls(): array {
		return [
			'plans' => [
				'type'   => 'repeater',
				'label'  => __( 'Plans', 'open-builder' ),
				'fields' => [
					'name'         => [ 'type' => 'text', 'label' => 'Name', 'default' => 'Plan' ],
					'price'        => [ 'type' => 'text', 'label' => 'Price', 'default' => '$29' ],
					'period'       => [ 'type' => 'text', 'label' => 'Period', 'default' => '/month' ],
					'features'     => [ 'type' => 'textarea', 'label' => 'Features (one per line)', 'default' => "Feature one\nFeature two\nFeature three" ],
					'button_label' => [ 'type' => 'text', 'label' => 'Button label', 'default' => 'Choose plan' ],
					'button_url'   => [ 'type' => 'url', 'label' => 'Button link', 'default' => '#contact' ],
					'featured'     => [ 'type' => 'toggle', 'label' => 'Highlight this plan', 'default' => false ],
				],
				'default' => [
					[ 'name' => 'Starter', 'price' => '$19', 'period' => '/month', 'features' => "Everything to get going\nEmail support\n1 project", 'button_label' => 'Choose Starter', 'button_url' => '#contact', 'featured' => '' ],
					[ 'name' => 'Standard', 'price' => '$49', 'period' => '/month', 'features' => "Everything in Starter\nPriority support\n10 projects\nAdvanced features", 'button_label' => 'Choose Standard', 'button_url' => '#contact', 'featured' => '1' ],
					[ 'name' => 'Premium', 'price' => '$99', 'period' => '/month', 'features' => "Everything in Standard\nDedicated manager\nUnlimited projects", 'button_label' => 'Choose Premium', 'button_url' => '#contact', 'featured' => '' ],
				],
				'group' => 'content',
			],
			'columns' => [
				'type'    => 'select',
				'label'   => __( 'Columns', 'open-builder' ),
				'choices' => [ '2' => '2', '3' => '3', '4' => '4' ],
				'default' => '3',
				'group'   => 'content',
			],
		];
	}

	public function render( array $content, string $inner_html, array $node ): string {
		$plans   = is_array( $content['plans'] ?? null ) ? $content['plans'] : [];
		$columns = max( 2, min( 4, (int) ( $content['columns'] ?? 3 ) ) );

		if ( empty( $plans ) ) {
			return '<div class="ob-image__placeholder">' . esc_html__( 'Add plans in the settings panel.', 'open-builder' ) . '</div>';
		}

		$cards = '';
		foreach ( $plans as $plan ) {
			$featured = ! empty( $plan['featured'] );
			$name     = esc_html( (string) ( $plan['name'] ?? '' ) );
			$price    = esc_html( (string) ( $plan['price'] ?? '' ) );
			$period   = esc_html( (string) ( $plan['period'] ?? '' ) );
			$label    = esc_html( (string) ( $plan['button_label'] ?? 'Choose' ) );
			$url      = (string) ( $plan['button_url'] ?? '#' );

			$features_raw = (string) ( $plan['features'] ?? '' );
			$lines = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $features_raw ) ) );
			$list  = '';
			foreach ( $lines as $line ) {
				$list .= sprintf( '<li class="ob-pricing__feature">%s</li>', esc_html( $line ) );
			}

			$cards .= sprintf(
				'<div class="ob-pricing__card%1$s">%2$s<div class="ob-pricing__head"><div class="ob-pricing__name">%3$s</div><div class="ob-pricing__price">%4$s<span class="ob-pricing__period">%5$s</span></div></div><ul class="ob-pricing__features">%6$s</ul><a class="ob-button ob-pricing__btn %7$s" href="%8$s">%9$s</a></div>',
				$featured ? ' is-featured' : '',
				$featured ? '<div class="ob-pricing__badge">' . esc_html__( 'Most popular', 'open-builder' ) . '</div>' : '',
				$name,
				$price,
				$period,
				$list,
				$featured ? 'ob-button--primary' : 'ob-button--outline',
				esc_url( $url ),
				$label
			);
		}

		return sprintf(
			'<div class="ob-pricing" style="--ob-pricing-cols:%d">%s</div>',
			$columns,
			$cards
		);
	}
}
