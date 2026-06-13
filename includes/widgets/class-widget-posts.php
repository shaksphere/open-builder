<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Posts loop widget. In an archive body template it renders the current query;
 * elsewhere it runs a configurable query (post type, count). Outputs an
 * accessible card grid — useful for archives, blog indexes and "recent posts".
 */
class Widget_Posts extends Abstract_Widget {

	public function type(): string { return 'posts'; }
	public function title(): string { return __( 'Posts', 'open-builder' ); }
	public function category(): string { return 'Dynamic'; }
	public function icon(): string { return 'columns'; }

	public function controls(): array {
		$types = [];
		foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $pt ) {
			$types[ $pt->name ] = $pt->labels->name;
		}
		return [
			'source' => [
				'type'    => 'select',
				'label'   => __( 'Source', 'open-builder' ),
				'choices' => [ 'current' => 'Current Query (archive)', 'custom' => 'Custom Query' ],
				'default' => 'current',
				'group'   => 'content',
			],
			'post_type' => [
				'type'    => 'select',
				'label'   => __( 'Post Type', 'open-builder' ),
				'choices' => $types ?: [ 'post' => 'Posts' ],
				'default' => 'post',
				'group'   => 'content',
			],
			'count' => [
				'type'    => 'number',
				'label'   => __( 'Number of Posts', 'open-builder' ),
				'default' => 6,
				'group'   => 'content',
			],
			'columns' => [
				'type'    => 'select',
				'label'   => __( 'Columns', 'open-builder' ),
				'choices' => [ '1' => '1', '2' => '2', '3' => '3', '4' => '4' ],
				'default' => '3',
				'group'   => 'content',
			],
			'show_excerpt' => [
				'type'    => 'toggle',
				'label'   => __( 'Show Excerpt', 'open-builder' ),
				'default' => true,
				'group'   => 'content',
			],
			'show_image' => [
				'type'    => 'toggle',
				'label'   => __( 'Show Featured Image', 'open-builder' ),
				'default' => true,
				'group'   => 'content',
			],
		];
	}

	public function render( array $content, string $inner_html, array $node ): string {
		$columns = (int) ( $content['columns'] ?? 3 );
		$columns = max( 1, min( 4, $columns ) );
		$show_excerpt = ! empty( $content['show_excerpt'] );
		$show_image   = ! empty( $content['show_image'] );

		if ( ( $content['source'] ?? 'current' ) === 'current' && ! Render_Context::is_editor() && ( is_archive() || is_home() || is_search() ) ) {
			$query = $GLOBALS['wp_query'];
		} else {
			$count = (int) ( $content['count'] ?? 6 );
			$count = max( 1, min( 50, $count ) );
			$query = new \WP_Query( [
				'post_type'           => sanitize_key( $content['post_type'] ?? 'post' ),
				'posts_per_page'      => $count,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			] );
		}

		if ( ! $query->have_posts() ) {
			return '<p class="ob-posts__empty">' . esc_html__( 'No posts found.', 'open-builder' ) . '</p>';
		}

		$out = sprintf( '<div class="ob-posts ob-posts--cols-%d">', $columns );
		while ( $query->have_posts() ) {
			$query->the_post();
			$thumb = ( $show_image && has_post_thumbnail() )
				? sprintf( '<a class="ob-posts__thumb" href="%s">%s</a>', esc_url( get_permalink() ), get_the_post_thumbnail( get_the_ID(), 'medium_large', [ 'loading' => 'lazy' ] ) )
				: '';
			$excerpt = $show_excerpt ? sprintf( '<div class="ob-posts__excerpt">%s</div>', wp_kses_post( get_the_excerpt() ) ) : '';
			$out .= sprintf(
				'<article class="ob-posts__item">%s<h3 class="ob-posts__title"><a href="%s">%s</a></h3>%s</article>',
				$thumb,
				esc_url( get_permalink() ),
				esc_html( get_the_title() ),
				$excerpt
			);
		}
		$out .= '</div>';
		wp_reset_postdata();

		return $out;
	}
}
