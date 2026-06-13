<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

class Widget_Nav_Menu extends Abstract_Widget {

	public function type(): string { return 'nav_menu'; }
	public function title(): string { return __( 'Nav Menu', 'open-builder' ); }
	public function category(): string { return 'Dynamic'; }
	public function icon(): string { return 'columns'; }

	public function controls(): array {
		$menus = [ '' => __( '— Select —', 'open-builder' ) ];
		if ( function_exists( 'wp_get_nav_menus' ) ) {
			foreach ( wp_get_nav_menus() as $menu ) {
				$menus[ (string) $menu->term_id ] = $menu->name;
			}
		}
		return [
			'menu' => [
				'type'    => 'select',
				'label'   => __( 'Menu', 'open-builder' ),
				'choices' => $menus,
				'default' => '',
				'group'   => 'content',
			],
			'layout' => [
				'type'    => 'select',
				'label'   => __( 'Layout', 'open-builder' ),
				'choices' => [ 'horizontal' => 'Horizontal', 'vertical' => 'Vertical' ],
				'default' => 'horizontal',
				'group'   => 'content',
			],
		];
	}

	public function render( array $content, string $inner_html, array $node ): string {
		$menu_id = absint( $content['menu'] ?? 0 );
		$layout  = ( $content['layout'] ?? 'horizontal' ) === 'vertical' ? 'vertical' : 'horizontal';

		if ( ! $menu_id ) {
			if ( Render_Context::is_editor() ) {
				return '<nav class="ob-nav ob-nav--' . $layout . ' ob-dynamic"><ul><li><a href="#">Menu Item</a></li><li><a href="#">Menu Item</a></li><li><a href="#">Menu Item</a></li></ul></nav>';
			}
			return '';
		}

		$menu = wp_nav_menu( [
			'menu'        => $menu_id,
			'container'   => 'nav',
			'container_class' => 'ob-nav ob-nav--' . $layout,
			'menu_class'  => 'ob-nav__list',
			'echo'        => false,
			'fallback_cb' => false,
			'depth'       => 2,
		] );

		return is_string( $menu ) ? $menu : '';
	}
}
