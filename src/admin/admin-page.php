<?php

/**
 * Page Management Plugin Admin Page
 *
 * This file sets up the admin page for the plugin and includes the necessary hooks
 * to display the plugin interface in the WordPress admin dashboard.
 * Added layout switching functionality between table and tree views.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Add admin menu and scripts
add_action('admin_menu', 'dynapama_add_admin_menu');
add_action('admin_enqueue_scripts', 'dynapama_enqueue_scripts');
add_action('admin_init', 'dynapama_save_layout_preference');

/**
 * Add menu page to WordPress admin
 */
function dynapama_add_admin_menu()
{
    add_menu_page(
        'Page Management',
        'Page Management',
        'manage_options',
        'page-management',
        'dynapama_admin_page',
        'dashicons-admin-page',
        6
    );
}

/**
 * Enqueue necessary scripts and styles
 */
function dynapama_enqueue_scripts($hook)
{
    if ('toplevel_page_page-management' !== $hook) {
        return;
    }

    wp_enqueue_script('dynapama-plugin-scripts', plugin_dir_url(__FILE__) . '../public/js/plugin-scripts.js', array('jquery'), "1.0.0", true);

    // Add custom styles for layout options and modal
    wp_enqueue_style('dynapama-admin-styles', plugin_dir_url(__FILE__) . '../public/css/admin-styles.css', array(), "1.0.0");

    // Add custom JS for layout switching and import functionality
    wp_register_script('dynapama-layout-switcher', plugin_dir_url(__FILE__) . '../public/js/layout-switcher.js', array('jquery'), "1.0.0", true);

    // Add new script for import functionality
    wp_register_script('dynapama-import-page', plugin_dir_url(__FILE__) . '../public/js/import-page.js', array('jquery'), "1.0.0", true);

    // Pass data to JS
    wp_localize_script('dynapama-layout-switcher', 'dynapamaData', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('dynapama_layout_nonce')
    ));

    wp_enqueue_script('dynapama-layout-switcher');
    wp_enqueue_script('dynapama-import-page');
}

/**
 * Save layout preference
 */
function dynapama_save_layout_preference()
{
    if (isset($_POST['dynapama_layout']) && current_user_can('manage_options')) {
        check_admin_referer('dynapama_layout_change', 'dynapama_layout_nonce');
        $layout = sanitize_text_field(wp_unslash($_POST['dynapama_layout']));

        if (in_array($layout, array('table', 'tree'))) {
            update_user_meta(get_current_user_id(), 'dynapama_layout_preference', $layout);
        }

        wp_redirect(admin_url('admin.php?page=page-management'));
        exit;
    }
}

/**
 * Get dynamic meta data for a post
 * 
 * @param int $post_id The post ID
 * @return array The unserialized meta data
 */
function dynapama_get_dynamic_meta($post_id)
{
    $meta_data = get_post_meta($post_id, 'rdynamic_meta_data', true);
    $meta_data = maybe_unserialize($meta_data);
    return empty($meta_data) ? array() : $meta_data;
}

/**
 * Get unique categories from pages
 * 
 * @param array $pages Array of page objects
 * @param string $category_key Meta key for the category
 * @return array Array of unique categories
 */
function dynapama_get_categories($pages, $category_key = 'rpage_category')
{
    $categories = [];
    foreach ($pages as $page) {
        $meta_data = dynapama_get_dynamic_meta($page->ID);
        $category = $meta_data[$category_key] ?? '';
        if (!empty($category) && !in_array($category, $categories)) {
            $categories[] = $category;
        }
    }
    return $categories;
}

/**
 * Render tabs for categories
 * 
 * @param array $categories Array of categories
 */
function dynapama_render_category_tabs($categories)
{
    foreach ($categories as $index => $category) {
        $active_class = $index === 0 ? 'nav-tab-active' : '';
        echo '<a href="#tab-' . esc_attr($index + 1) . '" class="nav-tab ' . esc_attr($active_class) . '">' . esc_html($category) . '</a>';
    }
}

