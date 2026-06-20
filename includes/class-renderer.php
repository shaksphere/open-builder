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

	/** HTML appended after a loop widget's grid (its pagination), if any. */
	private $loop_tail = '';

	/** Block ids currently rendering, to guard against reference cycles. */
	private $rendering_blocks = [];

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
		if ( 'global_block' === $type ) {
			// Reusable block: render the referenced block's tree in place.
			$inner_html = $this->render_global_block( (array) $content );
		} elseif ( $widget->is_loop() && ! Render_Context::is_editor() ) {
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

		// Append the loop's pagination (set by render_loop) just after its grid,
		// so the pager is a sibling of .ob-loop, not a grid cell.
		if ( $widget->is_loop() && '' !== $this->loop_tail ) {
			$rendered      .= $this->loop_tail;
			$this->loop_tail = '';
		}

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
		$this->loop_tail = '';
		$children        = $node['children'] ?? [];
		if ( empty( $children ) ) {
			return '';
		}

		$paginate = ! empty( $content['pagination'] );
		$current  = $paginate ? self::current_loop_page() : 1;

		$args = Widget_Query_Loop::build_query_args( $content );
		if ( $paginate ) {
			$args['no_found_rows'] = false; // we need max_num_pages
			$args['paged']         = $current;
			unset( $args['offset'] );       // offset + paged don't mix cleanly
		}

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

		$total = (int) $query->max_num_pages;

		wp_reset_postdata();
		if ( null !== $outer_post ) {
			$GLOBALS['post'] = $outer_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			setup_postdata( $outer_post );
		}

		// Stash pagination to be emitted after the loop widget's grid.
		if ( $paginate && $total > 1 ) {
			$this->loop_tail = $this->build_loop_pagination( $total, $current );
		}

		return $html;
	}

	/**
	 * Render a global block's tree in place. Guards against reference cycles
	 * (a block that includes itself, directly or transitively) and only renders
	 * published openb_block posts.
	 */
	private function render_global_block( array $content ): string {
		$block_id = (int) ( $content['block_id'] ?? 0 );
		if ( ! $block_id || isset( $this->rendering_blocks[ $block_id ] ) ) {
			return '';
		}
		if ( Post_Types::CPT_BLOCK !== get_post_type( $block_id ) || 'publish' !== get_post_status( $block_id ) ) {
			return '';
		}

		$this->rendering_blocks[ $block_id ] = true;
		$html = '';
		foreach ( Post_Types::get_tree( $block_id ) as $child ) {
			$html .= $this->render_node( $child );
		}
		unset( $this->rendering_blocks[ $block_id ] );

		return $html;
	}

	/** Current page for a query loop, from the ?ob_page=N query parameter. */
	private static function current_loop_page(): int {
		// Read-only navigation parameter; no nonce required.
		$page = isset( $_GET['ob_page'] ) ? absint( wp_unslash( $_GET['ob_page'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return max( 1, $page );
	}

	/**
	 * Build an accessible, windowed pager. Links preserve the current URL and set
	 * ?ob_page=N (page 1 drops the param). All query loops on a page share this
	 * parameter, so a page is best designed with a single paginated loop.
	 */
	private function build_loop_pagination( int $total, int $current ): string {
		$current = min( max( 1, $current ), $total );

		$url = static function ( int $n ): string {
			return esc_url( $n <= 1 ? remove_query_arg( 'ob_page' ) : add_query_arg( 'ob_page', $n ) );
		};
		$item = static function ( int $n ) use ( $url, $current ): string {
			if ( $n === $current ) {
				return sprintf( '<span class="ob-page-link is-current" aria-current="page">%d</span>', $n );
			}
			return sprintf( '<a class="ob-page-link" href="%s">%d</a>', $url( $n ), $n );
		};

		// Windowed page numbers: 1, …, current-1, current, current+1, …, last.
		$pages = [];
		foreach ( range( 1, $total ) as $n ) {
			if ( $n <= 2 || $n > $total - 2 || abs( $n - $current ) <= 1 ) {
				$pages[] = $n;
			}
		}
		$pages = array_values( array_unique( $pages ) );

		$out  = '';
		$prev = 0;
		foreach ( $pages as $n ) {
			if ( $prev && $n - $prev > 1 ) {
				$out .= '<span class="ob-page-ellipsis" aria-hidden="true">&hellip;</span>';
			}
			$out  .= $item( $n );
			$prev  = $n;
		}

		$nav  = '';
		if ( $current > 1 ) {
			$nav .= sprintf( '<a class="ob-page-link ob-page-prev" href="%s" rel="prev">%s</a>', $url( $current - 1 ), esc_html__( '‹ Prev', 'open-builder' ) );
		}
		$nav .= $out;
		if ( $current < $total ) {
			$nav .= sprintf( '<a class="ob-page-link ob-page-next" href="%s" rel="next">%s</a>', $url( $current + 1 ), esc_html__( 'Next ›', 'open-builder' ) );
		}

		return sprintf(
			'<nav class="ob-loop__pagination" role="navigation" aria-label="%s">%s</nav>',
			esc_attr__( 'Pagination', 'open-builder' ),
			$nav
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

	/**
	 * Per-node CSS only (no global :root vars or responsive base). Used for the
	 * cached per-page CSS file, since the global file carries the shared parts.
	 */
	public function compile_node_css( array $tree ): string {
		return $this->css->compile( $tree );
	}
}
