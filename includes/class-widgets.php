<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Widget registry. Each widget is an object implementing Widget_Interface and
 * provides: metadata, a control schema (consumed by the JS editor and by the
 * sanitizer), and a server-side render() that produces the inner HTML.
 *
 * The registry is held statically so the sanitizer can validate a tree without
 * a Plugin instance.
 */
class Widgets {

	/** @var array<string, Widget_Interface> */
	private static $registry = [];

	public function __construct() {
		$this->load();
	}

	private function load(): void {
		if ( ! empty( self::$registry ) ) {
			return;
		}

		require_once OPENB_DIR . 'includes/widgets/interface-widget.php';
		require_once OPENB_DIR . 'includes/widgets/abstract-widget.php';

		$dir = OPENB_DIR . 'includes/widgets/';
		$files = [
			'class-widget-section.php',
			'class-widget-columns.php',
			'class-widget-column.php',
			'class-widget-heading.php',
			'class-widget-text.php',
			'class-widget-button.php',
			'class-widget-image.php',
			'class-widget-spacer.php',
			'class-widget-divider.php',
			'class-widget-icon.php',
			'class-widget-html.php',
			'class-widget-form.php',
		];
		foreach ( $files as $file ) {
			require_once $dir . $file;
		}

		$classes = [
			Widget_Section::class,
			Widget_Columns::class,
			Widget_Column::class,
			Widget_Heading::class,
			Widget_Text::class,
			Widget_Button::class,
			Widget_Image::class,
			Widget_Spacer::class,
			Widget_Divider::class,
			Widget_Icon::class,
			Widget_Html::class,
			Widget_Form::class,
		];

		foreach ( $classes as $class ) {
			$widget = new $class();
			self::$registry[ $widget->type() ] = $widget;
		}

		/** Allow add-ons to register custom widgets. */
		do_action( 'open_builder_register_widgets', $this );
	}

	public function register( Widget_Interface $widget ): void {
		self::$registry[ $widget->type() ] = $widget;
	}

	public static function is_registered( string $type ): bool {
		return isset( self::$registry[ $type ] );
	}

	public function get( string $type ): ?Widget_Interface {
		return self::$registry[ $type ] ?? null;
	}

	/** @return array<string, Widget_Interface> */
	public function all(): array {
		return self::$registry;
	}

	/** Static accessor used by the sanitizer. */
	public static function get_controls( string $type ): array {
		return isset( self::$registry[ $type ] ) ? self::$registry[ $type ]->controls() : [];
	}

	/** Whether a widget type can contain children in the editor. */
	public static function is_container( string $type ): bool {
		return isset( self::$registry[ $type ] ) && self::$registry[ $type ]->is_container();
	}

	/**
	 * Schema sent to the JS editor: metadata + controls for every widget.
	 */
	public function schema_for_editor(): array {
		$out = [];
		foreach ( self::$registry as $type => $widget ) {
			$out[ $type ] = [
				'type'        => $type,
				'title'       => $widget->title(),
				'icon'        => $widget->icon(),
				'category'    => $widget->category(),
				'isContainer' => $widget->is_container(),
				'accepts'     => $widget->accepts(),
				'controls'    => $widget->controls(),
				'defaults'    => $widget->default_content(),
			];
		}
		return $out;
	}
}
