<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Botri_Elementor_Image_With_Filter_Widget extends \Elementor\Widget_Base {
    
    public function get_name() { 
        return 'botri_image_with_filter'; 
    }
    
    public function get_title() { 
        return 'Botri Image + Filter Link'; 
    }
    
    public function get_icon() { 
        return 'eicon-image-hotspot'; 
    }
    
    public function get_categories() { 
        return [ 'general' ]; 
    }
    
    public function get_keywords() {
        return [ 'botri', 'image', 'filter', 'link', 'banner' ];
    }

    protected function register_controls() {
        
        // بخش تصویر
        $this->start_controls_section(
            'section_image',
            [
                'label' => '🖼️ تصویر بنر',
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'image',
            [
                'label' => 'انتخاب تصویر',
                'type' => \Elementor\Controls_Manager::MEDIA,
                'default' => [
                    'url' => \Elementor\Utils::get_placeholder_image_src(),
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Image_Size::get_type(),
            [
                'name' => 'image',
                'default' => 'full',
            ]
        );

        $this->end_controls_section();

        // بخش فیلتر
        $this->start_controls_section(
            'section_filter',
            [
                'label' => '🔗 لینک دهی به نان‌سئو فیلتر',
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        // انتخاب دسته‌بندی
        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ]);
        
        $cat_options = [ '' => '-- انتخاب کنید --' ];
        if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
            foreach ( $categories as $cat ) {
                $cat_options[ $cat->term_id ] = $cat->name;
            }
        }

        $this->add_control(
            'target_category',
            [
                'label' => '📦 دسته‌بندی مقصد',
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => $cat_options,
                'default' => '',
                'description' => 'دسته‌بندی که کاربر به آن هدایت می‌شود',
            ]
        );

        // نمایش فیلترهای موجود
        $this->add_control(
            'info_filters',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => $this->get_available_filters_html(),
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
            ]
        );

        // چند فیلتر می‌توان اضافه کرد
        $this->add_control(
            'filter_1',
            [
                'label' => '🔹 فیلتر 1',
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => 'مثال: use-type:oil-shop',
                'description' => 'فرمت: taxonomy:term-slug',
                'label_block' => true,
            ]
        );

        $this->add_control(
            'filter_2',
            [
                'label' => '🔹 فیلتر 2',
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => 'مثال: neck:wide-mouth',
                'description' => 'فرمت: taxonomy:term-slug',
                'label_block' => true,
            ]
        );

        $this->add_control(
            'filter_3',
            [
                'label' => '🔹 فیلتر 3',
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => 'مثال: color:transparent',
                'description' => 'فرمت: taxonomy:term-slug',
                'label_block' => true,
            ]
        );

        $this->end_controls_section();

        // استایل تصویر
        $this->start_controls_section(
            'section_style_image',
            [
                'label' => '🎨 استایل تصویر',
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'width',
            [
                'label' => 'عرض',
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => [ '%', 'px', 'vw' ],
                'range' => [
                    '%' => [
                        'min' => 1,
                        'max' => 100,
                    ],
                    'px' => [
                        'min' => 1,
                        'max' => 2000,
                    ],
                    'vw' => [
                        'min' => 1,
                        'max' => 100,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .botri-filter-image-wrapper' => 'max-width: {{SIZE}}{{UNIT}};',
                ],
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
                'default' => 'center',
                'selectors' => [
                    '{{WRAPPER}}' => 'text-align: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'image_border',
                'selector' => '{{WRAPPER}} .botri-filter-image',
            ]
        );

        $this->add_responsive_control(
            'border_radius',
            [
                'label' => 'گردی گوشه‌ها',
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%' ],
                'selectors' => [
                    '{{WRAPPER}} .botri-filter-image' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'image_box_shadow',
                'selector' => '{{WRAPPER}} .botri-filter-image',
            ]
        );

        $this->add_control(
            'hover_animation',
            [
                'label' => 'انیمیشن هاور',
                'type' => \Elementor\Controls_Manager::HOVER_ANIMATION,
            ]
        );

        $this->end_controls_section();
    }

    /**
     * تولید HTML فیلترهای موجود
     */
    private function get_available_filters_html() {
        $nonseo_rules = get_posts([
            'post_type' => 'filter_nonseo_rule',
            'numberposts' => -1,
            'post_status' => 'publish'
        ]);

        if ( empty( $nonseo_rules ) ) {
            return '<strong>⚠️ هیچ فیلتر Non-SEO فعالی وجود ندارد!</strong><br>لطفاً ابتدا فیلتر اضافه کنید.';
        }

        $html = '<strong>📋 فیلترهای موجود:</strong><br><br>';
        
        $filters_by_tax = [];
        foreach ( $nonseo_rules as $rule ) {
            $taxonomy = get_post_meta( $rule->ID, '_taxonomy', true );
            $terms_ids = (array) get_post_meta( $rule->ID, '_terms', true );
            
            if ( empty( $taxonomy ) || empty( $terms_ids ) ) continue;
            
            $tax_real = ( 0 === strpos( $taxonomy, 'pa_' ) ) ? $taxonomy : 'pa_' . $taxonomy;
            $tax_key = str_replace( 'pa_', '', $tax_real );
            
            foreach ( $terms_ids as $tid ) {
                $term = get_term( $tid, $tax_real );
                if ( $term && ! is_wp_error( $term ) ) {
                    $filters_by_tax[ $tax_key ][] = [
                        'name' => $term->name,
                        'slug' => $term->slug,
                    ];
                }
            }
        }

        if ( empty( $filters_by_tax ) ) {
            return '<strong>⚠️ هیچ ترمی یافت نشد!</strong>';
        }

        foreach ( $filters_by_tax as $tax => $terms ) {
            $tax_obj = get_taxonomy( 'pa_' . $tax );
            $label = $tax_obj ? $tax_obj->label : $tax;
            
            $html .= '<strong>' . esc_html( $label ) . ':</strong><br>';
            $html .= '<ul style="margin:5px 0 15px 20px; list-style:disc;">';
            
            foreach ( $terms as $term ) {
                $html .= '<li><code>' . esc_html( $tax ) . ':' . esc_html( $term['slug'] ) . '</code> - ' . esc_html( $term['name'] ) . '</li>';
            }
            
            $html .= '</ul>';
        }

        return $html;
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        if ( empty( $settings['image']['url'] ) ) {
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                echo '<div class="elementor-alert elementor-alert-warning">لطفاً تصویر را انتخاب کنید.</div>';
            }
            return;
        }

        // دریافت URL دسته‌بندی
        $category_url = '';
        if ( ! empty( $settings['target_category'] ) ) {
            $term = get_term( $settings['target_category'], 'product_cat' );
            if ( $term && ! is_wp_error( $term ) ) {
                $category_url = get_term_link( $term );
            }
        }

        // جمع‌آوری فیلترها
        $filters = [];
        for ( $i = 1; $i <= 3; $i++ ) {
            $filter = $settings[ 'filter_' . $i ];
            if ( ! empty( $filter ) && strpos( $filter, ':' ) !== false ) {
                list( $tax, $slug ) = explode( ':', $filter, 2 );
                $tax = trim( $tax );
                $slug = trim( $slug );
                
                if ( ! empty( $tax ) && ! empty( $slug ) ) {
                    $key = 'filter_' . $tax;
                    if ( ! isset( $filters[ $key ] ) ) {
                        $filters[ $key ] = [];
                    }
                    $filters[ $key ][] = $slug;
                }
            }
        }

        // تبدیل آرایه‌ها به رشته
        foreach ( $filters as $key => $values ) {
            $filters[ $key ] = implode( ',', $values );
        }

        $filter_data_json = ! empty( $filters ) ? json_encode( $filters ) : '';

        // تولید HTML
        $image_html = \Elementor\Group_Control_Image_Size::get_attachment_image_html( $settings, 'image', 'image' );

        $animation_class = ! empty( $settings['hover_animation'] ) ? 'elementor-animation-' . $settings['hover_animation'] : '';

        ?>
        <div class="botri-filter-image-wrapper">
            <?php if ( ! empty( $category_url ) && ! empty( $filter_data_json ) ): ?>
                <a href="#" 
                   class="botri-filter-link botri-filter-image-link <?php echo esc_attr( $animation_class ); ?>"
                   data-botri-filter-link
                   data-botri-category-url="<?php echo esc_url( $category_url ); ?>"
                   data-botri-filter-data='<?php echo esc_attr( $filter_data_json ); ?>'>
                    <img src="<?php echo esc_url( $settings['image']['url'] ); ?>" 
                         alt="<?php echo esc_attr( \Elementor\Control_Media::get_image_alt( $settings['image'] ) ); ?>"
                         class="botri-filter-image">
                </a>
            <?php else: ?>
                <?php if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ): ?>
                    <div class="elementor-alert elementor-alert-warning">
                        ⚠️ لطفاً دسته‌بندی مقصد و حداقل یک فیلتر را مشخص کنید.
                    </div>
                <?php endif; ?>
                <img src="<?php echo esc_url( $settings['image']['url'] ); ?>" 
                     alt="<?php echo esc_attr( \Elementor\Control_Media::get_image_alt( $settings['image'] ) ); ?>"
                     class="botri-filter-image">
            <?php endif; ?>
        </div>

        <style>
            .botri-filter-image-wrapper {
                display: inline-block;
                max-width: 100%;
            }
            
            .botri-filter-image {
                display: block;
                width: 100%;
                height: auto;
            }
            
            .botri-filter-image-link {
                display: block;
                cursor: pointer;
            }
        </style>
        <?php
    }

    protected function content_template() {
        ?>
        <#
        var categoryUrl = '';
        var filterData = {};
        
        if ( settings.filter_1 ) {
            var parts = settings.filter_1.split(':');
            if ( parts.length === 2 ) {
                filterData['filter_' + parts[0].trim()] = parts[1].trim();
            }
        }
        
        var animationClass = settings.hover_animation ? 'elementor-animation-' + settings.hover_animation : '';
        #>
        
        <div class="botri-filter-image-wrapper">
            <# if ( settings.image.url ) { #>
                <img src="{{ settings.image.url }}" class="botri-filter-image {{ animationClass }}">
            <# } else { #>
                <div class="elementor-alert elementor-alert-warning">لطفاً تصویر را انتخاب کنید.</div>
            <# } #>
        </div>
        <?php
    }
}