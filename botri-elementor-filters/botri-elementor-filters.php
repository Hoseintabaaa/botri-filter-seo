<?php
/**
 * Plugin Name: Botri — Elementor Filters
 * Description: ویجت‌های المنتور برای نمایش فیلترهای Botri Filter SEO Manager
 * Version: 2.0.0
 * Author: Younes
 * Text Domain: botri-elementor-filters
 * Requires Plugins: elementor,botri-filter-seo,woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Botri_Elementor_Filters {
    
    private static $instance = null;
    private $log_file;
    
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // تنظیم مسیر فایل لاگ
        $upload_dir = wp_upload_dir();
        $this->log_file = $upload_dir['basedir'] . '/botri-debug.log';
        
        add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_botri_apply_filter', [ $this, 'ajax_apply_filter' ] );
        add_action( 'wp_ajax_nopriv_botri_apply_filter', [ $this, 'ajax_apply_filter' ] );
        add_action( 'wp_footer', [ $this, 'add_loading_overlay' ] );
        
        add_shortcode( 'botri_filter_link', [ $this, 'filter_link_shortcode' ] );
        
        // 🔥 FIX: Shortcode های dynamic برای جلوگیری از کش
        add_shortcode( 'botri_dynamic_content', [ $this, 'render_dynamic_content' ] );
        add_shortcode( 'botri_dynamic_h1', [ $this, 'render_dynamic_h1' ] );
        
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'elementor/init', [ $this, 'load_image_widget_extension' ] );
        
        // صفحه Debug Log در ادمین
        add_action( 'admin_menu', [ $this, 'add_debug_menu' ], 20 );
        add_action( 'admin_post_botri_download_log', [ $this, 'download_log' ] );
        add_action( 'admin_post_botri_clear_log', [ $this, 'clear_log' ] );
        
        // شروع لاگ
        $this->log( '=== BOTRI ELEMENTOR FILTERS v2.0 INITIALIZED ===' );
        $this->log( 'WordPress Version: ' . get_bloginfo( 'version' ) );
        $this->log( 'WooCommerce Version: ' . ( defined( 'WC_VERSION' ) ? WC_VERSION : 'Not installed' ) );
        $this->log( 'Elementor Version: ' . ( defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : 'Not installed' ) );
        $this->log( '================================' );
    }

    /**
     * تابع Logging
     */
    private function log( $message, $data = null ) {
        if ( ! defined( 'BOTRI_DEBUG' ) || ! BOTRI_DEBUG ) {
            return;
        }
        
        $timestamp = current_time( 'Y-m-d H:i:s' );
        $log_entry = "[{$timestamp}] {$message}";
        
        if ( $data !== null ) {
            $log_entry .= "\n" . print_r( $data, true );
        }
        
        $log_entry .= "\n---\n";
        
        error_log( $log_entry, 3, $this->log_file );
    }

    /**
     * منوی Debug در ادمین
     */
    public function add_debug_menu() {
        add_submenu_page(
            'edit.php?post_type=filter_seo_rule',
            '🔍 Debug Log',
            '🔍 Debug Log',
            'manage_options',
            'botri-debug-log',
            [ $this, 'render_debug_page' ]
        );
    }

    /**
     * صفحه Debug Log
     */
    public function render_debug_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Access denied' );
        }
        
        $log_exists = file_exists( $this->log_file );
        $log_size = $log_exists ? size_format( filesize( $this->log_file ) ) : '0 B';
        $log_lines = $log_exists ? count( file( $this->log_file ) ) : 0;
        
        $last_lines = '';
        if ( $log_exists ) {
            $lines = file( $this->log_file );
            $last_100 = array_slice( $lines, -100 );
            $last_lines = implode( '', $last_100 );
        }
        
        ?>
        <div class="wrap">
            <h1>🔍 Botri Debug Log</h1>
            
            <div class="card" style="max-width: 100%; margin-top: 20px; background: #f0f0f1; padding: 20px;">
                <h2>📊 اطلاعات فایل لاگ</h2>
                <table class="widefat">
                    <tr>
                        <th style="width: 200px;">مسیر فایل:</th>
                        <td><code><?php echo esc_html( $this->log_file ); ?></code></td>
                    </tr>
                    <tr>
                        <th>حجم فایل:</th>
                        <td><?php echo esc_html( $log_size ); ?></td>
                    </tr>
                    <tr>
                        <th>تعداد خطوط:</th>
                        <td><?php echo number_format( $log_lines ); ?></td>
                    </tr>
                    <tr>
                        <th>وضعیت:</th>
                        <td>
                            <?php if ( $log_exists ): ?>
                                <span style="color: green;">✅ فعال</span>
                            <?php else: ?>
                                <span style="color: red;">❌ فایل لاگ وجود ندارد</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h2>⚙️ تنظیمات Debug</h2>
                <div style="background: #fff3cd; padding: 15px; border-radius: 4px; border-left: 4px solid #ffc107; margin-bottom: 15px;">
                    <strong>💡 نکته مهم:</strong> برای فعال کردن سیستم Logging، خط زیر را به فایل <code>wp-config.php</code> اضافه کنید:
                    <pre style="background: #2c3e50; color: #ecf0f1; padding: 10px; margin-top: 10px; border-radius: 4px;">define( 'BOTRI_DEBUG', true );</pre>
                </div>
                
                <?php if ( defined( 'BOTRI_DEBUG' ) && BOTRI_DEBUG ): ?>
                    <div style="background: #d4edda; padding: 15px; border-radius: 4px; border-left: 4px solid #28a745;">
                        <strong>✅ سیستم Logging فعال است</strong>
                    </div>
                <?php else: ?>
                    <div style="background: #f8d7da; padding: 15px; border-radius: 4px; border-left: 4px solid #dc3545;">
                        <strong>⚠️ سیستم Logging غیرفعال است</strong><br>
                        لطفاً <code>define( 'BOTRI_DEBUG', true );</code> را به wp-config.php اضافه کنید.
                    </div>
                <?php endif; ?>
            </div>

            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h2>🎮 عملیات</h2>
                <p>
                    <a href="<?php echo admin_url( 'admin-post.php?action=botri_download_log' ); ?>" 
                       class="button button-primary" 
                       <?php echo ! $log_exists ? 'disabled' : ''; ?>>
                        📥 دانلود فایل کامل لاگ
                    </a>
                    
                    <a href="<?php echo admin_url( 'admin-post.php?action=botri_clear_log' ); ?>" 
                       class="button button-secondary"
                       onclick="return confirm('آیا مطمئن هستید که می‌خواهید لاگ را پاک کنید؟');"
                       <?php echo ! $log_exists ? 'disabled' : ''; ?>>
                        🗑️ پاک کردن لاگ
                    </a>
                    
                    <button type="button" class="button" onclick="location.reload();">
                        🔄 بارگذاری مجدد
                    </button>
                </p>
            </div>

            <?php if ( $log_exists && ! empty( $last_lines ) ): ?>
            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h2>📄 آخرین 100 خط لاگ</h2>
                <div style="background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 4px; max-height: 500px; overflow-y: auto; font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.6; direction: ltr; text-align: left;">
                    <pre style="margin: 0; white-space: pre-wrap;"><?php echo esc_html( $last_lines ); ?></pre>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <style>
            .card { 
                padding: 20px; 
                background: #fff; 
                border: 1px solid #ccc; 
                border-radius: 5px; 
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            .card h2 { 
                margin-top: 0; 
                color: #0073aa; 
                border-bottom: 2px solid #0073aa;
                padding-bottom: 10px;
            }
            .card code { 
                background: #f0f0f0; 
                padding: 2px 6px; 
                border-radius: 3px; 
                font-family: monospace; 
            }
        </style>
        <?php
    }

    /**
     * دانلود فایل لاگ
     */
    public function download_log() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Access denied' );
        }
        
        if ( ! file_exists( $this->log_file ) ) {
            wp_die( 'فایل لاگ وجود ندارد' );
        }
        
        $filename = 'botri-debug-' . date( 'Y-m-d-H-i-s' ) . '.log';
        
        header( 'Content-Type: text/plain' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . filesize( $this->log_file ) );
        
        readfile( $this->log_file );
        exit;
    }

    /**
     * پاک کردن لاگ
     */
    public function clear_log() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Access denied' );
        }
        
        if ( file_exists( $this->log_file ) ) {
            unlink( $this->log_file );
        }
        
        wp_redirect( admin_url( 'edit.php?post_type=filter_seo_rule&page=botri-debug-log' ) );
        exit;
    }

    public function add_loading_overlay() {
        if ( ! is_product_category() && ! is_shop() ) return;
        echo '<div class="botri-loading-overlay" style="display: none;"><div class="botri-loading-spinner"></div></div>';
    }
    
    public function load_image_widget_extension() {
        $extension_file = plugin_dir_path( __FILE__ ) . 'includes/extend-image-widget.php';
        
        if ( file_exists( $extension_file ) ) {
            require_once( $extension_file );
        }
    }

    public function register_widgets( $widgets_manager ) {
        if ( ! class_exists( 'Elementor\Widget_Base' ) ) {
            return;
        }
        
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }
        
        if ( ! class_exists( 'Botri_Filter_SEO_Manager' ) ) {
            return;
        }

        $widgets = [
            'seo-filters' => 'Botri_Elementor_SEO_Filters_Widget',
            'nonseo-filters' => 'Botri_Elementor_NonSEO_Filters_Widget',
            'price-filter' => 'Botri_Elementor_Price_Filter_Widget',
            'active-filters' => 'Botri_Elementor_Active_Filters_Widget',
            'seo-h1' => 'Botri_Elementor_SEO_H1_Widget',
            'seo-content' => 'Botri_Elementor_SEO_Content_Widget',
            'image-with-filter' => 'Botri_Elementor_Image_With_Filter_Widget',
        ];

        foreach ( $widgets as $file => $class ) {
            $file_path = plugin_dir_path( __FILE__ ) . 'widgets/' . $file . '.php';
            
            if ( file_exists( $file_path ) ) {
                require_once( $file_path );
                
                if ( class_exists( $class ) ) {
                    $widgets_manager->register( new $class() );
                }
            }
        }
    }

    public function enqueue_assets() {
        wp_enqueue_style( 
            'botri-elementor-styles', 
            plugin_dir_url( __FILE__ ) . 'assets/style.css',
            [],
            '2.0.0'
        );
        
        wp_enqueue_script( 
            'botri-elementor-js', 
            plugin_dir_url( __FILE__ ) . 'assets/script.js', 
            [ 'jquery' ], 
            '2.0.0', 
            true 
        );
        
        wp_localize_script( 'botri-elementor-js', 'botri_ajax', [ 
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'botri_filter_nonce' ),
            'infinite_nonce' => wp_create_nonce( 'botri_infinite_scroll' ),
            'debug' => defined( 'BOTRI_DEBUG' ) && BOTRI_DEBUG,
        ]);

        if ( is_shop() || is_product_category() ) {
            wp_localize_script( 'botri-elementor-js', 'wc_price_settings', [
                'currency_symbol' => get_woocommerce_currency_symbol(),
                'decimal_separator' => wc_get_price_decimal_separator(),
                'thousand_separator' => wc_get_price_thousand_separator(),
                'decimals' => wc_get_price_decimals(),
                'price_format' => get_woocommerce_price_format(),
            ]);

            wp_enqueue_script( 'wc-price-slider' );
            wp_enqueue_script( 'jquery-ui-slider' );
            wp_enqueue_style( 'jquery-ui', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css', [], '1.12.1' );
        }
    }

    public function ajax_apply_filter() {
        check_ajax_referer( 'botri_filter_nonce', 'nonce' );
        
        $this->log( 'AJAX: botri_apply_filter called', $_POST );
        
        if ( ! is_user_logged_in() && ! isset( $_COOKIE['botri_nonseo_filters'] ) && empty( $_POST ) ) {
            $this->log( 'ERROR: No filters provided' );
            wp_send_json_error( [ 'message' => 'No filters provided' ] );
        }

        $tax_query = [];
        $meta_query = [];

        $filters = [];
        if ( isset( $_COOKIE['botri_nonseo_filters'] ) ) {
            $filters = json_decode( stripslashes( $_COOKIE['botri_nonseo_filters'] ), true );
            if ( ! is_array( $filters ) ) {
                $filters = [];
            }
        }

        if ( ! empty( $_POST ) ) {
            foreach ( $_POST as $key => $value ) {
                if ( $key === 'action' || $key === 'nonce' ) continue;
                
                if ( strpos( $key, 'filter_' ) === 0 && ! empty( $value ) ) {
                    $filters[ $key ] = sanitize_text_field( $value );
                }
                
                if ( $key === 'min_price' && ! empty( $value ) ) {
                    $filters['min_price'] = intval( $value );
                }
                
                if ( $key === 'max_price' && ! empty( $value ) ) {
                    $filters['max_price'] = intval( $value );
                }
            }
        }

        if ( isset( $filters['min_price'] ) && isset( $filters['max_price'] ) ) {
            $meta_query[] = [
                'key'     => '_price',
                'value'   => [ floatval( $filters['min_price'] ), floatval( $filters['max_price'] ) ],
                'type'    => 'numeric',
                'compare' => 'BETWEEN',
            ];
        }

        foreach ( $filters as $key => $value ) {
            if ( strpos( $key, 'filter_' ) === 0 ) {
                $attr = str_replace( 'filter_', '', $key );
                $tax_real = 'pa_' . sanitize_key( $attr );
                
                if ( taxonomy_exists( $tax_real ) ) {
                    $term_slugs = explode( ',', $value );
                    $tax_query[] = [
                        'taxonomy' => $tax_real,
                        'field'    => 'slug',
                        'terms'    => array_map( 'sanitize_title', $term_slugs ),
                        'operator' => 'IN',
                    ];
                }
            }
        }

        foreach ( $_POST as $key => $value ) {
            if ( in_array( $key, [ 'action', 'nonce', 'orderby', 'min_price', 'max_price', 'paged', 's' ] ) ) continue;
            if ( strpos( $key, 'filter_' ) === 0 ) continue;
            if ( empty( $value ) ) continue;

            $tax_real = ( 0 === strpos( $key, 'pa_' ) ) ? $key : 'pa_' . sanitize_key( $key );
            $term_slugs = array_map( 'sanitize_title', explode( ',', $value ) );

            if ( taxonomy_exists( $tax_real ) ) {
                $tax_query[] = [
                    'taxonomy' => $tax_real,
                    'field'    => 'slug',
                    'terms'    => $term_slugs,
                    'operator' => 'IN',
                ];
            }
        }

        if ( count( $tax_query ) > 1 ) {
            $tax_query['relation'] = 'AND';
        }

        if ( count( $meta_query ) > 1 ) {
            $meta_query['relation'] = 'AND';
        }

        $paged = isset( $_POST['paged'] ) ? intval( $_POST['paged'] ) : 1;

        $query_args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => get_option( 'posts_per_page', 12 ),
            'paged'          => $paged,
            'tax_query'      => $tax_query,
            'meta_query'     => $meta_query,
        ];

        $this->log( 'Query Args', $query_args );

        $loop = new WP_Query( $query_args );

        $this->log( 'Query Results: Found ' . $loop->found_posts . ' posts' );

        ob_start();
        if ( $loop->have_posts() ) {
            woocommerce_product_loop_start();
            while ( $loop->have_posts() ) {
                $loop->the_post();
                wc_get_template_part( 'content', 'product' );
            }
            woocommerce_product_loop_end();
        } else {
            echo '<p class="woocommerce-info">' . esc_html__( 'محصولی یافت نشد.', 'woocommerce' ) . '</p>';
        }
        $products_html = ob_get_clean();

        wp_reset_postdata();

        $this->log( 'SUCCESS: Returning products HTML' );

        wp_send_json_success( [ 
            'products' => $products_html,
            'found_posts' => $loop->found_posts 
        ] );
    }

    /**
     * 🔥 FIX: Shortcode برای محتوای dynamic
     * این در هر بار page load اجرا می‌شود، نه در زمان کش
     */
    public function render_dynamic_content( $atts ) {
        $atts = shortcode_atts( [
            'fallback' => 'yes',
        ], $atts );

        $content = '';

        // دریافت محتوا از فیلتر (این در runtime اجرا می‌شود)
        $content = apply_filters( 'botri_seo_content', '' );

        // اگر خالی بود، از fallback استفاده کن
        if ( empty( $content ) && 'yes' === $atts['fallback'] ) {
            $cat = get_queried_object();
            if ( $cat && isset( $cat->description ) && ! empty( $cat->description ) ) {
                $content = $cat->description;
            }
        }

        if ( empty( $content ) ) {
            return '';
        }

        return '<div class="botri-seo-content">' . wp_kses_post( $content ) . '</div>';
    }

    /**
     * 🔥 FIX: Shortcode برای H1 dynamic
     * این در هر بار page load اجرا می‌شود، نه در زمان کش
     */
    public function render_dynamic_h1( $atts ) {
        $atts = shortcode_atts( [
            'tag' => 'h1',
            'fallback' => '',
        ], $atts );

        $h1 = '';

        // دریافت H1 از فیلتر (این در runtime اجرا می‌شود)
        $h1 = apply_filters( 'botri_seo_h1', '' );

        // اگر خالی بود، از fallback استفاده کن
        if ( empty( $h1 ) ) {
            if ( ! empty( $atts['fallback'] ) ) {
                $h1 = $atts['fallback'];
            } else {
                $cat = get_queried_object();
                if ( $cat && isset( $cat->name ) ) {
                    $h1 = $cat->name;
                }
            }
        }

        if ( empty( $h1 ) ) {
            return '';
        }

        $html_tag = tag_escape( $atts['tag'] );

        return sprintf(
            '<%1$s class="botri-seo-h1">%2$s</%1$s>',
            $html_tag,
            esc_html( $h1 )
        );
    }

    public function filter_link_shortcode( $atts, $content = null ) {
        $atts = shortcode_atts( [
            'category' => '',
            'filters' => '',
            'class' => '',
            'style' => '',
        ], $atts );

        if ( empty( $atts['category'] ) ) {
            if ( current_user_can( 'edit_posts' ) ) {
                return '<span style="color:red;font-weight:bold;">[خطا Botri: پارامتر category الزامی است]</span>';
            }
            return '';
        }

        $category_term = get_term_by( 'slug', $atts['category'], 'product_cat' );
        if ( ! $category_term || is_wp_error( $category_term ) ) {
            if ( current_user_can( 'edit_posts' ) ) {
                return '<span style="color:red;font-weight:bold;">[خطا Botri: دسته‌بندی "' . esc_html( $atts['category'] ) . '" پیدا نشد]</span>';
            }
            return '';
        }

        $category_url = get_term_link( $category_term );

        $filter_data = [];
        if ( ! empty( $atts['filters'] ) ) {
            $filters_array = explode( ',', $atts['filters'] );
            foreach ( $filters_array as $filter ) {
                $parts = explode( ':', trim( $filter ) );
                if ( count( $parts ) === 2 ) {
                    $key = trim( $parts[0] );
                    $value = trim( $parts[1] );
                    
                    if ( in_array( $key, [ 'min_price', 'max_price' ] ) ) {
                        $filter_data[ $key ] = intval( $value );
                    } else {
                        $filter_data[ 'filter_' . $key ] = $value;
                    }
                }
            }
        }

        $filter_data_json = json_encode( $filter_data, JSON_HEX_APOS | JSON_HEX_QUOT );
        $class = ! empty( $atts['class'] ) ? ' ' . esc_attr( $atts['class'] ) : '';
        $style = ! empty( $atts['style'] ) ? ' style="' . esc_attr( $atts['style'] ) . '"' : '';

        $output = sprintf(
            '<a href="#" class="botri-filter-link%s" data-botri-filter-link data-botri-category-url="%s" data-botri-filter-data=\'%s\'%s>%s</a>',
            $class,
            esc_url( $category_url ),
            esc_attr( $filter_data_json ),
            $style,
            do_shortcode( $content )
        );

        return $output;
    }

    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=filter_seo_rule',
            'راهنمای لینک به فیلترها',
            '📖 راهنمای لینک',
            'manage_options',
            'botri-filter-links-guide',
            [ $this, 'render_admin_guide_page' ]
        );
    }

    public function render_admin_guide_page() {
        ?>
        <div class="wrap">
            <h1>📖 راهنمای لینک دادن به فیلترهای Non-SEO</h1>
            
            <div class="card" style="max-width: 900px; margin-top: 20px; background: #e3f2fd; border-left: 4px solid #2196F3;">
                <h2>⭐ بهترین روش: استفاده از ویجت Image المنتور</h2>
                <p><strong>با این روش از تمام استایل‌های المنتور استفاده می‌کنید!</strong></p>
                <ol style="line-height: 2;">
                    <li>در المنتور، ویجت معمولی <strong>"Image"</strong> را اضافه کنید</li>
                    <li>تصویر بنر خود را انتخاب کنید</li>
                    <li>به تب <strong>"Advanced"</strong> بروید</li>
                    <li>به بخش <strong>"🔗 لینک به نان‌سئو فیلتر"</strong> بروید</li>
                    <li>سوییچ <strong>"فعال‌سازی لینک به فیلتر"</strong> را روشن کنید</li>
                    <li>دسته‌بندی مقصد را انتخاب کنید</li>
                    <li>از لیست فیلترهای موجود، فیلتر دلخواه را کپی و در کادرها paste کنید</li>
                    <li>تمام! ✨</li>
                </ol>
            </div>
        </div>

        <style>
            .card { padding: 20px; background: #fff; border: 1px solid #ccc; border-radius: 5px; }
            .card h2 { margin-top: 0; color: #0073aa; }
        </style>
        <?php
    }
}

// Initialize
Botri_Elementor_Filters::get_instance();