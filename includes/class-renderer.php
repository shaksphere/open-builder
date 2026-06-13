<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Renders a node tree to HTML. This is the single source of truth used by both
 * the front end and the editor canvas, guaranteeing WYSIWYG output.
 */
class Renderer {

	/** @var Widgets */
	private $widgets;

	/** @var Css_Generator */
	private $css;

	public function __construct( Widgets $widgets, Css_Generator $css ) {
		$this->widgets = $widgets;
		$this->css     = $css;
	}

	/**
	 * Render the full tree wrapped in the root container.
	 */
	public function render_tree( array $tree ): string {
		$html = '';
		foreach ( $tree as $node ) {
			$html .= $this->render_node( $node );
		}
		return '<div class="ob-root">' . $html . '</div>';
	}

	private function render_node( array $node ): string {
		$type = $node['type'] ?? '';
		$widget = $this->widgets->get( $type );
		if ( ! $widget ) {
			return '';
		}

		$id       = $node['id'] ?? Security::generate_id();
		$content  = $node['settings']['content'] ?? [];
		$advanced = $node['settings']['advanced'] ?? [];

		$inner_html = '';
		if ( $widget->is_container() && ! empty( $node['children'] ) ) {
			foreach ( $node['children'] as $child ) {
				$inner_html .= $this->render_node( $child );
			}
		}

		$rendered = $widget->render( (array) $content, $inner_html, $node );

		// Wrapper carries the scoping class + any user CSS id/classes.
		$classes = [ 'ob-node', 'ob-' . $type, 'ob-' . $id ];
		if ( ! empty( $advanced['css_classes'] ) ) {
			$classes[] = $advanced['css_classes'];
		}
		$class_attr = esc_attr( implode( ' ', $classes ) );

		$id_attr = '';
		if ( ! empty( $advanced['css_id'] ) ) {
			$id_attr = sprintf( ' id="%s"', esc_attr( $advanced['css_id'] ) );
		}

		$tag = $this->wrapper_tag( $type, $content );

		return sprintf(
			'<%1$s class="%2$s"%3$s data-ob-id="%5$s" data-ob-type="%6$s">%4$s</%1$s>',
			$tag,
			$class_attr,
			$id_attr,
			$rendered,
			esc_attr( $id ),
			esc_attr( $type )
		);
	}

	/** Choose the wrapper element for a node type. */
	private function wrapper_tag( string $type, array $content ): string {
		if ( 'section' === $type ) {
			$allowed = [ 'section', 'div', 'header', 'footer', 'main' ];
			$tag = $content['tag'] ?? 'section';
			return in_array( $tag, $allowed, true ) ? $tag : 'section';
		}
		return 'div';
	}

	/**
	 * Full document CSS for a tree: global vars + responsive base + compiled
	 * node styles. Used by both the front end (cached in meta) and the editor.
	 */
	public function compile_css( array $tree, Global_Styles $globals ): string {
		return $globals->to_css() . $this->css->responsive_base() . $this->css->compile( $tree );
	}
}
