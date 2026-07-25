<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Icon widget backed by a small, bundled, royalty-free SVG set (no external
 * icon font, no third-party licensing concerns).
 */
class Widget_Icon extends Abstract_Widget {

	public function type(): string { return 'icon'; }
	public function title(): string { return __( 'Icon', 'open-builder' ); }
	public function category(): string { return 'Basic'; }
	public function icon(): string { return 'star'; }

	/**
	 * Bundled icon set: original, hand-authored geometric SVG paths (stroke-based,
	 * 24×24 viewBox). No external icon font and no third-party icon-library path
	 * data, so there are no licensing concerns. Extend freely — every consumer
	 * (Icon, Icon Box, Icon List, the editor picker) reads from this one source.
	 */
	public static function set(): array {
		return [
			// Originals.
			'star'    => '<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>',
			'check'   => '<path d="M20 6L9 17l-5-5"/>',
			'arrow'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
			'heart'   => '<path d="M20.8 4.6a5.5 5.5 0 00-7.8 0L12 5.6l-1-1a5.5 5.5 0 00-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 000-7.8z"/>',
			'bolt'    => '<path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>',
			'mail'    => '<path d="M4 4h16v16H4z"/><path d="M22 6l-10 7L2 6"/>',
			'phone'   => '<path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.13.96.36 1.9.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0122 16.92z"/>',
			'globe'   => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 010 20 15.3 15.3 0 010-20z"/>',
			// UI / general.
			'plus'    => '<path d="M12 5v14M5 12h14"/>',
			'minus'   => '<path d="M5 12h14"/>',
			'close'   => '<path d="M6 6l12 12M18 6L6 18"/>',
			'search'  => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
			'settings'=> '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.9 4.9L7 7M17 17l2.1 2.1M2 12h3M19 12h3M4.9 19.1L7 17M17 7l2.1-2.1"/>',
			'edit'    => '<path d="M4 20h4L20 8l-4-4L4 16v4z"/><path d="M14 6l4 4"/>',
			'trash'   => '<path d="M4 6h16M9 6V4h6v2M6 6l1 14h10l1-14"/>',
			'link'    => '<path d="M9 15l6-6M10 6l1-1a4 4 0 016 6l-1 1M14 18l-1 1a4 4 0 01-6-6l1-1"/>',
			'external'=> '<path d="M14 4h6v6M20 4l-9 9M18 14v5H5V6h5"/>',
			'info'    => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
			'help'    => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 013.5-2c1.5.5 1.5 2 .5 3s-1.5 1-1.5 2.5M12 17h.01"/>',
			'alert'   => '<path d="M12 3l9 16H3l9-16z"/><path d="M12 10v4M12 17h.01"/>',
			'download'=> '<path d="M12 3v12M7 11l5 5 5-5M4 21h16"/>',
			'upload'  => '<path d="M12 21V9M7 13l5-5 5 5M4 3h16"/>',
			'play'    => '<circle cx="12" cy="12" r="9"/><path d="M10 8l6 4-6 4z"/>',
			// People / communication.
			'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
			'users'   => '<circle cx="9" cy="8" r="3.5"/><path d="M2 21c0-3.5 3-5 7-5s7 1.5 7 5"/><path d="M16 5.5a3.5 3.5 0 010 6.9M22 21c0-3-2-4.5-4.5-5"/>',
			'chat'    => '<path d="M4 5h16v11H9l-5 4V5z"/>',
			'headset' => '<path d="M4 14v-2a8 8 0 0116 0v2"/><rect x="3" y="14" width="4" height="6" rx="1.5"/><rect x="17" y="14" width="4" height="6" rx="1.5"/>',
			'smile'   => '<circle cx="12" cy="12" r="9"/><path d="M8 14s1.5 2 4 2 4-2 4-2M9 9h.01M15 9h.01"/>',
			// Place / time.
			'home'    => '<path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/>',
			'pin'     => '<path d="M12 21s-7-6-7-11a7 7 0 0114 0c0 5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
			'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
			'calendar'=> '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 9h18M8 3v4M16 3v4"/>',
			// Business / commerce.
			'briefcase'=> '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2M3 12h18"/>',
			'cart'    => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.5 13h11l2-9H6"/>',
			'tag'     => '<path d="M3 11V4h7l11 11-7 7L3 11z"/><circle cx="7.5" cy="7.5" r="1.3"/>',
			'dollar'  => '<circle cx="12" cy="12" r="9"/><path d="M12 7v10M14.5 9.5A2.5 2.5 0 0010 10c0 3 4.5 1.5 4.5 4a2.5 2.5 0 01-4.5 1"/>',
			'card'    => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 9h20M6 15h4"/>',
			'chart'   => '<path d="M4 20V10M10 20V4M16 20v-7M20 20H4"/>',
			'target'  => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.5"/>',
			'award'   => '<circle cx="12" cy="9" r="6"/><path d="M9 14l-1.5 7L12 18l4.5 3L15 14"/>',
			// Trust / security.
			'lock'    => '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/>',
			'shield'  => '<path d="M12 3l7 3v5c0 5-3.5 8-7 10-3.5-2-7-5-7-10V6l7-3z"/>',
			'thumbup' => '<path d="M7 11v9H4v-9zM7 11l4-8a2 2 0 012 2v4h5a2 2 0 012 2l-1.5 7a2 2 0 01-2 1.5H7"/>',
			// Media / misc.
			'image'   => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="M21 16l-5-5-9 9"/>',
			'camera'  => '<rect x="3" y="7" width="18" height="13" rx="2"/><circle cx="12" cy="13.5" r="3.5"/><path d="M8 7l2-3h4l2 3"/>',
			'wifi'    => '<path d="M2 8.5a15 15 0 0120 0M5 12a10 10 0 0114 0M8.5 15.5a5 5 0 017 0M12 19h.01"/>',
			'rocket'  => '<path d="M5 15c-2 2-2 5-2 5s3 0 5-2M9 15l-3-3c1-6 6-9 12-9 0 6-3 11-9 12l-3-3z"/><circle cx="14" cy="10" r="1.5"/>',
			'gift'    => '<rect x="3" y="8" width="18" height="4"/><path d="M5 12v9h14v-9M12 8v13M12 8S9 3 6.5 5 8 8 12 8zM12 8s3-5 5.5-3S16 8 12 8z"/>',
			'leaf'    => '<path d="M4 20c0-8 6-14 16-14 0 10-6 16-16 14z"/><path d="M4 20C8 14 12 11 18 9"/>',
			'sun'     => '<circle cx="12" cy="12" r="4.5"/><path d="M12 2v2M12 20v2M4 12H2M22 12h-2M5 5l1.5 1.5M17.5 17.5L19 19M19 5l-1.5 1.5M6.5 17.5L5 19"/>',
			'coffee'  => '<path d="M4 8h13v5a5 5 0 01-5 5H9a5 5 0 01-5-5V8z"/><path d="M17 9h2a2 2 0 010 4h-2"/>',
			'wrench'  => '<path d="M15 3a5 5 0 00-4 8L3 19l2 2 8-8a5 5 0 004-10l-3 3-2-2 3-3z"/>',
			'truck'   => '<rect x="2" y="7" width="12" height="9"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="6" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/>',
			'key'     => '<circle cx="8" cy="8" r="4"/><path d="M11 11l9 9M17 17l2-2M15 19l2-2"/>',
			'book'    => '<path d="M4 4h11a3 3 0 013 3v13H7a3 3 0 01-3-3V4z"/><path d="M4 17a3 3 0 013-3h11"/>',
			'graduation'=> '<path d="M2 8l10-4 10 4-10 4-10-4z"/><path d="M6 10v5c0 1.5 3 3 6 3s6-1.5 6-3v-5"/>',
		];
	}

