<?php

/*
Plugin Name: Flying Eye Inventory
Plugin URI: https://codepixelzmedia.com/
Description: Flying Eye Inventory Update
Version: 1.0.0
Author: CPM
Author URI: https://codepixelzmedia.com/
*/

if (!defined('ABSPATH')) {
   exit;
}

define('PLUGIN_DIR_PATH',plugin_dir_path(__FILE__));
define('PLUGIN_ENQUEUE_PATH',plugin_dir_url( __FILE__ ));

add_action('admin_enqueue_scripts', 'enqueue_select2_assets');
function enqueue_select2_assets()
{
    wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
    wp_enqueue_style('flying-eye-plugin-admin-css',  PLUGIN_ENQUEUE_PATH . 'admin/style.css', array(),rand());
    wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), null, true);
    wp_enqueue_script('flying-eye-plugin-admin-js', PLUGIN_ENQUEUE_PATH . 'admin/main.js', array('jquery'), rand(), true);
    wp_localize_script(
        'flying-eye-plugin-admin-js',
        'admin_ajax',
        array('ajaxurl' => admin_url('admin-ajax.php'))
    );
}

// Include necessary files.
require PLUGIN_DIR_PATH . 'admin/admin-setting.php';
require PLUGIN_DIR_PATH . 'admin/product-inventory-update-table.php';
require PLUGIN_DIR_PATH . 'admin/stock-change.php';