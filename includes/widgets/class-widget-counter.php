<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

class Widget_Counter extends Abstract_Widget {

	public function type(): string { return 'counter'; }
	public function title(): string { return __( 'Counter', 'open-builder' ); }
	public function category(): string { return 'Marketing'; }
	public function icon(): string { return 'chart'; }

	public function controls(): array {
		return [
			'end' => [
				'type'    => 'number',
				'label'   => __( 'Target Number', 'open-builder' ),
				'default' => 1000,
				'group'   => 'content',
			],
			'start' => [
				'type'    => 'number',
				'label'   => __( 'Start Number', 'open-builder' ),
				'default' => 0,
				'group'   => 'content',
			],
			'duration' => [
				'type'    => 'number',
				'label'   => __( 'Duration (ms)', 'open-builder' ),
				'default' => 2000,
				'group'   => 'content',
			],
			'prefix' => [
				'type'    => 'text',
				'label'   => __( 'Prefix', 'open-builder' ),
				'default' => '',
				'group'   => 'content',
			],
			'suffix' => [
				'type'    => 'text',
				'label'   => __( 'Suffix', 'open-builder' ),
				'default' => '+',
				'group'   => 'content',
			],
			'label' => [
				'type'    => 'text',
				'label'   => __( 'Label', 'open-builder' ),
				'default' => 'Happy Customers',
				'group'   => 'content',
			],
		];
	}

	public function render( array $content, string $inner_html, array $node ): string {
		$end      = (float) ( $content['end'] ?? 0 );
		$start    = (float) ( $content['start'] ?? 0 );
		$duration = max( 0, (int) ( $content['duration'] ?? 2000 ) );
		$prefix   = esc_html( $content['prefix'] ?? '' );
		$suffix   = esc_html( $content['suffix'] ?? '' );
		$label    = esc_html( $content['label'] ?? '' );

		return sprintf(
			'<div class="ob-counter"><div class="ob-counter__number"><span class="ob-counter__prefix">%1$s</span>'
			. '<span class="ob-counter__value" data-ob-counter data-start="%2$s" data-end="%3$s" data-duration="%4$d">%5$s</span>'
			. '<span class="ob-counter__suffix">%6$s</span></div><div class="ob-counter__label">%7$s</div></div>',
			$prefix,
			esc_attr( (string) $start ),
			esc_attr( (string) $end ),
			$duration,
			esc_html( (string) $start ),
			$suffix,
			$label
		);
	}
}
