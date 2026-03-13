<?php
/**
 * _sk functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package _sk
 */

if (!defined('_S_VERSION')) {
	// Replace the version number of the theme on each release.
	define('_S_VERSION', '1.0.0');
}

/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 */
function _sk_setup()
{
	/*
	 * Make theme available for translation.
	 * Translations can be filed in the /languages/ directory.
	 * If you're building a theme based on _s, use a find and replace
	 * to change '_sk' to the name of your theme in all the template files.
	 */
	load_theme_textdomain('_sk', get_template_directory() . '/languages');

	// Add default posts and comments RSS feed links to head.
	add_theme_support('automatic-feed-links');

	/*
	 * Let WordPress manage the document title.
	 * By adding theme support, we declare that this theme does not use a
	 * hard-coded <title> tag in the document head, and expect WordPress to
	 * provide it for us.
	 */
	add_theme_support('title-tag');

	/*
	 * Enable support for Post Thumbnails on posts and pages.
	 *
	 * @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
	 */
	add_theme_support('post-thumbnails');

	// This theme uses wp_nav_menu() in one location.
	register_nav_menus(
		array(
			'menu-1' => esc_html__('Primary', '_sk'),
		)
	);

	/*
	 * Switch default core markup for search form, comment form, and comments
	 * to output valid HTML5.
	 */
	add_theme_support(
		'html5',
		array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
			'style',
			'script',
		)
	);

	// Set up the WordPress core custom background feature.
	add_theme_support(
		'custom-background',
		apply_filters(
			'_sk_custom_background_args',
			array(
				'default-color' => 'ffffff',
				'default-image' => '',
			)
		)
	);

	// Add theme support for selective refresh for widgets.
	add_theme_support('customize-selective-refresh-widgets');

	/**
	 * Add support for core custom logo.
	 *
	 * @link https://codex.wordpress.org/Theme_Logo
	 */
	add_theme_support(
		'custom-logo',
		array(
			'height' => 250,
			'width' => 250,
			'flex-width' => true,
			'flex-height' => true,
		)
	);
}
add_action('after_setup_theme', '_sk_setup');

/**
 * Set the content width in pixels, based on the theme's design and stylesheet.
 *
 * Priority 0 to make it available to lower priority callbacks.
 *
 * @global int $content_width
 */
function _sk_content_width()
{
	$GLOBALS['content_width'] = apply_filters('_sk_content_width', 640);
}
add_action('after_setup_theme', '_sk_content_width', 0);

/**
 * Register widget area.
 *
 * @link https://developer.wordpress.org/themes/functionality/sidebars/#registering-a-sidebar
 */
function _sk_widgets_init()
{
	register_sidebar(
		array(
			'name' => esc_html__('Sidebar', '_sk'),
			'id' => 'sidebar-1',
			'description' => esc_html__('Add widgets here.', '_sk'),
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget' => '</section>',
			'before_title' => '<h2 class="widget-title">',
			'after_title' => '</h2>',
		)
	);
}
add_action('widgets_init', '_sk_widgets_init');

/**
 * Enqueue scripts and styles.
 */
function _sk_scripts()
{
	wp_enqueue_style('_sk-style', get_stylesheet_uri(), array(), _S_VERSION);
	wp_style_add_data('_sk-style', 'rtl', 'replace');

	wp_enqueue_script('_sk-navigation', get_template_directory_uri() . '/js/navigation.js', array(), _S_VERSION, true);

	if (is_singular() && comments_open() && get_option('thread_comments')) {
		wp_enqueue_script('comment-reply');
	}
}
add_action('wp_enqueue_scripts', '_sk_scripts');

/**
 * Implement the Custom Header feature.
 */
require get_template_directory() . '/inc/custom-header.php';

/**
 * Custom template tags for this theme.
 */
require get_template_directory() . '/inc/template-tags.php';

require get_template_directory() . '/inc/acf-options.php';


/**
 * Functions which enhance the theme by hooking into WordPress.
 */
require get_template_directory() . '/inc/template-functions.php';

/**
 * Customizer additions.
 */
require get_template_directory() . '/inc/customizer.php';

/**
 * Load Jetpack compatibility file.
 */
if (defined('JETPACK__VERSION')) {
	require get_template_directory() . '/inc/jetpack.php';
}

/**
 * Load WooCommerce compatibility file.
 */
if (class_exists('WooCommerce')) {
	require get_template_directory() . '/inc/woocommerce.php';
}

/**
 * Auto-install & activate bundled plugins on theme activation.
 *
 * @package _sk
 */
function _sk_install_bundled_plugins()
{

	// Only run once
	if (get_option('_sk_plugins_installed')) {
		return;
	}

	$plugins_dir = get_template_directory() . '/_plugins/';

	if (!is_dir($plugins_dir)) {
		return;
	}

	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/misc.php';

	$zip_files = glob($plugins_dir . '*.zip');

	if (empty($zip_files)) {
		return;
	}

	foreach ($zip_files as $zip_file) {

		$upgrader = new Plugin_Upgrader(new WP_Ajax_Upgrader_Skin());
		$result = $upgrader->install($zip_file);

		if (is_wp_error($result) || !$result) {
			continue;
		}

		// Get installed plugin folder name
		$destination = $upgrader->result['destination_name'] ?? '';

		if (empty($destination)) {
			continue;
		}

		// Find the main plugin file
		$installed_plugins = get_plugins('/' . $destination);

		if (empty($installed_plugins)) {
			continue;
		}

		$plugin_file = $destination . '/' . array_key_first($installed_plugins);

		if (!is_plugin_active($plugin_file)) {
			activate_plugin($plugin_file);
		}
	}

	// Mark as done — never runs again
	update_option('_sk_plugins_installed', true);
}
add_action('after_switch_theme', '_sk_install_bundled_plugins');
