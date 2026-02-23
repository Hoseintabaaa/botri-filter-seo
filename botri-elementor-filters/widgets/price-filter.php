<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Botri_Elementor_Price_Filter_Widget extends \Elementor\Widget_Base {

    public function get_name() { 
        return 'botri_price_filter'; 
    }
    
    public function get_title() { 
        return 'Botri Price Filter'; 
    }
    
    public function get_icon() { 
        return 'eicon-product-price'; 
    }
    
    public function get_categories() { 
        return [ 'woocommerce-elements' ]; 
    }
    
    public function get_keywords() {
        return [ 'botri', 'price', 'filter', 'قیمت', 'فیلتر' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_settings',
            [ 
                'label' => 'تنظیمات فیلتر قیمت',
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        
        $this->add_control(
            'title',
            [
                'label' => 'عنوان',
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'محدوده قیمت',
            ]
        );

        $this->add_control(
            'button_text',
            [
                'label' => 'متن دکمه',
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'اعمال فیلتر قیمت',
            ]
        );

        $this->add_control(
            'show_in_details',
            [
                'label' => 'نمایش در Details؟',
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => 'بله',
                'label_off' => 'خیر',
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->end_controls_section();

        // استایل عنوان
        $this->start_controls_section(
            'section_title_style',
            [
                'label' => 'استایل عنوان',
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => 'رنگ عنوان',
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .botri-filter-group summary, {{WRAPPER}} .botri-price-title' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .botri-filter-group summary, {{WRAPPER}} .botri-price-title',
            ]
        );

        $this->end_controls_section();

        // استایل اسلایدر
        $this->start_controls_section(
            'section_slider_style',
            [
                'label' => 'استایل اسلایدر',
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'slider_color',
            [
                'label' => 'رنگ اسلایدر',
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#007bff',
                'selectors' => [
                    '{{WRAPPER}} .ui-slider .ui-slider-range' => 'background: {{VALUE}};',
                    '{{WRAPPER}} .ui-slider .ui-slider-handle' => 'background: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'slider_bg_color',
            [
                'label' => 'رنگ پس‌زمینه اسلایدر',
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#e0e0e0',
                'selectors' => [
                    '{{WRAPPER}} .ui-slider' => 'background: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // استایل دکمه
        $this->start_controls_section(
            'section_button_style',
            [
                'label' => 'استایل دکمه',
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'button_bg_color',
            [
                'label' => 'رنگ پس‌زمینه دکمه',
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#007bff',
                'selectors' => [
                    '{{WRAPPER}} .botri-price-submit' => 'background: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_text_color',
            [
                'label' => 'رنگ متن دکمه',
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .botri-price-submit' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .botri-price-submit',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        if ( ! is_product_category() && ! is_shop() ) {
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                echo '<div class="elementor-alert elementor-alert-warning">';
                echo 'این ویجت فقط در صفحات فروشگاه و دسته‌بندی محصولات نمایش داده می‌شود.';
                echo '</div>';
            }
            return;
        }

        // محاسبه min و max قیمت از محصولات
        $prices = $this->get_filtered_price();
        $min_price = $prices['min_price'];
        $max_price = $prices['max_price'];

        // چک کردن کوکی برای مقادیر قبلی
        $current_min = $min_price;
        $current_max = $max_price;
        
        if ( isset( $_COOKIE['botri_nonseo_filters'] ) ) {
            $filters = json_decode( stripslashes( $_COOKIE['botri_nonseo_filters'] ), true );
            if ( isset( $filters['min_price'] ) ) {
                $current_min = intval( $filters['min_price'] );
            }
            if ( isset( $filters['max_price'] ) ) {
                $current_max = intval( $filters['max_price'] );
            }
        }

        $widget_id = 'botri-price-slider-' . $this->get_id();

        echo '<div class="botri-elementor-price-filter">';

        if ( 'yes' === $settings['show_in_details'] ) {
            echo '<details class="botri-filter-group botri-nonseo" open>';
            echo '<summary>' . esc_html( $settings['title'] ) . '</summary>';
            echo '<div class="botri-filter-options">';
        } else {
            echo '<h3 class="botri-price-title">' . esc_html( $settings['title'] ) . '</h3>';
        }

        ?>
        <div class="botri-price-filter-wrapper">
            <div id="<?php echo esc_attr( $widget_id ); ?>" 
                 class="botri-price-slider-el" 
                 data-min="<?php echo esc_attr( $min_price ); ?>" 
                 data-max="<?php echo esc_attr( $max_price ); ?>"
                 data-current-min="<?php echo esc_attr( $current_min ); ?>"
                 data-current-max="<?php echo esc_attr( $current_max ); ?>">
            </div>
            
            <div class="botri-price-range-display">
                <span id="<?php echo esc_attr( $widget_id ); ?>-range" class="botri-price-range">
                    <?php echo wc_price( $current_min ); ?> - <?php echo wc_price( $current_max ); ?>
                </span>
            </div>
            
            <input type="hidden" 
                   id="<?php echo esc_attr( $widget_id ); ?>-min" 
                   class="botri-min-price" 
                   name="min_price" 
                   value="<?php echo esc_attr( $current_min ); ?>">
            
            <input type="hidden" 
                   id="<?php echo esc_attr( $widget_id ); ?>-max" 
                   class="botri-max-price" 
                   name="max_price" 
                   value="<?php echo esc_attr( $current_max ); ?>">
            
            <button type="button" class="button botri-price-submit">
                <?php echo esc_html( $settings['button_text'] ); ?>
            </button>
        </div>
        <?php

        if ( 'yes' === $settings['show_in_details'] ) {
            echo '</div></details>';
        }

        echo '</div>';

        // اضافه کردن اسکریپت inline برای initialize کردن این slider خاص
        $this->render_slider_script( $widget_id, $min_price, $max_price, $current_min, $current_max );
    }

    private function render_slider_script( $widget_id, $min_price, $max_price, $current_min, $current_max ) {
        ?>
        <script>
        jQuery(document).ready(function($) {
            if (typeof $.fn.slider === 'undefined') {
                console.error('jQuery UI Slider not loaded');
                return;
            }

            var sliderId = '<?php echo esc_js( $widget_id ); ?>';
            var $slider = $('#' + sliderId);
            
            if ($slider.length === 0) {
                return;
            }

            // جلوگیری از initialize مجدد
            if ($slider.hasClass('ui-slider')) {
                return;
            }

            var isRTL = $('html').attr('dir') === 'rtl';
            var minPrice = parseInt(<?php echo esc_js( $min_price ); ?>);
            var maxPrice = parseInt(<?php echo esc_js( $max_price ); ?>);
            var currentMin = parseInt(<?php echo esc_js( $current_min ); ?>);
            var currentMax = parseInt(<?php echo esc_js( $current_max ); ?>);

            function formatPrice(price) {
                if (typeof wc_price_settings === 'undefined') {
                    return price;
                }
                
                var formattedPrice = parseFloat(price).toFixed(wc_price_settings.decimals);
                formattedPrice = formattedPrice.replace('.', wc_price_settings.decimal_separator);
                
                var parts = formattedPrice.split(wc_price_settings.decimal_separator);
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, wc_price_settings.thousand_separator);
                formattedPrice = parts.join(wc_price_settings.decimal_separator);
                
                return wc_price_settings.price_format
                    .replace('%1$s', wc_price_settings.currency_symbol)
                    .replace('%2$s', formattedPrice);
            }

            var sliderConfig = {
                range: true,
                min: minPrice,
                max: maxPrice,
                values: [currentMin, currentMax],
                slide: function(event, ui) {
                    $('#' + sliderId + '-min').val(ui.values[0]);
                    $('#' + sliderId + '-max').val(ui.values[1]);
                    $('#' + sliderId + '-range').html(formatPrice(ui.values[0]) + ' - ' + formatPrice(ui.values[1]));
                }
            };

            if (isRTL) {
                sliderConfig.isRTL = true;
            }

            try {
                $slider.slider(sliderConfig);
                console.log('✅ Price slider initialized:', sliderId);
            } catch(e) {
                console.error('❌ Slider error:', e);
            }
        });
        </script>
        <?php
    }

    private function get_filtered_price() {
        global $wpdb;

        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        );

        // اگر در دسته‌بندی هستیم
        if ( is_product_category() ) {
            $cat = get_queried_object();
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => $cat->term_id,
                )
            );
        }

        $query = new WP_Query( $args );
        $product_ids = $query->posts;

        if ( empty( $product_ids ) ) {
            return array(
                'min_price' => 0,
                'max_price' => 1000000,
            );
        }

        $product_ids_str = implode( ',', array_map( 'absint', $product_ids ) );

        $min_price = $wpdb->get_var( 
            "SELECT MIN(CAST(meta_value AS UNSIGNED)) 
             FROM {$wpdb->postmeta} 
             WHERE meta_key = '_price' 
             AND post_id IN ({$product_ids_str})"
        );

        $max_price = $wpdb->get_var( 
            "SELECT MAX(CAST(meta_value AS UNSIGNED)) 
             FROM {$wpdb->postmeta} 
             WHERE meta_key = '_price' 
             AND post_id IN ({$product_ids_str})"
        );

        return array(
            'min_price' => $min_price ? intval( $min_price ) : 0,
            'max_price' => $max_price ? intval( $max_price ) : 1000000,
        );
    }

    protected function content_template() {
        ?>
        <#
        var title = settings.title || 'محدوده قیمت';
        var buttonText = settings.button_text || 'اعمال فیلتر قیمت';
        var showInDetails = settings.show_in_details === 'yes';
        #>
        
        <div class="botri-elementor-price-filter">
            <# if ( showInDetails ) { #>
                <details class="botri-filter-group botri-nonseo" open>
                    <summary>{{{ title }}}</summary>
                    <div class="botri-filter-options">
            <# } else { #>
                <h3 class="botri-price-title">{{{ title }}}</h3>
            <# } #>
            
                        <div class="botri-price-filter-wrapper">
                            <div class="botri-price-slider-el" style="height: 8px; background: #e0e0e0; border-radius: 4px; position: relative;">
                                <div style="position: absolute; left: 20%; right: 20%; height: 100%; background: #007bff; border-radius: 4px;"></div>
                            </div>
                            <div class="botri-price-range-display">
                                <span class="botri-price-range">2,450 تومان - 16,400 تومان</span>
                            </div>
                            <button type="button" class="button botri-price-submit">{{{ buttonText }}}</button>
                        </div>
            
            <# if ( showInDetails ) { #>
                    </div>
                </details>
            <# } #>
        </div>
        <?php
    }
}