/**
 * Render form buttons and hidden inputs
 * 
 * @param array $data Form data
 * @param string $button_text Text for the button
 * @param string $class Additional CSS class
 * @param bool $confirm Whether to ask for confirmation
 */
function dynapama_render_form_button($data, $button_text, $class = 'button-primary', $confirm = false, $none_action = 'dynapama_import_action', $none_txt = 'dynapama_nonce')
{
?>
    <form method="post" action="" style="display: inline;">
        <?php wp_nonce_field($none_action, $none_txt); ?>
        <?php foreach ($data as $key => $value): ?>
            <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>">
        <?php endforeach; ?>
        <button type="submit" name="<?php echo esc_attr($data['action'] ?? 'import_template'); ?>"
            class="<?php echo $class !== 'inline' ? 'button ' . esc_attr($class) : ''; ?>"
            style="<?php echo $class === 'inline' ? 'cursor:pointer; border: none; background: transparent; padding: 0; font-size: 12px; margin-right: 5px;' : ''; ?>"
            <?php echo $confirm ? 'onclick="return confirm(\'Are you sure you want to delete this page?\')"' : ''; ?>>
            <?php echo esc_html($button_text); ?>
        </button>
    </form>
<?php
}

/**
 * Get all child pages for a parent page (at any level)
 * 
 * @param int $parent_id The parent page ID
 * @param array $all_child_pages Array of all child page objects
 * @return array Array of child page objects for this parent
 */
function dynapama_get_child_pages_for_parent($parent_id, $all_child_pages)
{
    $parent_slug = get_post_field('post_name', $parent_id);
    return array_filter($all_child_pages, function ($child_page) use ($parent_slug) {
        $meta = get_post_meta($child_page->ID, 'child_page_of', true);
        return $meta === $parent_slug;
    });
}

/**
 * Render a page row in table layout
 * 
 * @param object $page The page object
 * @param array $meta_data The page's metadata
 * @param array $all_child_pages Array of all child pages
 * @param int $nesting_level Current nesting level for indentation
 */
