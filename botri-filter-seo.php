<?php
/**
 * Plugin Name: Botri — Filter SEO Manager
 * Description: مدیریت فیلترهای سئو برای ووکامرس بدون نیاز به افزونه فیلتر
 * Version: 2.3.0
 * Author: Younes
 * Text Domain: botri-filter-seo
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action('plugins_loaded', function(){

    if ( ! class_exists('WooCommerce') ) {
        add_action('admin_notices', function(){
            echo '<div class="notice notice-warning"><p><strong>Botri Filter SEO Manager</strong> نیازمند فعال بودن ووکامرس است.</p></div>';
        });
        return;
    }

    class Botri_Filter_SEO_Manager {
        const CPT = 'filter_seo_rule';
        const CPT_NONSEO = 'filter_nonseo_rule';
        const VERSION = '2.3.0';

        private $current_rule = null;
        private $current_filters = [];

        public function __construct() {
            // ثبت CPT
            add_action('init', [$this, 'register_cpt']);
            
            // متاباکس‌ها
            add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
            add_action('save_post_' . self::CPT, [$this, 'save_meta'], 10, 2);
            add_action('save_post_' . self::CPT_NONSEO, [$this, 'save_meta_nonseo'], 10, 2);

            // اعمال قوانین SEO
            add_action('template_redirect', [$this, 'apply_rule'], 5);
            
            // نمایش فیلترها
            add_action('woocommerce_sidebar', [$this, 'show_filters_on_category'], 2);
            add_action('woocommerce_no_products_found', [$this, 'show_filters_on_category_no_products'], 2);
            
            // فیلتر محصولات
            add_action('woocommerce_product_query', [$this, 'filter_products_by_attributes'], 10);
            add_action('woocommerce_product_query', [$this, 'apply_non_seo_filters'], 10);

            // ستون‌های ادمین
            add_filter('manage_edit-' . self::CPT . '_columns', [$this, 'columns']);
            add_action('manage_' . self::CPT . '_posts_custom_column', [$this, 'column_content'], 10, 2);
            add_filter('manage_edit-' . self::CPT_NONSEO . '_columns', [$this, 'columns_nonseo']);
            add_action('manage_' . self::CPT_NONSEO . '_posts_custom_column', [$this, 'column_content_nonseo'], 10, 2);

            // اسکریپت‌ها و AJAX
            add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
            add_action('wp_footer', [$this, 'ajax_filter_js']);

            // جلوگیری از کش
            add_action('template_redirect', [$this, 'disable_cache_for_filters'], 1);

            // AJAX Handlers
            add_action('wp_ajax_botri_load_more_products', [$this, 'ajax_load_more_products']);
            add_action('wp_ajax_nopriv_botri_load_more_products', [$this, 'ajax_load_more_products']);
            
            // تنظیم تعداد محصولات
            add_action('pre_get_posts', [$this, 'set_products_per_page']);
            
            // پاک‌سازی کش در صورت تغییر
            add_action('save_post_' . self::CPT, [$this, 'clear_cache_on_save']);
            add_action('save_post_' . self::CPT_NONSEO, [$this, 'clear_cache_on_save']);
        }

        /**
         * ✅ Enqueue Scripts با nonce صحیح
         */
        public function enqueue_scripts() {
            if (!is_shop() && !is_product_category() && !is_product_tag()) {
                return;
            }

            wp_localize_script('jquery', 'botri_ajax', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('botri_filter_nonce'),
                'infinite_nonce' => wp_create_nonce('botri_infinite_scroll'),
            ]);
        }

        /**
         * ✅ جلوگیری کامل از کش برای صفحات فیلتر
         */
        public function disable_cache_for_filters() {
            if (!is_shop() && !is_product_category() && !is_product_tag()) {
                return;
            }

            if (!defined('DONOTCACHEPAGE')) {
                define('DONOTCACHEPAGE', true);
            }
            if (!defined('DONOTCACHEDB')) {
                define('DONOTCACHEDB', true);
            }
            if (!defined('DONOTMINIFY')) {
                define('DONOTMINIFY', true);
            }
            if (!defined('DONOTCDN')) {
                define('DONOTCDN', true);
            }
            if (!defined('DONOTCACHEOBJECT')) {
                define('DONOTCACHEOBJECT', true);
            }

            nocache_headers();
            
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Cache-Control: post-check=0, pre-check=0', false);
            header('Pragma: no-cache');
            header('Expires: 0');
        }

        /**
         * پاک‌سازی کش پس از ذخیره
         */
        public function clear_cache_on_save($post_id) {
            if (function_exists('wc_delete_product_transients')) {
                wc_delete_product_transients();
            }
            
            wp_cache_flush();
            
            if (function_exists('wpseo_clear_cache')) {
                wpseo_clear_cache();
            }
            
            // پاک کردن کش rule matching
            wp_cache_delete('botri_all_rules', 'botri_filters');
            
            // حذف transient های محتوا
            delete_transient('botri_content_cache');
            delete_transient('botri_h1_cache');
        }

        /**
         * ✅ تنظیم تعداد محصولات - صفحه اول 12، بعدی‌ها 9
         */
        public function set_products_per_page($query) {
            if (!is_admin() && $query->is_main_query() && (is_shop() || is_product_category() || is_product_tag())) {
                $query->set('posts_per_page', 12);
            }
        }

        /**
         * ✅ AJAX Handler برای لود محصولات بیشتر - 9 تا در هر بار
         * 
         * ✅ FIX v2.3:
         *   1. فیلترها از $_POST['filters'] خوانده می‌شوند (نه $_GET که در AJAX خالی است)
         *   2. offset به درستی محاسبه می‌شود:
         *      - صفحه 1: 12 محصول (از main query)
         *      - صفحه 2+: offset = 12 + (paged-2)*9
         *   3. max_num_pages بر اساس total products محاسبه می‌شود
         */
        public function ajax_load_more_products() {
            check_ajax_referer('botri_infinite_scroll', 'nonce');
            
            $paged    = isset($_POST['paged'])    ? intval($_POST['paged'])    : 2;
            $category = isset($_POST['category']) ? intval($_POST['category']) : 0;
            $wd_hover = isset($_POST['wd_hover']) ? sanitize_key($_POST['wd_hover']) : '';

            // ✅ FIX: محاسبه صحیح offset
            // صفحه اول (main query): 12 محصول
            // صفحه‌های بعدی (AJAX): هر بار 9 محصول
            $per_page_first = 12;
            $per_page_ajax  = 9;
            $offset = $per_page_first + ($paged - 2) * $per_page_ajax;

            $args = [
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => $per_page_ajax,
                'offset'         => $offset,
            ];

            if ($category > 0) {
                $args['tax_query'] = [
                    [
                        'taxonomy' => 'product_cat',
                        'field'    => 'term_id',
                        'terms'    => $category,
                    ]
                ];
            }

            // ✅ FIX v2.3: خواندن فیلترهای SEO از $_POST['filters'] (نه $_GET)
            // JS این پارامترها را به صورت query string در POST['filters'] ارسال می‌کند
            $get_filters = [];
            if (!empty($_POST['filters'])) {
                parse_str(sanitize_text_field(wp_unslash($_POST['filters'])), $get_filters);
            }

            $tax_query = isset($args['tax_query']) ? $args['tax_query'] : [];
            $skip_keys = ['orderby', 'min_price', 'max_price', 'paged', 's', 'action', 'nonce'];
            
            foreach ($get_filters as $key => $value) {
                if (in_array($key, $skip_keys, true)) continue;
                if (empty($value)) continue;

                $tax_real  = (0 === strpos($key, 'pa_')) ? $key : 'pa_' . sanitize_key($key);
                $term_slugs = array_map('sanitize_title', explode(',', $value));

                if (taxonomy_exists($tax_real)) {
                    $tax_query[] = [
                        'taxonomy' => $tax_real,
                        'field'    => 'slug',
                        'terms'    => $term_slugs,
                        'operator' => 'IN',
                    ];
                }
            }

            // اضافه کردن فیلترهای Non-SEO از کوکی
            if (isset($_COOKIE['botri_nonseo_filters'])) {
                $filters = json_decode(stripslashes($_COOKIE['botri_nonseo_filters']), true);
                if (is_array($filters)) {
                    foreach ($filters as $key => $value) {
                        if (strpos($key, 'filter_') === 0) {
                            $attr = str_replace('filter_', '', $key);
                            $tax_real = 'pa_' . $attr;
                            if (taxonomy_exists($tax_real)) {
                                $term_slugs = explode(',', $value);
                                $tax_query[] = [
                                    'taxonomy' => $tax_real,
                                    'field' => 'slug',
                                    'terms' => array_map('sanitize_text_field', $term_slugs),
                                    'operator' => 'IN',
                                ];
                            }
                        }
                    }

                    if (isset($filters['min_price']) && isset($filters['max_price'])) {
                        $args['meta_query'] = [
                            [
                                'key' => '_price',
                                'value' => [floatval($filters['min_price']), floatval($filters['max_price'])],
                                'type' => 'numeric',
                                'compare' => 'BETWEEN',
                            ]
                        ];
                    }
                }
            }

            if (count($tax_query) > 1) {
                $tax_query['relation'] = 'AND';
            }

            if (!empty($tax_query)) {
                $args['tax_query'] = $tax_query;
            }

            $query = new WP_Query($args);

            ob_start();
            if ($query->have_posts()) {
                // ✅ FIX v2.6: تنظیم کامل WooCommerce loop context و Woodmart Hover
                
                // ابتدا loop قبلی را reset کن
                wc_reset_loop();
                
                // تنظیم متغیرهای جهانی وودمارت برای حفظ ظاهر
                if (!empty($wd_hover)) {
                    global $woodmart_loop;
                    $woodmart_loop['hover'] = $wd_hover;
                }

                // تنظیم loop properties برای shop/archive context
                wc_setup_loop([
                    'name'         => '',          // نه shortcode، نه widget - archive معمولی
                    'columns'      => wc_get_default_products_per_row(),
                    'is_shortcode' => false,
                    'is_paginated' => false,
                    'is_search'    => !empty($get_filters) || !empty($tax_query),
                    'is_filtered'  => !empty($get_filters) || !empty($tax_query),
                    'total'        => $query->found_posts,
                    'total_pages'  => 1,
                    'per_page'     => $per_page_ajax,
                    'current_page' => $paged,
                ]);

                while ($query->have_posts()) {
                    $query->the_post();
                    // اضافه کردن کلاس برای شناسایی در JS
                    echo '<div class="botri-ajax-item">';
                    wc_get_template_part('content', 'product');
                    echo '</div>';
                }
                
                // reset بعد از اتمام
                wc_reset_loop();
            }
            $products_html = ob_get_clean();

            wp_reset_postdata();

            // ✅ FIX v2.3: محاسبه صحیح max_num_pages
            // total_products = تعداد کل محصولات فیلترشده
            // صفحه 1: 12 تا | صفحه‌های بعدی: هر بار 9 تا
            $total_products = $query->found_posts;
            if ($total_products <= $per_page_first) {
                $max_num_pages = 1;
            } else {
                $max_num_pages = 1 + ceil(($total_products - $per_page_first) / $per_page_ajax);
            }

            wp_send_json_success([
                'products'      => $products_html,
                'found_posts'   => $total_products,
                'max_num_pages' => $max_num_pages,
                'current_page'  => $paged,
                'debug_classes' => $this->get_first_product_classes($products_html),
            ]);
        }

        /**
         * Debug: استخراج کلاس‌های اولین محصول از HTML
         */
        private function get_first_product_classes($html) {
            preg_match('/<(li|div)[^>]*class="([^"]*product[^"]*)"/', $html, $m);
            return $m[2] ?? 'not-found';
        }

        /** --- ثبت پست تایپ قوانین فیلتر --- **/
        public function register_cpt() {
            register_post_type(self::CPT, [
                'label' => 'قوانین فیلتر SEO',
                'public' => false,
                'show_ui' => true,
                'menu_icon' => 'dashicons-filter',
                'supports' => ['title'],
                'show_in_menu' => true,
            ]);

            register_post_type(self::CPT_NONSEO, [
                'label' => 'قوانین فیلتر Non-SEO',
                'public' => false,
                'show_ui' => true,
                'menu_icon' => 'dashicons-filter',
                'supports' => ['title'],
                'show_in_menu' => true,
            ]);
        }

        /** --- متاباکس‌ها --- **/
        public function register_meta_boxes() {
            add_meta_box('botri_filter_main', 'تنظیمات رول فیلتر', [$this, 'metabox_main'], self::CPT);
            add_meta_box('botri_filter_seo', 'تنظیمات سئو', [$this, 'metabox_seo'], self::CPT);
            add_meta_box('botri_filter_nonseo', 'تنظیمات رول فیلتر غیر سئویی', [$this, 'metabox_nonseo'], self::CPT_NONSEO);
        }

        public function metabox_main($post) {
            wp_nonce_field('botri_save_meta', 'botri_meta_nonce');
            
            $taxonomy = get_post_meta($post->ID, '_taxonomy', true);
            $term = get_post_meta($post->ID, '_term', true);
            $cats = (array)get_post_meta($post->ID, '_cats', true);
            $index = get_post_meta($post->ID, '_index', true);
            $product_cats = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
            ?>
            <p><label>تاکسونومی ویژگی (مثلاً use-type):</label>
                <input type="text" name="botri_taxonomy" value="<?= esc_attr($taxonomy) ?>" class="widefat" placeholder="مثلاً use-type"></p>
            <p><label>Slug ویژگی (مثلاً bottle-for-juice):</label>
                <input type="text" name="botri_term" value="<?= esc_attr($term) ?>" class="widefat"></p>
            <p><label>دسته‌هاییی که این فیلتر در آن فعال باشد:</label></p>
            <div style="max-height:200px;overflow:auto;border:1px solid #ddd;padding:5px;">
                <?php foreach($product_cats as $c): ?>
                    <label><input type="checkbox" name="botri_cats[]" value="<?=$c->term_id?>" <?php checked(in_array($c->term_id,$cats));?>> <?=$c->name?></label><br>
                <?php endforeach; ?>
            </div>
            <p><label><input type="checkbox" name="botri_index" value="1" <?php checked($index,'1');?>> اجازه ایندکس این URL (پیروی از تنظیمات Yoast)</label></p>
            <?php
        }

        public function metabox_seo($post) {
            $title = get_post_meta($post->ID, '_title', true);
            $desc = get_post_meta($post->ID, '_desc', true);
            $h1 = get_post_meta($post->ID, '_h1', true);
            $content = get_post_meta($post->ID, '_content', true);
            ?>
            <p><label>Meta Title:</label>
                <input type="text" name="botri_title" value="<?= esc_attr($title) ?>" class="widefat"></p>
            <p><label>Meta Description:</label>
                <textarea name="botri_desc" rows="3" class="widefat"><?= esc_textarea($desc) ?></textarea></p>
            <p><label>H1:</label>
                <input type="text" name="botri_h1" value="<?= esc_attr($h1) ?>" class="widefat"></p>
            <p><label>متن توضیحات (HTML مجاز است):</label>
                <?php wp_editor($content, 'botri_content', ['textarea_name' => 'botri_content', 'media_buttons' => false]); ?></p>
            <?php
        }

        public function metabox_nonseo($post) {
            wp_nonce_field('botri_save_meta_nonseo', 'botri_meta_nonseo_nonce');
            
            $taxonomy = get_post_meta($post->ID, '_taxonomy', true);
            $terms = (array)get_post_meta($post->ID, '_terms', true);
            $cats = (array)get_post_meta($post->ID, '_cats', true);
            $enable_shop = get_post_meta($post->ID, '_enable_shop', true);
            $product_cats = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
            ?>
            <div style="background: #e3f2fd; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #2196F3;">
                <h4 style="margin-top: 0;">💡 راهنمای استفاده</h4>
                <ul style="margin: 0; padding-right: 20px;">
                    <li>✅ با فعال کردن "نمایش در Shop"، این فیلتر در صفحه فروشگاه اصلی نمایش داده می‌شود</li>
                    <li>✅ می‌توانید همزمان برای Shop و دسته‌بندی‌های خاص فعال کنید</li>
                    <li>✅ فیلترها SEO-safe هستند و URL را تغییر نمی‌دهند</li>
                </ul>
            </div>

            <p><label>تاکسونومی ویژگی (مثلاً neck):</label>
                <input type="text" name="botri_taxonomy" value="<?= esc_attr($taxonomy) ?>" class="widefat" placeholder="مثلاً neck"></p>
            
            <p><label>مشخصه‌هاییی که نمایش داده شوند:</label></p>
            <?php if ($taxonomy): ?>
                <?php 
                $tax_real = $this->normalize_tax($taxonomy);
                $attr_terms = get_terms(['taxonomy' => $tax_real, 'hide_empty' => false]); 
                ?>
                <div style="max-height:200px;overflow:auto;border:1px solid #ddd;padding:5px;">
                    <?php foreach($attr_terms as $t): ?>
                        <label><input type="checkbox" name="botri_terms[]" value="<?=$t->term_id?>" <?php checked(in_array($t->term_id,$terms));?>> <?=$t->name?></label><br>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>ابتدا تاکسونومی را وارد کنید و ذخیره کنید تا مشخصه‌ها نمایش داده شوند.</p>
            <?php endif; ?>
            
            <hr style="margin: 20px 0;">
            
            <h4 style="color: #2196F3;">📍 محل نمایش فیلتر</h4>
            
            <p style="background: #fff3cd; padding: 10px; border-radius: 4px; border-left: 3px solid #ffc107;">
                <strong>🪐 نمایش در صفحه Shop (فروشگاه اصلی)</strong>
            </p>
            <p>
                <label style="font-size: 15px;">
                    <input type="checkbox" name="botri_enable_shop" value="1" <?php checked($enable_shop, '1'); ?>> 
                    <strong>نمایش این فیلتر در صفحه Shop</strong>
                </label>
            </p>
            
            <hr style="margin: 20px 0;">
            
            <p style="background: #e8f5e9; padding: 10px; border-radius: 4px; border-left: 3px solid #4caf50;">
                <strong>📂 نمایش در دسته‌بندی‌های خاص</strong>
            </p>
            <p><label>دسته‌هاییی که این فیلتر در آن فعال باشد:</label></p>
            <div style="max-height:200px;overflow:auto;border:1px solid #ddd;padding:5px;">
                <?php foreach($product_cats as $c): ?>
                    <label><input type="checkbox" name="botri_cats[]" value="<?=$c->term_id?>" <?php checked(in_array($c->term_id,$cats));?>> <?=$c->name?></label><br>
                <?php endforeach; ?>
            </div>
            <?php
        }

        public function save_meta($id, $post) {
            if (!isset($_POST['botri_meta_nonce']) || !wp_verify_nonce($_POST['botri_meta_nonce'], 'botri_save_meta')) {
                return;
            }
            
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
            if (!current_user_can('edit_post', $id)) return;

            update_post_meta($id, '_taxonomy', sanitize_text_field($_POST['botri_taxonomy'] ?? ''));
            update_post_meta($id, '_term', sanitize_text_field($_POST['botri_term'] ?? ''));
            update_post_meta($id, '_cats', array_map('intval', $_POST['botri_cats'] ?? []));
            update_post_meta($id, '_index', isset($_POST['botri_index']) ? '1' : '0');
            update_post_meta($id, '_title', sanitize_text_field($_POST['botri_title'] ?? ''));
            update_post_meta($id, '_desc', sanitize_textarea_field($_POST['botri_desc'] ?? ''));
            update_post_meta($id, '_h1', sanitize_text_field($_POST['botri_h1'] ?? ''));
            update_post_meta($id, '_content', wp_kses_post($_POST['botri_content'] ?? ''));
        }

        public function save_meta_nonseo($id, $post) {
            if (!isset($_POST['botri_meta_nonseo_nonce']) || !wp_verify_nonce($_POST['botri_meta_nonseo_nonce'], 'botri_save_meta_nonseo')) {
                return;
            }
            
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
            if (!current_user_can('edit_post', $id)) return;

            update_post_meta($id, '_taxonomy', sanitize_text_field($_POST['botri_taxonomy'] ?? ''));
            update_post_meta($id, '_terms', array_map('intval', $_POST['botri_terms'] ?? []));
            update_post_meta($id, '_cats', array_map('intval', $_POST['botri_cats'] ?? []));
            update_post_meta($id, '_enable_shop', isset($_POST['botri_enable_shop']) ? '1' : '0');
        }

        /** --- ابزارهای داخلی --- **/
        private function normalize_tax($tax) {
            return (0 === strpos($tax, 'pa_')) ? $tax : 'pa_' . $tax;
        }

        private function query_key($tax) {
            return ltrim(preg_replace('/^pa_/', '', $tax));
        }

        /** --- 🔥 یافتن رول فعال - FIXED برای جلوگیری از کش نادرست --- **/
        private function get_matching_rule() {
            if (!is_product_category()) return null;
            
            $cat = get_queried_object();
            if (!$cat || !isset($cat->term_id)) return null;
            
            // ⚠️ حذف کش برای جلوگیری از مشکل محتوای تکراری
            // کش می‌تواند باعث شود محتوای یک صفحه در صفحه دیگر نمایش داده شود
            
            $rules = get_posts([
                'post_type' => self::CPT,
                'numberposts' => -1,
                'post_status' => 'publish',
                'cache_results' => false, // ضد کش
                'no_found_rows' => true
            ]);
            
            $matched_rule = null;
            
            foreach($rules as $r) {
                $tax_input = get_post_meta($r->ID, '_taxonomy', true);
                $term = get_post_meta($r->ID, '_term', true);
                $cats = (array)get_post_meta($r->ID, '_cats', true);

                if (empty($tax_input) || empty($term) || empty($cats)) continue;

                // چک کردن اینکه این دسته در لیست دسته‌های این rule هست
                if (!in_array($cat->term_id, $cats)) continue;

                $tax_real = $this->normalize_tax($tax_input);
                $q_key = $this->query_key($tax_real);

                // چک کردن اینکه پارامتر GET مورد نظر وجود داره و با term مطابقت داره
                if (isset($_GET[$q_key]) && sanitize_title($_GET[$q_key]) === $term) {
                    $matched_rule = $r;
                    break; // اولین rule منطبق رو برمیگردونیم
                }
            }
            
            return $matched_rule;
        }

        /** --- 🔥 اعمال متاتگ‌ها و محتوا - FIXED برای جلوگیری از تداخل --- **/
        public function apply_rule() {
            // ✅ FIX v2.6: همیشه فیلترها را ریست کن (حتی اگر در دسته نیستیم)
            // تا از نشت محتوا بین صفحات مختلف جلوگیری شود
            remove_all_filters('botri_seo_h1');
            remove_all_filters('botri_seo_content');
            
            if (!is_product_category()) return;

            $rule = $this->get_matching_rule();
            if (!$rule) {
                // اگر rule پیدا نشد، محتوای پیش‌فرض دسته نمایش داده می‌شه
                return;
            }

            $this->current_rule = $rule;

            $index = get_post_meta($rule->ID, '_index', true) === '1';
            $title = get_post_meta($rule->ID, '_title', true);
            $desc = get_post_meta($rule->ID, '_desc', true);
            $h1 = get_post_meta($rule->ID, '_h1', true);
            $content = get_post_meta($rule->ID, '_content', true);
            
            // URL فعلی
            $current_url = home_url(add_query_arg(null, null));
            
            // شناسایی فیلترهای اضافی
            $extra_params = $this->has_extra_filters($rule);
            
            // متاتگ‌ها
            add_filter('wpseo_title', fn($t) => $title ?: $t, 999);
            add_filter('wpseo_metadesc', fn($d) => $desc ?: $d, 999);
            add_filter('document_title_parts', fn($parts) => ['title' => $title ?: $parts['title']], 999);
            
            // Open Graph
            add_filter('wpseo_opengraph_title', fn($t) => $title ?: $t, 999);
            add_filter('wpseo_opengraph_desc', fn($d) => $desc ?: $d, 999);
            add_filter('wpseo_opengraph_type', fn() => 'product.group', 999);
            add_filter('wpseo_opengraph_url', fn() => $current_url, 999);
            
            // Twitter
            add_filter('wpseo_twitter_title', fn($t) => $title ?: $t, 999);
            add_filter('wpseo_twitter_description', fn($d) => $desc ?: $d, 999);

            // Canonical و Robots
            if ($extra_params) {
                add_filter('wpseo_robots', fn() => 'noindex,follow', 999);
                $canonical_url = $this->get_clean_canonical_url($rule);
                add_filter('wpseo_canonical', fn() => $canonical_url, 999);
            } else {
                if ($index) {
                    add_filter('wpseo_robots', fn() => 'index,follow', 999);
                } else {
                    add_filter('wpseo_robots', fn() => 'noindex,follow', 999);
                }
                add_filter('wpseo_canonical', fn() => $current_url, 999);
            }

            // Schema
            add_filter('wpseo_schema_graph', function($graph) use ($title, $desc, $current_url) {
                foreach ($graph as &$piece) {
                    if (isset($piece['@type']) && $piece['@type'] === 'CollectionPage') {
                        $piece['name'] = $title;
                        $piece['description'] = $desc;
                        $piece['url'] = $current_url;
                        $piece['@id'] = $current_url . '#webpage';
                    }
                    if (isset($piece['@type']) && $piece['@type'] === 'BreadcrumbList' && !empty($piece['itemListElement'])) {
                        $last = count($piece['itemListElement']) - 1;
                        $piece['itemListElement'][$last]['name'] = $title;
                        $piece['itemListElement'][$last]['item'] = $current_url;
                    }
                }
                return $graph;
            }, 999);

            // 🔥 ذخیره H1 و Content برای استفاده در ویجت‌ها
            // با استفاده از متغیرهای جداگانه برای هر rule
            $h1_value = $h1;  // کپی مقدار برای جلوگیری از reference
            $content_value = $content;  // کپی مقدار برای جلوگیری از reference
            
            add_filter('botri_seo_h1', function() use ($h1_value) {
                return $h1_value;
            }, 999);
            
            add_filter('botri_seo_content', function() use ($content_value) {
                return $content_value;
            }, 999);

            // نمایش در loop (فقط برای sidebar - نه برای المنتور)
            add_action('woocommerce_before_shop_loop', function() use ($h1_value, $content_value) {
                if (did_action('elementor/loaded')) return;
                
                echo '<div class="botri-filter-seo" style="clear:both;margin:20px 0;padding:10px 0;border-bottom:1px solid #eee;">';
                if ($h1_value) echo '<h1 style="font-size:22px;margin-bottom:10px;">' . esc_html($h1_value) . '</h1>';
                if ($content_value) echo '<div class="botri-seo-content">' . wp_kses_post($content_value) . '</div>';
                echo '</div>';
            }, 3);
        }

        /**
         * ✅ شناسایی فیلترهای اضافی
         */
        private function has_extra_filters($rule) {
            if (!$rule) return false;
            
            $tax_input = get_post_meta($rule->ID, '_taxonomy', true);
            if (empty($tax_input)) return false;
            
            $tax_real = $this->normalize_tax($tax_input);
            $seo_key = $this->query_key($tax_real);
            
            $ignored_keys = ['orderby', 'min_price', 'max_price', 'paged', 's', 'product-page'];
            
            foreach ($_GET as $key => $value) {
                if ($key !== $seo_key && !in_array($key, $ignored_keys)) {
                    return true;
                }
            }
            
            return false;
        }

        /**
         * ✅ دریافت URL کانونیکال تمیز
         */
        private function get_clean_canonical_url($rule) {
            $cat = get_queried_object();
            $base_url = get_term_link($cat);
            
            $tax_input = get_post_meta($rule->ID, '_taxonomy', true);
            $term = get_post_meta($rule->ID, '_term', true);
            
            if ($tax_input && $term) {
                $q_key = $this->query_key($this->normalize_tax($tax_input));
                $base_url = add_query_arg($q_key, $term, $base_url);
            }
            
            return $base_url;
        }

        /** --- نمایش فیلترها در دسته محصول --- **/
        public function show_filters_on_category() {
            if (!is_product_category() && !is_shop()) return;
            
            $cat = null;
            $is_shop = is_shop();
            
            if (!$is_shop) {
                $cat = get_queried_object();
                if (!$cat) return;
            }
            
            $seo_filters = $this->collect_seo_filters($cat, $is_shop);
            $nonseo_filters = $this->collect_nonseo_filters($cat, $is_shop);
            
            if (empty($seo_filters) && empty($nonseo_filters)) return;

            echo '<div class="botri-sidebar-filters">';
            echo '<h3 class="botri-filter-title">فیلتر محصولات</h3>';

            // نمایش فیلترهای SEO
            foreach ($seo_filters as $tax => $group) {
                echo '<details class="botri-filter-group botri-seo">';
                echo '<summary>'.esc_html($group['label']).'</summary>';
                echo '<div class="botri-filter-options">';
                foreach ($group['terms'] as $t) {
                    // ✅ FIX: شروع از URL پایه دسته‌بندی - نه URL فعلی
                    $base_url  = $cat ? get_term_link($cat) : home_url('/');
                    $url       = add_query_arg($tax, $t->slug, $base_url);
                    $is_active = (isset($_GET[$tax]) && $_GET[$tax] === $t->slug);
                    $active    = $is_active ? 'active' : '';
                    $href      = $is_active ? esc_url($base_url) : esc_url($url);
                    echo '<a href="'.$href.'" class="botri-filter-item '.$active.'">'.esc_html($t->name).'</a>';
                }
                echo '</div></details>';
            }

            // نمایش فیلترهای Non-SEO
            foreach ($nonseo_filters as $tax => $group) {
                echo '<details class="botri-filter-group botri-nonseo">';
                echo '<summary>'.esc_html($group['label']).'</summary>';
                echo '<div class="botri-filter-options">';
                foreach ($group['terms'] as $t) {
                    $active = '';
                    $cookie = json_decode(stripslashes($_COOKIE['botri_nonseo_filters'] ?? '{}'), true);
                    $filter_key = 'filter_' . $tax;
                    if (isset($cookie[$filter_key]) && in_array($t->slug, explode(',', $cookie[$filter_key]))) {
                        $active = 'active';
                    }
                    echo '<a href="javascript:void(0);" data-slug="' . esc_attr($t->slug) . '" data-tax="' . esc_attr($tax) . '" class="botri-filter-item botri-nonseo-item '.$active.'">'.esc_html($t->name).'</a>';
                }
                echo '</div></details>';
            }

            // فیلتر قیمت
            echo '<details class="botri-filter-group botri-nonseo">';
            echo '<summary>محدوده قیمت</summary>';
            echo '<div class="botri-filter-options">';
            the_widget('WC_Widget_Price_Filter');
            echo '</div></details>';

            // فیلترهای فعال
            echo '<h3 class="botri-filter-title">فیلترهای فعال</h3>';
            echo '<ul class="botri-active-filters">';
            the_widget('WC_Widget_Layered_Nav_Filters');
            
            if (isset($_COOKIE['botri_nonseo_filters'])) {
                $filters = json_decode(stripslashes($_COOKIE['botri_nonseo_filters']), true);
                if (isset($filters['min_price']) && isset($filters['max_price'])) {
                    echo '<li>قیمت: ' . wc_price($filters['min_price']) . ' - ' . wc_price($filters['max_price']) . ' <a href="#" class="botri-remove-nonseo" data-key="price">حذف</a></li>';
                }
                foreach ($filters as $key => $value) {
                    if (strpos($key, 'filter_') === 0) {
                        $attr = str_replace('filter_', '', $key);
                        $term_slugs = explode(',', $value);
                        foreach ($term_slugs as $slug) {
                            $term = get_term_by('slug', $slug, 'pa_' . $attr);
                            if ($term && !is_wp_error($term)) {
                                echo '<li>' . esc_html($term->name) . ' <a href="#" class="botri-remove-nonseo" data-key="' . esc_attr($key) . '" data-slug="' . esc_attr($slug) . '">حذف</a></li>';
                            }
                        }
                    }
                }
            }
            echo '</ul>';
            echo '<a href="#" class="botri-clear-all">پاک کردن همه فیلترها</a>';
            echo '</div>';

            $this->render_filter_styles();
        }

        /**
         * ✅ جمع‌آوری فیلترهای SEO
         */
        private function collect_seo_filters($cat, $is_shop) {
            $filters_by_tax = [];
            
            if ($is_shop) return $filters_by_tax;
            
            $rules = get_posts(['post_type' => self::CPT, 'numberposts' => -1, 'post_status' => 'publish']);
            
            foreach ($rules as $r) {
                $tax_input = get_post_meta($r->ID, '_taxonomy', true);
                $term = get_post_meta($r->ID, '_term', true);
                $cats = (array)get_post_meta($r->ID, '_cats', true);

                if (in_array($cat->term_id, $cats) && $tax_input && $term) {
                    $tax_real = $this->normalize_tax($tax_input);
                    $q_key = $this->query_key($tax_real);

                    $term_obj = get_term_by('slug', $term, $tax_real);
                    if ($term_obj && !is_wp_error($term_obj)) {
                        $label = get_taxonomy($tax_real)->label ?? $q_key;
                        $label = str_replace(['محصول ', 'Product '], '', $label);
                        
                        $filters_by_tax[$q_key]['label'] = $label;
                        $filters_by_tax[$q_key]['terms'][] = $term_obj;
                    }
                }
            }
            
            return $filters_by_tax;
        }

        /**
         * ✅ جمع‌آوری فیلترهای Non-SEO
         */
        private function collect_nonseo_filters($cat, $is_shop) {
            $nonseo_by_tax = [];
            $nonseo_rules = get_posts(['post_type' => self::CPT_NONSEO, 'numberposts' => -1, 'post_status' => 'publish']);
            
            foreach ($nonseo_rules as $r) {
                $tax_input = get_post_meta($r->ID, '_taxonomy', true);
                $terms_ids = (array)get_post_meta($r->ID, '_terms', true);
                $cats = (array)get_post_meta($r->ID, '_cats', true);
                $enable_shop = get_post_meta($r->ID, '_enable_shop', true);

                $should_show = false;
                
                if ($is_shop && $enable_shop === '1') {
                    $should_show = true;
                } elseif (!$is_shop && in_array($cat->term_id, $cats)) {
                    $should_show = true;
                }

                if ($should_show && $tax_input) {
                    $tax_real = $this->normalize_tax($tax_input);
                    $q_key = $this->query_key($tax_real);
                    $label = get_taxonomy($tax_real)->label ?? $q_key;
                    $label = str_replace(['محصول ', 'Product '], '', $label);
                    
                    $terms = [];
                    
                    foreach ($terms_ids as $tid) {
                        $term = get_term($tid, $tax_real);
                        if ($term && !is_wp_error($term)) $terms[] = $term;
                    }
                    
                    if (!empty($terms)) {
                        $nonseo_by_tax[$q_key]['label'] = $label;
                        $nonseo_by_tax[$q_key]['terms'] = $terms;
                    }
                }
            }
            
            return $nonseo_by_tax;
        }

        public function show_filters_on_category_no_products() {
            $this->show_filters_on_category();
        }

        private function render_filter_styles() {
            echo '<style>
            .botri-sidebar-filters {
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 12px;
                background: #fafafa;
                margin-bottom: 20px;
            }
            .botri-filter-title {
                font-size: 15px;
                font-weight: 700;
                color: #333;
                border-bottom: 1px solid #ddd;
                padding-bottom: 5px;
                margin-bottom: 8px;
                margin-top: 0;
            }
            .botri-filter-group summary {
                cursor: pointer;
                font-weight: 600;
                font-size: 14px;
                color: #0073aa;
                padding: 8px 0;
            }
            .botri-filter-options {
                margin-top: 5px;
                padding-left: 10px;
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
            }
            .botri-filter-item {
                padding: 3px 9px;
                border: 1px solid #ccc;
                border-radius: 15px;
                background: #fff;
                color: #333;
                text-decoration: none;
                font-size: 13px;
                transition: all 0.2s ease;
            }
            .botri-filter-item:hover {
                border-color: #0073aa;
                color: #0073aa;
            }
            .botri-filter-item.active {
                background: #0073aa;
                border-color: #0073aa;
                color: #fff !important;
            }
            .botri-clear-all {
                display: block;
                margin-top: 10px;
                color: #0073aa;
                text-decoration: none;
            }
            </style>';
        }

        /** --- فیلتر واقعی محصولات بر اساس ویژگی انتخاب‌شده --- **/
        public function filter_products_by_attributes($q) {
            if ((!is_product_category() && !is_shop()) || is_admin() || !isset($q)) return;
            
            // ⚠️ حذف شرط جلوگیری از فیلتر در صفحات بعدی
            // این باعث می‌شد محصولات فیلتر نشده در infinite scroll لود شوند
            
            $tax_query = (array) $q->get('tax_query');

            foreach ($_GET as $key => $value) {
                if (in_array($key, ['orderby','min_price','max_price','paged','product-page','s'])) continue;
                if (empty($value)) continue;

                $tax_real = (0 === strpos($key, 'pa_')) ? $key : 'pa_' . $key;
                $term_slugs = array_map('sanitize_title', explode(',', $value));

                if (taxonomy_exists($tax_real)) {
                    $tax_query[] = [
                        'taxonomy' => $tax_real,
                        'field'    => 'slug',
                        'terms'    => $term_slugs,
                        'operator' => 'IN',
                    ];
                }
            }

            if (count($tax_query) > 1) {
                $tax_query['relation'] = 'AND';
            }

            $q->set('tax_query', $tax_query);
        }

        /** --- اعمال فیلترهای non-SEO از کوکی --- **/
        public function apply_non_seo_filters($q) {
            if ((!is_product_category() && !is_shop()) || is_admin() || !isset($q)) return;
            
            // ⚠️ حذف شرط جلوگیری از فیلتر در صفحات بعدی
            
            if (isset($_COOKIE['botri_nonseo_filters'])) {
                $filters = json_decode(stripslashes($_COOKIE['botri_nonseo_filters']), true);
                if (!is_array($filters)) return;
                
                $tax_query = (array) $q->get('tax_query');
                $meta_query = (array) $q->get('meta_query');

                if (isset($filters['min_price']) && isset($filters['max_price'])) {
                    $meta_query[] = [
                        'key' => '_price',
                        'value' => [floatval($filters['min_price']), floatval($filters['max_price'])],
                        'type' => 'numeric',
                        'compare' => 'BETWEEN'
                    ];
                }

                foreach ($filters as $key => $value) {
                    if (strpos($key, 'filter_') === 0) {
                        $attr = str_replace('filter_', '', $key);
                        $tax_real = 'pa_' . $attr;
                        if (taxonomy_exists($tax_real)) {
                            $term_slugs = explode(',', $value);
                            $tax_query[] = [
                                'taxonomy' => $tax_real,
                                'field'    => 'slug',
                                'terms'    => array_map('sanitize_text_field', $term_slugs),
                                'operator' => 'IN',
                            ];
                        }
                    }
                }

                if (count($tax_query) > 1) {
                    $tax_query['relation'] = 'AND';
                }

                if (count($meta_query) > 1) {
                    $meta_query['relation'] = 'AND';
                }

                $q->set('tax_query', $tax_query);
                $q->set('meta_query', $meta_query);
            }
        }

        /** --- اسکریپت JS برای مدیریت فیلترهای non-SEO --- **/
        public function ajax_filter_js() {
            if (!is_product_category() && !is_shop()) return;
            ?>
            <script type="text/javascript">
                function getCookie(name) {
                    let matches = document.cookie.match(new RegExp("(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"));
                    return matches ? decodeURIComponent(matches[1]) : undefined;
                }

                function setCookie(name, value, days) {
                    let date = new Date;
                    date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                    document.cookie = name + "=" + encodeURIComponent(value) + ";path=/;expires=" + date.toUTCString() + ";SameSite=Lax";
                }

                document.addEventListener('DOMContentLoaded', function() {
                    document.querySelectorAll('.botri-nonseo-item').forEach(function(a) {
                        a.addEventListener('click', function(e) {
                            e.preventDefault();
                            
                            let tax = a.dataset.tax;
                            let slug = a.dataset.slug;
                            let obj = JSON.parse(getCookie('botri_nonseo_filters') || '{}');
                            let key = 'filter_' + tax;
                            let values = obj[key] ? obj[key].split(',') : [];
                            let index = values.indexOf(slug);
                            
                            if (index > -1) {
                                values.splice(index, 1);
                                a.classList.remove('active');
                            } else {
                                values.push(slug);
                                a.classList.add('active');
                            }
                            
                            if (values.length > 0) {
                                obj[key] = values.join(',');
                            } else {
                                delete obj[key];
                            }
                            
                            setCookie('botri_nonseo_filters', JSON.stringify(obj), 1);
                            
                            if (typeof showFullPageLoading === 'function') {
                                showFullPageLoading();
                            }
                            
                            setTimeout(function() {
                                window.location.reload();
                            }, 300);
                        });
                    });

                    let priceForm = document.querySelector('.botri-nonseo .widget_price_filter form');
                    if (priceForm) {
                        priceForm.querySelector('button[type="submit"]').addEventListener('click', function(e) {
                            e.preventDefault();
                            let min = priceForm.querySelector('input[name="min_price"]').value;
                            let max = priceForm.querySelector('input[name="max_price"]').value;
                            let obj = JSON.parse(getCookie('botri_nonseo_filters') || '{}');
                            
                            if (min && max) {
                                obj['min_price'] = min;
                                obj['max_price'] = max;
                            } else {
                                delete obj['min_price'];
                                delete obj['max_price'];
                            }
                            
                            setCookie('botri_nonseo_filters', JSON.stringify(obj), 1);
                            
                            if (typeof showFullPageLoading === 'function') {
                                showFullPageLoading();
                            }
                            
                            setTimeout(function() {
                                window.location.reload();
                            }, 300);
                        });
                    }

                    document.querySelectorAll('.botri-remove-nonseo').forEach(function(a) {
                        a.addEventListener('click', function(e) {
                            e.preventDefault();
                            let key = a.dataset.key;
                            let slug = a.dataset.slug || null;
                            let obj = JSON.parse(getCookie('botri_nonseo_filters') || '{}');
                            
                            if (slug && obj[key]) {
                                let values = obj[key].split(',');
                                values = values.filter(function(v) { return v !== slug; });
                                if (values.length > 0) {
                                    obj[key] = values.join(',');
                                } else {
                                    delete obj[key];
                                }
                            } else if (key === 'price') {
                                delete obj['min_price'];
                                delete obj['max_price'];
                            } else {
                                delete obj[key];
                            }
                            
                            setCookie('botri_nonseo_filters', JSON.stringify(obj), 1);
                            
                            if (typeof showFullPageLoading === 'function') {
                                showFullPageLoading();
                            }
                            
                            setTimeout(function() {
                                window.location.reload();
                            }, 300);
                        });
                    });

                    document.querySelectorAll('.botri-clear-all').forEach(function(a) {
                        a.addEventListener('click', function(e) {
                            e.preventDefault();
                            setCookie('botri_nonseo_filters', '{}', -1);
                            
                            if (typeof showFullPageLoading === 'function') {
                                showFullPageLoading();
                            }
                            
                            setTimeout(function() {
                                window.location.reload();
                            }, 300);
                        });
                    });
                });
            </script>
            <?php
        }

        /** --- ستون‌های ادمین --- **/
        public function columns($cols) {
            return [
                'cb' => '<input type="checkbox" />',
                'title' => 'عنوان',
                'taxonomy' => 'Taxonomy',
                'term' => 'Term',
                'cats' => 'دسته‌ها',
                'date' => 'تاریخ'
            ];
        }

        public function column_content($col, $id) {
            if ($col === 'taxonomy') echo esc_html(get_post_meta($id, '_taxonomy', true));
            if ($col === 'term') echo esc_html(get_post_meta($id, '_term', true));
            if ($col === 'cats') {
                $ids = (array)get_post_meta($id, '_cats', true);
                $names = [];
                foreach($ids as $cid) {
                    $term = get_term($cid, 'product_cat');
                    if ($term && !is_wp_error($term)) $names[] = $term->name;
                }
                echo esc_html(implode(', ', $names));
            }
        }

        public function columns_nonseo($cols) {
            return [
                'cb' => '<input type="checkbox" />',
                'title' => 'عنوان',
                'taxonomy' => 'Taxonomy',
                'terms' => 'مشخصه‌ها',
                'location' => 'محل نمایش',
                'date' => 'تاریخ'
            ];
        }

        public function column_content_nonseo($col, $id) {
            if ($col === 'taxonomy') echo esc_html(get_post_meta($id, '_taxonomy', true));
            if ($col === 'terms') {
                $ids = (array)get_post_meta($id, '_terms', true);
                $taxonomy = get_post_meta($id, '_taxonomy', true);
                $tax_real = $this->normalize_tax($taxonomy);
                $names = [];
                foreach($ids as $tid) {
                    $term = get_term($tid, $tax_real);
                    if ($term && !is_wp_error($term)) $names[] = $term->name;
                }
                echo esc_html(implode(', ', $names));
            }
            if ($col === 'location') {
                $enable_shop = get_post_meta($id, '_enable_shop', true);
                $cats = (array)get_post_meta($id, '_cats', true);
                $locations = [];
                
                if ($enable_shop === '1') {
                    $locations[] = '<strong>🪐 Shop</strong>';
                }
                
                if (!empty($cats)) {
                    $cat_names = [];
                    foreach($cats as $cid) {
                        $term = get_term($cid, 'product_cat');
                        if ($term && !is_wp_error($term)) $cat_names[] = $term->name;
                    }
                    if (!empty($cat_names)) {
                        $locations[] = '📂 ' . implode(', ', $cat_names);
                    }
                }
                
                echo !empty($locations) ? implode('<br>', $locations) : '—';
            }
        }
    }

    new Botri_Filter_SEO_Manager();
});