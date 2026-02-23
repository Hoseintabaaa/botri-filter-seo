<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Botri_Elementor_SEO_H1_Widget extends \Elementor\Widget_Base {
    
    public function get_name() { 
        return 'botri_seo_h1'; 
    }
    
    public function get_title() { 
        return 'Botri SEO H1'; 
    }
    
    public function get_icon() { 
        return 'eicon-heading'; 
    }
    
    public function get_categories() { 
        return [ 'woocommerce-elements' ]; 
    }
    
    public function get_keywords() {
        return [ 'botri', 'seo', 'h1', 'heading', 'filter' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => 'تنظیمات H1',
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'html_tag',
            [
                'label' => 'تگ HTML',
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'h1' => 'H1',
                    'h2' => 'H2',
                    'h3' => 'H3',
                    'div' => 'DIV',
                ],
                'default' => 'h1',
            ]
        );

        $this->add_control(
            'fallback_text',
            [
                'label' => 'متن پیش‌فرض (در صورت نبود فیلتر)',
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '',
                'placeholder' => 'عنوان دسته‌بندی نمایش داده می‌شود',
            ]
        );

        $this->end_controls_section();

        // استایل
        $this->start_controls_section(
            'section_style',
            [
                'label' => 'استایل',
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'text_color',
            [
                'label' => 'رنگ متن',
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .botri-seo-h1' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'typography',
                'selector' => '{{WRAPPER}} .botri-seo-h1',
            ]
        );

        $this->add_responsive_control(
            'align',
            [
                'label' => 'تراز',
                'type' => \Elementor\Controls_Manager::CHOOSE,
                'options' => [
                    'left' => [
                        'title' => 'چپ',
                        'icon' => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => 'وسط',
                        'icon' => 'eicon-text-align-center',
                    ],
                    'right' => [
                        'title' => 'راست',
                        'icon' => 'eicon-text-align-right',
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .botri-seo-h1' => 'text-align: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        
        if ( ! is_product_category() && ! is_shop() ) {
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                echo '<div class="elementor-alert elementor-alert-warning">';
                echo 'این ویجت فقط در صفحات دسته‌بندی محصولات نمایش داده می‌شود.';
                echo '</div>';
            }
            return;
        }

        $h1 = '';

        // ⚠️ CRITICAL FIX: دریافت H1 به صورت مستقیم در همین لحظه
        // بدون استفاده از کش یا فیلتر قبلی
        $h1 = apply_filters( 'botri_seo_h1', '' );

        // اگر خالی بود، از fallback یا عنوان دسته استفاده کن
        if ( empty( $h1 ) ) {
            if ( ! empty( $settings['fallback_text'] ) ) {
                $h1 = $settings['fallback_text'];
            } else {
                $cat = get_queried_object();
                if ( $cat && isset( $cat->name ) ) {
                    $h1 = $cat->name;
                }
            }
        }

        if ( empty( $h1 ) ) {
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                $h1 = 'عنوان H1 اینجا نمایش داده می‌شود';
            } else {
                return;
            }
        }

        $html_tag = $settings['html_tag'];

        // ⚠️ اضافه کردن data attribute برای debugging
        printf(
            '<%1$s class="botri-seo-h1" data-timestamp="%3$s">%2$s</%1$s>',
            tag_escape( $html_tag ),
            esc_html( $h1 ),
            time()
        );
    }

    protected function content_template() {
        ?>
        <#
        var htmlTag = settings.html_tag || 'h1';
        var fallbackText = settings.fallback_text || 'عنوان H1 اینجا نمایش داده می‌شود';
        #>
        <{{{ htmlTag }}} class="botri-seo-h1">{{{ fallbackText }}}</{{{ htmlTag }}}>
        <?php
    }
}