function dynapama_render_page_row_table($page, $meta_data, $all_child_pages, $nesting_level = 0)
{
    $template_id = $meta_data['rdynamic_template_id'] ?? '';
    $category = $meta_data['rpage_category'] ?? $meta_data['rpage_child_category'] ?? '';
    $page_slug = get_post_field('post_name', $page->ID);

    // Add indentation based on nesting level
    $indent_style = $nesting_level > 0 ? 'margin-left: ' . ($nesting_level * 20) . 'px;' : '';
?>
    <tr>
        <td style="<?php echo esc_attr($indent_style); ?>">
            <!-- Notice the changed checkbox name from 'selected_pages' to 'selected_pages[]' -->
            <div style="display: flex ; align-items: center; gap: 5px;">
                <input name="selected_pages[]" type="checkbox" value="<?php echo esc_attr($page->ID); ?>">
                <h3><?php echo esc_html(get_the_title($page->ID)); ?></h3>
            </div>
            <?php dynapama_render_form_button(
                ['template_title' => $category, 'existing_page' => $template_id, 'page_id' => $page->ID, 'action' => 'import_template', 'child_page' => isset($meta_data['rpage_category']) ? 'no' : 'yes'],
                'Edit',
                'inline'
            );
            dynapama_render_form_button(
                ['page_id' => $page->ID, 'action' => 'delete_page'],
                'Delete',
                'inline',
                true,
                'dynapama_delete_action',
                'dynapama_delete_nonce'
            );
            dynapama_render_form_button(
                ['page_id' => $page->ID, 'action' => 'duplicate_page'],
                'Duplicate',
                'inline',
                false,
                'dynapama_duplicate_action',
                'dynapama_duplicate_nonce'
            );

            ?>

            <a href="<?php echo esc_url(get_permalink($template_id)); ?>" target="_blank">template</a>
            <a href="<?php echo esc_url(get_permalink($page->ID)); ?>" target="_blank"><?php esc_html_e('View', 'dynamic-page-manager'); ?></a>
        </td>
        <td colspan="2">
            <?php
            // Get direct child pages for this page
            $direct_child_pages = dynapama_get_child_pages_for_parent($page->ID, $all_child_pages);
            if (!empty($direct_child_pages)) {
                $child_categories = dynapama_get_categories($direct_child_pages, "rpage_child_category");
                if (empty($child_categories)) {
                    $child_categories = ['Default']; // Provide a default category if none exists
                }
                foreach ($child_categories as $child_category) :
                    echo '<h3>' . esc_html($child_category) . '</h3>';
            ?>
                    <table class="wp-list-table widefat fixed striped" style="border: none;">
                        <tbody>
                            <?php dynapama_render_nested_child_pages_table($page, $direct_child_pages, $all_child_pages, $child_category, $page_slug, $nesting_level + 1); ?>
                        </tbody>
                    </table>
                <?php endforeach; ?>
                <button class="button button-primary choose_templates" data-child-page="yes" data-parent-id="<?php echo esc_attr($page->ID); ?>" data-parent-slug="<?php echo esc_attr($page_slug); ?>" style="margin-top: 50px;">
                    <?php esc_html_e('Choose Template', 'dynamic-page-manager'); ?>
                </button>
            <?php } else {
                // Button to add first child page if none exist 
            ?>
                <button class="button button-primary choose_templates" data-child-page="yes" data-parent-id="<?php echo esc_attr($page->ID); ?>" data-parent-slug="<?php echo esc_attr($page_slug); ?>">
                    <?php esc_html_e('Choose Child Template', 'dynamic-page-manager'); ?>
                </button>
            <?php } ?>
        </td>
    </tr>
    <?php
}

/**
 * Render nested child pages recursively for table layout
 * 
 * @param object $parent_page The parent page object
 * @param array $direct_child_pages Array of direct child pages for this parent
 * @param array $all_child_pages Array of all child pages
 * @param string $category The category to filter by
 * @param int $nesting_level Current nesting level for indentation
 */
function dynapama_render_nested_child_pages_table($parent_page, $direct_child_pages, $all_child_pages, $category, $parent_page_slug, $nesting_level = 1)
{
    // Filter child pages by category
    $filtered_pages = array_filter($direct_child_pages, function ($child_page) use ($category) {
        $meta_data = dynapama_get_dynamic_meta($child_page->ID);
        $child_category = $meta_data['rpage_child_category'] ?? 'Default';
        return $child_category === $category;
    });
    $last_child_page = end($filtered_pages); // Get the last child page
    // Render each child page
    foreach ($filtered_pages as $child_page) {
        $meta_data = dynapama_get_dynamic_meta($child_page->ID);
        $template_id = $meta_data['rdynamic_template_id'] ?? '';
        // Render this child page
        dynapama_render_page_row_table($child_page, $meta_data, $all_child_pages, $nesting_level);
        if ($child_page->ID === $last_child_page->ID) { ?>
            <tr>
                <td style="display: flex ; gap: 10px;">
                    <?php
                    dynapama_render_form_button(
                        ['template_title' => $category, 'child_page' => 'yes', 'parent_slug' => $parent_page_slug, 'existing_page' => $template_id],
                        'Create New Child Page'
                    );
                    dynapama_render_import_button(['template_title' => $category, 'child_page' => 'yes', 'parent_slug' => $parent_page_slug, 'existing_page' => $template_id], 'Import Existing Page', 'button-secondary');
                    ?>
                </td>
            </tr>
    <?php
        }
    }
}

/**
 * Render a page in tree layout
 * 
 * @param object $page The page object
 * @param array $meta_data The page's metadata
 * @param array $all_child_pages Array of all child pages
 * @param int $nesting_level Current nesting level for indentation
 */
