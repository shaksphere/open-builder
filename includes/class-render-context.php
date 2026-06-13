<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Lightweight ambient context for a render pass. Dynamic widgets (Post Title,
 * Post Content, Posts loop) read this to decide whether to output real data
 * (front end, inside the loop) or editor placeholders (builder canvas).
 */
class Render_Context {

	const MODE_FRONTEND = 'frontend';
	const MODE_EDITOR   = 'editor';

	/** @var string */
	public static $mode = self::MODE_FRONTEND;

	public static function is_editor(): bool {
		return self::$mode === self::MODE_EDITOR;
	}

	public static function set( string $mode ): void {
		self::$mode = $mode;
	}
}
