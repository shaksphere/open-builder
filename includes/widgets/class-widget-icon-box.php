<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

class Widget_Icon_Box extends Abstract_Widget {

	public function type(): string { return 'icon_box'; }
	public function title(): string { return __( 'Icon Box', 'open-builder' ); }
	public function category(): string { return 'Basic'; }
	public function icon(): string { return 'star'; }

	public function controls(): array {
		return [
			'icon' => [
				'type'    => 'icon',
				'label'   => __( 'Icon', 'open-builder' ),
				'choices' => array_keys( Widget_Icon::set() ),
				'default' => 'star',
				'group'   => 'content',
			],
			'title' => [
				'type'    => 'text',
				'label'   => __( 'Title', 'open-builder' ),
				'default' => 'Feature Title',
				'group'   => 'content',
			],
			'text' => [
				'type'    => 'richtext',
				'label'   => __( 'Description', 'open-builder' ),
				'default' => '<p>Describe this feature in a sentence or two so visitors understand the benefit.</p>',
				'group'   => 'content',
			],
			'link' => [
				'type'    => 'url',
				'label'   => __( 'Link', 'open-builder' ),
				'default' => '',
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
		$set   = Widget_Icon::set();
		$name  = (string) ( $content['icon'] ?? 'star' );
		$path  = $set[ $name ] ?? $set['star'];
		$color = Security::sanitize_color( (string) ( $content['color'] ?? '' ) ) ?: 'currentColor';
		$align = ( $content['align'] ?? 'center' ) === 'left' ? 'left' : 'center';
		$title = esc_html( $content['title'] ?? '' );
		$text  = $content['text'] ?? ''; // wp_kses_post'd by sanitizer.
		$link  = (string) ( $content['link'] ?? '' );

		$svg = sprintf(
			'<svg class="ob-iconbox__icon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="%s" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>',
			esc_attr( $color ),
			$path
		);

		$title_html = $title;
		if ( '' !== $link ) {
			$title_html = sprintf( '<a href="%s">%s</a>', esc_url( $link ), $title );
		}

		return sprintf(
			'<div class="ob-iconbox ob-iconbox--%1$s">%2$s<h3 class="ob-iconbox__title">%3$s</h3><div class="ob-iconbox__text">%4$s</div></div>',
			esc_attr( $align ),
			$svg,
			$title_html,
			$text
		);
	}
}
