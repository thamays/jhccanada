<?php
/**
 * Plugin Name: JHCA Admin Panel
 * Description: Custom admin panel (at /jhc-admin/) for managing JHCA Canada content, built from modules.
 * Version: 0.2.0
 *
 * ADDING A NEW SECTION
 *  - Simple member-style list: add one line in jhc_register_modules() below (see Annual Members).
 *  - Anything else: extend JHC_Module in includes/, then register it the same way.
 */
if (!defined('ABSPATH')) exit;

define('JHC_DIR', plugin_dir_path(__FILE__));
define('JHC_URL', plugin_dir_url(__FILE__));
const JHC_ADMIN_SLUG = 'jhc-admin';
const JHC_DB_VERSION = '2';

require_once JHC_DIR . 'includes/class-module.php';
require_once JHC_DIR . 'includes/class-panel.php';
require_once JHC_DIR . 'includes/class-dashboard.php';
require_once JHC_DIR . 'includes/class-members-module.php';

/** Register every panel section here, in sidebar order. */
function jhc_register_modules() {
    JHC_Panel::register(new JHC_Dashboard());
    JHC_Panel::register(new JHC_Members_Module([
        'slug' => 'life-members', 'label' => 'Life Members', 'singular' => 'life member', 'icon' => '★',
        'table' => 'jhc_life_members', 'prefix' => 'LM-', 'pad' => 3, 'page' => 'life-members', 'shortcode' => 'jhc_life_members',
    ]));
    JHC_Panel::register(new JHC_Members_Module([
        'slug' => 'annual-members', 'label' => 'Annual Members', 'singular' => 'annual member', 'icon' => '◔',
        'table' => 'jhc_annual_members', 'prefix' => 'AM-', 'pad' => 4, 'page' => 'annual-members', 'shortcode' => 'jhc_annual_members',
    ]));
}
add_action('init', 'jhc_register_modules', 5);
add_action('init', ['JHC_Panel', 'boot'], 6);

function jhc_install_all() {
    JHC_Panel::add_rewrite();
    foreach (JHC_Panel::modules() as $m) $m->install();
    flush_rewrite_rules();
    update_option('jhc_db_ver', JHC_DB_VERSION);
}
register_activation_hook(__FILE__, function () { jhc_register_modules(); JHC_Panel::boot(); jhc_install_all(); });
add_action('init', function () { if (get_option('jhc_db_ver') !== JHC_DB_VERSION) jhc_install_all(); }, 20);