function dynapama_render_page_tree($page, $meta_data, $all_child_pages, $nesting_level = 0)
{
    $template_id = $meta_data['rdynamic_template_id'] ?? '';
    $category = $meta_data['rpage_category'] ?? $meta_data['rpage_child_category'] ?? '';
    $page_slug = get_post_field('post_name', $page->ID);
    $direct_child_pages = dynapama_get_child_pages_for_parent($page->ID, $all_child_pages);
    $has_children = !empty($direct_child_pages);

    // Add indentation based on nesting level
    $indent = $nesting_level * 20;
    $line_style = $nesting_level > 0 ? "border-left: 1px solid #ccc; padding-left: 10px; margin-left: {$indent}px;" : "";
    ?>
    <div class="dynapama-tree-item" style="<?php echo esc_attr($line_style); ?>">
        <div class="dynapama-tree-node <?php echo $has_children ? 'has-children' : ''; ?>">
            <span class="dynapama-node-toggle"><?php echo $has_children ? '<span class="dashicons dashicons-arrow-down"></span>' : ''; ?></span>
            <!-- Add checkbox for tree view bulk actions -->
            <div style="display: flex ; align-items: center; gap: 5px;">
                <input name="selected_pages[]" type="checkbox" value="<?php echo esc_attr($page->ID); ?>">
                <span class="dynapama-node-title"><?php echo esc_html(get_the_title($page->ID)); ?></span>
            </div>
            <span class="dynapama-node-actions">
                <?php dynapama_render_form_button(
                    ['template_title' => $category, 'existing_page' => $template_id, 'page_id' => $page->ID, 'action' => 'import_template', 'child_page' => isset($meta_data['rpage_category']) ? 'no' : 'yes'],
                    'Edit',
                    'inline'
                );
                dynapama_render_form_button(
                    ['page_id' => $page->ID, 'action' => 'delete_page'],
                    'Delete',
                    'inline',
                    true,
                    'dynapama_delete_action',
                    'dynapama_delete_nonce'
                );
                dynapama_render_form_button(
                    ['page_id' => $page->ID, 'action' => 'duplicate_page'],
                    'Duplicate',
                    'inline',
                    false,
                    'dynapama_duplicate_action',
                    'dynapama_duplicate_nonce'
                );

                ?>
                <a href="<?php echo esc_url(get_permalink($template_id)); ?>" target="_blank">template</a>
                <a href="<?php echo esc_url(get_permalink($page->ID)); ?>" target="_blank"><?php esc_html_e('View', 'dynamic-page-manager'); ?></a>
            </span>
        </div>

        <?php if ($has_children): ?>
            <div class="dynapama-tree-children">
                <?php
                $child_categories = dynapama_get_categories($direct_child_pages, "rpage_child_category");
                if (empty($child_categories)) {
                    $child_categories = ['Default'];
                }
                foreach ($child_categories as $child_category):
                    // Filter child pages by category
                    $filtered_pages = array_filter($direct_child_pages, function ($child_page) use ($child_category) {
                        $meta_data = dynapama_get_dynamic_meta($child_page->ID);
                        $page_category = $meta_data['rpage_child_category'] ?? 'Default';
                        return $page_category === $child_category;
                    });
                ?>
                    <div class="dynapama-tree-category">
                        <h4><?php echo esc_html($child_category); ?></h4>
                        <?php foreach ($filtered_pages as $child_page) {
                            $child_meta_data = dynapama_get_dynamic_meta($child_page->ID);
                            dynapama_render_page_tree($child_page, $child_meta_data, $all_child_pages, $nesting_level + 1);
                        } ?>
                        <div class="dynapama-tree-actions" style="margin-left: <?php echo esc_attr(($nesting_level + 1) * 20); ?>px;">
                            <?php dynapama_render_form_button(
                                ['template_title' => $child_category, 'child_page' => 'yes', 'parent_slug' => $page_slug, 'existing_page' => $template_id],
                                'Create New Child Page'
                            );
                            dynapama_render_import_button(
                                ['template_title' => $category, 'child_page' => 'yes', 'parent_slug' => $page_slug, 'existing_page' => $template_id],
                                'Import Existing Page',
                                'button-secondary'
                            ); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="dynapama-tree-actions" style="margin-left: <?php echo esc_attr(($nesting_level + 1) * 20); ?>px;">
                    <button class="button button-primary choose_templates" data-child-page="yes" data-parent-id="<?php echo esc_attr($page->ID); ?>" data-parent-slug="<?php echo esc_attr($page_slug); ?>">
                        <?php esc_html_e('Choose Template', 'dynamic-page-manager'); ?>
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="dynapama-tree-actions" style="margin-left: <?php echo esc_attr(($nesting_level + 1) * 20); ?>px;">
                <button class="button button-primary choose_templates" data-child-page="yes" data-parent-id="<?php echo esc_attr($page->ID); ?>" data-parent-slug="<?php echo esc_attr($page_slug); ?>">
                    <?php esc_html_e('Choose Child Template', 'dynamic-page-manager'); ?>
                </button>
            </div>
        <?php endif; ?>
    </div>
<?php
}

