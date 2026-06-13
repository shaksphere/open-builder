<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

class Widget_Accordion extends Abstract_Widget {

	public function type(): string { return 'accordion'; }
	public function title(): string { return __( 'Accordion', 'open-builder' ); }
	public function category(): string { return 'Interactive'; }
	public function icon(): string { return 'accordion'; }

	public function controls(): array {
		return [
			'items' => [
				'type'   => 'repeater',
				'label'  => __( 'Items', 'open-builder' ),
				'fields' => [
					'title'   => [ 'type' => 'text', 'label' => 'Title', 'default' => 'Question' ],
					'content' => [ 'type' => 'richtext', 'label' => 'Content', 'default' => '<p>Answer goes here.</p>' ],
				],
				'default' => [
					[ 'title' => 'What is included?', 'content' => '<p>Everything you need to get started.</p>' ],
					[ 'title' => 'Can I cancel anytime?', 'content' => '<p>Yes, cancel whenever you like.</p>' ],
				],
				'group' => 'content',
			],
			'first_open' => [
				'type'    => 'toggle',
				'label'   => __( 'Open first item', 'open-builder' ),
				'default' => true,
				'group'   => 'content',
			],
		];
	}

	public function render( array $content, string $inner_html, array $node ): string {
		$items = is_array( $content['items'] ?? null ) ? $content['items'] : [];
		$first_open = ! empty( $content['first_open'] );
		$base = $node['id'] ?? Security::generate_id();

		if ( empty( $items ) ) {
			return '';
		}

		$out = '<div class="ob-accordion" data-ob-accordion>';
		foreach ( array_values( $items ) as $i => $item ) {
			$open  = ( 0 === $i && $first_open );
			$title = esc_html( $item['title'] ?? '' );
			$body  = $item['content'] ?? ''; // wp_kses_post'd by sanitizer.
			$pid   = esc_attr( $base . '-p' . $i );
			$out  .= sprintf(
				'<div class="ob-accordion__item%1$s">'
				. '<button type="button" class="ob-accordion__header" aria-expanded="%2$s" aria-controls="%3$s">'
				. '<span class="ob-accordion__title">%4$s</span><span class="ob-accordion__indicator" aria-hidden="true"></span></button>'
				. '<div class="ob-accordion__panel" id="%3$s" role="region"%5$s><div class="ob-accordion__content">%6$s</div></div></div>',
				$open ? ' is-open' : '',
				$open ? 'true' : 'false',
				$pid,
				$title,
				$open ? '' : ' hidden',
				$body
			);
		}
		$out .= '</div>';
		return $out;
	}
}
