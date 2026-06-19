<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin bootstrap. Loads modules and wires up WordPress hooks.
 */
final class Plugin {

	/** @var Plugin|null */
	private static $instance = null;

	/** @var Widgets */
	public $widgets;

	/** @var Renderer */
	public $renderer;

	/** @var Css_Generator */
	public $css;

	/** @var Rest */
	public $rest;

	/** @var Forms */
	public $forms;

	/** @var Global_Styles */
	public $global_styles;

	/** @var Theme_Builder */
	public $theme_builder;

	/** @var Popups */
	public $popups;

	/** @var Css_Store */
	public $css_store;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load_files();
	}

	private function load_files(): void {
		$includes = OPENB_DIR . 'includes/';
		require_once $includes . 'class-security.php';
		require_once $includes . 'class-post-types.php';
		require_once $includes . 'class-render-context.php';
		require_once $includes . 'class-global-styles.php';
		require_once $includes . 'class-widgets.php';
		require_once $includes . 'class-dynamic-tags.php';
		require_once $includes . 'class-css-generator.php';
		require_once $includes . 'class-renderer.php';
		require_once $includes . 'class-css-store.php';
		require_once $includes . 'class-forms.php';
		require_once $includes . 'class-rest.php';
		require_once $includes . 'class-theme-builder.php';
		require_once $includes . 'class-popups.php';
		require_once $includes . 'class-editor.php';
		require_once $includes . 'class-frontend.php';
		require_once $includes . 'class-admin.php';
	}

	public function boot(): void {
		load_plugin_textdomain( 'open-builder', false, dirname( plugin_basename( OPENB_FILE ) ) . '/languages' );

		Post_Types::instance()->register_hooks();

		$this->global_styles = new Global_Styles();
		$this->widgets       = new Widgets();
		$this->css           = new Css_Generator( $this->global_styles );
		$this->renderer      = new Renderer( $this->widgets, $this->css );
		$this->css_store     = new Css_Store( $this->renderer, $this->global_styles );
		$this->forms         = new Forms();
		$this->rest          = new Rest( $this->widgets, $this->renderer, $this->global_styles, $this->forms );
		$this->forms->register_hooks();
		$this->rest->register_hooks();

		$this->theme_builder = new Theme_Builder();
		$this->theme_builder->register_hooks();

		$this->popups = new Popups();
		$this->popups->register_hooks();

		( new Editor() )->register_hooks();
		( new Frontend( $this->renderer ) )->register_hooks();

		if ( is_admin() ) {
			( new Admin() )->register_hooks();
		}

		do_action( 'open_builder_loaded', $this );
	}

	/** Activation: register CPTs then flush, create DB tables. */
	public static function activate(): void {
		require_once OPENB_DIR . 'includes/class-security.php';
		require_once OPENB_DIR . 'includes/class-post-types.php';
		require_once OPENB_DIR . 'includes/class-forms.php';

		Post_Types::instance()->register_post_types();
		Forms::install_table();

		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
