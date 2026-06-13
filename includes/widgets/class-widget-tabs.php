<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

class Widget_Tabs extends Abstract_Widget {

	public function type(): string { return 'tabs'; }
	public function title(): string { return __( 'Tabs', 'open-builder' ); }
	public function category(): string { return 'Interactive'; }
	public function icon(): string { return 'tabs'; }

	public function controls(): array {
		return [
			'items' => [
				'type'   => 'repeater',
				'label'  => __( 'Tabs', 'open-builder' ),
				'fields' => [
					'title'   => [ 'type' => 'text', 'label' => 'Tab Label', 'default' => 'Tab' ],
					'content' => [ 'type' => 'richtext', 'label' => 'Content', 'default' => '<p>Tab content.</p>' ],
				],
				'default' => [
					[ 'title' => 'Overview', 'content' => '<p>Overview content.</p>' ],
					[ 'title' => 'Details',  'content' => '<p>Details content.</p>' ],
					[ 'title' => 'Pricing',  'content' => '<p>Pricing content.</p>' ],
				],
				'group' => 'content',
			],
		];
	}

	public function render( array $content, string $inner_html, array $node ): string {
		$items = is_array( $content['items'] ?? null ) ? $content['items'] : [];
		$base  = $node['id'] ?? Security::generate_id();

		if ( empty( $items ) ) {
			return '';
		}

		$tabs   = '';
		$panels = '';
		foreach ( array_values( $items ) as $i => $item ) {
			$active = ( 0 === $i );
			$title  = esc_html( $item['title'] ?? '' );
			$body   = $item['content'] ?? '';
			$tid    = esc_attr( $base . '-t' . $i );
			$pid    = esc_attr( $base . '-tp' . $i );

			$tabs .= sprintf(
				'<button type="button" class="ob-tabs__tab%1$s" role="tab" id="%2$s" aria-controls="%3$s" aria-selected="%4$s" tabindex="%5$s">%6$s</button>',
				$active ? ' is-active' : '',
				$tid,
				$pid,
				$active ? 'true' : 'false',
				$active ? '0' : '-1',
				$title
			);
			$panels .= sprintf(
				'<div class="ob-tabs__panel%1$s" role="tabpanel" id="%2$s" aria-labelledby="%3$s"%4$s>%5$s</div>',
				$active ? ' is-active' : '',
				$pid,
				$tid,
				$active ? '' : ' hidden',
				$body
			);
		}

		return sprintf(
			'<div class="ob-tabs" data-ob-tabs><div class="ob-tabs__nav" role="tablist">%s</div><div class="ob-tabs__panels">%s</div></div>',
			$tabs,
			$panels
		);
	}
}
