<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

class Widget_List extends Abstract_Widget {

	public function type(): string { return 'icon_list'; }
	public function title(): string { return __( 'Icon List', 'open-builder' ); }
	public function category(): string { return 'Basic'; }
	public function icon(): string { return 'check'; }

	public function controls(): array {
		return [
			'items' => [
				'type'   => 'repeater',
				'label'  => __( 'Items', 'open-builder' ),
				'fields' => [
					'text' => [ 'type' => 'text', 'label' => 'Text', 'default' => 'List item' ],
					'icon' => [ 'type' => 'icon', 'label' => 'Icon', 'choices' => array_keys( Widget_Icon::set() ), 'default' => 'check' ],
					'link' => [ 'type' => 'url', 'label' => 'Link', 'default' => '' ],
				],
				'default' => [
					[ 'text' => 'First benefit',  'icon' => 'check', 'link' => '' ],
					[ 'text' => 'Second benefit', 'icon' => 'check', 'link' => '' ],
					[ 'text' => 'Third benefit',  'icon' => 'check', 'link' => '' ],
				],
				'group' => 'content',
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
		$items = is_array( $content['items'] ?? null ) ? $content['items'] : [];
		$color = Security::sanitize_color( (string) ( $content['color'] ?? '' ) ) ?: 'currentColor';
		$set   = Widget_Icon::set();

		if ( empty( $items ) ) {
			return '';
		}

		$rows = '';
		foreach ( $items as $item ) {
			$name = (string) ( $item['icon'] ?? 'check' );
			$path = $set[ $name ] ?? $set['check'];
			$text = esc_html( $item['text'] ?? '' );
			$link = (string) ( $item['link'] ?? '' );
			if ( '' !== $link ) {
				$text = sprintf( '<a href="%s">%s</a>', esc_url( $link ), $text );
			}
			$svg = sprintf(
				'<svg class="ob-list__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="%s" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>',
				esc_attr( $color ),
				$path
			);
			$rows .= sprintf( '<li class="ob-list__item">%s<span class="ob-list__text">%s</span></li>', $svg, $text );
		}

		return '<ul class="ob-list">' . $rows . '</ul>';
	}
}
