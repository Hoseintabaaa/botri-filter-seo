<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Botri_Elementor_NonSEO_Filters_Widget extends \Elementor\Widget_Base {
    
    public function get_name() { 
        return 'botri_nonseo_filters'; 
    }
    
    public function get_title() { 
        return 'Botri Non-SEO Filters'; 
    }
    
    public function get_icon() { 
        return 'eicon-filter'; 
    }
    
    public function get_categories() { 
        return [ 'woocommerce-elements' ]; 
    }
    
    public function get_keywords() {
        return [ 'botri', 'nonseo', 'filter', 'فیلتر', 'غیرسئو' ];
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
                'label' => 'نمایش عنوان فیلترها؟',
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
                'label' => 'عنوان فیلترها',
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'فیلتر محصولات',
                'condition' => [
                    'show_title' => 'yes',
                ],
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

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        
        // چک کنیم که در Shop یا Category هستیم
        if ( ! is_product_category() && ! is_shop() ) {
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                echo '<div class="elementor-alert elementor-alert-warning">';
                echo 'این ویجت فقط در صفحات دسته‌بندی محصولات و صفحه Shop نمایش داده می‌شود.';
                echo '</div>';
            }
            return;
        }

        $cat = null;
        $is_shop = is_shop();
        
        if ( ! $is_shop ) {
            $cat = get_queried_object();
            if ( ! $cat ) return;
        }

        // جمع‌آوری فیلترهای SEO
        $seo_filters = $this->collect_seo_filters( $cat, $is_shop );
        
        // جمع‌آوری فیلترهای Non-SEO
        $nonseo_filters = $this->collect_nonseo_filters( $cat, $is_shop );
        
        if ( empty( $seo_filters ) && empty( $nonseo_filters ) ) {
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                echo '<div class="elementor-alert elementor-alert-info">';
                if ( $is_shop ) {
                    echo 'هیچ فیلتری برای صفحه Shop تعریف نشده است.';
                } else {
                    echo 'هیچ فیلتری برای این دسته‌بندی وجود ندارد.';
                }
                echo '</div>';
            }
            return;
        }

        echo '<div class="botri-elementor-nonseo-filters">';
        
        if ( 'yes' === $settings['show_title'] ) {
            echo '<h3 class="botri-filter-title">' . esc_html( $settings['title_text'] ) . '</h3>';
        }
        
        // نمایش فیلترهای SEO
        foreach ( $seo_filters as $tax => $group ) {
            echo '<details class="botri-filter-group botri-seo">';
            echo '<summary>' . esc_html( $group['label'] ) . '</summary>';
            echo '<div class="botri-filter-options">';
            foreach ( $group['terms'] as $t ) {
                // ✅ FIX: شروع از URL پایه دسته‌بندی (بدون پارامترهای قدیمی)
                // اگر از add_query_arg() بدون base استفاده کنیم، پارامتر قبلی باقی می‌ماند
                // مثال اشتباه: /pet-bottle/?use-type=juice&shape=ketabi → 404
                // مثال درست:  /pet-bottle/?shape=ketabi
                $base_url  = $cat ? get_term_link( $cat ) : home_url( '/' );
                $url       = add_query_arg( $tax, $t->slug, $base_url );
                $is_active = ( isset( $_GET[ $tax ] ) && $_GET[ $tax ] === $t->slug );
                $active    = $is_active ? 'active' : '';
                // toggle: کلیک روی فیلتر فعال → برگشت به دسته‌بندی بدون فیلتر
                $href = $is_active ? esc_url( $base_url ) : esc_url( $url );
                echo '<a href="' . $href . '" class="botri-filter-item botri-seo-item ' . $active . '">' . esc_html( $t->name ) . '</a>';
            }
            echo '</div></details>';
        }
        
        // نمایش فیلترهای Non-SEO
        foreach ( $nonseo_filters as $tax => $group ) {
            echo '<details class="botri-filter-group botri-nonseo">';
            echo '<summary>' . esc_html( $group['label'] ) . '</summary>';
            echo '<div class="botri-filter-options">';
            
            foreach ( $group['terms'] as $t ) {
                $active = '';
                $cookie = json_decode( stripslashes( $_COOKIE['botri_nonseo_filters'] ?? '{}' ), true );
                $filter_key = 'filter_' . $tax;
                if ( isset( $cookie[ $filter_key ] ) && in_array( $t->slug, explode( ',', $cookie[ $filter_key ] ) ) ) {
                    $active = 'active';
                }
                
                echo '<a href="javascript:void(0);" data-slug="' . esc_attr( $t->slug ) . '" data-tax="' . esc_attr( $tax ) . '" class="botri-filter-item botri-nonseo-item ' . $active . '">' . esc_html( $t->name ) . '</a>';
            }
            
            echo '</div></details>';
        }
        
        echo '</div>';
    }

    /**
     * ✅ جمع‌آوری فیلترهای SEO
     */
    private function collect_seo_filters( $cat, $is_shop ) {
        $filters_by_tax = [];
        
        if ( $is_shop ) return $filters_by_tax; // فیلترهای SEO فقط برای دسته‌بندی‌ها
        
        $rules = get_posts( [ 'post_type' => 'filter_seo_rule', 'numberposts' => -1, 'post_status' => 'publish' ] );
        
        foreach ( $rules as $r ) {
            $tax_input = get_post_meta( $r->ID, '_taxonomy', true );
            $term = get_post_meta( $r->ID, '_term', true );
            $cats = (array) get_post_meta( $r->ID, '_cats', true );

            if ( in_array( $cat->term_id, $cats ) && $tax_input && $term ) {
                $tax_real = ( 0 === strpos( $tax_input, 'pa_' ) ) ? $tax_input : 'pa_' . $tax_input;
                $q_key = ltrim( preg_replace( '/^pa_/', '', $tax_real ) );

                $term_obj = get_term_by( 'slug', $term, $tax_real );
                if ( $term_obj && ! is_wp_error( $term_obj ) ) {
                    $label = get_taxonomy( $tax_real )->label ?? $q_key;
                    
                    // حذف کلمه "محصول"
                    $label = str_replace( 'محصول ', '', $label );
                    $label = str_replace( 'Product ', '', $label );
                    
                    $filters_by_tax[ $q_key ]['label'] = $label;
                    $filters_by_tax[ $q_key ]['terms'][] = $term_obj;
                }
            }
        }
        
        return $filters_by_tax;
    }

    /**
     * ✅ جمع‌آوری فیلترهای Non-SEO
     */
    private function collect_nonseo_filters( $cat, $is_shop ) {
        $nonseo_by_tax = [];
        $nonseo_rules = get_posts( [ 'post_type' => 'filter_nonseo_rule', 'numberposts' => -1, 'post_status' => 'publish' ] );
        
        foreach ( $nonseo_rules as $r ) {
            $tax_input = get_post_meta( $r->ID, '_taxonomy', true );
            $terms_ids = (array) get_post_meta( $r->ID, '_terms', true );
            $cats = (array) get_post_meta( $r->ID, '_cats', true );
            $enable_shop = get_post_meta( $r->ID, '_enable_shop', true );

            $should_show = false;
            
            if ( $is_shop && $enable_shop === '1' ) {
                $should_show = true;
            } elseif ( ! $is_shop && in_array( $cat->term_id, $cats ) ) {
                $should_show = true;
            }

            if ( $should_show && $tax_input ) {
                $tax_real = ( 0 === strpos( $tax_input, 'pa_' ) ) ? $tax_input : 'pa_' . $tax_input;
                $q_key = ltrim( preg_replace( '/^pa_/', '', $tax_real ) );
                $label = get_taxonomy( $tax_real )->label ?? $q_key;
                
                // حذف کلمه "محصول"
                $label = str_replace( 'محصول ', '', $label );
                $label = str_replace( 'Product ', '', $label );
                
                $terms = [];
                
                foreach ( $terms_ids as $tid ) {
                    $term = get_term( $tid, $tax_real );
                    if ( $term && ! is_wp_error( $term ) ) $terms[] = $term;
                }
                
                if ( ! empty( $terms ) ) {
                    $nonseo_by_tax[ $q_key ]['label'] = $label;
                    $nonseo_by_tax[ $q_key ]['terms'] = $terms;
                }
            }
        }
        
        return $nonseo_by_tax;
    }

    protected function content_template() {
        ?>
        <#
        var showTitle = settings.show_title === 'yes';
        var titleText = settings.title_text || 'فیلتر محصولات';
        #>
        
        <div class="botri-elementor-nonseo-filters">
            <# if ( showTitle ) { #>
                <h3 class="botri-filter-title">{{{ titleText }}}</h3>
            <# } #>
            
            <details class="botri-filter-group botri-seo" open>
                <summary>کاربرد</summary>
                <div class="botri-filter-options">
                    <a href="#" class="botri-filter-item">بطری آب</a>
                    <a href="#" class="botri-filter-item active">بطری آبمیوه</a>
                </div>
            </details>
            
            <details class="botri-filter-group botri-nonseo" open>
                <summary>دهانه</summary>
                <div class="botri-filter-options">
                    <a href="#" class="botri-filter-item">دهانه گشاد</a>
                    <a href="#" class="botri-filter-item">دهانه باریک</a>
                </div>
            </details>
        </div>
        <?php
    }
}