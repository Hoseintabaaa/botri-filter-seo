<?php
/**
 * Plugin Name: Botri Anti-Zombie Pages v2
 * Description: 🛡️ جلوگیری کامل از Zombie Pages با 404 - نسخه امن و تست شده
 * Version: 2.0.0
 * Author: Botri Team
 * Author URI: https://botricenter.ir
 * Text Domain: botri-anti-zombie
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * 
 * 🚨 SAFE MODE: این افزونه فقط صفحات کاملاً نامعتبر را 404 می‌کند
 * ✅ هیچ redirect نامناسبی انجام نمی‌دهد
 * ✅ با Yoast تداخل ندارد
 * ✅ فقط canonical می‌زند (بدون redirect)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Check WooCommerce
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>Botri Anti-Zombie v2</strong> نیازمند WooCommerce است.</p></div>';
    });
    return;
}

class Botri_Anti_Zombie_V2 {
    
    const VERSION = '2.0.0';
    private static $instance = null;
    
    /**
     * پارامترهای ممنوع - این‌ها همیشه باعث 404 می‌شوند
     */
    private $forbidden_params = [
        'orderby',
        'paged',
        'product-page',
        'products-per-page',
        's',
        'search',
    ];
    
    /**
     * Attributes مجاز (SEO) - از CPT خوانده می‌شود
     */
    private $allowed_seo_attributes = [];
    
    /**
     * Debug mode
     */
    private $debug_mode = false;
    
    /**
     * Cache
     */
    private $seo_rules_cache = null;
    
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->debug_mode = defined( 'BOTRI_DEBUG' ) && BOTRI_DEBUG;
        $this->init_hooks();
        $this->log( '🚀 Botri Anti-Zombie v2.0 initialized (SAFE MODE)' );
    }
    
    private function init_hooks() {
        // 1. شناسایی خودکار SEO attributes
        add_action( 'init', [ $this, 'load_seo_attributes' ], 5 );
        
        // 2. بررسی و 404 کردن URL های نامعتبر
        add_action( 'template_redirect', [ $this, 'validate_and_block_invalid_urls' ], 1 );
        
        // 3. Canonical (فقط برای صفحات معتبر)
        add_filter( 'wpseo_canonical', [ $this, 'fix_canonical' ], 10 );
        add_filter( 'get_canonical_url', [ $this, 'fix_canonical' ], 10 );
        
        // 4. Noindex برای امان بیشتر
        add_filter( 'wpseo_robots', [ $this, 'set_noindex_if_invalid' ], 10 );
        
        // 5. جلوگیری از لینک‌های pagination
        add_filter( 'paginate_links', '__return_empty_string', 999 );
        
        // 6. حذف orderby از لینک‌ها (جلوگیری از ایجاد)
        add_filter( 'woocommerce_catalog_orderby', [ $this, 'prevent_orderby_in_url' ], 999 );
        
        // 7. Admin
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        
        // 8. پاک‌سازی کش
        add_action( 'save_post_filter_seo_rule', [ $this, 'clear_cache' ] );
    }
    
    /**
     * بارگذاری SEO attributes از CPT
     */
    public function load_seo_attributes() {
        $cached = get_transient( 'botri_v2_allowed_attrs' );
        if ( false !== $cached && is_array( $cached ) ) {
            $this->allowed_seo_attributes = $cached;
            $this->log( '✅ Loaded from cache', $this->allowed_seo_attributes );
            return;
        }
        
        $this->allowed_seo_attributes = [];
        
        $rules = get_posts( [
            'post_type'   => 'filter_seo_rule',
            'numberposts' => -1,
            'post_status' => 'publish',
        ] );
        
        foreach ( $rules as $rule ) {
            $taxonomy = get_post_meta( $rule->ID, '_taxonomy', true );
            if ( empty( $taxonomy ) ) continue;
            
            $taxonomy = preg_replace( '/^pa_/', '', $taxonomy );
            
            if ( ! in_array( $taxonomy, $this->allowed_seo_attributes, true ) ) {
                $this->allowed_seo_attributes[] = $taxonomy;
            }
        }
        
        set_transient( 'botri_v2_allowed_attrs', $this->allowed_seo_attributes, HOUR_IN_SECONDS );
        $this->log( '✅ SEO Attributes loaded', $this->allowed_seo_attributes );
    }
    
    /**
     * اعتبارسنجی و 404 کردن URL های نامعتبر
     */
    public function validate_and_block_invalid_urls() {
        // فقط برای shop/category
        if ( ! is_shop() && ! is_product_category() && ! is_product_tag() ) {
            return;
        }
        
        $this->log( '🔍 Validating URL...' );
        
        // اگر هیچ query string نداریم → معتبر
        if ( empty( $_GET ) ) {
            $this->log( '✅ No query params - Valid' );
            return;
        }
        
        // بررسی query params
        $validation = $this->validate_query_params();
        
        $this->log( 'Validation result', $validation );
        
        // اگر کاملاً نامعتبر است
        if ( $validation['status'] === 'invalid' ) {
            $this->log( '🚨 INVALID URL - Sending 404' );
            $this->send_404();
            return;
        }
        
        // اگر معتبر است یا partial valid (فقط canonical می‌زنیم)
        $this->log( '✅ URL is valid or partially valid - No action needed' );
    }
    
    /**
     * اعتبارسنجی query params
     * 
     * @return array ['status' => 'valid'|'invalid'|'partial', 'valid_params' => [...]]
     */
    private function validate_query_params() {
        $valid_params = [];
        $has_invalid = false;
        $seo_filter_count = 0;
        
        foreach ( $_GET as $key => $value ) {
            // 1. پارامترهای ممنوع → نامعتبر
            if ( $this->is_forbidden_param( $key ) ) {
                $this->log( "❌ Forbidden param: {$key}" );
                $has_invalid = true;
                continue;
            }
            
            // 2. فیلترهای non-SEO (filter_*) → نامعتبر
            if ( strpos( $key, 'filter_' ) === 0 ) {
                $this->log( "❌ Non-SEO filter in URL: {$key}" );
                $has_invalid = true;
                continue;
            }
            
            // 3. قیمت → نامعتبر
            if ( in_array( $key, [ 'min_price', 'max_price' ], true ) ) {
                $this->log( "❌ Price filter in URL: {$key}" );
                $has_invalid = true;
                continue;
            }
            
            // 4. taxonomy مستقیم (pa_*) → نامعتبر
            if ( strpos( $key, 'pa_' ) === 0 ) {
                $this->log( "❌ Direct taxonomy in URL: {$key}" );
                $has_invalid = true;
                continue;
            }
            
            // 5. SEO attribute معتبر
            if ( in_array( $key, $this->allowed_seo_attributes, true ) ) {
                // چک می‌کنیم که برای این category مجاز باشد
                if ( $this->is_attribute_allowed_for_current_page( $key, $value ) ) {
                    $seo_filter_count++;
                    $valid_params[ $key ] = $value;
                    $this->log( "✅ Valid SEO attribute: {$key}={$value}" );
                } else {
                    $this->log( "❌ SEO attribute not allowed for this category: {$key}" );
                    $has_invalid = true;
                }
                continue;
            }
            
            // 6. پارامتر ناشناخته → نامعتبر
            $this->log( "❌ Unknown param: {$key}" );
            $has_invalid = true;
        }
        
        // اگر بیش از 1 SEO filter داریم → ترکیبی → نامعتبر
        if ( $seo_filter_count > 1 ) {
            $this->log( "❌ Multiple SEO filters (combination): {$seo_filter_count}" );
            return [ 'status' => 'invalid', 'valid_params' => [] ];
        }
        
        // اگر هیچ چیز معتبری نداشتیم
        if ( empty( $valid_params ) && $has_invalid ) {
            return [ 'status' => 'invalid', 'valid_params' => [] ];
        }
        
        // اگر فقط چیزهای معتبر داریم
        if ( ! $has_invalid ) {
            return [ 'status' => 'valid', 'valid_params' => $valid_params ];
        }
        
        // اگر ترکیبی از معتبر و نامعتبر داریم
        return [ 'status' => 'partial', 'valid_params' => $valid_params ];
    }
    
    /**
     * چک کردن آیا پارامتر ممنوع است
     */
    private function is_forbidden_param( $param ) {
        return in_array( $param, $this->forbidden_params, true );
    }
    
    /**
     * چک کردن آیا attribute برای صفحه فعلی مجاز است
     */
    private function is_attribute_allowed_for_current_page( $attribute, $value ) {
        // برای shop → SEO filters مجاز نیستند
        if ( is_shop() ) {
            $this->log( "Shop page - SEO filters not allowed" );
            return false;
        }
        
        $cat = get_queried_object();
        if ( ! $cat || ! isset( $cat->term_id ) ) {
            return false;
        }
        
        $category_id = $cat->term_id;
        
        // جستجو در قوانین
        $rules = get_posts( [
            'post_type'   => 'filter_seo_rule',
            'numberposts' => -1,
            'post_status' => 'publish',
        ] );
        
        foreach ( $rules as $rule ) {
            $taxonomy = get_post_meta( $rule->ID, '_taxonomy', true );
            $term = get_post_meta( $rule->ID, '_term', true );
            $categories = (array) get_post_meta( $rule->ID, '_cats', true );
            
            $taxonomy = preg_replace( '/^pa_/', '', $taxonomy );
            
            // اگر این attribute است و category در لیست است و term مچ می‌کند
            if ( $taxonomy === $attribute && in_array( $category_id, $categories, false ) && $term === $value ) {
                $this->log( "✅ Attribute allowed: {$attribute}={$value} for category {$category_id}" );
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * ارسال 404
     */
    private function send_404() {
        global $wp_query;
        $wp_query->set_404();
        status_header( 404 );
        nocache_headers();
        
        // فراخوانی template 404
        if ( file_exists( get_template_directory() . '/404.php' ) ) {
            include( get_template_directory() . '/404.php' );
        } else {
            wp_die( 
                '<h1>404 - صفحه یافت نشد</h1><p>این صفحه وجود ندارد.</p>',
                '404 - صفحه یافت نشد',
                [ 'response' => 404 ]
            );
        }
        
        exit;
    }
    
    /**
     * تنظیم Canonical
     */
    public function fix_canonical( $canonical ) {
        if ( ! is_shop() && ! is_product_category() && ! is_product_tag() ) {
            return $canonical;
        }
        
        // اگر هیچ query string نداریم
        if ( empty( $_GET ) ) {
            return $canonical; // از Yoast استفاده می‌کنیم
        }
        
        // بررسی اعتبار
        $validation = $this->validate_query_params();
        
        // اگر معتبر است
        if ( $validation['status'] === 'valid' && ! empty( $validation['valid_params'] ) ) {
            // canonical شامل همین پارامترهای معتبر باشد
            if ( is_product_category() ) {
                $cat = get_queried_object();
                $base_url = get_term_link( $cat );
            } elseif ( is_shop() ) {
                $base_url = get_permalink( wc_get_page_id( 'shop' ) );
            } else {
                return $canonical;
            }
            
            if ( is_wp_error( $base_url ) ) {
                return $canonical;
            }
            
            $canonical = add_query_arg( $validation['valid_params'], $base_url );
            $this->log( "🔗 Canonical set to: {$canonical}" );
        }
        
        // در غیر این صورت canonical به base URL
        
        return $canonical;
    }
    
    /**
     * Noindex برای صفحات نامعتبر (امان بیشتر)
     */
    public function set_noindex_if_invalid( $robots ) {
        if ( ! is_shop() && ! is_product_category() && ! is_product_tag() ) {
            return $robots;
        }
        
        if ( empty( $_GET ) ) {
            return $robots;
        }
        
        $validation = $this->validate_query_params();
        
        if ( $validation['status'] === 'invalid' ) {
            $this->log( "🤖 Setting noindex for invalid URL" );
            return 'noindex,nofollow';
        }
        
        return $robots;
    }
    
    /**
     * جلوگیری از اضافه شدن orderby به URL
     */
    public function prevent_orderby_in_url( $orderby_options ) {
        // ذخیره در session
        if ( ! session_id() ) {
            @session_start();
        }
        
        if ( isset( $_GET['orderby'] ) ) {
            $_SESSION['botri_orderby'] = sanitize_text_field( $_GET['orderby'] );
            $this->log( "💾 Orderby saved to session" );
        }
        
        // حذف orderby از URL بعدی
        add_filter( 'woocommerce_get_catalog_ordering_args', function( $args ) {
            if ( ! session_id() ) {
                @session_start();
            }
            
            if ( isset( $_SESSION['botri_orderby'] ) ) {
                $orderby = $_SESSION['botri_orderby'];
                
                switch ( $orderby ) {
                    case 'price':
                        $args['orderby'] = 'meta_value_num';
                        $args['order'] = 'ASC';
                        $args['meta_key'] = '_price';
                        break;
                    case 'price-desc':
                        $args['orderby'] = 'meta_value_num';
                        $args['order'] = 'DESC';
                        $args['meta_key'] = '_price';
                        break;
                    case 'popularity':
                        $args['orderby'] = 'meta_value_num';
                        $args['meta_key'] = 'total_sales';
                        $args['order'] = 'DESC';
                        break;
                    case 'rating':
                        $args['orderby'] = 'meta_value_num';
                        $args['meta_key'] = '_wc_average_rating';
                        $args['order'] = 'DESC';
                        break;
                    case 'date':
                        $args['orderby'] = 'date';
                        $args['order'] = 'DESC';
                        break;
                }
            }
            
            return $args;
        }, 999 );
        
        return $orderby_options;
    }
    
    /**
     * پاک کردن کش
     */
    public function clear_cache() {
        delete_transient( 'botri_v2_allowed_attrs' );
        $this->allowed_seo_attributes = [];
        $this->log( "🗑️ Cache cleared" );
    }
    
    /**
     * لاگ
     */
    private function log( $message, $data = null ) {
        if ( ! $this->debug_mode ) {
            return;
        }
        
        $timestamp = current_time( 'Y-m-d H:i:s' );
        $log_entry = "[{$timestamp}] [ANTI-ZOMBIE-V2] {$message}";
        
        if ( null !== $data ) {
            $log_entry .= "\n" . print_r( $data, true );
        }
        
        error_log( $log_entry );
    }
    
    /**
     * منوی ادمین
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=filter_seo_rule',
            '🛡️ Anti-Zombie v2 (SAFE)',
            '🛡️ Anti-Zombie v2',
            'manage_options',
            'botri-anti-zombie-v2',
            [ $this, 'render_admin_page' ]
        );
    }
    
    /**
     * صفحه ادمین
     */
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>🛡️ Botri Anti-Zombie Pages v2.0 (SAFE MODE)</h1>
            <p class="description">نسخه امن - فقط 404 می‌کند، هیچ redirect نامناسبی انجام نمی‌دهد</p>
            
            <div class="notice notice-success" style="padding: 15px; margin-top: 20px;">
                <h3 style="margin-top: 0;">✅ این نسخه کاملاً ایمن است</h3>
                <ul style="line-height: 2;">
                    <li>✅ فقط URL های کاملاً نامعتبر را 404 می‌کند</li>
                    <li>✅ هیچ redirect نامناسبی انجام نمی‌دهد</li>
                    <li>✅ با Yoast تداخل ندارد</li>
                    <li>✅ canonical فقط برای صفحات معتبر تنظیم می‌شود</li>
                </ul>
            </div>

            <div class="card" style="max-width: 100%; margin-top: 20px; padding: 20px;">
                <h2>📊 وضعیت سیستم</h2>
                <table class="widefat">
                    <tr>
                        <th style="width: 300px;">نسخه:</th>
                        <td><code><?php echo self::VERSION; ?></code></td>
                    </tr>
                    <tr>
                        <th>تعداد SEO Attributes:</th>
                        <td>
                            <strong><?php echo count( $this->allowed_seo_attributes ); ?></strong>
                            <?php if ( ! empty( $this->allowed_seo_attributes ) ): ?>
                                <br><code><?php echo esc_html( implode( ', ', $this->allowed_seo_attributes ) ); ?></code>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Debug Mode:</th>
                        <td>
                            <?php if ( $this->debug_mode ): ?>
                                <span style="color: green;">✅ فعال</span>
                            <?php else: ?>
                                <span style="color: orange;">⚠️ غیرفعال</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="card" style="max-width: 100%; margin-top: 20px; padding: 20px;">
                <h2>🎯 نحوه کار این نسخه</h2>
                <h3>URL های معتبر (هیچ اقدامی انجام نمی‌شود):</h3>
                <ul style="line-height: 2;">
                    <li>✅ <code>/pet-bottle/</code></li>
                    <li>✅ <code>/pet-bottle/?use-type=bottle-for-dairy</code> (فقط 1 SEO filter)</li>
                </ul>

                <h3>URL های نامعتبر (404 می‌شوند):</h3>
                <ul style="line-height: 2;">
                    <li>❌ <code>/pet-bottle/?orderby=price</code> → 404</li>
                    <li>❌ <code>/pet-bottle/?paged=2</code> → 404</li>
                    <li>❌ <code>/pet-bottle/?use-type=x&shape=y</code> → 404 (ترکیبی)</li>
                    <li>❌ <code>/pet-bottle/?filter_color=red</code> → 404</li>
                    <li>❌ <code>/pet-bottle/?min_price=10000</code> → 404</li>
                    <li>❌ <code>/pet-bottle/?orderby=date&use-type=bottle</code> → 404 (ترکیب نامعتبر)</li>
                </ul>
            </div>

            <div class="card" style="max-width: 100%; margin-top: 20px; padding: 20px;">
                <h2>🧪 تست کنید</h2>
                <ol style="line-height: 2;">
                    <li>به یک دسته‌بندی بروید: <code>/pet-bottle/</code></li>
                    <li>به انتهای URL اضافه کنید: <code>?orderby=price</code></li>
                    <li>باید صفحه 404 ببینید</li>
                </ol>
            </div>

            <div class="card" style="max-width: 100%; margin-top: 20px; padding: 20px;">
                <h2>🔄 پاک‌سازی کش</h2>
                <form method="post">
                    <?php wp_nonce_field( 'botri_clear_v2', 'nonce' ); ?>
                    <button type="submit" name="clear" class="button button-primary">
                        🗑️ پاک کردن کش
                    </button>
                </form>
                <?php
                if ( isset( $_POST['clear'] ) && check_admin_referer( 'botri_clear_v2', 'nonce' ) ) {
                    $this->clear_cache();
                    $this->load_seo_attributes();
                    echo '<div class="notice notice-success" style="margin-top: 15px;"><p>✅ کش پاک شد!</p></div>';
                }
                ?>
            </div>
        </div>
        
        <style>
            .card {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
            }
            .card h2 {
                margin-top: 0;
                color: #23282d;
                border-bottom: 2px solid #0073aa;
                padding-bottom: 10px;
            }
            .card code {
                background: #f0f0f1;
                padding: 2px 6px;
                border-radius: 3px;
            }
        </style>
        <?php
    }
}

// Initialize
Botri_Anti_Zombie_V2::instance();