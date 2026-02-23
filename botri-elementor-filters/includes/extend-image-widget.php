<?php
/**
 * Extend Elementor Image Widgets with Filter Link Controls
 * 
 * Adds filter link capability to individual images in:
 * - Image Widget
 * - Gallery Widget (per image)
 * - Image Carousel Widget (per slide)
 * - Container Widget
 * 
 * Supports linking to both Product Categories and Shop page
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Botri_Extend_Image_Widget {
    
    private static $instance = null;
    
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Image Widget
        add_action( 'elementor/element/image/section_image/after_section_end', [ $this, 'add_filter_controls_to_image' ], 10, 2 );
        
        // Gallery Widget
        add_action( 'elementor/element/image-gallery/section_gallery_images/after_section_end', [ $this, 'add_filter_controls_to_gallery' ], 10, 2 );
        
        // Image Carousel Widget
        add_action( 'elementor/element/image-carousel/section_additional_options/after_section_end', [ $this, 'add_filter_controls_to_carousel' ], 10, 2 );
        
        // Container Widget
        add_action( 'elementor/element/container/section_layout/after_section_end', [ $this, 'add_filter_controls_to_container' ], 10, 2 );
        
        // رندر خروجی
        add_action( 'elementor/widget/render_content', [ $this, 'modify_widget_output' ], 10, 2 );
        add_action( 'elementor/frontend/container/before_render', [ $this, 'modify_container_output' ], 10, 1 );
    }
    
    /**
     * کنترل‌های فیلتر برای Image Widget
     */
    public function add_filter_controls_to_image( $element, $args ) {
        $this->add_single_image_filter_controls( $element );
    }
    
    /**
     * کنترل‌های فیلتر برای Gallery Widget
     */
    public function add_filter_controls_to_gallery( $element, $args ) {
        $element->start_controls_section(
            'botri_gallery_filter_section',
            [
                'label' => '🔗 لینک به نان‌سئو فیلتر (برای هر تصویر)',
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        
        $element->add_control(
            'botri_gallery_filter_info',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => '<div style="background: #e3f2fd; padding: 10px; border-radius: 4px; border-left: 3px solid #2196F3;">
                    <strong>💡 راهنما:</strong><br>
                    با این ویژگی می‌توانید <strong>هر تصویر</strong> در گالری را به فیلتر مجزا لینک دهید.<br>
                    می‌توانید به <strong>دسته‌بندی خاص</strong> یا <strong>صفحه فروشگاه (Shop)</strong> لینک دهید.<br>
                    <small>⚠️ توجه: Lightbox باید غیرفعال باشد تا لینک فیلتر کار کند.</small>
                </div>',
            ]
        );
        
        $element->add_control(
            'botri_gallery_enable',
            [
                'label' => 'فعال‌سازی لینک فیلتر',
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => 'بله',
                'label_off' => 'خیر',
                'return_value' => 'yes',
                'default' => '',
            ]
        );
        
        $element->add_control(
            'botri_gallery_available_filters',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => $this->get_available_filters_html(),
                'condition' => [
                    'botri_gallery_enable' => 'yes',
                ],
            ]
        );
        
        // Repeater برای هر تصویر
        $repeater = new \Elementor\Repeater();
        
        $categories = $this->get_categories_options();
        
        $repeater->add_control(
            'category',
            [
                'label' => '📦 مقصد',
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => $categories,
                'default' => '',
                'description' => 'انتخاب کنید: فروشگاه (Shop) یا دسته‌بندی خاص',
            ]
        );
        
        for ( $i = 1; $i <= 5; $i++ ) {
            $repeater->add_control(
                'filter_' . $i,
                [
                    'label' => '🔹 فیلتر ' . $i,
                    'type' => \Elementor\Controls_Manager::TEXT,
                    'placeholder' => 'مثال: use-type:oil-shop',
                    'label_block' => true,
                ]
            );
        }
        
        $element->add_control(
            'botri_gallery_items',
            [
                'label' => 'تنظیمات فیلتر برای هر تصویر',
                'type' => \Elementor\Controls_Manager::REPEATER,
                'fields' => $repeater->get_controls(),
                'title_field' => 'تصویر {{{ _id }}}',
                'condition' => [
                    'botri_gallery_enable' => 'yes',
                ],
            ]
        );
        
        $element->end_controls_section();
    }
    
    /**
     * کنترل‌های فیلتر برای Image Carousel Widget
     */
    public function add_filter_controls_to_carousel( $element, $args ) {
        $element->start_controls_section(
            'botri_carousel_filter_section',
            [
                'label' => '🔗 لینک به نان‌سئو فیلتر (برای هر اسلاید)',
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        
        $element->add_control(
            'botri_carousel_filter_info',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => '<div style="background: #e3f2fd; padding: 10px; border-radius: 4px; border-left: 3px solid #2196F3;">
                    <strong>💡 راهنما:</strong><br>
                    با این ویژگی می‌توانید <strong>هر اسلاید</strong> را به فیلتر مجزا لینک دهید.<br>
                    می‌توانید به <strong>دسته‌بندی خاص</strong> یا <strong>صفحه فروشگاه (Shop)</strong> لینک دهید.
                </div>',
            ]
        );
        
        $element->add_control(
            'botri_carousel_enable',
            [
                'label' => 'فعال‌سازی لینک فیلتر',
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => 'بله',
                'label_off' => 'خیر',
                'return_value' => 'yes',
                'default' => '',
            ]
        );
        
        $element->add_control(
            'botri_carousel_available_filters',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => $this->get_available_filters_html(),
                'condition' => [
                    'botri_carousel_enable' => 'yes',
                ],
            ]
        );
        
        // Repeater برای هر اسلاید
        $repeater = new \Elementor\Repeater();
        
        $categories = $this->get_categories_options();
        
        $repeater->add_control(
            'category',
            [
                'label' => '📦 مقصد',
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => $categories,
                'default' => '',
                'description' => 'انتخاب کنید: فروشگاه (Shop) یا دسته‌بندی خاص',
            ]
        );
        
        for ( $i = 1; $i <= 5; $i++ ) {
            $repeater->add_control(
                'filter_' . $i,
                [
                    'label' => '🔹 فیلتر ' . $i,
                    'type' => \Elementor\Controls_Manager::TEXT,
                    'placeholder' => 'مثال: use-type:oil-shop',
                    'label_block' => true,
                ]
            );
        }
        
        $element->add_control(
            'botri_carousel_items',
            [
                'label' => 'تنظیمات فیلتر برای هر اسلاید',
                'type' => \Elementor\Controls_Manager::REPEATER,
                'fields' => $repeater->get_controls(),
                'title_field' => 'اسلاید {{{ _id }}}',
                'condition' => [
                    'botri_carousel_enable' => 'yes',
                ],
            ]
        );
        
        $element->end_controls_section();
    }
    
    /**
     * کنترل‌های فیلتر برای Container
     */
    public function add_filter_controls_to_container( $element, $args ) {
        $this->add_single_image_filter_controls( $element, 'container' );
    }
    
    /**
     * کنترل‌های مشترک برای یک تصویر (Image & Container)
     */
    private function add_single_image_filter_controls( $element, $widget_type = 'image' ) {
        $element->start_controls_section(
            'botri_filter_link_section',
            [
                'label' => '🔗 لینک به نان‌سئو فیلتر',
                'tab' => \Elementor\Controls_Manager::TAB_ADVANCED,
            ]
        );
        
        $element->add_control(
            'botri_enable_filter_link',
            [
                'label' => 'فعال‌سازی لینک به فیلتر',
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => 'بله',
                'label_off' => 'خیر',
                'return_value' => 'yes',
                'default' => '',
            ]
        );
        
        $element->add_control(
            'botri_filter_info',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => '<div style="background: #e3f2fd; padding: 10px; border-radius: 4px; border-left: 3px solid #2196F3;">
                    <strong>💡 راهنمای سریع:</strong><br>
                    با این ویژگی می‌توانید ' . ( $widget_type === 'container' ? 'کانتینر' : 'تصویر' ) . ' را به یک فیلتر Non-SEO لینک دهید.<br>
                    می‌توانید به <strong>دسته‌بندی خاص</strong> یا <strong>صفحه فروشگاه (Shop)</strong> لینک دهید.
                </div>',
                'condition' => [
                    'botri_enable_filter_link' => 'yes',
                ],
            ]
        );
        
        $categories = $this->get_categories_options();
        
        $element->add_control(
            'botri_target_category',
            [
                'label' => '📦 مقصد',
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => $categories,
                'default' => '',
                'description' => 'انتخاب کنید: فروشگاه (Shop) یا دسته‌بندی خاص',
                'condition' => [
                    'botri_enable_filter_link' => 'yes',
                ],
            ]
        );
        
        $element->add_control(
            'botri_available_filters',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => $this->get_available_filters_html(),
                'condition' => [
                    'botri_enable_filter_link' => 'yes',
                ],
            ]
        );
        
        for ( $i = 1; $i <= 5; $i++ ) {
            $element->add_control(
                'botri_filter_' . $i,
                [
                    'label' => '🔹 فیلتر ' . $i,
                    'type' => \Elementor\Controls_Manager::TEXT,
                    'placeholder' => 'مثال: use-type:oil-shop',
                    'label_block' => true,
                    'condition' => [
                        'botri_enable_filter_link' => 'yes',
                    ],
                ]
            );
        }
        
        $element->end_controls_section();
    }
    
    /**
     * دریافت لیست دسته‌بندی‌ها + Shop
     */
    private function get_categories_options() {
        $cat_options = [ 
            '' => '-- انتخاب کنید --',
            'shop' => '🏪 فروشگاه (Shop)'
        ];
        
        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ]);
        
        if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
            foreach ( $categories as $cat ) {
                $cat_options[ $cat->term_id ] = $cat->name;
            }
        }
        
        return $cat_options;
    }
    
    /**
     * HTML فیلترهای موجود
     */
    private function get_available_filters_html() {
        $nonseo_rules = get_posts([
            'post_type' => 'filter_nonseo_rule',
            'numberposts' => -1,
            'post_status' => 'publish'
        ]);

        if ( empty( $nonseo_rules ) ) {
            return '<div style="background: #fff3cd; padding: 10px; border-radius: 4px;">
                <strong>⚠️ هیچ فیلتر Non-SEO فعالی وجود ندارد!</strong>
            </div>';
        }

        $html = '<div style="background: #e8f5e9; padding: 12px; border-radius: 4px; max-height: 300px; overflow-y: auto; border: 1px solid #4caf50;">';
        $html .= '<strong>📋 فیلترهای موجود (کلیک=کپی):</strong><br><br>';
        
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
            return '<div style="background: #ffebee; padding: 10px; border-radius: 4px;"><strong>⚠️ هیچ ترمی یافت نشد!</strong></div>';
        }

        foreach ( $filters_by_tax as $tax => $terms ) {
            $tax_obj = get_taxonomy( 'pa_' . $tax );
            $label = $tax_obj ? $tax_obj->label : $tax;
            
            $html .= '<strong style="color: #2e7d32;">' . esc_html( $label ) . ':</strong><br>';
            $html .= '<ul style="margin: 5px 0 15px 20px; list-style: none; padding: 0;">';
            
            foreach ( $terms as $term ) {
                $filter_code = esc_html( $tax ) . ':' . esc_html( $term['slug'] );
                $html .= '<li style="margin-bottom: 3px;">
                    <code style="background: #fff; padding: 2px 6px; border-radius: 3px; font-size: 12px; cursor: pointer;" 
                          onclick="navigator.clipboard.writeText(\'' . $filter_code . '\'); this.style.background=\'#4caf50\'; this.style.color=\'white\'; setTimeout(() => { this.style.background=\'#fff\'; this.style.color=\'inherit\'; }, 1000);" 
                          title="کلیک کنید تا کپی شود">
                        ' . $filter_code . '
                    </code> - ' . esc_html( $term['name'] ) . '
                </li>';
            }
            
            $html .= '</ul>';
        }

        $html .= '</div>';
        return $html;
    }
    
    /**
     * تغییر خروجی ویجت‌ها
     */
    public function modify_widget_output( $content, $widget ) {
        $widget_name = $widget->get_name();
        
        if ( $widget_name === 'image' ) {
            return $this->modify_image_widget( $content, $widget );
        } elseif ( $widget_name === 'image-gallery' ) {
            return $this->modify_gallery_widget( $content, $widget );
        } elseif ( $widget_name === 'image-carousel' ) {
            return $this->modify_carousel_widget( $content, $widget );
        }
        
        return $content;
    }
    
    /**
     * تغییر خروجی Image Widget
     */
    private function modify_image_widget( $content, $widget ) {
        $settings = $widget->get_settings();
        
        if ( empty( $settings['botri_enable_filter_link'] ) || 'yes' !== $settings['botri_enable_filter_link'] ) {
            return $content;
        }
        
        $category_url = $this->get_category_url( $settings['botri_target_category'] );
        if ( empty( $category_url ) ) {
            return $content;
        }
        
        $filters = $this->collect_filters( $settings, 'botri_filter_' );
        if ( empty( $filters ) ) {
            return $content;
        }
        
        return $this->wrap_with_link( $content, $category_url, $filters );
    }
    
    /**
     * تغییر خروجی Gallery Widget
     */
    private function modify_gallery_widget( $content, $widget ) {
        $settings = $widget->get_settings();
        
        if ( empty( $settings['botri_gallery_enable'] ) || 'yes' !== $settings['botri_gallery_enable'] ) {
            return $content;
        }
        
        $items = isset( $settings['botri_gallery_items'] ) ? $settings['botri_gallery_items'] : [];
        if ( empty( $items ) ) {
            return $content;
        }
        
        preg_match_all( '/<img[^>]*>/i', $content, $matches );
        if ( empty( $matches[0] ) ) {
            return $content;
        }
        
        $new_content = $content;
        foreach ( $matches[0] as $index => $img_tag ) {
            if ( ! isset( $items[ $index ] ) ) {
                continue;
            }
            
            $item = $items[ $index ];
            $category_url = $this->get_category_url( $item['category'] );
            if ( empty( $category_url ) ) {
                continue;
            }
            
            $filters = $this->collect_filters( $item, 'filter_' );
            if ( empty( $filters ) ) {
                continue;
            }
            
            $filter_data_json = json_encode( $filters );
            $link = '<a href="#" class="botri-filter-link" data-botri-filter-link data-botri-category-url="' . esc_url( $category_url ) . '" data-botri-filter-data=\'' . esc_attr( $filter_data_json ) . '\'>' . $img_tag . '</a>';
            
            $new_content = preg_replace( '/' . preg_quote( $img_tag, '/' ) . '/', $link, $new_content, 1 );
        }
        
        return $new_content;
    }
    
    /**
     * تغییر خروجی Image Carousel Widget
     */
    private function modify_carousel_widget( $content, $widget ) {
        $settings = $widget->get_settings();
        
        if ( empty( $settings['botri_carousel_enable'] ) || 'yes' !== $settings['botri_carousel_enable'] ) {
            return $content;
        }
        
        $items = isset( $settings['botri_carousel_items'] ) ? $settings['botri_carousel_items'] : [];
        if ( empty( $items ) ) {
            return $content;
        }
        
        preg_match_all( '/<img[^>]*>/i', $content, $matches );
        if ( empty( $matches[0] ) ) {
            return $content;
        }
        
        $new_content = $content;
        foreach ( $matches[0] as $index => $img_tag ) {
            if ( ! isset( $items[ $index ] ) ) {
                continue;
            }
            
            $item = $items[ $index ];
            $category_url = $this->get_category_url( $item['category'] );
            if ( empty( $category_url ) ) {
                continue;
            }
            
            $filters = $this->collect_filters( $item, 'filter_' );
            if ( empty( $filters ) ) {
                continue;
            }
            
            $filter_data_json = json_encode( $filters );
            $link = '<a href="#" class="botri-filter-link" data-botri-filter-link data-botri-category-url="' . esc_url( $category_url ) . '" data-botri-filter-data=\'' . esc_attr( $filter_data_json ) . '\'>' . $img_tag . '</a>';
            
            $new_content = preg_replace( '/' . preg_quote( $img_tag, '/' ) . '/', $link, $new_content, 1 );
        }
        
        return $new_content;
    }
    
    /**
     * تغییر خروجی Container
     */
    public function modify_container_output( $element ) {
        $settings = $element->get_settings();
        
        if ( empty( $settings['botri_enable_filter_link'] ) || 'yes' !== $settings['botri_enable_filter_link'] ) {
            return;
        }
        
        $category_url = $this->get_category_url( $settings['botri_target_category'] );
        if ( empty( $category_url ) ) {
            return;
        }
        
        $filters = $this->collect_filters( $settings, 'botri_filter_' );
        if ( empty( $filters ) ) {
            return;
        }
        
        $filter_data_json = json_encode( $filters );
        
        $element->add_render_attribute( '_wrapper', [
            'class' => 'botri-filter-link',
            'data-botri-filter-link' => '',
            'data-botri-category-url' => esc_url( $category_url ),
            'data-botri-filter-data' => esc_attr( $filter_data_json ),
            'style' => 'cursor: pointer;',
        ]);
    }
    
    /**
     * دریافت URL دسته‌بندی یا Shop
     */
    private function get_category_url( $term_id ) {
        if ( empty( $term_id ) ) {
            return '';
        }
        
        // اگر Shop انتخاب شده
        if ( $term_id === 'shop' ) {
            return get_permalink( wc_get_page_id( 'shop' ) );
        }
        
        // دسته‌بندی معمولی
        $term = get_term( $term_id, 'product_cat' );
        if ( ! $term || is_wp_error( $term ) ) {
            return '';
        }
        
        return get_term_link( $term );
    }
    
    /**
     * جمع‌آوری فیلترها
     */
    private function collect_filters( $settings, $prefix ) {
        $filters = [];
        
        for ( $i = 1; $i <= 5; $i++ ) {
            $key = $prefix . $i;
            $filter = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
            
            if ( empty( $filter ) || strpos( $filter, ':' ) === false ) {
                continue;
            }
            
            list( $tax, $slug ) = explode( ':', $filter, 2 );
            $tax = trim( $tax );
            $slug = trim( $slug );
            
            if ( empty( $tax ) || empty( $slug ) ) {
                continue;
            }
            
            $filter_key = 'filter_' . $tax;
            if ( ! isset( $filters[ $filter_key ] ) ) {
                $filters[ $filter_key ] = [];
            }
            $filters[ $filter_key ][] = $slug;
        }
        
        foreach ( $filters as $key => $values ) {
            $filters[ $key ] = implode( ',', $values );
        }
        
        return $filters;
    }
    
    /**
     * Wrap کردن با لینک
     */
    private function wrap_with_link( $content, $category_url, $filters ) {
        $filter_data_json = json_encode( $filters );
        
        $wrapper_start = '<a href="#" class="botri-filter-link" data-botri-filter-link data-botri-category-url="' . esc_url( $category_url ) . '" data-botri-filter-data=\'' . esc_attr( $filter_data_json ) . '\'>';
        $wrapper_end = '</a>';
        
        if ( preg_match( '/<img[^>]*>/i', $content, $matches ) ) {
            $img_tag = $matches[0];
            return str_replace( $img_tag, $wrapper_start . $img_tag . $wrapper_end, $content );
        }
        
        return $content;
    }
}

// Initialize
Botri_Extend_Image_Widget::get_instance();