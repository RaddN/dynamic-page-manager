<?php
/**
 * Plugin Name: Dynamic Page Manager
 * Description: A powerful plugin for managing and creating multiple pages based on customizable templates with dynamic content integration.
 * Version: 1.0
 * Author: raddp
 * License: GPL-2.0-or-later
 * License URI: https://opensource.org/licenses/GPL-2.0
 * Text Domain: dynamic-page-manager
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'DYNAPAMA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DYNAPAMA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include necessary files
require_once DYNAPAMA_PLUGIN_DIR . 'src/admin/admin-page.php';
require_once DYNAPAMA_PLUGIN_DIR . 'src/blocks/class-dynapama-blocks.php';


// Deactivation hook
function dynapama_deactivate() {
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'dynapama_deactivate' );

// Enqueue block editor assets
function dynapama_enqueue_block_editor_assets() {
    // Enqueue block editor script
    wp_enqueue_script(
        'dynapama-blocks',
        DYNAPAMA_PLUGIN_URL . 'src/blocks/dynamic-content-block/block.js',
        array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components'),
        filemtime(DYNAPAMA_PLUGIN_DIR . 'src/blocks/dynamic-content-block/block.js'),
        true
    );

    // Enqueue editor styles
    wp_enqueue_style(
        'dynapama-blocks-editor',
        DYNAPAMA_PLUGIN_URL . 'src/blocks/dynamic-content-block/editor.css',
        array('wp-edit-blocks'),
        filemtime(DYNAPAMA_PLUGIN_DIR . 'src/blocks/dynamic-content-block/editor.css')
    );
}
add_action('enqueue_block_editor_assets', 'dynapama_enqueue_block_editor_assets');


function dynapama_get_dependent_pages($template_id) {
    
    $dependent_pages = get_posts(array(
        'post_type' => 'page',
        'meta_query' => array(
            array(
                'key' => 'rdynamic_template_id',
                'value' => $template_id,
                'compare' => '='
            )
        ),
        'posts_per_page' => -1,
        'fields' => 'ids' // Only get IDs for better performance
    ));
    
    return $dependent_pages;
}


// on template page update, update all included pages
/**
 * Hook into post update to handle template changes
 */
function dynapama_template_update_handler($post_id, $post_after, $post_before) {
    // Only run for pages
    if ($post_after->post_type !== 'page') {
        return;
    }
    
    // Check if the content has changed
    $content_changed = $post_after->post_content !== $post_before->post_content;
    $title_changed = $post_after->post_title !== $post_before->post_title;
    
    // Find all pages that use this template
    $dependent_pages = dynapama_get_dependent_pages($post_id);
    
    if (empty($dependent_pages)) {
        return;
    }
    
    // Get ALL meta data from the template page
    $template_meta = get_post_meta($post_id);
    
    // Loop through each dependent page and update it
    foreach ($dependent_pages as $page) {
        // Update content if it changed
        if ($content_changed) {
            dynapama_update_dependent_page_content($page, $post_after->post_content);
        }
        
        // Update ALL meta data from template to dependent page
        dynapama_update_dependent_page_meta($page, $template_meta);
    }
}
add_action('post_updated', 'dynapama_template_update_handler', 10, 3);

/**
 * Update a page that depends on the template - content only
 */
function dynapama_update_dependent_page_content($page_id, $template_content) {
    // Get the stored meta data for this page
    $meta_data = get_post_meta($page_id, 'rdynamic_meta_data', true);
    
    if (empty($meta_data)) {
        return;
    }
    
    // Use the template content and replace dynamic placeholders with stored values
    $updated_content = $template_content;
    
    // Replace single dynamic content placeholders
    foreach ($meta_data as $key => $value) {
        if (strpos($key, 'rdynamic_') === 0) {
            $dynamic_name = sanitize_key(substr($key, 9)); // Remove 'rdynamic_' prefix and sanitize
            $updated_content = preg_replace(
                '/\{\{\{rdynamic_content type=[\'"][^\'"]+[\'"] name=[\'"]' . preg_quote($dynamic_name, '/') . '[\'"] title=[\'"][^\'"]+[\'"]\}\}\}/',
                wp_kses_post($value),
                $updated_content
            );
        }
    }
    
    // Handle loop content replacement
    $pattern = '/{{{rdynamic_content_loop_start name=[\'"]?([^\'"]+)[\'"]?}}}(.*?){{{rdynamic_content_loop_ends name=[\'"]?\1[\'"]?}}}/s';
    if (preg_match_all($pattern, $updated_content, $loop_matches)) {
        foreach ($loop_matches[0] as $index => $loop_match) {
            $loop_name = $loop_matches[1][$index]; // Extract loop name
            $loop_template = $loop_matches[2][$index]; // Extract loop template
            
            // Count how many loop items we have for this loop name
            $loop_count = 0;
            foreach ($meta_data as $key => $value) {
                if (preg_match('/^rdynamic_' . preg_quote($loop_name, '/') . '_(\d+)_/', $key, $matches)) {
                    $item_number = intval($matches[1]);
                    if ($item_number > $loop_count) {
                        $loop_count = $item_number;
                    }
                }
            }
            
            // Generate the loop content
            $loop_content = '';
            for ($i = 1; $i <= $loop_count; $i++) {
                $current_loop_content = $loop_template;
                
                // Replace each placeholder in this loop item
                foreach ($meta_data as $key => $value) {
                    if (preg_match('/^rdynamic_' . preg_quote($loop_name, '/') . '_' . $i . '_(.+)$/', $key, $matches)) {
                        $dynamic_name = $matches[1];
                        $current_loop_content = preg_replace(
                            '/\{\{\{loop_content type=[\'"][^\'"]+[\'"] name=[\'"]' . preg_quote($dynamic_name, '/') . '[\'"] title=[\'"][^\'"]+[\'"]\}\}\}/',
                            wp_kses_post($value),
                            $current_loop_content
                        );
                    }
                }
                
                $loop_content .= $current_loop_content;
            }
            
            // Replace the loop placeholder with the generated content
            $updated_content = str_replace($loop_match, $loop_content, $updated_content);
        }
    }
    
    // Update the page with the new content
    wp_update_post(array(
        'ID' => $page_id,
        'post_content' => $updated_content
    ));
}

