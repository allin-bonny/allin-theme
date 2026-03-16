<?php
/**
 * _sk functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package _sk
 */

if (!defined('_S_VERSION')) {
	define('_S_VERSION', '1.0.0');
}

/**
 * Sets up theme defaults and registers support for various WordPress features.
 */
function _sk_setup()
{
	load_theme_textdomain('_sk', get_template_directory() . '/languages');

	add_theme_support('automatic-feed-links');
	add_theme_support('title-tag');
	add_theme_support('post-thumbnails');

	register_nav_menus([
		'menu-1' => esc_html__('Primary', '_sk'),
		'menu-2' => esc_html__('Footer', '_sk'),
	]);

	add_theme_support('html5', [
		'search-form',
		'comment-form',
		'comment-list',
		'gallery',
		'caption',
		'style',
		'script',
	]);

	add_theme_support('custom-background', apply_filters('_sk_custom_background_args', [
		'default-color' => 'ffffff',
		'default-image' => '',
	]));

	add_theme_support('customize-selective-refresh-widgets');

	add_theme_support('custom-logo', [
		'height' => 250,
		'width' => 250,
		'flex-width' => true,
		'flex-height' => true,
	]);

	add_theme_support('woocommerce');
	add_theme_support('wc-product-gallery-zoom');
	add_theme_support('wc-product-gallery-lightbox');
	add_theme_support('wc-product-gallery-slider');
}
add_action('after_setup_theme', '_sk_setup');

/**
 * Set the content width in pixels.
 *
 * @global int $content_width
 */
function _sk_content_width()
{
	$GLOBALS['content_width'] = apply_filters('_sk_content_width', 1200);
}
add_action('after_setup_theme', '_sk_content_width', 0);

/**
 * Register widget area.
 */
function _sk_widgets_init()
{
	register_sidebar([
		'name' => esc_html__('Sidebar', '_sk'),
		'id' => 'sidebar-1',
		'description' => esc_html__('Add widgets here.', '_sk'),
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget' => '</section>',
		'before_title' => '<h2 class="widget-title">',
		'after_title' => '</h2>',
	]);
}
add_action('widgets_init', '_sk_widgets_init');

/**
 * Enqueue scripts and styles.
 */
function _sk_scripts()
{
	wp_enqueue_style('_sk-style', get_stylesheet_uri(), [], _S_VERSION);
	wp_style_add_data('_sk-style', 'rtl', 'replace');

	wp_enqueue_script('_sk-navigation', get_template_directory_uri() . '/js/navigation.js', [], _S_VERSION, true);

	if (is_singular() && comments_open() && get_option('thread_comments')) {
		wp_enqueue_script('comment-reply');
	}
}
add_action('wp_enqueue_scripts', '_sk_scripts');

/**
 * Required plugins notice — must load early before theme activates
 */
require get_template_directory() . '/inc/required-plugins.php';

/**
 * Custom Header
 */
require get_template_directory() . '/inc/custom-header.php';

/**
 * Custom template tags
 */
require get_template_directory() . '/inc/template-tags.php';

/**
 * ACF / SCF Options Pages
 */
require get_template_directory() . '/inc/acf-options.php';

/**
 * Template functions
 */
require get_template_directory() . '/inc/template-functions.php';

/**
 * Customizer additions
 */
require get_template_directory() . '/inc/customizer.php';

/**
 * Jetpack compatibility
 */
if (defined('JETPACK__VERSION')) {
	require get_template_directory() . '/inc/jetpack.php';
}

/**
 * WooCommerce compatibility
 */
if (class_exists('WooCommerce')) {
	require get_template_directory() . '/inc/woocommerce.php';
}