/**
 * Render filtered pages for a specific category in table layout
 * 
 * @param array $pages Array of page objects
 * @param array $all_child_pages Array of all child pages
 * @param string $category The category to filter by
 */
function dynapama_render_filtered_pages_table($pages, $all_child_pages, $category)
{
    $filtered_pages = [];
    $template_id = '';

    // Filter pages by category
    foreach ($pages as $page) {
        $meta_data = dynapama_get_dynamic_meta($page->ID);
        if (isset($meta_data['rpage_category']) && $meta_data['rpage_category'] === $category) {
            $filtered_pages[] = ['page' => $page, 'meta' => $meta_data];
            $template_id = $meta_data['rdynamic_template_id'] ?? '';
        }
    }

    if (!empty($filtered_pages)) {
        foreach ($filtered_pages as $page_data) {
            dynapama_render_page_row_table($page_data['page'], $page_data['meta'], $all_child_pages);
        }
        echo '<tr><td colspan="2">';
        dynapama_render_form_button(
            ['template_title' => $category, 'existing_page' => $template_id],
            'Create New Page'
        );
        dynapama_render_import_button(['template_title' => $category, 'child_page' => 'No', 'parent_slug' => '', 'existing_page' => $template_id], 'Import Existing Page', 'button-secondary');

        echo '</td></tr>';
    } else {
        echo '<tr><td colspan="2">' . esc_html__('No pages found.', 'dynamic-page-manager') . '</td></tr>';
    }
}

/**
 * Render filtered pages for a specific category in tree layout
 * 
 * @param array $pages Array of page objects
 * @param array $all_child_pages Array of all child pages
 * @param string $category The category to filter by
 */
function dynapama_render_filtered_pages_tree($pages, $all_child_pages, $category)
{
    $filtered_pages = [];
    $template_id = '';

    // Filter pages by category
    foreach ($pages as $page) {
        $meta_data = dynapama_get_dynamic_meta($page->ID);
        if (isset($meta_data['rpage_category']) && $meta_data['rpage_category'] === $category) {
            $filtered_pages[] = ['page' => $page, 'meta' => $meta_data];
            $template_id = $meta_data['rdynamic_template_id'] ?? '';
        }
    }

    if (!empty($filtered_pages)) {
        echo '<div class="dynapama-tree-container">';
        foreach ($filtered_pages as $page_data) {
            dynapama_render_page_tree($page_data['page'], $page_data['meta'], $all_child_pages);
        }
        echo '<div class="dynapama-tree-actions">';
        dynapama_render_form_button(
            ['template_title' => $category, 'existing_page' => $template_id],
            'Create New Page'
        );
        dynapama_render_import_button(['template_title' => $category, 'child_page' => 'no', 'parent_slug' => '', 'existing_page' => $template_id], 'Import Existing Page', 'button-secondary');
        echo '</div></div>';
    } else {
        echo '<div class="dynapama-no-pages">' . esc_html__('No pages found.', 'dynamic-page-manager') . '</div>';
    }
}

