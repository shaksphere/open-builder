<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Contract every widget fulfils. Keeps the registry, sanitizer, renderer and
 * JS editor in agreement about a widget's identity, controls and output.
 */
interface Widget_Interface {

	/** Machine type, e.g. "heading". Must be unique and a valid sanitize_key(). */
	public function type(): string;

	/** Human label shown in the editor panel. */
	public function title(): string;

	/** Inline SVG or dashicon name shown next to the title. */
	public function icon(): string;

	/** Grouping in the editor widget panel, e.g. "Layout", "Basic". */
	public function category(): string;

	/** Whether the widget can hold child nodes. */
	public function is_container(): bool;

	/** Whether the widget repeats its children over a query (a Query Loop). */
	public function is_loop(): bool;

	/** Child widget types this container accepts ([] = any, used by containers). */
	public function accepts(): array;

	/**
	 * Control schema keyed by content field. Each entry: type, label, default,
	 * and type-specific options (e.g. choices). Consumed by JS + sanitizer.
	 */
	public function controls(): array;

	/** Default content values for a freshly dropped widget. */
	public function default_content(): array;

	/**
	 * Render the widget's inner HTML. The renderer supplies the merged content
	 * values and the wrapper element/attributes are handled by the renderer.
	 *
	 * @param array  $content   Sanitized content values.
	 * @param string $inner_html Pre-rendered children (for containers).
	 * @param array  $node      Full node (id, settings) for advanced needs.
	 */
	public function render( array $content, string $inner_html, array $node ): string;
}
