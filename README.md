# botri-filter-seo
botri-filter
# Botri Advanced Filter & SEO Plugin

**نسخه:** 2.5  
**سازگاری:** WordPress 6.0+, WooCommerce 8.0+, Elementor 3.0+, Woodmart Theme  
**زبان:** فارسی/انگلیسی  
**نویسنده:** Botri Development Team

---

## 📋 فهرست مطالب

1. [معرفی کلی](#معرفی-کلی)
2. [معماری و ساختار فایل‌ها](#معماری-و-ساختار-فایلها)
3. [قابلیت‌های اصلی](#قابلیتهای-اصلی)
4. [فیلترهای SEO (URL-based)](#فیلترهای-seo-url-based)
5. [فیلترهای Non-SEO (Cookie-based)](#فیلترهای-non-seo-cookie-based)
6. [Infinite Scroll سفارشی](#infinite-scroll-سفارشی)
7. [Elementor Widgets](#elementor-widgets)
8. [مشکلات حل شده و Fix های Critical](#مشکلات-حل-شده-و-fixهای-critical)
9. [خط قرمزها و محدودیت‌های مهم](#خط-قرمزها-و-محدودیتهای-مهم)
10. [نصب و پیکربندی](#نصب-و-پیکربندی)
11. [Troubleshooting](#troubleshooting)
12. [تاریخچه نسخه‌ها](#تاریخچه-نسخهها)

---

## معرفی کلی

این افزونه یک **سیستم فیلتر پیشرفته** برای WooCommerce است که به طور خاص برای تم Woodmart بهینه‌سازی شده است. هدف اصلی آن:

### اهداف اصلی:
1. **فیلترینگ محصولات بدون Reload صفحه** (با AJAX و Cookie)
2. **فیلترهای SEO-Friendly** (با URL parameters و محتوای داینامیک)
3. **Infinite Scroll سفارشی** که با Woodmart conflict ندارد
4. **محتوای SEO داینامیک** برای هر ترکیب فیلتر (H1، توضیحات، متا)
5. **حفظ کامل ظاهر و UX تم Woodmart** در محصولات AJAX-loaded

### چرا این افزونه ساخته شد؟

**مشکلات موجود در سیستم پیش‌فرض:**
- فیلترهای WooCommerce استاندارد صفحه را reload می‌کنند → UX ضعیف
- Infinite Scroll پیش‌فرض Woodmart با فیلترهای سفارشی conflict دارد
- محتوای SEO برای فیلترها قابل مدیریت نیست
- ترکیب فیلترهای مختلف باعث duplicate content می‌شود
- محصولات AJAX-loaded ظاهر متفاوتی از محصولات اولیه دارند (hover effects اشتباه)

**راه‌حل این افزونه:**
- دو نوع فیلتر: **SEO** (در URL) و **Non-SEO** (در Cookie)
- Infinite Scroll جداگانه که Woodmart را disable می‌کند
- مدیریت محتوای SEO از طریق Custom Post Type
- همگام‌سازی کامل ظاهر محصولات AJAX با DOM اصلی
- جلوگیری از کش شدن محتوای داینامیک در Elementor

---

## معماری و ساختار فایل‌ها

```
botri-filter-seo/
├── botri-filter-seo.php          # فایل اصلی افزونه + AJAX handler
├── botri-elementor-filters.php   # ثبت و مدیریت Elementor widgets
├── widgets/
│   ├── nonseo-filters.php        # Widget فیلترهای Non-SEO (checkbox, price slider)
│   ├── seo-filters.php           # Widget فیلترهای SEO (لینک‌های فیلتر)
│   ├── seo-h1.php                # Widget H1 داینامیک بر اساس فیلتر
│   └── seo-content.php           # Widget محتوای داینامیک بر اساس فیلتر
├── assets/
│   ├── script.js                 # Infinite Scroll + Filter handling + AJAX
│   └── style.css                 # استایل loading، filters، و UI components
└── README.md                     # این فایل
```

### نقش هر فایل:

#### 1. `botri-filter-seo.php` (فایل اصلی)
**وظایف:**
- ثبت Custom Post Type `botri_filter_rule` (قوانین فیلتر SEO)
- Meta Boxes برای تعریف شرایط نمایش محتوا (تاکسونومی، ترم، دسته‌بندی‌ها)
- AJAX handler: `ajax_load_more_products()` برای بارگذاری محصولات بیشتر
- Enqueue کردن `script.js` و `style.css`
- تزریق متغیر `botri_ajax` به JavaScript (ajaxurl, nonce)

**متغیرهای JavaScript تزریق شده:**
```javascript
var botri_ajax = {
    ajaxurl: '/wp-admin/admin-ajax.php',
    infinite_nonce: 'nonce_value_here'
};
```

#### 2. `botri-elementor-filters.php`
**وظایف:**
- ثبت 4 widget در Elementor:
  - `botri_nonseo_filters` (فیلترهای Non-SEO)
  - `botri_seo_filters` (فیلترهای SEO)
  - `botri_seo_h1` (H1 داینامیک)
  - `botri_seo_content` (محتوای داینامیک)
- اضافه کردن دسته‌بندی `woocommerce-elements` برای widget ها

#### 3. `widgets/nonseo-filters.php`
**عملکرد:**
- نمایش checkbox برای attribute های WooCommerce
- نمایش Price Slider (با jQuery UI)
- ذخیره‌سازی فیلترها در **Cookie** با نام `botri_nonseo_filters`
- Format داده در Cookie:
```json
{
  "filter_color": "red,blue",
  "filter_size": "large",
  "min_price": 1000,
  "max_price": 50000
}
```

**تنظیمات Widget:**
- انتخاب attribute (pa_color, pa_size, ...)
- نمایش/عدم نمایش عنوان
- سفارشی‌سازی استایل checkbox

#### 4. `widgets/seo-filters.php`
**عملکرد:**
- نمایش لینک‌های فیلتر که در **URL** قرار می‌گیرند
- هر لینک به صورت: `/category-name/?attribute=value`
- تشخیص فیلتر فعال و نمایش حالت active
- قابلیت toggle: کلیک روی فیلتر فعال آن را حذف می‌کند

**تنظیمات Widget:**
- انتخاب attribute
- انتخاب دسته‌بندی هدف
- نمایش به صورت لینک یا دکمه

**مثال URL:**
```
# فیلتر واحد
/pet-bottle/?shape=ketabi

# چند فیلتر (ترکیب SEO + Non-SEO)
/pet-bottle/?shape=ketabi&color=blue  # color در Cookie
```

#### 5. `widgets/seo-h1.php`
**عملکرد:**
- نمایش H1 داینامیک بر اساس فیلتر فعال
- جستجوی قانون مطابق در `botri_filter_rule` CPT
- شرایط تطبیق:
  - تاکسونومی (مثلاً `pa_shape`)
  - ترم (مثلاً `ketabi`)
  - دسته‌بندی‌های مجاز (اگر مشخص شده باشد)

**Fallback:**
- اگر قانونی پیدا نشد → عنوان دسته‌بندی یا آرشیو فعلی

**⚠️ Critical:** `is_dynamic_content() = true` برای جلوگیری از کش Elementor

#### 6. `widgets/seo-content.php`
**عملکرد:**
- نمایش محتوای داینامیک (توضیحات SEO) بر اساس فیلتر فعال
- مشابه `seo-h1.php` ولی برای محتوا
- قابلیت نمایش توضیحات دسته‌بندی به عنوان پیش‌فرض

**⚠️ Critical:** `is_dynamic_content() = true` برای جلوگیری از کش Elementor

#### 7. `assets/script.js`
**اجزای اصلی:**

**7.1. Disable Woodmart Infinite Scroll:**
```javascript
woodmart_settings.infiniteScrollOffset = 999999;
$(window).off('scroll.wdInfiniteScroll scroll.infiniteScroll scroll.woodmart');
```

**7.2. Block URL Changes:**
```javascript
history.pushState override → بلاک /page/N در URL
```

**7.3. Infinite Scroll:**
- تشخیص scroll به انتهای صفحه (500px قبل از end)
- بارگذاری AJAX محصولات بیشتر
- offset calculation:
  - صفحه 1: 12 محصول (main query)
  - صفحه 2+: هر بار 9 محصول

**7.4. Filter Handling:**
- Non-SEO filters → Cookie management
- SEO filters → URL parameters
- Price filter → Cookie: `min_price`, `max_price`

**7.5. Container Detection:**
- جستجوی container محصولات (Elementor + Woodmart)
- پشتیبانی از `ul.products` و `div.products`
- پشتیبانی از `li.product` و `div.product`

**7.6. Product Buttons Fix:**
- همگام‌سازی کلاس‌های `wd-hover-*`
- جایگزینی کامل HTML ساختار buttons
- Replace کردن `product_id` در HTML template

#### 8. `assets/style.css`
**محتویات:**
- Loading overlays (full page + Woodmart loader)
- Spinner animations
- Filter checkbox styles
- Active filter badges
- Price slider styles
- End message style

---

## قابلیت‌های اصلی

### 1. دو نوع فیلتر مجزا

#### فیلترهای SEO (URL-based):
- **مزایا:**
  - SEO-friendly (قابل index توسط Google)
  - قابل share (لینک مستقیم به محصولات فیلتر شده)
  - محتوای داینامیک (H1, description)
- **نحوه کار:**
  - فیلتر در URL: `/category/?attribute=value`
  - Query string به WP_Query ارسال می‌شود
  - محتوای SEO از CPT `botri_filter_rule` خوانده می‌شود
- **موارد استفاده:**
  - فیلترهای اصلی محصول (شکل، نوع، کاربرد)
  - صفحات landing
  - کمپین‌های مارکتینگ

#### فیلترهای Non-SEO (Cookie-based):
- **مزایا:**
  - بدون آلودگی URL
  - ترکیب نامحدود فیلترها
  - حفظ فیلترها بین صفحات
- **نحوه کار:**
  - فیلتر در Cookie: `botri_nonseo_filters`
  - JavaScript کوکی را می‌خواند و صفحه را reload می‌کند
  - PHP کوکی را می‌خواند و به WP_Query اضافه می‌کند
- **موارد استفاده:**
  - رنگ، سایز، برند
  - فیلتر قیمت
  - فیلترهای ثانویه

### 2. Infinite Scroll سفارشی

**چرا سفارشی؟**
- Infinite Scroll پیش‌فرض Woodmart با فیلترهای AJAX conflict دارد
- نیاز به ارسال filter parameters در هر request
- نیاز به همگام‌سازی ظاهر محصولات

**نحوه کار:**
1. Disable کردن Woodmart scroll
2. Listener روی `scroll.botriInfinite` (namespace جداگانه)
3. وقتی کاربر 500px به انتهای صفحه می‌رسد → AJAX
4. AJAX request شامل:
   - `paged`: شماره صفحه
   - `category`: ID دسته‌بندی فعلی
   - `filters`: query string از URL
   - `wd_hover`: hover style از DOM
5. PHP محصولات را با همان فیلترها query می‌کند
6. JavaScript محصولات را به container اضافه می‌کند
7. `fixProductButtons()` ظاهر را یکسان‌سازی می‌کند
8. Woodmart را re-initialize می‌کند

**Offset Calculation:**
```
صفحه 1 (main query): 12 محصول
صفحه 2: offset = 12 + (2-2)*9 = 12
صفحه 3: offset = 12 + (3-2)*9 = 21
صفحه N: offset = 12 + (N-2)*9
```

**max_num_pages Calculation:**
```javascript
if (total <= 12) {
    max_pages = 1
} else {
    max_pages = 1 + ceil((total - 12) / 9)
}
```

### 3. محتوای SEO داینامیک

**سیستم قوانین (Rules):**
- هر قانون = یک post در CPT `botri_filter_rule`
- Meta fields:
  - `_taxonomy`: تاکسونومی هدف (pa_shape)
  - `_term`: ترم هدف (ketabi)
  - `_cats`: دسته‌بندی‌های مجاز (آرایه term IDs)
  - `_h1`: عنوان H1 سفارشی
  - `_content`: محتوای توضیحات
  - `_meta_title`: عنوان متا (اختیاری)
  - `_meta_description`: توضیحات متا (اختیاری)

**نحوه تطبیق:**
```php
// URL فعلی: /pet-bottle/?shape=ketabi

1. بررسی query string: shape=ketabi
2. جستجو در botri_filter_rule:
   - _taxonomy = 'pa_shape'
   - _term = 'ketabi'
   - _cats شامل term_id دسته فعلی (pet-bottle) باشد یا خالی باشد
3. اگر پیدا شد → نمایش _h1 و _content
4. اگر پیدا نشد → fallback به عنوان دسته
```

**مثال کاربرد:**
```
URL: /pet-bottle/?shape=ketabi
Rule:
  - تاکسونومی: pa_shape
  - ترم: ketabi
  - دسته‌ها: [pet-bottle, plastic-bottle]
  - H1: "بطری پت با شکل کتابی - خرید مستقیم از کارخانه"
  - محتوا: "بطری‌های پت کتابی شکل برای آبمیوه و نوشیدنی..."
```

### 4. Price Filter

**نحوه کار:**
1. Widget نمایش می‌دهد: slider با min/max
2. کاربر slider را تغییر می‌دهد
3. کلیک روی "اعمال فیلتر"
4. JavaScript مقادیر را در Cookie ذخیره می‌کند:
```json
{
  "min_price": 10000,
  "max_price": 50000
}
```
5. صفحه reload می‌شود
6. PHP کوکی را می‌خواند و به `meta_query` اضافه می‌کند:
```php
'meta_query' => [
    [
        'key' => '_price',
        'value' => [10000, 50000],
        'type' => 'numeric',
        'compare' => 'BETWEEN'
    ]
]
```

**تنظیمات:**
- حداقل قیمت (از تمام محصولات)
- حداکثر قیمت (از تمام محصولات)
- فرمت نمایش قیمت (تومان، ریال، ...)

---

## فیلترهای SEO (URL-based)

### نحوه ایجاد فیلتر SEO:

#### 1. ایجاد Attribute در WooCommerce:
```
Products → Attributes → Add New
Name: شکل بطری
Slug: shape
```

#### 2. ایجاد Terms:
```
Products → Attributes → شکل بطری → Terms
Add: کتابی (slug: ketabi)
Add: استوانه‌ای (slug: cylinder)
```

#### 3. ایجاد قانون SEO:
```
Dashboard → قوانین فیلتر SEO → افزودن
Title: محتوای SEO برای بطری کتابی
Meta Boxes:
  - تاکسونومی: pa_shape
  - ترم: ketabi
  - دسته‌بندی‌ها: بطری پت
  - H1: بطری پت کتابی - خرید از تولیدی
  - محتوا: توضیحات کامل درباره بطری کتابی...
```

#### 4. افزودن Widget در Elementor:
```
Elementor → Edit Category Page Template
Add Widget: Botri SEO Filters
Settings:
  - Attribute: شکل بطری (pa_shape)
  - دسته‌بندی: بطری پت
  - نوع نمایش: لینک
```

#### 5. نمایش محتوای SEO:
```
Elementor → Edit Category Page Template
Add Widget: Botri SEO H1
(محتوا به صورت خودکار بر اساس URL تغییر می‌کند)

Add Widget: Botri SEO Content
(محتوا به صورت خودکار بر اساس URL تغییر می‌کند)
```

### چرخه کامل فیلتر SEO:

```
1. کاربر روی لینک "کتابی" کلیک می‌کند
   ↓
2. JavaScript URL را به /pet-bottle/?shape=ketabi تغییر می‌دهد
   ↓
3. صفحه بدون reload (یا با AJAX) محصولات را فیلتر می‌کند
   ↓
4. Widget SEO H1 قانون مطابق را پیدا می‌کند
   ↓
5. H1 و محتوا به صورت داینامیک تغییر می‌کند
   ↓
6. Infinite Scroll این query string را در AJAX ارسال می‌کند
```

---

## فیلترهای Non-SEO (Cookie-based)

### نحوه ایجاد فیلتر Non-SEO:

#### 1. ایجاد Attribute (مشابه بالا)

#### 2. افزودن Widget در Sidebar:
```
Appearance → Widgets
یا
Elementor → Edit Shop Sidebar

Add Widget: Botri Non-SEO Filters
Settings:
  - Attribute: رنگ (pa_color)
  - نمایش عنوان: بله
  - استایل: Checkbox
```

### Cookie Structure:
```json
{
  "filter_color": "red,blue,green",
  "filter_size": "large",
  "filter_brand": "nike",
  "min_price": 100000,
  "max_price": 500000
}
```

### چرخه کامل فیلتر Non-SEO:

```
1. کاربر checkbox رنگ "قرمز" را چک می‌کند
   ↓
2. JavaScript event: $('.botri-nonseo-checkbox').on('change')
   ↓
3. خواندن کوکی فعلی: getCookie('botri_nonseo_filters')
   ↓
4. اضافه/حذف مقدار:
   قبل: {"filter_color": "blue"}
   بعد: {"filter_color": "blue,red"}
   ↓
5. ذخیره در کوکی: setCookie('botri_nonseo_filters', ...)
   ↓
6. Reload صفحه با loading overlay
   ↓
7. PHP کوکی را می‌خواند و به tax_query اضافه می‌کند
   ↓
8. محصولات فیلتر شده نمایش داده می‌شوند
```

### حذف فیلترها:

**حذف یک فیلتر:**
```html
<span class="botri-remove-nonseo" data-key="filter_color" data-slug="red">
    ✕
</span>
```

**حذف همه فیلترها:**
```html
<button class="botri-clear-all">پاک کردن همه فیلترها</button>
```

---

## Infinite Scroll سفارشی

### دلایل Disable کردن Woodmart Scroll:

1. **Conflict با فیلترها:**
   - Woodmart scroll فیلترها را نمی‌فهمد
   - محصولات بدون فیلتر لود می‌شوند

2. **URL Change:**
   - Woodmart URL را به `/page/2/` تغییر می‌دهد
   - این برای SEO و فیلترهای ما مشکل‌ساز است

3. **Container Detection:**
   - Woodmart فقط selector های خودش را می‌شناسد
   - با Elementor custom layouts کار نمی‌کند

### Disable Methods:

```javascript
// 1. تنظیمات Woodmart
woodmart_settings.infiniteScrollOffset = 999999;

// 2. Unbind scroll listeners
$(window).off('scroll.wdInfiniteScroll scroll.infiniteScroll scroll.woodmart');

// 3. Block URL changes
history.pushState override برای /page/N
```

### Container Detection Algorithm:

```javascript
function getProductsContainer() {
    // 1. بررسی کش
    if (foundContainer && در DOM است && محصول دارد) {
        return foundContainer;
    }
    
    // 2. جستجوی اولویت‌دار
    selectors = [
        '.elementor-widget-archive-products ul.products',
        '.elementor-widget-archive-products div.products',
        'ul.products.wd-products',
        'div.products.wd-products',
        // ... 20 selector دیگر
    ];
    
    for each selector {
        if (exists && has products) {
            return container;
        }
    }
    
    // 3. Fallback: والد اولین .product
    return $('.product').first().parent();
}
```

### AJAX Request Structure:

```javascript
$.ajax({
    url: botri_ajax.ajaxurl,
    type: 'POST',
    data: {
        action: 'botri_load_more_products',
        nonce: botri_ajax.infinite_nonce,
        paged: 2,                           // صفحه جاری
        category: 123,                      // term_id دسته فعلی
        filters: 'shape=ketabi&color=red',  // query string
        wd_hover: 'buttons-on-hover'        // hover style از DOM
    },
    success: function(response) {
        // response.data.products = HTML محصولات
        // response.data.max_num_pages = تعداد کل صفحات
        // response.data.found_posts = تعداد کل محصولات
    }
});
```

### PHP AJAX Handler:

```php
public function ajax_load_more_products() {
    // 1. Verify nonce
    check_ajax_referer('botri_infinite_scroll', 'nonce');
    
    // 2. Get parameters
    $paged = intval($_POST['paged']);
    $category = intval($_POST['category']);
    
    // 3. محاسبه offset
    $offset = 12 + ($paged - 2) * 9;
    
    // 4. Build WP_Query
    $args = [
        'post_type' => 'product',
        'posts_per_page' => 9,
        'offset' => $offset,
        'tax_query' => [...], // از URL filters
    ];
    
    // 5. Add non-SEO filters از Cookie
    if (isset($_COOKIE['botri_nonseo_filters'])) {
        // اضافه به tax_query
    }
    
    // 6. تنظیم Woodmart hover style
    $wd_hover = sanitize_key($_POST['wd_hover']);
    global $woodmart_loop;
    $woodmart_loop['hover'] = $wd_hover;
    
    // 7. Render products
    $query = new WP_Query($args);
    ob_start();
    while ($query->have_posts()) {
        $query->the_post();
        wc_get_template_part('content', 'product');
    }
    $html = ob_get_clean();
    
    // 8. Return JSON
    wp_send_json_success([
        'products' => $html,
        'max_num_pages' => $max_pages,
        'found_posts' => $query->found_posts
    ]);
}
```

---

## Elementor Widgets

### 1. Botri Non-SEO Filters Widget

**Controls:**
- `attribute`: انتخاب از لیست attribute های WooCommerce
- `show_title`: نمایش عنوان attribute
- `custom_title`: عنوان سفارشی

**Render:**
```php
foreach ($terms as $term) {
    $checked = in_array($term->slug, $active_values);
    echo '<label>';
    echo '<input type="checkbox" 
                 class="botri-nonseo-checkbox" 
                 data-tax="' . $tax . '" 
                 data-slug="' . $term->slug . '"
                 ' . checked($checked, true, false) . '>';
    echo $term->name;
    echo '</label>';
}
```

### 2. Botri SEO Filters Widget

**Controls:**
- `attribute`: انتخاب attribute
- `category_id`: دسته‌بندی هدف
- `display_type`: 'link' یا 'button'

**Render:**
```php
foreach ($terms as $term) {
    $is_active = ($_GET[$tax] === $term->slug);
    $url = $is_active 
        ? get_term_link($category)  // حذف فیلتر
        : add_query_arg($tax, $term->slug, get_term_link($category));
    
    echo '<a href="' . esc_url($url) . '" 
             class="' . ($is_active ? 'active' : '') . '">';
    echo $term->name;
    echo '</a>';
}
```

**Toggle Behavior:**
- کلیک روی فیلتر غیرفعال → اضافه به URL
- کلیک روی فیلتر فعال → حذف از URL

### 3. Botri SEO H1 Widget

**Controls:**
- `tag`: H1, H2, H3, ...
- `show_fallback`: نمایش عنوان دسته اگر قانون پیدا نشد

**Render Logic:**
```php
function render() {
    // 1. بررسی query string
    $active_filters = $_GET;
    
    // 2. جستجوی قانون مطابق
    foreach ($active_filters as $tax => $value) {
        $rule = find_matching_rule($tax, $value);
        if ($rule) {
            $h1 = get_post_meta($rule->ID, '_h1', true);
            if ($h1) {
                echo '<h1>' . esc_html($h1) . '</h1>';
                return;
            }
        }
    }
    
    // 3. Fallback
    if ($settings['show_fallback']) {
        echo '<h1>' . single_cat_title('', false) . '</h1>';
    }
}
```

**⚠️ Critical:**
```php
protected function is_dynamic_content() {
    return true;  // جلوگیری از کش Elementor
}
```

### 4. Botri SEO Content Widget

**Controls:**
- `show_fallback`: نمایش توضیحات دسته اگر قانون پیدا نشد

**Render Logic:**
- مشابه SEO H1 ولی برای `_content`
- پشتیبانی از HTML در محتوا
- `wpautop()` برای فرمت پاراگراف‌ها

---

## مشکلات حل شده و Fix های Critical

### نسخه 2.0: پایه اولیه
- ایجاد ساختار افزونه
- فیلترهای Non-SEO
- Infinite Scroll پایه

### نسخه 2.1: فیلترهای SEO
- Custom Post Type قوانین
- Widget های SEO H1 و Content
- تطبیق query string با قوانین

### نسخه 2.2: Woodmart Compatibility
- حل conflict با Infinite Scroll Woodmart
- حفظ ظاهر محصولات

### نسخه 2.3: Critical Fixes

#### FIX 1: Scroll Listener Conflict ⚠️
**مشکل:**
```javascript
// کد قدیمی - اشتباه
const originalOn = $.fn.on;
$.fn.on = function(events, selector, data, handler) {
    if (events.includes('scroll')) {
        return this; // بلاک همه scroll ها
    }
    return originalOn.apply(this, arguments);
};
```
این کد **همه** scroll listener ها را بلاک می‌کرد، حتی scroll های مفید مثل sticky header.

**راه‌حل:**
```javascript
// فقط namespace های Woodmart را unbind کن
$(window).off('scroll.wdInfiniteScroll scroll.infiniteScroll scroll.woodmart');

// scroll خودمان را با namespace جداگانه ثبت کن
$(window).on('scroll.botriInfinite', function() {
    // ...
});
```

#### FIX 2: URL Filter Parameters در AJAX
**مشکل:**
- فیلترهای SEO در URL بودند: `/category/?shape=ketabi`
- AJAX request این پارامترها را ارسال نمی‌کرد
- محصولات بدون فیلتر لود می‌شدند

**راه‌حل:**
```javascript
// خواندن query string از URL
const currentUrl = new URL(window.location.href);
const params = currentUrl.searchParams;

// ارسال در AJAX
$.ajax({
    data: {
        filters: params.toString()  // "shape=ketabi&color=red"
    }
});
```

```php
// PHP: پارس کردن
parse_str($_POST['filters'], $get_filters);
// اضافه به tax_query
```

#### FIX 3: Offset Calculation
**مشکل:**
```php
// کد قدیمی - اشتباه
$offset = ($paged - 1) * 12;
```
این فرض می‌کرد همه صفحات 12 محصول دارند، ولی:
- صفحه 1 (main query): 12 محصول
- صفحه 2+ (AJAX): 9 محصول

**راه‌حل:**
```php
$per_page_first = 12;
$per_page_ajax = 9;
$offset = $per_page_first + ($paged - 2) * $per_page_ajax;

// مثال:
// صفحه 2: 12 + (2-2)*9 = 12
// صفحه 3: 12 + (3-2)*9 = 21
// صفحه 4: 12 + (4-2)*9 = 30
```

#### FIX 4: max_num_pages Calculation
```php
if ($total_products <= 12) {
    $max_num_pages = 1;
} else {
    $max_num_pages = 1 + ceil(($total_products - 12) / 9);
}
```

#### FIX 5: MutationObserver Performance
**مشکل:**
```javascript
// کد قدیمی - اشتباه
new MutationObserver(function() {
    removePagination();  // هر بار DOM تغییر کرد
}).observe(document.body, {
    childList: true,
    subtree: true  // تمام زیردرخت را watch کن
});
```
این باعث lag می‌شد چون هزاران بار trigger می‌شد.

**راه‌حل:**
```javascript
let mutationTimer = null;
new MutationObserver(function() {
    if (!isShopOrCategoryPage()) return;  // فقط در shop
    clearTimeout(mutationTimer);
    mutationTimer = setTimeout(function() {
        removePagination();
    }, 200);  // debounce 200ms
}).observe(document.body, {
    childList: true,
    subtree: false  // فقط children مستقیم body
});
```

### نسخه 2.4: Elementor Compatibility

#### FIX 6: Container Detection برای div.product
**مشکل:**
- Woodmart با Elementor widgets گاهی `div.products` و `div.product` رندر می‌کند
- کد قدیمی فقط `ul.products` و `li.product` را می‌شناخت

**راه‌حل:**
```javascript
function getProductCountIn($el) {
    const liCount = $el.find('li.product').length;
    const divCount = $el.find('div.product:not(.wd-product-info)').length;
    return liCount || divCount;
}

function getProductItemSelector() {
    if (foundContainer.find('li.product').length > 0) {
        return 'li.product';
    }
    return 'div.product';
}
```

#### FIX 7: $.contains Check برای Cache
**مشکل:**
- container را کش می‌کردیم
- اگر Elementor template تغییر می‌کرد، element از DOM حذف می‌شد
- کش قدیمی invalid بود

**راه‌حل:**
```javascript
if (foundContainer && foundContainer.length &&
    $.contains(document.body, foundContainer[0]) &&  // بررسی وجود در DOM
    getProductCountIn(foundContainer) > 0) {
    return foundContainer;
}
```

### نسخه 2.5: Visual Consistency

#### FIX 8: Hover Style Mismatch
**مشکل:**
- محصولات اولیه: `wd-hover-buttons-on-hover` → دکمه‌ها فقط در hover
- محصولات AJAX: `wd-hover-icons` → دکمه‌ها همیشه visible

**علت:**
Woodmart در AJAX context نمی‌داند Elementor widget چه `hover style` تنظیم کرده، پس default می‌گذارد.

**راه‌حل 3-لایه:**

**لایه 1: ارسال hover style به PHP**
```javascript
// خواندن از DOM
var $first = getProductsContainer().find('.product').first();
var hoverClass = extractWdClass($first, 'wd-hover-');
var hoverValue = hoverClass.replace('wd-hover-', '');
// مثال: 'wd-hover-buttons-on-hover' → 'buttons-on-hover'

$.ajax({
    data: {
        wd_hover: hoverValue
    }
});
```

**لایه 2: تنظیم در PHP**
```php
$wd_hover = sanitize_key($_POST['wd_hover']);
global $woodmart_loop;
$woodmart_loop['hover'] = $wd_hover;

// حالا wc_get_template_part با hover صحیح رندر می‌کند
```

**لایه 3: Class Sync در JS**
```javascript
// اگر PHP باز هم اشتباه رندر کرد (مثلاً بخاطر کش)
// کلاس‌ها را مستقیم در JS جایگزین کن
['wd-hover-', 'wd-col-', 'wd-with-'].forEach(function(prefix) {
    var correctClass = extractWdClass($domRef, prefix);
    $newItems.removeClass(/wd-hover-\S+/).addClass(correctClass);
});
```

#### FIX 9: Buttons HTML Structure
**مشکل:**
حتی با کلاس صحیح، ساختار HTML داخل `.wd-product-btns` متفاوت بود:

**DOM:**
```html
<div class="wd-product-btns wd-show-on-hover">
    <div class="wd-btns-inner">
        <a href="?add-to-cart=123" class="add_to_cart_button">...</a>
        <a href="..." class="quick-view">...</a>
    </div>
</div>
```

**AJAX:**
```html
<div class="wd-product-btns">
    <a href="?add-to-cart=123" class="add_to_cart_button">...</a>
    <a href="..." class="quick-view">...</a>
</div>
```

**راه‌حل:**
```javascript
function fixProductButtons($container, $newItems) {
    var $domBtns = $container.find('.product').first().find('.wd-product-btns');
    var templateHTML = $domBtns.html();
    var templateClass = $domBtns.attr('class');
    
    $newItems.each(function() {
        var $itemBtns = $(this).find('.wd-product-btns');
        var itemID = $(this).attr('data-id');
        var oldID = $domBtns.closest('.product').attr('data-id');
        
        // جایگزینی کامل HTML
        var newHTML = templateHTML.replace(
            new RegExp('product_id=' + oldID, 'g'),
            'product_id=' + itemID
        );
        
        $itemBtns.attr('class', templateClass);
        $itemBtns.html(newHTML);
    });
}
```

#### FIX 10: Elementor Widget Caching
**مشکل:**
- Widget های `seo-h1` و `seo-content` محتوای داینامیک دارند
- Elementor آنها را کش می‌کرد
- بعد از تغییر URL، محتوای قدیمی نمایش داده می‌شد

**راه‌حل:**
```php
class Botri_Elementor_SEO_H1_Widget extends \Elementor\Widget_Base {
    
    protected function is_dynamic_content() {
        return true;  // به Elementor می‌گوید این widget را کش نکن
    }
}
```

---

## خط قرمزها و محدودیت‌های مهم

### ⛔ خط قرمز 1: هرگز `$.fn.on` را override نکنید

**اشتباه:**
```javascript
const originalOn = $.fn.on;
$.fn.on = function(events, ...) {
    if (events.includes('scroll')) return this;
    return originalOn.apply(...);
};
```

**چرا؟**
- همه plugin ها و تم‌ها از `$.fn.on` استفاده می‌کنند
- override کردن آن **همه چیز** را خراب می‌کند
- Sticky headers، modals، carousels، همه fail می‌شوند

**درست:**
```javascript
$(window).off('scroll.wdInfiniteScroll'); // فقط namespace خاص
$(window).on('scroll.botriInfinite', ...); // namespace خودمان
```

### ⛔ خط قرمز 2: هرگز `$(window).off('scroll')` بدون namespace

**اشتباه:**
```javascript
$(window).off('scroll'); // همه scroll listener ها حذف می‌شوند
```

**درست:**
```javascript
$(window).off('scroll.botriInfinite'); // فقط namespace ما
```

### ⛔ خط قرمز 3: هرگز history.pushState را کلاً بلاک نکنید

**اشتباه:**
```javascript
history.pushState = function() { return; }; // همه تغییرات URL بلاک می‌شوند
```

**درست:**
```javascript
const orig = history.pushState;
history.pushState = function(state, title, url) {
    if (url && /\/page\/\d+/.test(url)) return; // فقط /page/N
    return orig.apply(history, arguments);
};
```

### ⛔ خط قرمز 4: هرگز MutationObserver با subtree:true بدون debounce

**اشتباه:**
```javascript
new MutationObserver(fn).observe(document.body, {
    childList: true,
    subtree: true // هزاران mutation در ثانیه
});
```

**درست:**
```javascript
let timer = null;
new MutationObserver(function() {
    clearTimeout(timer);
    timer = setTimeout(fn, 200); // debounce
}).observe(document.body, {
    childList: true,
    subtree: false // فقط direct children
});
```

### ⛔ خط قرمز 5: هرگز Elementor dynamic widgets را کش نکنید

**الزامی:**
```php
protected function is_dynamic_content() {
    return true;
}
```

اگر این را اضافه نکنید، محتوا بعد از اولین بار ثابت می‌ماند.

### ⛔ خط قرمز 6: هرگز offset را بدون در نظر گرفتن صفحه اول محاسبه نکنید

**اشتباه:**
```php
$offset = ($paged - 1) * $posts_per_page;
```

**درست:**
```php
$first_page = 12;
$other_pages = 9;
$offset = $first_page + ($paged - 2) * $other_pages;
```

### ⛔ خط قرمز 7: هرگز container را بدون بررسی وجود در DOM کش نکنید

**اشتباه:**
```javascript
if (foundContainer) return foundContainer;
```

**درست:**
```javascript
if (foundContainer && $.contains(document.body, foundContainer[0])) {
    return foundContainer;
}
```

### ⛔ خط قرمز 8: هرگز AJAX triggers خطرناک WooCommerce را اجرا نکنید

**ممنوع:**
```javascript
$(document.body).trigger('added_to_cart'); // crash می‌کند
$(document.body).trigger('wc_fragments_refreshed'); // crash می‌کند
```

**مجاز:**
```javascript
$(document.body).trigger('wdShopPageInit'); // Woodmart
$(document.body).trigger('botri_products_loaded'); // Custom
```

### ⛔ خط قرمز 9: هرگز regex pattern را بدون escape proper ننویسید

**اشتباه:**
```javascript
var pattern = /\bwd-hover-\S+/g; // \b به صورت literal
```

**درست:**
```javascript
var pattern = new RegExp('(\\bwd-hover-\\S+)', 'g'); // double escape
```

### ⛔ خط قرمز 10: هرگز product_id را بدون replace در HTML template نگذارید

**اشتباه:**
```javascript
$itemBtns.html(templateHTML); // همه محصولات ID یکسان دارند
```

**درست:**
```javascript
var newHTML = templateHTML.replace(
    new RegExp('product_id=' + oldID, 'g'),
    'product_id=' + itemID
);
$itemBtns.html(newHTML);
```

---

## نصب و پیکربندی

### پیش‌نیازها:
- WordPress 6.0+
- WooCommerce 8.0+
- Elementor 3.0+ (برای widgets)
- تم Woodmart (توصیه می‌شود)
- PHP 7.4+

### مراحل نصب:

#### 1. آپلود افزونه:
```
wp-content/plugins/botri-filter-seo/
```

#### 2. فعال‌سازی:
```
Dashboard → Plugins → Botri Advanced Filter & SEO → Activate
```

#### 3. ایجاد Attributes:
```
Products → Attributes → Add New
- شکل (shape)
- رنگ (color)
- سایز (size)
...
```

#### 4. ایجاد قوانین SEO:
```
Dashboard → قوانین فیلتر SEO → افزودن

مثال:
Title: محتوای بطری کتابی
Meta:
  - تاکسونومی: pa_shape
  - ترم: ketabi
  - دسته‌ها: بطری پت
  - H1: بطری پت کتابی - فروش ویژه
  - محتوا: توضیحات کامل...
```

#### 5. تنظیم Elementor Template:

**A. Shop Archive Template:**
```
Elementor → Templates → Theme Builder → Archive
- ایجاد template جدید برای Product Archive
- افزودن widget: Archive Products (Elementor/Woodmart)
```

**B. افزودن Widgets:**
```
Sidebar:
  - Botri Non-SEO Filters (color, size, brand)
  - Botri Price Filter

Header/Above Products:
  - Botri SEO H1
  - Botri SEO Content
  
Filters Section:
  - Botri SEO Filters (shape, type, usage)
```

#### 6. تنظیم Display Conditions:
```
Elementor Template Settings → Display Conditions
Include: Product Archive → بطری پت
```

#### 7. تست:
```
1. رفتن به صفحه دسته‌بندی
2. کلیک روی فیلتر SEO → URL تغییر کند، H1 تغییر کند
3. چک کردن checkbox Non-SEO → محصولات فیلتر شوند
4. Scroll به انتهای صفحه → محصولات بیشتر لود شوند
```

### تنظیمات پیشرفته:

#### تعداد محصولات:
```php
// در botri-filter-seo.php خط 180
$per_page_first = 12;  // صفحه اول
$per_page_ajax = 9;    // صفحات بعدی
```

#### فاصله scroll trigger:
```javascript
// در script.js خط 430
if (scrollTop + windowHeight >= documentHeight - 500) {
    // 500px قبل از انتها
```

#### مدت زمان کوکی:
```javascript
// در script.js خط 110
setCookie('botri_nonseo_filters', data, 1); // 1 روز
```

---

## Troubleshooting

### مشکل 1: محصولات بیشتر لود نمی‌شوند

**بررسی:**
1. کنسول → آیا خطایی وجود دارد؟
2. Network tab → آیا AJAX request ارسال می‌شود؟
3. Response → آیا `success: true` است؟

**راه‌حل:**
```javascript
// بررسی botri_ajax
console.log(typeof botri_ajax); // باید 'object' باشد

// بررسی container
console.log(getProductsContainer()); // باید element برگرداند

// بررسی scroll listener
console.log(scrollListenerActive); // باید true باشد
```

### مشکل 2: محتوای SEO کش می‌شود

**بررسی:**
```php
// در seo-h1.php و seo-content.php
protected function is_dynamic_content() {
    return true; // باید وجود داشته باشد
}
```

**راه‌حل:**
1. Elementor → Tools → Regenerate CSS & Data
2. پاک کردن کش سرور (Redis, Memcached)
3. Ctrl + Shift + R در browser

### مشکل 3: ظاهر محصولات AJAX متفاوت است

**بررسی کنسول:**
```
🔧 fixProductButtons: Fixing 9 products
🔧 Template buttons class: wd-product-btns wd-show-on-hover
```

**اگر لاگ نیست:**
```javascript
// در script.js بعد از appendTo
fixProductButtons($container, $newItems);
// آیا این خط وجود دارد؟
```

**اگر لاگ هست ولی مشکل پابرجاست:**
```javascript
// بررسی template
var $domBtns = getProductsContainer().find('.product').first().find('.wd-product-btns');
console.log($domBtns.html()); // ساختار HTML
console.log($domBtns.attr('class')); // کلاس‌ها
```

### مشکل 4: فیلترهای Non-SEO کار نمی‌کنند

**بررسی کوکی:**
```javascript
console.log(document.cookie); // باید botri_nonseo_filters وجود داشته باشد
```

**بررسی PHP:**
```php
// در botri-filter-seo.php خط 220
var_dump($_COOKIE['botri_nonseo_filters']);
// باید JSON string برگرداند
```

**بررسی event listener:**
```javascript
$('.botri-nonseo-checkbox').on('change', function() {
    console.log('Checkbox changed'); // باید trigger شود
});
```

### مشکل 5: Scroll به انتهای صفحه trigger نمی‌شود

**بررسی:**
```javascript
$(window).on('scroll.botriInfinite', function() {
    console.log('Scroll event fired');
    console.log('scrollTop:', $(window).scrollTop());
    console.log('windowHeight:', $(window).height());
    console.log('documentHeight:', $(document).height());
});
```

**علل احتمالی:**
- `scrollListenerActive = false` (چرا؟)
- container پیدا نشده
- Woodmart scroll هنوز فعال است

### مشکل 6: URL فیلتر به /page/N تغییر می‌کند

**بررسی:**
```javascript
// history.pushState override شده؟
console.log(history.pushState.toString());
// باید شامل /\/page\/\d+/.test باشد
```

**اگر override نشده:**
```javascript
// در script.js خط 45-65
// بلاک تغییر URL را اضافه کنید
```

### مشکل 7: max_num_pages اشتباه است

**Debug در PHP:**
```php
// در AJAX handler
error_log('Total products: ' . $total_products);
error_log('First page: ' . $per_page_first);
error_log('Ajax pages: ' . $per_page_ajax);
error_log('Calculated max_pages: ' . $max_num_pages);
```

**فرمول صحیح:**
```php
if ($total <= 12) {
    $max = 1;
} else {
    $max = 1 + ceil(($total - 12) / 9);
}
```

### مشکل 8: Infinite scroll در صفحات دیگر هم فعال می‌شود

**بررسی:**
```javascript
function isShopOrCategoryPage() {
    return $('body').hasClass('woocommerce-shop') ||
           $('body').hasClass('tax-product_cat');
}

// آیا این تابع استفاده می‌شود؟
if (!isShopOrCategoryPage()) return;
```

---

## تاریخچه نسخه‌ها

### v2.5 (فعلی) - February 2026
**افزودن:**
- `fixProductButtons()` برای همگام‌سازی HTML ساختار buttons
- ارسال `wd_hover` به PHP برای رندر صحیح
- `is_dynamic_content()` در widget های SEO

**بهبود:**
- regex pattern برای class replacement
- container detection برای `div.product`
- دقت تشخیص محصولات در Elementor layouts

**رفع باگ:**
- کش شدن محتوای SEO در Elementor
- ظاهر متفاوت محصولات AJAX
- crash در `fixProductButtons` با محصولات بدون buttons

### v2.4 - February 2026
**افزودن:**
- پشتیبانی از `div.product` (Elementor + Woodmart)
- `getProductItemSelector()` داینامیک
- `$.contains()` check برای cache validation

**بهبود:**
- container detection algorithm با 20+ selector
- fallback search برای container
- debug logging برای troubleshooting

### v2.3 - February 2026
**افزودن:**
- ارسال URL filters در AJAX (`$_POST['filters']`)
- `isShopOrCategoryPage()` برای محدود کردن به shop
- debounce در MutationObserver

**بهبود:**
- offset calculation برای صفحه اول 12، بقیه 9
- max_num_pages calculation
- scroll listener با namespace `.botriInfinite`

**رفع باگ:**
- حذف `$.fn.on` override
- حذف `$(window).off('scroll')` بدون namespace
- فیلترهای URL در AJAX گم می‌شدند
- محصولات duplicate در صفحه 2+

### v2.2 - January 2026
**افزودن:**
- غیرفعال‌سازی ایمن Woodmart infinite scroll
- history.pushState override برای /page/N
- container caching

**بهبود:**
- removePagination() برای پاک کردن "برگ X"
- disableLoadMoreButtons() برای دکمه‌های Woodmart

### v2.1 - January 2026
**افزودن:**
- Custom Post Type `botri_filter_rule`
- Widget های SEO H1 و SEO Content
- سیستم تطبیق قوانین با query string

**بهبود:**
- Meta boxes برای تعریف شرایط
- fallback به عنوان دسته‌بندی

### v2.0 - December 2025
**اولین نسخه عمومی:**
- فیلترهای Non-SEO با Cookie
- Price Slider با jQuery UI
- Infinite Scroll پایه
- Widget های Elementor پایه

---

## نکات توسعه‌دهندگان

### Hooks & Filters:

**JavaScript Events:**
```javascript
// بعد از لود محصولات جدید
$(document.body).on('botri_products_loaded', function(e, $newItems) {
    // کدهای شما
});

// قبل از ارسال AJAX
$(document.body).on('botri_before_ajax', function(e, data) {
    // تغییر data.filters
});
```

**PHP Filters:**
```php
// تغییر args query محصولات
add_filter('botri_products_query_args', function($args, $paged, $category) {
    // تغییر $args
    return $args;
}, 10, 3);

// تغییر HTML محصولات AJAX
add_filter('botri_ajax_products_html', function($html, $query) {
    // تغییر $html
    return $html;
}, 10, 2);
```

### توسعه Widgets جدید:

```php
class Custom_Botri_Widget extends \Elementor\Widget_Base {
    
    public function get_name() { return 'custom_botri'; }
    public function get_categories() { return ['woocommerce-elements']; }
    
    // اگر محتوا داینامیک است
    protected function is_dynamic_content() {
        return true;
    }
    
    protected function render() {
        // کد render شما
    }
}

// ثبت widget
add_action('elementor/widgets/register', function($widgets_manager) {
    $widgets_manager->register(new Custom_Botri_Widget());
});
```

### Debug Mode:

```javascript
// فعال کردن debug logs بیشتر
window.botriDebug = true;

// حالا تمام console.log ها فعال می‌شوند
```

### Performance Monitoring:

```javascript
// زمان AJAX
console.time('AJAX Request');
$.ajax({
    success: function() {
        console.timeEnd('AJAX Request');
    }
});

// تعداد محصولات
console.log('Products loaded:', $('.product').length);
```

---

## لایسنس و پشتیبانی

**لایسنس:** GPL v2 or later  
**پشتیبانی:** support@botri.ir  
**مستندات:** https://docs.botri.ir/filters  
**گیت‌هاب:** https://github.com/botri/advanced-filters

---

## Credits

**توسعه‌دهندگان:**
- Lead Developer: Botri Team
- UI/UX: Botri Design
- Testing: Botri QA

**ابزارهای استفاده شده:**
- WordPress Core
- WooCommerce
- Elementor
- Woodmart Theme
- jQuery & jQuery UI

**تشکر ویژه:**
- تیم Woodmart برای تم عالی
- جامعه WordPress برای آموزش‌ها

---

**یادداشت پایانی:**
این افزونه با دقت و توجه به جزئیات طراحی شده است. هر تغییری در کد باید با درک کامل از معماری و fix های critical انجام شود. لطفاً قبل از هرگونه تغییر، این مستندات را به طور کامل مطالعه کنید.

**نکته مهم برای AI/LLM:**
این فایل شامل تمام اطلاعات ضروری برای درک کامل افزونه است. اگر قرار است تغییری اعمال شود:
1. ابتدا بخش "خط قرمزها" را بخوانید
2. بخش "مشکلات حل شده" را بررسی کنید تا از تکرار باگ‌های قدیمی جلوگیری شود
3. معماری و وابستگی‌های فایل‌ها را در نظر بگیرید
4. تغییرات را با debug logs تست کنید

**آخرین به‌روزرسانی:** February 2026  
**نسخه مستندات:** 2.5.0
