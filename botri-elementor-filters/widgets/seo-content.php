<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Botri_Elementor_SEO_Content_Widget extends \Elementor\Widget_Base {

    public function get_name()       { return 'botri_seo_content'; }
    public function get_title()      { return 'Botri SEO Content'; }
    public function get_icon()       { return 'eicon-text-area'; }
    public function get_categories() { return [ 'woocommerce-elements' ]; }
    public function get_keywords()   { return [ 'botri', 'seo', 'content', 'description', 'filter' ]; }

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
        $this->start_controls_section( 'section_content', [
            'label' => 'تنظیمات محتوا',
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'show_fallback', [
            'label'        => 'نمایش توضیحات دسته‌بندی به عنوان پیش‌فرض؟',
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'label_on'     => 'بله',
            'label_off'    => 'خیر',
            'return_value' => 'yes',
            'default'      => 'yes',
        ] );

        $this->add_control( 'fallback_text', [
            'label'     => 'متن پیش‌فرض سفارشی',
            'type'      => \Elementor\Controls_Manager::WYSIWYG,
            'default'   => '',
            'condition' => [ 'show_fallback' => '' ],
        ] );

        $this->end_controls_section();

        $this->start_controls_section( 'section_style', [
            'label' => 'استایل',
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ] );

        $this->add_control( 'text_color', [
            'label'     => 'رنگ متن',
            'type'      => \Elementor\Controls_Manager::COLOR,
            'selectors' => [ '{{WRAPPER}} .botri-seo-content' => 'color: {{VALUE}};' ],
        ] );

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
            'name'     => 'typography',
            'selector' => '{{WRAPPER}} .botri-seo-content',
        ] );

        $this->add_responsive_control( 'align', [
            'label'     => 'تراز',
            'type'      => \Elementor\Controls_Manager::CHOOSE,
            'options'   => [
                'left'    => [ 'title' => 'چپ',       'icon' => 'eicon-text-align-left' ],
                'center'  => [ 'title' => 'وسط',      'icon' => 'eicon-text-align-center' ],
                'right'   => [ 'title' => 'راست',     'icon' => 'eicon-text-align-right' ],
                'justify' => [ 'title' => 'تراز کامل','icon' => 'eicon-text-align-justify' ],
            ],
            'selectors' => [ '{{WRAPPER}} .botri-seo-content' => 'text-align: {{VALUE}};' ],
        ] );

        $this->add_responsive_control( 'padding', [
            'label'      => 'فاصله داخلی',
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px', 'em', '%' ],
            'selectors'  => [
                '{{WRAPPER}} .botri-seo-content' =>
                    'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ] );

        $this->end_controls_section();
    }

    /**
     * ✅ FIX v2.3: محاسبه مستقیم content از قانون در هر render - ضد کش
     *
     * مشکل قبلی: do_shortcode('[botri_dynamic_content]') که apply_filters('botri_seo_content', '')
     * را صدا می‌زد، ممکن بود توسط Elementor کش شود.
     *
     * راه‌حل: مستقیم از CPT قانون می‌خوانیم - کاملاً ضد کش.
     */
    protected function render() {
        $settings = $this->get_settings_for_display();

        // در Editor - پیش‌نمایش ثابت
        if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
            echo '<div class="botri-seo-content" style="padding: 10px; background: #f0f0f1; border: 1px dashed #ccc; text-align: center; color: #666; font-size: 13px;">';
            echo 'محتوای SEO در صفحات دسته‌بندی نمایش داده می‌شود';
            echo '</div>';
            return;
        }

        $content = '';

        // ✅ FIX v2.3: مستقیم از قانون می‌خوانیم (نه از filter که ممکن است stale باشد)
        if ( is_product_category() ) {
            $content = $this->get_content_direct();
        }

        // fallback
        if ( empty( $content ) && 'yes' === $settings['show_fallback'] ) {
            $cat = get_queried_object();
            if ( $cat && isset( $cat->description ) && ! empty( $cat->description ) ) {
                $content = $cat->description;
            }
        }

        if ( empty( $content ) && ! empty( $settings['fallback_text'] ) ) {
            $content = $settings['fallback_text'];
        }

        if ( empty( $content ) ) return;

        echo '<div class="botri-seo-content">' . wp_kses_post( $content ) . '</div>';
    }

    /**
     * ✅ FIX v2.3: پیدا کردن content مستقیم از قانون مطابق
     * بدون استفاده از filter - ضد کش اشتباه
     */
    private function get_content_direct() {
        if ( ! is_product_category() ) return '';

        $cat = get_queried_object();
        if ( ! $cat || ! isset( $cat->term_id ) ) return '';

        $rules = get_posts( [
            'post_type'     => 'filter_seo_rule',
            'numberposts'   => -1,
            'post_status'   => 'publish',
            'no_found_rows' => true,
            'cache_results' => false, // ضد کش
        ] );

        foreach ( $rules as $r ) {
            $tax_input = get_post_meta( $r->ID, '_taxonomy', true );
            $term      = get_post_meta( $r->ID, '_term',     true );
            $cats      = (array) get_post_meta( $r->ID, '_cats', true );

            if ( empty( $tax_input ) || empty( $term ) || empty( $cats ) ) continue;
            if ( ! in_array( $cat->term_id, $cats, true ) ) continue;

            $tax_real = str_starts_with( $tax_input, 'pa_' ) ? $tax_input : 'pa_' . $tax_input;
            $q_key    = ltrim( preg_replace( '/^pa_/', '', $tax_real ) );

            if ( isset( $_GET[ $q_key ] ) && sanitize_title( $_GET[ $q_key ] ) === $term ) {
                $content = get_post_meta( $r->ID, '_content', true );
                if ( ! empty( $content ) ) {
                    return $content;
                }
            }
        }

        return '';
    }

    protected function content_template() {
        ?>
        <div class="botri-seo-content" style="padding: 10px; background: #f0f0f1; border: 1px dashed #ccc; text-align: center; color: #666; font-size: 13px;">
            محتوای SEO در صفحات دسته‌بندی نمایش داده می‌شود
        </div>
        <?php
    }
}