/**
 * Render layout switcher
 * 
 * @param string $current_layout Current layout (table or tree)
 */
function dynapama_render_layout_switcher($current_layout)
{
?>
    <div class="dynapama-layout-switcher">
        <form method="post" action="">
            <?php wp_nonce_field('dynapama_layout_change', 'dynapama_layout_nonce'); ?>
            <span><?php esc_html_e('Layout:', 'dynamic-page-manager'); ?></span>
            <label>
                <input type="radio" name="dynapama_layout" value="table" <?php checked($current_layout, 'table'); ?>>
                <?php esc_html_e('Table', 'dynamic-page-manager'); ?>
            </label>
            <label>
                <input type="radio" name="dynapama_layout" value="tree" <?php checked($current_layout, 'tree'); ?>>
                <?php esc_html_e('Tree', 'dynamic-page-manager'); ?>
            </label>
            <input type="submit" class="button button-small" value="<?php esc_attr_e('Apply', 'dynamic-page-manager'); ?>">
        </form>
    </div>
    <?php
}

/**
 * Main admin page callback function
 */
function dynapama_admin_page()
{
    // Get user layout preference (default to table)
    $current_layout = get_user_meta(get_current_user_id(), 'dynapama_layout_preference', true);
    if (empty($current_layout)) {
        $current_layout = 'table';
    }

    // Fetch top-level pages (no parent)
    $args = array(
        'meta_key' => 'rdynamic_meta_data',
        'post_type' => 'page',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => 'child_page_of',
                'compare' => 'NOT EXISTS'
            )
        )
    );

    // Fetch all child pages (any level)
    $args2 = array(
        'meta_key' => 'child_page_of',
        'post_type' => 'page',
        'posts_per_page' => -1
    );

    $pages = get_posts($args);
    $all_child_pages = get_posts($args2);
    $categories = dynapama_get_categories($pages);

    require_once plugin_dir_path(__FILE__) . 'templates/form-template.php';

    if (!isset($_POST['dynapama_nonce']) && !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dynapama_nonce']??"")), 'dynapama_import_action') && !isset($_POST['import_template'])):
    ?>
        <div class="wrap" id="page-management">
            <h1><?php esc_html_e('Page Management System', 'dynamic-page-manager'); ?></h1>
            <div class="dynapama-header-actions">
                <?php dynapama_render_layout_switcher($current_layout); ?>
            </div>

            <!-- Start bulk action form here to include all checkboxes -->
            <form method="post" action="" id="dynapama-bulk-action-form">
                <?php wp_nonce_field('dynapama_bulk_action', 'dynapama_bulk_nonce'); ?>

                <div style="display: flex; justify-content: space-between;">
                    <h2 class="nav-tab-wrapper">
                        <?php dynapama_render_category_tabs($categories); ?>
                        <button class="button button-primary choose_templates">
                            <?php echo empty($categories) ? esc_html__('Choose Template', 'dynamic-page-manager') : esc_html__('+', 'dynamic-page-manager'); ?>
                        </button>
                    </h2>
                    <?php
                    dynapama_render_bulk_actions(); ?>
                </div>

                <?php foreach ($categories as $index => $category) : ?>
                    <div id="tab-<?php echo esc_attr($index + 1); ?>" class="tab-content" style="<?php echo $index === 0 ? 'display:block;' : 'display:none;'; ?>">
                        <?php if ($current_layout === 'table') : ?>
                            <table class="wp-list-table widefat fixed striped dynapama-table-layout">
                                <thead>
                                    <tr>
                                        <th>
                                            <input type="checkbox" class="select-all-pages" />
                                            <?php esc_html_e('Pages', 'dynamic-page-manager'); ?>
                                        </th>
                                        <th colspan="2"><?php esc_html_e('Child Pages', 'dynamic-page-manager'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php dynapama_render_filtered_pages_table($pages, $all_child_pages, $category); ?>
                                </tbody>
                            </table>
                        <?php else : ?>
                            <div class="dynapama-tree-layout">
                                <?php dynapama_render_filtered_pages_tree($pages, $all_child_pages, $category); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </form>
            <!-- End bulk action form -->
        </div>
    <?php endif; ?>
    <p><?php esc_html_e('Instead of content, use {{{rdynamic_content type="text" name="first_title" title="first Title"}}} in your template page', 'dynamic-page-manager'); ?></p>

    <!-- Import Existing Page Modal -->
    <div id="import_existing_page_modal" class="dynapama-modal" style="display: none;">
        <div class="dynapama-modal-content">
            <span class="close-modal">&times;</span>
            <h2>Import Existing Page</h2>
            <p>Select an existing page to import:</p>
            <form method="post" action="">
                <?php wp_nonce_field('dynapama_import_action', 'dynapama_nonce'); ?>
                <select id="select_page_to_import" name="page_id">
                    <option value="">Select a page</option>
                    <?php
                    // Fetch existing pages and populate the dropdown
                    $pages = get_posts(array(
                        'post_type' => 'page',
                        'numberposts' => -1,
                        'post_status' => array('publish', 'draft')
                    ));
                    foreach ($pages as $page) {
                        echo '<option value="' . esc_attr($page->ID) . '">' . esc_html($page->post_title) . ' (' . esc_html($page->post_status) . ')</option>';
                    }
                    ?>
                </select>
                <div id="import_loading" style="display: none;">
                    <p>Loading page data...</p>
                </div>
                <input type="hidden" name="template_title" id="template_title_hidden" value="">
                <input type="hidden" id="child_page_hidden" value="">
                <input type="hidden" id="parent_slug_hidden" value="">
                <input type="hidden" name="existing_page" id="existing_page_hidden" value="">
                <input type="hidden" name="action" value="import_template">
                <button type="submit" id="import_selected_page_btn" class="button button-primary" disabled>Import Selected Page</button>
            </form>
        </div>
    </div>

<?php
    // Add JavaScript for bulk actions
    $inline_script = "
    jQuery(document).ready(function($) {
        // Handle select all checkbox
        $('.select-all-pages').on('change', function() {
            $('input[name=\"selected_pages[]\"]').prop('checked', $(this).prop('checked'));
        });
        
        // Form submission validation
        $('#dynapama-bulk-action-form .dynapama-bulk-actions button').on('click', function(e) {
            var action = $('select[name=\"bulk_action\"]').val();
            var checked = $('input[name=\"selected_pages[]\"]:checked').length;
            
            if (action === '') {
                alert('Please select an action');
                e.preventDefault();
                return false;
            }
            
            if (checked === 0) {
                alert('Please select at least one page');
                e.preventDefault();
                return false;
            }
            
            if (action === 'delete') {
                if (!confirm('Are you sure you want to delete the selected pages?')) {
                    e.preventDefault();
                    return false;
                }
            }
            
            return true;
        });
        
        // Tab switching
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            var target = $(this).attr('href');
            
            // Hide all tabs
            $('.tab-content').hide();
            
            // Remove active class
            $('.nav-tab').removeClass('nav-tab-active');
            
            // Show target tab
            $(target).show();
            
            // Add active class
            $(this).addClass('nav-tab-active');
        });
    });
    ";
    wp_add_inline_script('dynapama-plugin-scripts', $inline_script);
}


