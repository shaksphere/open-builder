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

		// Dynamic data binding: override bound content fields from the current
		// post (live on the front end; readable placeholders in the editor).
		$dynamic = $node['settings']['dynamic'] ?? [];
		if ( ! empty( $dynamic ) && is_array( $dynamic ) ) {
			$content = Dynamic_Tags::apply( $type, (array) $content, $dynamic );
		}

		$inner_html = '';
		if ( $widget->is_loop() && ! Render_Context::is_editor() ) {
			// Query Loop: repeat the card template (children) once per query result,
			// setting each post as the current post so dynamic bindings resolve.
			$inner_html = $this->render_loop( $node, (array) $content );
		} elseif ( $widget->is_container() && ! empty( $node['children'] ) ) {
			foreach ( $node['children'] as $child ) {
				$inner_html .= $this->render_node( $child );
			}
		}

		// In the editor, give empty containers a visible, droppable placeholder
		// so they don't collapse to 0px and become impossible drag targets.
		if ( '' === $inner_html && $widget->is_container() && Render_Context::is_editor() ) {
			$inner_html = sprintf(
				'<div class="ob-empty-dropzone" aria-hidden="true">%s</div>',
				esc_html__( 'Drop a widget here', 'open-builder' )
			);
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

	/**
	 * Render a Query Loop node's children once per post of its configured query.
	 * Each iteration sets the global post so dynamic bindings inside the card
	 * resolve to that post. Uses a secondary WP_Query and restores afterwards.
	 */
	private function render_loop( array $node, array $content ): string {
		$children = $node['children'] ?? [];
		if ( empty( $children ) ) {
			return '';
		}

		$args  = Widget_Query_Loop::build_query_args( $content );
		$query = new \WP_Query( $args );

		if ( ! $query->have_posts() ) {
			wp_reset_postdata();
			return '';
		}

		// Preserve the outer current post so nested loops don't corrupt context.
		$outer_post = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;

		$html = '';
		while ( $query->have_posts() ) {
			$query->the_post();
			foreach ( $children as $child ) {
				$html .= $this->render_node( $child );
			}
		}

		wp_reset_postdata();
		if ( null !== $outer_post ) {
			$GLOBALS['post'] = $outer_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			setup_postdata( $outer_post );
		}

		return $html;
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

	/**
	 * Per-node CSS only (no global :root vars or responsive base). Used for the
	 * cached per-page CSS file, since the global file carries the shared parts.
	 */
	public function compile_node_css( array $tree ): string {
		return $this->css->compile( $tree );
	}
}
