<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Responsive video widget. Supports YouTube, Vimeo and self-hosted files.
 * Uses a click-to-play facade for embeds to avoid loading third-party players
 * (and their cookies) until the visitor interacts — better speed and privacy.
 */
class Widget_Video extends Abstract_Widget {

	public function type(): string { return 'video'; }
	public function title(): string { return __( 'Video', 'open-builder' ); }
	public function category(): string { return 'Media'; }
	public function icon(): string { return 'play'; }

	public function controls(): array {
		return [
			'source' => [
				'type'    => 'select',
				'label'   => __( 'Source', 'open-builder' ),
				'choices' => [ 'youtube' => 'YouTube', 'vimeo' => 'Vimeo', 'file' => 'Self-hosted (MP4)' ],
				'default' => 'youtube',
				'group'   => 'content',
			],
			'url' => [
				'type'    => 'url',
				'label'   => __( 'Video URL', 'open-builder' ),
				'default' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
				'group'   => 'content',
			],
			'ratio' => [
				'type'    => 'select',
				'label'   => __( 'Aspect Ratio', 'open-builder' ),
				'choices' => [ '16-9' => '16:9', '4-3' => '4:3', '1-1' => '1:1', '21-9' => '21:9' ],
				'default' => '16-9',
				'group'   => 'content',
			],
			'autoplay' => [
				'type'    => 'toggle',
				'label'   => __( 'Autoplay (muted)', 'open-builder' ),
				'default' => false,
				'group'   => 'content',
			],
		];
	}

	public function render( array $content, string $inner_html, array $node ): string {
		$source = in_array( $content['source'] ?? 'youtube', [ 'youtube', 'vimeo', 'file' ], true ) ? $content['source'] : 'youtube';
		$url    = (string) ( $content['url'] ?? '' );
		$ratio  = in_array( $content['ratio'] ?? '16-9', [ '16-9', '4-3', '1-1', '21-9' ], true ) ? $content['ratio'] : '16-9';
		$auto   = ! empty( $content['autoplay'] );

		$wrap_open  = sprintf( '<div class="ob-video ob-video--%s">', esc_attr( $ratio ) );
		$wrap_close = '</div>';

		if ( 'file' === $source ) {
			if ( '' === $url ) {
				return $wrap_open . '<div class="ob-video__placeholder">' . esc_html__( 'Add a video URL', 'open-builder' ) . '</div>' . $wrap_close;
			}
			$attrs = 'controls playsinline';
			if ( $auto ) {
				$attrs .= ' autoplay muted loop';
			}
			return $wrap_open . sprintf( '<video class="ob-video__player" %s src="%s"></video>', $attrs, esc_url( $url ) ) . $wrap_close;
		}

		$embed = $this->embed_url( $source, $url, $auto );
		if ( '' === $embed ) {
			return $wrap_open . '<div class="ob-video__placeholder">' . esc_html__( 'Enter a valid video URL', 'open-builder' ) . '</div>' . $wrap_close;
		}

		// Facade: store the embed URL; frontend.js swaps in the iframe on click.
		return $wrap_open . sprintf(
			'<button type="button" class="ob-video__facade" data-ob-embed="%s" aria-label="%s"><span class="ob-video__play"></span></button>',
			esc_url( $embed ),
			esc_attr__( 'Play video', 'open-builder' )
		) . $wrap_close;
	}

	private function embed_url( string $source, string $url, bool $auto ): string {
		if ( 'youtube' === $source ) {
			if ( ! preg_match( '~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([A-Za-z0-9_-]{6,})~', $url, $m ) ) {
				return '';
			}
			$q = $auto ? '?autoplay=1&mute=1&rel=0' : '?rel=0';
			return 'https://www.youtube-nocookie.com/embed/' . $m[1] . $q;
		}
		if ( 'vimeo' === $source ) {
			if ( ! preg_match( '~vimeo\.com/(?:video/)?(\d+)~', $url, $m ) ) {
				return '';
			}
			$q = $auto ? '?autoplay=1&muted=1' : '';
			return 'https://player.vimeo.com/video/' . $m[1] . $q;
		}
		return '';
	}
}
