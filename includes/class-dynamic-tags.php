<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Dynamic data binding. A node may bind any text/url/image content field to a
 * dynamic source (post title/excerpt/featured image, post meta, ACF, site
 * info…) via a per-field map stored at settings.dynamic[fieldKey] =
 * { source, key, fallback }. The Renderer resolves these against the current
 * post just before a widget renders, so the binding layer is widget-agnostic.
 *
 * On the editor canvas (MODE_EDITOR) bound fields show a readable placeholder
 * like "[Post Title]" instead of live data, since there is no real loop there.
 */
class Dynamic_Tags {

	/**
	 * Source definitions. `text` = usable by text/url controls; `image` = usable
	 * by image controls; `needs_key` = requires a meta/field key.
	 *
	 * @return array<string,array{label:string,text:bool,image:bool,needs_key:bool}>
	 */
	public static function sources(): array {
		$sources = [
			'post_title'     => [ 'label' => __( 'Post Title', 'open-builder' ),        'text' => true,  'image' => false, 'needs_key' => false ],
			'post_excerpt'   => [ 'label' => __( 'Post Excerpt', 'open-builder' ),      'text' => true,  'image' => false, 'needs_key' => false ],
			'post_content'   => [ 'label' => __( 'Post Content', 'open-builder' ),      'text' => true,  'image' => false, 'needs_key' => false ],
			'post_date'      => [ 'label' => __( 'Post Date', 'open-builder' ),         'text' => true,  'image' => false, 'needs_key' => false ],
			'post_permalink' => [ 'label' => __( 'Post URL', 'open-builder' ),          'text' => true,  'image' => false, 'needs_key' => false ],
			'featured_image' => [ 'label' => __( 'Featured Image', 'open-builder' ),    'text' => true,  'image' => true,  'needs_key' => false ],
			'author_name'    => [ 'label' => __( 'Author Name', 'open-builder' ),       'text' => true,  'image' => false, 'needs_key' => false ],
			'author_url'     => [ 'label' => __( 'Author URL', 'open-builder' ),        'text' => true,  'image' => false, 'needs_key' => false ],
			'site_title'     => [ 'label' => __( 'Site Title', 'open-builder' ),        'text' => true,  'image' => false, 'needs_key' => false ],
			'site_tagline'   => [ 'label' => __( 'Site Tagline', 'open-builder' ),      'text' => true,  'image' => false, 'needs_key' => false ],
			'site_url'       => [ 'label' => __( 'Site URL', 'open-builder' ),          'text' => true,  'image' => false, 'needs_key' => false ],
			'current_year'   => [ 'label' => __( 'Current Year', 'open-builder' ),      'text' => true,  'image' => false, 'needs_key' => false ],
			'post_meta'      => [ 'label' => __( 'Custom Field (post meta)', 'open-builder' ), 'text' => true, 'image' => true, 'needs_key' => true ],
		];
		if ( function_exists( 'get_field' ) ) {
			$sources['acf'] = [ 'label' => __( 'ACF Field', 'open-builder' ), 'text' => true, 'image' => true, 'needs_key' => true ];
		}
		return $sources;
	}

	/** Valid source keys (used by the sanitizer). */
	public static function source_keys(): array {
		return array_keys( self::sources() );
	}

	/** Editor payload: list form with kinds, for the dynamic control UI. */
	public static function sources_for_editor(): array {
		$out = [];
		foreach ( self::sources() as $key => $def ) {
			$out[] = [
				'value'    => $key,
				'label'    => $def['label'],
				'text'     => $def['text'],
				'image'    => $def['image'],
				'needsKey' => $def['needs_key'],
			];
		}
		return $out;
	}

	/**
	 * Resolve dynamic bindings for one node's content, returning a new content
	 * array with bound fields overridden. Field kinds come from the widget's
	 * control schema (image controls resolve to {id,url}; others to a string).
	 */
	public static function apply( string $widget_type, array $content, array $dynamic ): array {
		if ( empty( $dynamic ) ) {
			return $content;
		}
		$controls = Widgets::get_controls( $widget_type );
		$post_id  = (int) get_the_ID();

		foreach ( $dynamic as $field => $binding ) {
			if ( ! is_array( $binding ) || empty( $binding['source'] ) || ! isset( $controls[ $field ] ) ) {
				continue;
			}
			$is_image = 'image' === ( $controls[ $field ]['type'] ?? 'text' );

			if ( Render_Context::is_editor() ) {
				$content[ $field ] = $is_image ? [ 'id' => 0, 'url' => '' ] : self::placeholder( $binding );
				continue;
			}

			$content[ $field ] = $is_image
				? self::resolve_image( $binding, $post_id )
				: self::resolve_text( $binding, $post_id );
		}
		return $content;
	}