/**
 * Update all meta data for dependent pages
 */
function dynapama_update_dependent_page_meta($page_id, $template_meta) {
    // List of meta keys that should NOT be copied from template to dependent pages
    $exclude_meta_keys = array(
        '_edit_lock',                    // Editing locks
        '_edit_last',                    // Last editor
        '_wp_page_template',             // Page template
        '_wp_old_slug'                  // Old slug
    );
    
    // Store any page-specific meta that we want to preserve
    $preserved_meta = array();
    foreach ($exclude_meta_keys as $key) {
        $value = get_post_meta($page_id, $key, true);
        if (!empty($value)) {
            $preserved_meta[$key] = $value;
        }
    }
    
    // Apply all template meta to the dependent page
    foreach ($template_meta as $meta_key => $meta_values) {
        // Skip excluded meta keys
        if (in_array($meta_key, $exclude_meta_keys)) {
            continue;
        }
        
        // Meta comes as array, but we typically want the first value
        $meta_value = reset($meta_values);
        
        // Update the meta value
        update_post_meta($page_id, $meta_key, $meta_value);
    }
    
    // Restore preserved meta values
    foreach ($preserved_meta as $key => $value) {
        update_post_meta($page_id, $key, $value);
    }
    
    // Trigger action to allow additional customizations
    do_action('rdynamic_after_meta_update', $page_id, $template_meta);
}

/**
 * Add meta box to display which pages are using this template
 */
function dynapama_add_template_info_meta_box() {
    add_meta_box(
        'rdynamic_template_info',
        'Template Information',
        'dynapama_render_template_info_meta_box',
        'page',
        'side',
        'high'
    );
}
add_action('add_meta_boxes', 'dynapama_add_template_info_meta_box');

/**
 * Render the template info meta box
 */
function dynapama_render_template_info_meta_box($post) {
    // Check if this is a template page
    $dependent_pages = dynapama_get_dependent_pages($post->ID);
    
    if (!empty($dependent_pages)) {
        echo '<p><strong>This is a template page used by:</strong></p>';
        echo '<ul>';
        foreach ($dependent_pages as $page_id) {
            echo '<li><a href="' . esc_url(get_edit_post_link($page_id)) . '">' . esc_html(get_the_title($page_id)) . '</a></li>';
        }
        echo '</ul>';
        echo '<p><em>Content and style changes made to this page will update all dependent pages.</em></p>';
        
        // Add info about title visibility
        $title_visibility = get_post_meta($post->ID, 'ast-title-bar-display', true) === 'disabled' ? 'Hidden' : 'Visible';
        echo '<p><strong>Title visibility:</strong> ' . esc_attr($title_visibility) . '</p>';
        echo '<p><em>All meta data changes will affect all dependent pages.</em></p>';
    } else {
        // Check if this page uses a template
        $template_id = get_post_meta($post->ID, 'rdynamic_template_id', true);
        if (!empty($template_id)) {
            $template = get_post($template_id);
            if ($template) {
                echo '<p><strong>This page uses template:</strong> <a href="' . esc_url(get_edit_post_link($template_id)) . '">' . esc_html($template->post_title) . '</a></p>';
                
                // Show if title is visible or hidden based on template settings
                $title_visibility = get_post_meta($post->ID, 'ast-title-bar-display', true) === 'disabled' ? 'Hidden' : 'Visible';
                echo '<p><strong>Title visibility:</strong> ' . esc_attr($title_visibility) . ' (inherited from template)</p>';
            }
        }
    }
}


// Check if Elementor is active
function dynapama_elementor_check() {
    if (!did_action('elementor/loaded')) {
        return false;
    }
    return true;
}

// Initialize the plugin
function dynapama_init() {
    // Check if Elementor is installed and activated
    if (!dynapama_elementor_check()) {
        return;
    }
    
    // Register the widget
    add_action('elementor/widgets/widgets_registered', 'dynapama_register_widgets');
}
add_action('plugins_loaded', 'dynapama_init');

// Register widget
function dynapama_register_widgets() {
    // require_once(__DIR__ . '/widgets/dynamic-content-widget.php');
    require_once DYNAPAMA_PLUGIN_DIR . 'src/elementor/widget.php';
}


// Register widget styles
function dynapama_register_styles() {
    wp_register_style(
        'dynamic-page-manager-styles',
        plugins_url('src/elementor/dynamic-content.css', __FILE__),
        [],
        '1.0.0'
    );
}
add_action('wp_enqueue_scripts', 'dynapama_register_styles');
add_action('elementor/editor/before_enqueue_scripts', 'dynapama_register_styles');