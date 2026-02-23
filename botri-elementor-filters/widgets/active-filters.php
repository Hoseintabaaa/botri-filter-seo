<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Botri_Elementor_Active_Filters_Widget extends \Elementor\Widget_Base {
    
    public function get_name() { 
        return 'botri_active_filters'; 
    }
    
    public function get_title() { 
        return 'Botri Active Filters'; 
    }
    
    public function get_icon() { 
        return 'eicon-filter'; 
    }
    
    public function get_categories() { 
        return [ 'woocommerce-elements' ]; 
    }
    
    public function get_keywords() {
        return [ 'botri', 'active', 'filters', 'فیلتر', 'فعال' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_settings',
            [ 
                'label' => 'تنظیمات عمومی',
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        
        $this->add_control(
            'show_title',
            [
                'label' => 'نمایش عنوان فیلترهای فعال؟',
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => 'بله',
                'label_off' => 'خیر',
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );
        
        $this->add_control(
            'title_text',
            [
                'label' => 'عنوان',
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'فیلترهای فعال',
                'condition' => [
                    'show_title' => 'yes',
                ],
            ]
        );
        
        $this->add_control(
            'show_clear_all',
            [
                'label' => 'نمایش دکمه پاک کردن همه فیلترها؟',
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => 'بله',
                'label_off' => 'خیر',
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );
        
        $this->add_control(
            'clear_all_text',
            [
                'label' => 'متن دکمه پاک کردن',
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'پاک کردن همه فیلترها',
                'condition' => [
                    'show_clear_all' => 'yes',
                ],
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
            'title_color',
            [
                'label' => 'رنگ عنوان',
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .botri-filter-title' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .botri-filter-title',
            ]
        );

        $this->add_control(
            'remove_color',
            [
                'label' => 'رنگ لینک حذف',
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .botri-remove-nonseo' => 'color: {{VALUE}};',
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
                echo 'این ویجت فقط در صفحات دسته‌بندی محصولات و صفحه Shop نمایش داده می‌شود.';
                echo '</div>';
            }
            return;
        }
        
        echo '<div class="botri-elementor-active-filters">';
        
        if ( 'yes' === $settings['show_title'] ) {
            echo '<h3 class="botri-filter-title">' . esc_html( $settings['title_text'] ) . '</h3>';
        }
        
        echo '<ul class="botri-active-filters">';
        
        // نمایش فیلترهای SEO از WooCommerce
        the_widget( 'WC_Widget_Layered_Nav_Filters' );
        
        // نمایش فیلترهای Non-SEO از کوکی
        if ( isset( $_COOKIE['botri_nonseo_filters'] ) ) {
            $filters = json_decode( stripslashes( $_COOKIE['botri_nonseo_filters'] ), true );
            
            if ( isset( $filters['min_price'] ) && isset( $filters['max_price'] ) ) {
                echo '<li>قیمت: ' . wc_price( $filters['min_price'] ) . ' - ' . wc_price( $filters['max_price'] ) . ' <a href="#" class="botri-remove-nonseo" data-key="price">حذف</a></li>';
            }
            
            foreach ( $filters as $key => $value ) {
                if ( strpos( $key, 'filter_' ) === 0 ) {
                    $attr = str_replace( 'filter_', '', $key );
                    $term_slugs = explode( ',', $value );
                    foreach ( $term_slugs as $slug ) {
                        $term = get_term_by( 'slug', $slug, 'pa_' . $attr );
                        if ( $term && ! is_wp_error( $term ) ) {
                            echo '<li>' . esc_html( $term->name ) . ' <a href="#" class="botri-remove-nonseo" data-key="' . esc_attr( $key ) . '" data-slug="' . esc_attr( $slug ) . '">حذف</a></li>';
                        }
                    }
                }
            }
        }
        
        echo '</ul>';
        
        if ( 'yes' === $settings['show_clear_all'] ) {
            echo '<a href="#" class="botri-clear-all">' . esc_html( $settings['clear_all_text'] ) . '</a>';
        }
        
        echo '</div>';
    }

    protected function content_template() {
        ?>
        <#
        var showTitle = settings.show_title === 'yes';
        var titleText = settings.title_text || 'فیلترهای فعال';
        var showClearAll = settings.show_clear_all === 'yes';
        var clearAllText = settings.clear_all_text || 'پاک کردن همه فیلترها';
        #>
        
        <div class="botri-elementor-active-filters">
            <# if ( showTitle ) { #>
                <h3 class="botri-filter-title">{{{ titleText }}}</h3>
            <# } #>
            
            <ul class="botri-active-filters">
                <li>بطری آبمیوه <a href="#" class="botri-remove-nonseo">حذف</a></li>
                <li>دهانه گشاد <a href="#" class="botri-remove-nonseo">حذف</a></li>
            </ul>
            
            <# if ( showClearAll ) { #>
                <a href="#" class="botri-clear-all">{{{ clearAllText }}}</a>
            <# } #>
        </div>
        <?php
    }
}