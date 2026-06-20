<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Query Loop: a container whose children form a "card" template that is repeated
 * once per result of a configured query. Combined with dynamic data binding,
 * this lets a user design one card (Post Title, Featured Image, Excerpt, a
 * "Read more" button bound to the permalink…) and render it across a query.
 *
 * The repetition itself happens in the Renderer (so each card is rendered inside
 * the post's loop context); this widget supplies the query args and the grid
 * wrapper.
 */
class Widget_Query_Loop extends Abstract_Widget {

	public function type(): string { return 'query_loop'; }
	public function title(): string { return __( 'Query Loop', 'open-builder' ); }
	public function category(): string { return 'Dynamic'; }
	public function icon(): string { return 'columns'; }
	public function is_container(): bool { return true; }
	public function is_loop(): bool { return true; }

	public function controls(): array {
		$post_types = [];
		foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $pt ) {
			if ( 'attachment' === $pt->name ) {
				continue;
			}
			$post_types[ $pt->name ] = $pt->labels->singular_name;
		}
		if ( empty( $post_types ) ) {
			$post_types = [ 'post' => __( 'Post', 'open-builder' ) ];
		}

		$taxonomies = [ '' => __( '— None —', 'open-builder' ) ];
		foreach ( get_taxonomies( [ 'public' => true ], 'objects' ) as $tx ) {
			$taxonomies[ $tx->name ] = $tx->labels->singular_name;
		}

		return [
			'post_type' => [
				'type'    => 'select',
				'label'   => __( 'Post type', 'open-builder' ),
				'choices' => $post_types,
				'default' => array_key_exists( 'post', $post_types ) ? 'post' : (string) array_key_first( $post_types ),
				'group'   => 'content',
			],
			'per_page' => [
				'type'    => 'number',
				'label'   => __( 'Number of items', 'open-builder' ),
				'default' => 6,
				'group'   => 'content',
			],
			'columns' => [
				'type'    => 'select',
				'label'   => __( 'Columns', 'open-builder' ),
				'choices' => [ '1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6' ],
				'default' => '3',
				'group'   => 'content',
			],
			'orderby' => [
				'type'    => 'select',
				'label'   => __( 'Order by', 'open-builder' ),
				'choices' => [
					'date'       => __( 'Date', 'open-builder' ),
					'title'      => __( 'Title', 'open-builder' ),
					'menu_order' => __( 'Menu order', 'open-builder' ),
					'modified'   => __( 'Last modified', 'open-builder' ),
					'rand'       => __( 'Random', 'open-builder' ),
				],
				'default' => 'date',
				'group'   => 'content',
			],
			'order' => [
				'type'    => 'select',
				'label'   => __( 'Order', 'open-builder' ),
				'choices' => [ 'DESC' => __( 'Descending', 'open-builder' ), 'ASC' => __( 'Ascending', 'open-builder' ) ],
				'default' => 'DESC',
				'group'   => 'content',
			],
			'taxonomy' => [
				'type'    => 'select',
				'label'   => __( 'Filter by taxonomy', 'open-builder' ),
				'choices' => $taxonomies,
				'default' => '',
				'group'   => 'content',
			],
			'term' => [
				'type'    => 'text',
				'label'   => __( 'Term slug', 'open-builder' ),
				'default' => '',
				'group'   => 'content',
				'hint'    => __( 'Optional: limit to this term slug of the taxonomy above (e.g. "news").', 'open-builder' ),
			],
			'offset' => [
				'type'    => 'number',
				'label'   => __( 'Skip first N (offset)', 'open-builder' ),
				'default' => 0,
				'group'   => 'content',
			],
			'pagination' => [
				'type'    => 'toggle',
				'label'   => __( 'Show pagination', 'open-builder' ),
				'default' => false,
				'group'   => 'content',
				'hint'    => __( 'Adds page links below the loop. Use one paginated loop per page.', 'open-builder' ),
			],
		];
	}

	/** Build a sanitized WP_Query argument array from the widget's content. */
	public static function build_query_args( array $content ): array {
		$valid_types = array_keys( get_post_types( [ 'public' => true ] ) );
		$post_type   = isset( $content['post_type'] ) ? sanitize_key( (string) $content['post_type'] ) : 'post';
		if ( ! in_array( $post_type, $valid_types, true ) ) {
			$post_type = 'post';
		}

		$per_page = (int) ( $content['per_page'] ?? 6 );
		$per_page = max( 1, min( 50, $per_page ?: 6 ) );

		$orderby_allowed = [ 'date', 'title', 'menu_order', 'modified', 'rand' ];
		$orderby         = in_array( $content['orderby'] ?? 'date', $orderby_allowed, true ) ? (string) $content['orderby'] : 'date';
		$order           = ( strtoupper( (string) ( $content['order'] ?? 'DESC' ) ) === 'ASC' ) ? 'ASC' : 'DESC';
		$offset          = max( 0, (int) ( $content['offset'] ?? 0 ) );

		$args = [
			'post_type'           => $post_type,
			'posts_per_page'      => $per_page,
			'orderby'             => $orderby,
			'order'               => $order,
			'post_status'         => 'publish',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		];
		if ( $offset > 0 ) {
			$args['offset'] = $offset;
		}

		$taxonomy = isset( $content['taxonomy'] ) ? sanitize_key( (string) $content['taxonomy'] ) : '';
		$term     = isset( $content['term'] ) ? sanitize_title( (string) $content['term'] ) : '';
		if ( '' !== $taxonomy && '' !== $term && taxonomy_exists( $taxonomy ) ) {
			$args['tax_query'] = [
				[
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => $term,
				],
			];
		}

		return $args;
	}

	public function render( array $content, string $inner_html, array $node ): string {
		$cols = (int) ( $content['columns'] ?? 3 );
		$cols = max( 1, min( 6, $cols ?: 3 ) );

		// On the front end an empty result yields no cards.
		if ( '' === trim( $inner_html ) && ! Render_Context::is_editor() ) {
			return '';
		}

		return sprintf( '<div class="ob-loop ob-loop--cols-%d">%s</div>', $cols, $inner_html );
	}
}
