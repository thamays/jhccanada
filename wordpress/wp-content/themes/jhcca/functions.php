<?php
add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo');
    register_nav_menus(['header' => 'Header Menu']);
});
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('jhcca-fonts', 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap', [], null);
    wp_enqueue_style('jhcca', get_stylesheet_uri(), [], wp_get_theme()->get('Version'));
});
add_action('wp_head', function () {
    echo '<link rel="icon" href="' . esc_url(get_theme_file_uri('assets/img/favicon.png')) . '">';
});
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script('jhcca-menu', get_theme_file_uri('assets/js/menu.js'), [], wp_get_theme()->get('Version'), true);
});