	/** Human-readable placeholder shown on the editor canvas. */
	public static function placeholder( array $binding ): string {
		$sources = self::sources();
		$label   = $sources[ $binding['source'] ]['label'] ?? $binding['source'];
		if ( ! empty( $binding['key'] ) ) {
			$label .= ': ' . $binding['key'];
		}
		return '[' . $label . ']';
	}

	/** Resolve a binding to a string for text/url fields. */
	public static function resolve_text( array $binding, int $post_id ): string {
		$source = (string) ( $binding['source'] ?? '' );
		$key    = (string) ( $binding['key'] ?? '' );
		$val    = '';

		switch ( $source ) {
			case 'post_title':
				$val = $post_id ? get_the_title( $post_id ) : '';
				break;
			case 'post_excerpt':
				$val = $post_id ? get_the_excerpt( $post_id ) : '';
				break;
			case 'post_content':
				$val = $post_id ? apply_filters( 'the_content', get_post_field( 'post_content', $post_id ) ) : '';
				break;
			case 'post_date':
				$val = $post_id ? get_the_date( '', $post_id ) : '';
				break;
			case 'post_permalink':
				$val = $post_id ? (string) get_permalink( $post_id ) : '';
				break;
			case 'featured_image':
				$val = $post_id ? (string) get_the_post_thumbnail_url( $post_id, 'large' ) : '';
				break;
			case 'author_name':
				$val = $post_id ? get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $post_id ) ) : '';
				break;
			case 'author_url':
				$val = $post_id ? (string) get_author_posts_url( (int) get_post_field( 'post_author', $post_id ) ) : '';
				break;
			case 'site_title':
				$val = get_bloginfo( 'name' );
				break;
			case 'site_tagline':
				$val = get_bloginfo( 'description' );
				break;
			case 'site_url':
				$val = home_url( '/' );
				break;
			case 'current_year':
				$val = date_i18n( 'Y' );
				break;
			case 'post_meta':
				$val = ( $post_id && '' !== $key ) ? get_post_meta( $post_id, $key, true ) : '';
				break;
			case 'acf':
				$val = ( $post_id && '' !== $key && function_exists( 'get_field' ) ) ? get_field( $key, $post_id ) : '';
				break;
		}

		if ( ! is_scalar( $val ) ) {
			$val = is_array( $val ) ? implode( ', ', array_filter( $val, 'is_scalar' ) ) : '';
		}
		$val = (string) $val;

		if ( '' === trim( $val ) && isset( $binding['fallback'] ) && '' !== $binding['fallback'] ) {
			$val = (string) $binding['fallback'];
		}
		return $val;
	}

	/** Resolve a binding to an {id,url} pair for image fields. */
	public static function resolve_image( array $binding, int $post_id ): array {
		$source = (string) ( $binding['source'] ?? '' );
		$key    = (string) ( $binding['key'] ?? '' );

		if ( 'featured_image' === $source && $post_id ) {
			$id = (int) get_post_thumbnail_id( $post_id );
			return [ 'id' => $id, 'url' => $id ? (string) wp_get_attachment_image_url( $id, 'large' ) : '' ];
		}

		$raw = '';
		if ( 'acf' === $source && '' !== $key && function_exists( 'get_field' ) ) {
			$raw = $post_id ? get_field( $key, $post_id ) : '';
		} elseif ( 'post_meta' === $source && '' !== $key && $post_id ) {
			$raw = get_post_meta( $post_id, $key, true );
		}

		if ( is_array( $raw ) ) {
			return [ 'id' => (int) ( $raw['id'] ?? 0 ), 'url' => (string) ( $raw['url'] ?? '' ) ];
		}
		if ( is_numeric( $raw ) ) {
			return [ 'id' => (int) $raw, 'url' => (string) wp_get_attachment_image_url( (int) $raw, 'large' ) ];
		}
		if ( is_string( $raw ) && '' !== $raw ) {
			return [ 'id' => 0, 'url' => $raw ];
		}
		return [ 'id' => 0, 'url' => '' ];
	}
}
