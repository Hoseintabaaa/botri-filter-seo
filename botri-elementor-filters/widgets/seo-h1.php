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

    /**
     * ✅ FIX v2.6: جلوگیری از کش شدن ویجت در المنتور
     */
    public function get_stack_runtime_config() {
        return [
            'is_dynamic' => true,
        ];
    }

    /**
     * ✅ FIX v2.6: سیگنال به المنتور برای عدم کش خروجی HTML
     */
    protected function is_dynamic_content() {
        return true;
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

        // در ادمین المنتور
        if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
            $html_tag = $settings['html_tag'];
            printf('<%1$s class="botri-seo-h1" style="opacity: 0.6; border: 1px dashed #ccc; padding: 5px;">%2$s</%1$s>',
                tag_escape($html_tag),
                'عنوان H1 داینامیک (در سایت نمایش داده می‌شود)'
            );
            return;
        }

        if ( ! is_product_category() && ! is_shop() ) {
            return;
        }

        $h1 = '';

        // ✅ FIX v2.6: دریافت مستقیم برای اطمینان 100% از عدم کش
        if ( is_product_category() ) {
            $h1 = $this->get_h1_direct();
        }

        // اگر مستقیم پیدا نشد، از فیلتر (runtime) استفاده کن
        if ( empty( $h1 ) ) {
            $h1 = apply_filters( 'botri_seo_h1', '' );
        }

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

    /**
     * ✅ FIX v2.6: دریافت H1 مستقیم از دیتابیس (ضد کش)
     */
    private function get_h1_direct() {
        if ( ! is_product_category() ) return '';

        $cat = get_queried_object();
        if ( ! $cat || ! isset( $cat->term_id ) ) return '';

        $rules = get_posts( [
            'post_type'     => 'filter_seo_rule',
            'numberposts'   => -1,
            'post_status'   => 'publish',
            'no_found_rows' => true,
            'cache_results' => false, // جلوگیری از کش WP_Query
        ] );

        foreach ( $rules as $r ) {
            $tax_input = get_post_meta( $r->ID, '_taxonomy', true );
            $term      = get_post_meta( $r->ID, '_term',     true );
            $cats      = (array) get_post_meta( $r->ID, '_cats', true );

            if ( empty( $tax_input ) || empty( $term ) || empty( $cats ) ) continue;
            if ( ! in_array( $cat->term_id, $cats, true ) ) continue;

            $tax_real = ( 0 === strpos( $tax_input, 'pa_' ) ) ? $tax_input : 'pa_' . $tax_input;
            $q_key    = ltrim( preg_replace( '/^pa_/', '', $tax_real ) );

            if ( isset( $_GET[ $q_key ] ) && sanitize_title( $_GET[ $q_key ] ) === $term ) {
                $val = get_post_meta( $r->ID, '_h1', true );
                if ( ! empty( $val ) ) return $val;
            }
        }

        return '';
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