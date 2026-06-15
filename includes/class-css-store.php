<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Writes compiled CSS to physical files under uploads/open-builder/ so the
 * browser can cache them, instead of re-parsing an inline <style> on every
 * request. Two kinds of file:
 *
 *   - global.css       : :root brand variables + responsive base (site-wide).
 *   - page-{id}.css     : the per-node compiled CSS for one post.
 *
 * Files are generated on save and lazily rebuilt on the front end if missing.
 * If the filesystem isn't writable we transparently fall back to inline CSS,
 * so the plugin never breaks because of file permissions.
 */
class Css_Store {

	const SUBDIR = 'open-builder';

	/** @var Renderer */
	private $renderer;

	/** @var Global_Styles */
	private $globals;

	public function __construct( Renderer $renderer, Global_Styles $globals ) {
		$this->renderer = $renderer;
		$this->globals  = $globals;
	}

	/** Whether file output is disabled (debug or explicit opt-out). */
	public static function disabled(): bool {
		return defined( 'OPENB_INLINE_CSS' ) && OPENB_INLINE_CSS;
	}

	private function base_dir(): string {
		$up = wp_upload_dir();
		return trailingslashit( $up['basedir'] ) . self::SUBDIR;
	}

	private function base_url(): string {
		$up = wp_upload_dir();
		$url = trailingslashit( $up['baseurl'] ) . self::SUBDIR;
		// Keep the scheme consistent with the request (avoids mixed-content).
		return set_url_scheme( $url );
	}

	private function ensure_dir(): bool {
		$dir = $this->base_dir();
		if ( is_dir( $dir ) ) {
			return wp_is_writable( $dir );
		}
		return wp_mkdir_p( $dir ) && wp_is_writable( $dir );
	}

	/** Write a file atomically-ish; returns true on success. */
	private function put( string $filename, string $contents ): bool {
		if ( ! $this->ensure_dir() ) {
			return false;
		}
		$path = trailingslashit( $this->base_dir() ) . $filename;
		$tmp  = $path . '.' . wp_rand() . '.tmp';
		if ( false === @file_put_contents( $tmp, $contents ) ) {
			return false;
		}
		if ( ! @rename( $tmp, $path ) ) {
			@unlink( $tmp );
			return false;
		}
		return true;
	}

	/* ----------------------------- Global ------------------------------ */

	private function global_filename(): string {
		return 'global.css';
	}

	/** CSS for the site-wide global file. */
	public function global_css(): string {
		return $this->globals->to_css() . Plugin::instance()->css->responsive_base();
	}

	/** (Re)build the global file. Called when brand settings change. */
	public function rebuild_global(): bool {
		return $this->put( $this->global_filename(), $this->global_css() );
	}

	/** URL of the global file, building it lazily if needed. Empty on failure. */
	public function global_url(): string {
		if ( self::disabled() ) {
			return '';
		}
		$path = trailingslashit( $this->base_dir() ) . $this->global_filename();
		if ( ! file_exists( $path ) && ! $this->rebuild_global() ) {
			return '';
		}
		return trailingslashit( $this->base_url() ) . $this->global_filename() . '?ver=' . ( @filemtime( $path ) ?: OPENB_VERSION );
	}

	/* ------------------------------ Page ------------------------------- */

	private function page_filename( int $post_id ): string {
		return 'page-' . $post_id . '.css';
	}

	/** Write the per-node CSS file for a post (called on save). */
	public function write_page( int $post_id, string $node_css ): bool {
		if ( '' === trim( $node_css ) ) {
			$this->delete_page( $post_id );
			return true;
		}
		return $this->put( $this->page_filename( $post_id ), $node_css );
	}

	public function delete_page( int $post_id ): void {
		$path = trailingslashit( $this->base_dir() ) . $this->page_filename( $post_id );
		if ( file_exists( $path ) ) {
			@unlink( $path );
		}
	}

	/**
	 * URL of a post's CSS file, building it lazily from the saved tree if the
	 * file is missing. Returns '' if there's nothing to output or it can't be
	 * written (caller should then inline as a fallback).
	 */
	public function page_url( int $post_id ): string {
		if ( self::disabled() ) {
			return '';
		}
		$path = trailingslashit( $this->base_dir() ) . $this->page_filename( $post_id );
		if ( ! file_exists( $path ) ) {
			$tree = Post_Types::get_tree( $post_id );
			$css  = $this->renderer->compile_node_css( $tree );
			if ( '' === trim( $css ) || ! $this->write_page( $post_id, $css ) ) {
				return '';
			}
		}
		return trailingslashit( $this->base_url() ) . $this->page_filename( $post_id ) . '?ver=' . ( @filemtime( $path ) ?: OPENB_VERSION );
	}

	/** Remove the whole cache directory (used on uninstall / global reset). */
	public function flush_all(): void {
		$dir = $this->base_dir();
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( (array) glob( trailingslashit( $dir ) . '*.css' ) as $file ) {
			@unlink( $file );
		}
	}
}
