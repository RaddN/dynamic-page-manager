<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class DYNAPAMA_Elementor_Dynamic_Content_Management_Widget extends \Elementor\Widget_Base
{
    public function dynapama_get_name()
    {
        return 'dynamic_content_management';
    }

    public function dynapama_get_title()
    {
        return esc_html__('Dynamic Content Management', 'dynamic-page-manager');
    }

    public function dynapama_get_icon()
    {
        return 'eicon-code';
    }

    public function dynapama_get_categories()
    {
        return ['general'];
    }
    public function dynapama_get_style_depends() {
        return ['dynamic-page-manager-styles'];
    }

    protected function dynapama_register_controls()
    {
        // Content Tab
        $this->start_controls_section(
            'content_section',
            [
                'label' => esc_html__('Settings', 'dynamic-page-manager'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        // Option selector control
        $this->add_control(
            'content_type',
            [
                'label' => esc_html__('Select Option', 'dynamic-page-manager'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'dynamic_content',
                'options' => [
                    'dynamic_content' => esc_html__('Dynamic Content', 'dynamic-page-manager'),
                    'dynamic_loop' => esc_html__('Dynamic Loop', 'dynamic-page-manager'),
                    'loop_content' => esc_html__('Loop Content', 'dynamic-page-manager'),
                ],
            ]
        );

        // Dynamic Content Controls
        $this->add_control(
            'dynamic_content_type',
            [
                'label' => esc_html__('Input Type', 'dynamic-page-manager'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'text',
                'options' => [
                    'text' => esc_html__('Text', 'dynamic-page-manager'),
                    'text_area' => esc_html__('Text Area', 'dynamic-page-manager'),
                    'number' => esc_html__('Number', 'dynamic-page-manager'),
                    'email' => esc_html__('Email', 'dynamic-page-manager'),
                    'url' => esc_html__('URL', 'dynamic-page-manager'),
                    'select' => esc_html__('Select', 'dynamic-page-manager'),
                    'checkbox' => esc_html__('Checkbox', 'dynamic-page-manager'),
                ],
                'condition' => [
                    'content_type' => 'dynamic_content',
                ],
            ]
        );

        $this->add_control(
            'dynamic_content_name',
            [
                'label' => esc_html__('Name', 'dynamic-page-manager'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'field_name',
                'condition' => [
                    'content_type' => 'dynamic_content',
                ],
            ]
        );

        $this->add_control(
            'dynamic_content_title',
            [
                'label' => esc_html__('Title', 'dynamic-page-manager'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'Field Title',
                'condition' => [
                    'content_type' => 'dynamic_content',
                ],
            ]
        );

        // Dynamic Loop Controls
        $this->add_control(
            'loop_type',
            [
                'label' => esc_html__('Loop Type', 'dynamic-page-manager'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'start',
                'options' => [
                    'start' => esc_html__('Loop Start', 'dynamic-page-manager'),
                    'end' => esc_html__('Loop End', 'dynamic-page-manager'),
                ],
                'condition' => [
                    'content_type' => 'dynamic_loop',
                ],
            ]
        );

        $this->add_control(
            'loop_name',
            [
                'label' => esc_html__('Loop Name', 'dynamic-page-manager'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'steps',
                'condition' => [
                    'content_type' => 'dynamic_loop',
                ],
            ]
        );

        // Loop Content Controls
        $this->add_control(
            'loop_content_type',
            [
                'label' => esc_html__('Input Type', 'dynamic-page-manager'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'text',
                'options' => [
                    'text' => esc_html__('Text', 'dynamic-page-manager'),
                    'text_area' => esc_html__('Text Area', 'dynamic-page-manager'),
                    'number' => esc_html__('Number', 'dynamic-page-manager'),
                    'email' => esc_html__('Email', 'dynamic-page-manager'),
                    'url' => esc_html__('URL', 'dynamic-page-manager'),
                    'select' => esc_html__('Select', 'dynamic-page-manager'),
                    'checkbox' => esc_html__('Checkbox', 'dynamic-page-manager'),
                ],
                'condition' => [
                    'content_type' => 'loop_content',
                ],
            ]
        );

        $this->add_control(
            'loop_content_name',
            [
                'label' => esc_html__('Name', 'dynamic-page-manager'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'steps_title',
                'condition' => [
                    'content_type' => 'loop_content',
                ],
            ]
        );

        $this->add_control(
            'loop_content_title',
            [
                'label' => esc_html__('Title', 'dynamic-page-manager'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'Steps Title',
                'condition' => [
                    'content_type' => 'loop_content',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function dynapama_render() {
        $settings = $this->get_settings_for_display();
    
        // Based on selected option, dynapama_render the appropriate output
        switch ($settings['content_type']) {
            case 'dynamic_content':
                echo '<div class="dynapama-dynamic-content-widget">';
                echo '<div class="dynapama-block-header dynapama-dynamic-header">Dynamic Content</div>';
                echo '<div class="dynapama-widget-content">{{{rdynamic_content type=\'' . esc_attr($settings['dynamic_content_type']) . '\' name=\'' .
                    esc_attr($settings['dynamic_content_name']) . '\' title=\'' . esc_attr($settings['dynamic_content_title']) . '\'}}}</div>';
                echo '</div>';
                break;
    
            case 'dynamic_loop':
                if ($settings['loop_type'] === 'start') {
                    echo '<div class="dynapama-dynamic-loop-widget">';
                    echo '<div class="dynapama-block-header dynapama-loop-header">Dynamic Loop - Start</div>';
                    echo '<div class="dynapama-widget-content">{{{rdynamic_content_loop_start name=\'' . esc_attr($settings['loop_name']) . '\'}}}</div>';
                    echo '</div>';
                } else {
                    echo '<div class="dynapama-dynamic-loop-widget">';
                    echo '<div class="dynapama-block-header dynapama-loop-header">Dynamic Loop - Ends</div>';
                    echo '<div class="dynapama-widget-content">{{{rdynamic_content_loop_ends name=\'' . esc_attr($settings['loop_name']) . '\'}}}</div>';
                    echo '</div>';
                }
                break;
    
            case 'loop_content':
                echo '<div class="dynapama-dynamic-loop-content-widget">';
                echo '<div class="dynapama-block-header dynapama-content-header">Loop Content</div>';
                echo '<div class="dynapama-widget-content">{{{loop_content type=\'' . esc_attr($settings['loop_content_type']) . '\' name=\'' .
                    esc_attr($settings['loop_content_name']) . '\' title=\'' . esc_attr($settings['loop_content_title']) . '\'}}}</div>';
                echo '</div>';
                break;
        }
    }
}

// Register the widget
function dynapama_register_dynamic_content_widget()
{
    \Elementor\Plugin::instance()->widgets_manager->register(new DYNAPAMA_Elementor_Dynamic_Content_Management_Widget());
}
add_action('elementor/widgets/register', 'dynapama_register_dynamic_content_widget');