/**
 * Render import button
 * 
 * @param array $data Form data
 * @param string $button_text Text for the button
 * @param string $class Additional CSS class
 */
function dynapama_render_import_button($data, $button_text, $class = 'button-primary')
{
?>
    <button type="button" class="button <?php echo esc_attr($class); ?> import_existing_page"
        data-template-title="<?php echo esc_attr($data['template_title']); ?>"
        data-child-page="<?php echo esc_attr($data['child_page']); ?>"
        data-parent-slug="<?php echo esc_attr($data['parent_slug'] ?? ''); ?>"
        data-existing-page="<?php echo esc_attr($data['existing_page']); ?>">
        <?php echo esc_html($button_text); ?>
    </button>
<?php
}


/**
 * Register AJAX handler for importing existing pages
 */
add_action('wp_ajax_dynapama_import_existing_page', 'dynapama_handle_import_existing_page');

/**
 * AJAX handler for importing existing page data
 */
function dynapama_handle_import_existing_page()
{
    // Verify nonce
    if (!isset($_POST['nonce'])) {
        wp_send_json_error('Security check failed');
    }

    // Unsplash the nonce for safe verification
    $nonce = sanitize_text_field(wp_unslash($_POST['nonce']));

    if (!wp_verify_nonce($nonce, 'dynapama_layout_nonce')) {
        wp_send_json_error('Security check failed');
    }

    // Check for page ID
    if (!isset($_POST['page_id']) && empty($_POST['page_id'])) {
        wp_send_json_error('No page ID provided');
    }

    $page_id = intval($_POST['page_id']);
    $page = get_post($page_id);

    if (!$page) {
        wp_send_json_error('Page not found');
    }

    // Get page data
    $page_data = array(
        'title' => $page->post_title,
        'slug' => $page->post_name,
        'meta' => get_post_meta($page_id)
    );

    wp_send_json_success($page_data);
}

