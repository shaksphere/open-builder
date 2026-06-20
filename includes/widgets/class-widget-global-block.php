<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Global / saved block: a reference to a reusable block (an openb_block post,
 * designed with the builder). The same block can be dropped on many pages;
 * editing the block updates it everywhere it appears. The reference itself is
 * not editable inline — you edit the block under Open Builder → Blocks.
 *
 * The referenced block's tree is rendered by the Renderer (which holds a
 * recursion guard); this widget just supplies the block id and wraps the output.
 */
class Widget_Global_Block extends Abstract_Widget {

	public function type(): string { return 'global_block'; }
	public function title(): string { return __( 'Global Block', 'open-builder' ); }
	public function category(): string { return 'Advanced'; }
	public function icon(): string { return 'tabs'; }
	public function is_container(): bool { return false; }

	public function controls(): array {
		$choices = [ '' => __( '— Select a block —', 'open-builder' ) ];
		$blocks  = get_posts( [
			'post_type'        => Post_Types::CPT_BLOCK,
			'post_status'      => 'publish',
			'numberposts'      => 200,
			'orderby'          => 'title',
			'order'            => 'ASC',
			'suppress_filters' => false,
		] );
		foreach ( $blocks as $b ) {
			$choices[ (string) $b->ID ] = get_the_title( $b ) ?: ( '#' . $b->ID );
		}

		return [
			'block_id' => [
				'type'    => 'select',
				'label'   => __( 'Saved block', 'open-builder' ),
				'choices' => $choices,
				'default' => '',
				'group'   => 'content',
				'hint'    => __( 'Create and edit blocks under Open Builder → Blocks. Changes apply everywhere the block is used.', 'open-builder' ),
			],
		];
	}

	public function render( array $content, string $inner_html, array $node ): string {
		if ( '' === trim( $inner_html ) ) {
			if ( Render_Context::is_editor() ) {
				return '<div class="ob-globalblock-empty" aria-hidden="true">'
					. esc_html__( 'Global Block — choose a saved block, or create one under Open Builder → Blocks.', 'open-builder' )
					. '</div>';
			}
			return '';
		}
		return $inner_html;
	}
}
