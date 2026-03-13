<?php
/**
 * ACF / SCF Options Pages Registration
 *
 * @package _sk
 */

if (!function_exists('acf_add_options_page')) {
    return;
}

//test

// ── Main Options Page ────────────────────────────────────────────────────────
acf_add_options_page([
    'page_title' => __('Theme Settings', '_sk'),
    'menu_title' => __('Theme Settings', '_sk'),
    'menu_slug' => '_sk_options',
    'capability' => 'edit_theme_options',
    'redirect' => true,
    'icon_url' => 'dashicons-admin-settings',
    'position' => 61,
]);

// ── Sub Pages ────────────────────────────────────────────────────────────────
acf_add_options_sub_page([
    'page_title' => __('Header Settings', '_sk'),
    'menu_title' => __('Header', '_sk'),
    'menu_slug' => '_sk_header_options',
    'parent_slug' => '_sk_options',
]);

acf_add_options_sub_page([
    'page_title' => __('Footer Settings', '_sk'),
    'menu_title' => __('Footer', '_sk'),
    'menu_slug' => '_sk_footer_options',
    'parent_slug' => '_sk_options',
]);

acf_add_options_sub_page([
    'page_title' => __('Social Media', '_sk'),
    'menu_title' => __('Social Media', '_sk'),
    'menu_slug' => '_sk_social_options',
    'parent_slug' => '_sk_options',
]);