/**
 * Add bulk actions to the admin page
 */
function dynapama_render_bulk_actions()
{
?>
    <div class="dynapama-bulk-actions">
        <select name="bulk_action">
            <option value=""><?php esc_html_e('Bulk Actions', 'dynamic-page-manager'); ?></option>
            <option value="delete"><?php esc_html_e('Delete', 'dynamic-page-manager'); ?></option>
            <option value="publish"><?php esc_html_e('Publish', 'dynamic-page-manager'); ?></option>
            <option value="draft"><?php esc_html_e('Set to Draft', 'dynamic-page-manager'); ?></option>
        </select>
        <button type="submit" class="button" name="apply_bulk_action"><?php esc_html_e('Apply', 'dynamic-page-manager'); ?></button>
    </div>
    <?php
}

/**
 * Process bulk actions
 */
function dynapama_process_bulk_actions()
{
    if (!isset($_POST['dynapama_bulk_nonce']) && !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dynapama_bulk_nonce']??"")), 'dynapama_bulk_action')) {
        return;
    }

    if (!isset($_POST['bulk_action']) || empty($_POST['bulk_action']) || !isset($_POST['selected_pages']) || empty($_POST['selected_pages'])) {
        return;
    }

    $action = sanitize_text_field(wp_unslash($_POST['bulk_action']));
    $page_ids = array_map('intval', $_POST['selected_pages']);

    switch ($action) {
        case 'delete':
            foreach ($page_ids as $page_id) {
                if (current_user_can('delete_post', $page_id)) {
                    wp_delete_post($page_id, true);
                }
            }
            break;
        case 'publish':
            foreach ($page_ids as $page_id) {
                if (current_user_can('publish_posts')) {
                    wp_update_post(array(
                        'ID' => $page_id,
                        'post_status' => 'publish'
                    ));
                }
            }
            break;
        case 'draft':
            foreach ($page_ids as $page_id) {
                if (current_user_can('edit_post', $page_id)) {
                    wp_update_post(array(
                        'ID' => $page_id,
                        'post_status' => 'draft'
                    ));
                }
            }
            break;
    }


    // Redirect to avoid form resubmission
    wp_redirect(admin_url('admin.php?page=page-management&bulk_action=completed'));
    exit;
}


// Add action to process bulk actions
add_action('admin_init', 'dynapama_process_bulk_actions');
