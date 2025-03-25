<?php
/**
 * Template Handler Class
 * 
 * Handles parsing and processing of dynamic content shortcodes
 */


 // creating blocks for gutenberg

function dynapama_register_dynamic_ajax_filter_block()
{
    wp_register_script(
        'dynapama-blocks',
        plugins_url('dynamic-content-block/block.js', __FILE__),
        array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components'),
        filemtime(plugin_dir_path(__FILE__) . 'dynamic-content-block/block.js'),
        true
    );

    register_block_type('dynapama/dynamic-content', array(
        'editor_script' => 'dynamic-page-manager',
        'render_callback' => 'dynamic_content_management_func',
        'attributes' => array(
            'type' => array(
                'type' => 'string',
                'default' => 'text',
            ),
            'name' => array(
                'type' => 'string',
                'default' => '',
            ),
            'title' => array(
                'type' => 'string',
                'default' => '',
            ),
        ),
    ));

    register_block_type('dynapama/loop-content', array(
        'editor_script' => 'dynamic-loop-content-management',
        'render_callback' => 'dynamic__loop_content_management_func',
        'attributes' => array(
            'type' => array(
                'type' => 'string',
                'default' => 'text',
            ),
            'name' => array(
                'type' => 'string',
                'default' => '',
            ),
            'title' => array(
                'type' => 'string',
                'default' => '',
            ),
        ),
    ));
}
add_action('init', 'dynapama_register_dynamic_ajax_filter_block');

function dynamic_content_management_func($attributes)
{
    return $attributes["output"]??'';
}

function dynamic__loop_content_management_func($attributes)
{
    return $attributes["output"];
}