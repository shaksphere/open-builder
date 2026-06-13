<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

class Widget_Progress extends Abstract_Widget {

	public function type(): string { return 'progress'; }
	public function title(): string { return __( 'Progress Bar', 'open-builder' ); }
	public function category(): string { return 'Marketing'; }
	public function icon(): string { return 'chart'; }

	public function controls(): array {
		return [
			'label' => [
				'type'    => 'text',
				'label'   => __( 'Label', 'open-builder' ),
				'default' => 'Design',
				'group'   => 'content',
			],
			'percent' => [
				'type'    => 'number',
				'label'   => __( 'Percentage', 'open-builder' ),
				'default' => 80,
				'group'   => 'content',
			],
			'show_percent' => [
				'type'    => 'toggle',
				'label'   => __( 'Show percentage', 'open-builder' ),
				'default' => true,
				'group'   => 'content',
			],
			'color' => [
				'type'    => 'color',
				'label'   => __( 'Bar Color', 'open-builder' ),
				'default' => 'var(--ob-color-primary)',
				'group'   => 'content',
			],
		];
	}

	public function render( array $content, string $inner_html, array $node ): string {
		$label   = esc_html( $content['label'] ?? '' );
		$percent = (float) ( $content['percent'] ?? 0 );
		$percent = max( 0, min( 100, $percent ) );
		$show    = ! empty( $content['show_percent'] );
		$color   = Security::sanitize_color( (string) ( $content['color'] ?? '' ) ) ?: 'var(--ob-color-primary,#2563eb)';

		$pct_label = $show ? sprintf( '<span class="ob-progress__percent">%s%%</span>', esc_html( (string) $percent ) ) : '';

		return sprintf(
			'<div class="ob-progress"><div class="ob-progress__head"><span class="ob-progress__label">%1$s</span>%2$s</div>'
			. '<div class="ob-progress__track" role="progressbar" aria-valuenow="%3$s" aria-valuemin="0" aria-valuemax="100">'
			. '<div class="ob-progress__fill" data-ob-progress data-percent="%3$s" style="width:0%%;background:%4$s"></div></div></div>',
			$label,
			$pct_label,
			esc_attr( (string) $percent ),
			esc_attr( $color )
		);
	}
}