	public function controls(): array {
		return [
			'icon' => [
				'type'    => 'icon',
				'label'   => __( 'Icon', 'open-builder' ),
				'choices' => array_keys( self::set() ),
				'default' => 'star',
				'group'   => 'content',
			],
			'custom_svg' => [
				'type'    => 'svg',
				'label'   => __( 'Custom SVG (optional)', 'open-builder' ),
				'default' => '',
				'hint'    => __( 'Paste SVG markup to use your own icon. Overrides the picker above. Scripts and links are stripped.', 'open-builder' ),
				'group'   => 'content',
			],
			'size' => [
				'type'    => 'text',
				'label'   => __( 'Size', 'open-builder' ),
				'default' => '40px',
				'group'   => 'content',
			],
			'color' => [
				'type'    => 'color',
				'label'   => __( 'Color', 'open-builder' ),
				'default' => 'var(--ob-color-primary)',
				'group'   => 'content',
			],
			'link' => [
				'type'    => 'url',
				'label'   => __( 'Link', 'open-builder' ),
				'default' => '',
				'group'   => 'content',
			],
		];
	}

	public function render( array $content, string $inner_html, array $node ): string {
		$set  = self::set();
		$name = (string) $this->val( $content, 'icon', 'star' );
		$size = Security::sanitize_css_value( (string) $this->val( $content, 'size', '40px' ) ) ?: '40px';
		$color = Security::sanitize_color( (string) $this->val( $content, 'color', '' ) ) ?: 'currentColor';

		// A custom SVG (sanitized on the way in) wins over the bundled picker.
		$custom = Security::sanitize_svg_inner( (string) $this->val( $content, 'custom_svg', '' ) );
		$path   = '' !== $custom ? $custom : ( $set[ $name ] ?? $set['star'] );

		$svg = sprintf(
			'<svg class="ob-icon__svg" width="%1$s" height="%1$s" viewBox="0 0 24 24" fill="none" stroke="%2$s" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%3$s</svg>',
			esc_attr( $size ),
			esc_attr( $color ),
			$path // Bundled set is trusted; custom is wp_kses-sanitized above.
		);

		$link = (string) $this->val( $content, 'link', '' );
		if ( '' !== $link ) {
			$svg = sprintf( '<a href="%s">%s</a>', esc_url( $link ), $svg );
		}
		return $svg;
	}
}
