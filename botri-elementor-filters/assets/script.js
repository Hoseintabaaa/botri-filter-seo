(function($) {
    'use strict';

    console.log('%c🚀 Botri Filters v2.5 - Final Fix', 'background: #0073aa; color: white; padding: 5px 10px; border-radius: 3px;');

    // ========================================
    // 🛑 غیرفعال کردن Woodmart/WooCommerce Infinite Scroll
    // ✅ FIX v2.3: $.fn.on override حذف شد - این باعث block شدن تمام scroll ها می‌شد
    // ========================================
    (function() {
        if (typeof woodmart_settings !== 'undefined') {
            if (woodmart_settings.infiniteScrollOffset) {
                woodmart_settings.infiniteScrollOffset = 999999;
            }
            if (woodmart_settings.product_gallery) {
                woodmart_settings.product_gallery.infiniteScroll = false;
            }
        }
        $(window).off('scroll.wdInfiniteScroll scroll.infiniteScroll scroll.woodmart scroll.woocommerce');
        console.log('✅ Woodmart scroll namespaces disabled (safe method)');
    })();

    // ========================================
    // متغیرهای سراسری
    // ========================================
    let currentPage = 1;
    let isLoading = false;
    let hasMoreProducts = true;
    let scrollListenerActive = false;
    let foundContainer = null;

    // ========================================
    // 🚫 جلوگیری از تغییر URL به /page/N
    // ✅ FIX v2.3: فقط /page/N را block می‌کند
    // ========================================
    (function() {
        const originalPushState = history.pushState;
        const originalReplaceState = history.replaceState;

        history.pushState = function(state, title, url) {
            if (url && /\/page\/\d+/.test(url.toString())) {
                console.log('%c🚫 Blocked /page/N URL change', 'color: red; font-weight: bold;');
                return;
            }
            return originalPushState.apply(history, arguments);
        };

        history.replaceState = function(state, title, url) {
            if (url && /\/page\/\d+/.test(url.toString())) {
                console.log('%c🚫 Blocked /page/N URL replace', 'color: red; font-weight: bold;');
                return;
            }
            return originalReplaceState.apply(history, arguments);
        };

        console.log('✅ History API override installed (safe - /page/N only)');
    })();

    // ========================================
    // تشخیص صفحه shop/category
    // ✅ FIX v2.3: اضافه شد
    // ========================================
    function isShopOrCategoryPage() {
        return $('body').hasClass('woocommerce-shop') ||
               $('body').hasClass('post-type-archive-product') ||
               $('body').hasClass('tax-product_cat') ||
               $('body').hasClass('tax-product_tag');
    }

    // ========================================
    // حذف pagination
    // ========================================
    function removePagination() {
        if (!isShopOrCategoryPage()) return;
        $('.woocommerce-pagination, .pagination, .page-numbers, .woodmart-pagination, .wd-pagination, .wd-products-pagination').remove();
        $('.woocommerce-result-count').each(function() {
            let text = $(this).text();
            text = text.replace(/\s*برگ\s*\d+\s*/g, '');
            text = text.replace(/\s*Page\s*\d+\s*/gi, '');
            $(this).text(text);
        });
    }

    // ========================================
    // غیرفعال کردن Load More
    // ========================================
    function disableLoadMoreButtons() {
        if (!isShopOrCategoryPage()) return;
        $('.products-footer .load-more, .woodmart-load-more, .load-more-button, .wd-load-more').remove();
    }

    // ========================================
    // توابع کمکی کوکی
    // ========================================
    function getCookie(name) {
        const matches = document.cookie.match(new RegExp("(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\\/\+^])/g, '\\$1') + "=([^;]*)"));
        return matches ? decodeURIComponent(matches[1]) : '{}';
    }

    function setCookie(name, value, days) {
        const date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = name + "=" + encodeURIComponent(value) + ";path=/;expires=" + date.toUTCString() + ";SameSite=Lax";
    }

    function formatPrice(price) {
        if (typeof wc_price_settings === 'undefined') return price;
        let formattedPrice = parseFloat(price).toFixed(wc_price_settings.decimals);
        formattedPrice = formattedPrice.replace('.', wc_price_settings.decimal_separator);
        const parts = formattedPrice.split(wc_price_settings.decimal_separator);
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, wc_price_settings.thousand_separator);
        formattedPrice = parts.join(wc_price_settings.decimal_separator);
        return wc_price_settings.price_format
            .replace('%1$s', wc_price_settings.currency_symbol)
            .replace('%2$s', formattedPrice);
    }

    // ========================================
    // لودینگ اولیه (برای فیلترها)
    // ========================================
    window.showFullPageLoading = function() {
        if (!$('.botri-loading-overlay').length) {
            $('body').append(
                '<div class="botri-loading-overlay">' +
                '<div style="text-align: center;">' +
                '<div class="botri-spinner"></div>' +
                '<p style="color: #333; font-size: 16px; margin: 20px 0 0;">در حال اعمال فیلتر...</p>' +
                '</div></div>'
            );
        }
        $('.botri-loading-overlay').fadeIn(200);
    };

    // ========================================
    // لودینگ Woodmart
    // ========================================
    function showWoodmartLoader() {
        var $container = getProductsContainer();
        if (!$container || !$container.length) return;
        if (!$('.wd-loader-overlay').length) {
            $container.parent().css('position', 'relative').append(
                '<div class="wd-loader-overlay wd-fill">' +
                '<div class="wd-loader"><span></span><span></span></div>' +
                '</div>'
            );
        }
        $('.wd-loader-overlay').addClass('wd-loading').fadeIn(300);
    }

    function hideWoodmartLoader() {
        $('.wd-loader-overlay').removeClass('wd-loading').fadeOut(300, function() {
            $(this).remove();
        });
    }

    function showEndMessage() {
        if (!$('.botri-end-message').length) {
            var $container = getProductsContainer();
            if ($container && $container.length) {
                $container.parent().append(
                    '<div class="botri-end-message" style="width:100%;text-align:center;padding:30px 20px;color:#777;font-size:14px;border-top:1px solid #e0e0e0;margin-top:20px;">' +
                    '✓ تمام محصولات نمایش داده شدند' +
                    '</div>'
                );
            }
        }
        $('.botri-end-message').fadeIn(300);
    }

    // ========================================
    // استخراج کلاس Woodmart با prefix مشخص
    // ✅ FIX v2.5: برای همگام‌سازی کلاس‌های hover بین محصولات اولیه و AJAX
    // مثال: extractWdClass($el, 'wd-hover-') → 'wd-hover-buttons-on-hover'
    // ========================================
    function extractWdClass($el, prefix) {
        if (!$el || !$el.length) return null;
        var cls = $el.attr('class') || '';
        var escaped = prefix.replace(/-/g, '\\-');
        var match = cls.match(new RegExp('(\\b' + escaped + '\\S+)'));
        return match ? match[1] : null;
    }

    // ========================================
    // 🔥 پیدا کردن کانتینر محصولات
    // ✅ FIX v2.4: پشتیبانی از div.product (وودمارت+المنتور) علاوه بر li.product
    // ✅ FIX v2.3: بررسی کش با $.contains
    // ========================================
    function getProductCountIn($el) {
        if (!$el || !$el.length) return 0;
        var liCount  = $el.find('li.product').length;
        var divCount = $el.find('div.product:not(.wd-product-info)').length;
        return liCount || divCount;
    }

    function getProductItemSelector() {
        if (!foundContainer) return 'li.product';
        if (foundContainer.find('li.product').length > 0) return 'li.product';
        if (foundContainer.find('div.product').length > 0) return 'div.product';
        return 'li.product';
    }

    function getProductsContainer() {
        if (foundContainer && foundContainer.length &&
            $.contains(document.body, foundContainer[0]) &&
            getProductCountIn(foundContainer) > 0) {
            return foundContainer;
        }
        foundContainer = null;

        var selectors = [
            '.elementor-widget-archive-products ul.products',
            '.elementor-widget-archive-products div.products',
            '.elementor-widget-woocommerce-products ul.products',
            '.elementor-widget-woocommerce-products div.products',
            'ul.products.wd-products',
            'div.products.wd-products',
            'ul.products.woodmart-products-holder',
            '.wd-products-holder ul.products',
            '.wd-products-holder div.products',
            '.products-grid-wrapper ul.products',
            '.products-grid-wrapper div.products',
            '.main-page-wrapper ul.products',
            'ul.products.elements-grid',
            'ul.products.wd-grid-g',
            'ul.products.grid-columns',
            '.woocommerce ul.products',
            '.woocommerce div.products',
            'ul.products.columns-4',
            'ul.products.columns-3',
            'ul.products.columns-2',
            'ul.products',
            'div.products',
            '.products'
        ];

        console.log('🔍 Searching for products container...');

        for (var i = 0; i < selectors.length; i++) {
            var $c = $(selectors[i]).first();
            var count = getProductCountIn($c);
            if ($c.length && count > 0) {
                console.log('✅ Container found: "' + selectors[i] + '" (' + count + ' products)');
                foundContainer = $c;
                return $c;
            }
        }

        // جستجوی عمومی: والد اولین .product
        var $anyProduct = $('li.product, div.product').first();
        if ($anyProduct.length) {
            var $parent = $anyProduct.parent();
            var parentCount = getProductCountIn($parent);
            if (parentCount > 0) {
                console.log('✅ Container (broad search) found (' + parentCount + ' products)');
                foundContainer = $parent;
                return $parent;
            }
        }

        console.error('❌ Products container not found');
        console.error('   li.product:', $('li.product').length, '| div.product:', $('div.product').length);
        return null;
    }

    // ========================================
    // Infinite Scroll - بارگذاری محصولات بیشتر
    // ========================================
    function loadMoreProducts() {
        if (isLoading || !hasMoreProducts) return;

        var $container = getProductsContainer();
        if (!$container || !$container.length) {
            console.error('❌ Container not found for loading');
            return;
        }

        isLoading = true;
        currentPage++;
        console.log('📦 Loading page ' + currentPage + '...');
        showWoodmartLoader();

        var categoryId = 0;
        if ($('body').hasClass('tax-product_cat')) {
            var classes = document.body.className.split(' ');
            for (var i = 0; i < classes.length; i++) {
                if (classes[i].startsWith('term-')) {
                    categoryId = parseInt(classes[i].replace('term-', ''));
                    break;
                }
            }
        }

        if (typeof botri_ajax === 'undefined') {
            console.error('❌ botri_ajax is undefined');
            isLoading = false;
            hasMoreProducts = false;
            hideWoodmartLoader();
            return;
        }

        var currentUrl = new URL(window.location.href);
        var params = currentUrl.searchParams;

        // ✅ FIX v2.5: خواندن hover style از DOM و ارسال به PHP
        // PHP در AJAX نمی‌داند Elementor چه hover style دارد
        // JS آن را از اولین محصول DOM می‌خواند و به PHP می‌فرستد
        var $contForHover = getProductsContainer();
        var $firstProd = $contForHover ? $contForHover.find('li.product, div.product').first() : $();
        var wdHoverFull = extractWdClass($firstProd, 'wd-hover-');
        var wdHoverVal  = wdHoverFull ? wdHoverFull.replace('wd-hover-', '') : '';
        console.log('📤 Sending wd_hover to PHP:', wdHoverVal);

        $.ajax({
            url: botri_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'botri_load_more_products',
                nonce: botri_ajax.infinite_nonce,
                paged: currentPage,
                category: categoryId,
                filters: params.toString(),
                wd_hover: wdHoverVal
            },
            success: function(response) {
                console.log('📨 AJAX Response:', response);

                if (response.success && response.data && response.data.products) {
                    // ✅ FIX v2.4: پشتیبانی از div.product و li.product
                    var itemSel = getProductItemSelector();
                    var $parsed = $(response.data.products);
                    var $newItems = $parsed.filter(itemSel).length > 0
                        ? $parsed.filter(itemSel)
                        : $parsed.find(itemSel);

                    console.log('✅ Loaded ' + $newItems.length + ' new products (selector: ' + itemSel + ')');

                    if ($newItems.length > 0) {
                        // ✅ FIX v2.6: حذف wrapper اضافی botri-ajax-item اگر وجود دارد
                        $newItems.each(function() {
                            if ($(this).hasClass('botri-ajax-item')) {
                                var $realItem = $(this).children().first();
                                $(this).replaceWith($realItem);
                                // جایگزین کردن در مجموعه $newItems
                            }
                        });

                        // بازخوانی مجدد آیتم‌ها بعد از حذف wrapper
                        $newItems = $parsed.find(itemSel);
                        if ($newItems.length === 0) $newItems = $parsed.filter(itemSel);

                        // همگام‌سازی کلاس‌های Woodmart
                        var $ref = $container.find('li.product, div.product').first();
                        if ($ref.length) {
                            ['wd-hover-', 'wd-col-', 'wd-with-'].forEach(function(prefix) {
                                var correctClass = extractWdClass($ref, prefix);
                                if (!correctClass) return;
                                var pattern = new RegExp('\\b' + prefix.replace(/-/g, '\\-') + '\\S+', 'g');
                                $newItems.each(function() {
                                    $(this).removeClass(function(i, cls) {
                                        return (cls.match(pattern) || []).join(' ');
                                    }).addClass(correctClass).addClass('botri-loaded');
                                });
                            });
                        }

                        $newItems.appendTo($container);

                        // Re-init Woodmart
                        setTimeout(function() {
                            $(document.body).trigger('wdShopPageInit');
                            $(document.body).trigger('botri_products_loaded');
                            if (typeof woodmart !== 'undefined' && typeof woodmart.initProductsGrid === 'function') {
                                woodmart.initProductsGrid();
                            }
                            // اجرای مجدد انیمیشن‌های ظهور وودمارت
                            if (typeof woodmart_settings !== 'undefined') {
                                $(document.body).trigger('woodmart_products_loaded');
                            }
                        }, 100);

                        if (currentPage >= response.data.max_num_pages) {
                            hasMoreProducts = false;
                            showEndMessage();
                            console.log('🏁 No more products');
                        }
                    } else {
                        hasMoreProducts = false;
                        showEndMessage();
                    }
                } else {
                    console.error('❌ AJAX failed:', response);
                    hasMoreProducts = false;
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ AJAX Error:', error);
                hasMoreProducts = false;
            },
            complete: function() {
                isLoading = false;
                hideWoodmartLoader();
                removePagination();
                disableLoadMoreButtons();
            }
        });
    }

    // ========================================
    // تشخیص scroll به انتهای صفحه
    // ✅ FIX v2.3: فقط scroll.botriInfinite را unbind می‌کند (نه همه scroll ها)
    // ========================================
    function initInfiniteScroll() {
        if (!isShopOrCategoryPage()) {
            console.log('⭕️ Not a shop/category page');
            return;
        }

        if (scrollListenerActive) {
            console.log('⚠️ Scroll listener already active');
            return;
        }

        var $container = getProductsContainer();
        if (!$container || !$container.length) {
            console.error('❌ Cannot initialize - container not found');
            return;
        }

        var initialCount = getProductCountIn($container);
        console.log('🎯 Infinite Scroll initialized - Initial products: ' + initialCount);

        $(window).off('scroll.botriInfinite');

        $(window).on('scroll.botriInfinite', function() {
            if (isLoading || !hasMoreProducts) return;

            var scrollTop = $(window).scrollTop();
            var windowHeight = $(window).height();
            var documentHeight = $(document).height();

            if (scrollTop + windowHeight >= documentHeight - 500) {
                console.log('🔍 Scroll trigger reached');
                loadMoreProducts();
            }
        });

        scrollListenerActive = true;
        console.log('✅ Scroll listener activated (botriInfinite namespace)');
    }

    // ========================================
    // لینک‌های فیلتر
    // ========================================
    function initFilterLinks() {
        $(document).off('click.botriFilter');
        $(document).on('click.botriFilter', '[data-botri-filter-link], a.botri-filter-link, .botri-filter-link', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var $element = $(this);
            var categoryUrl = $element.data('botri-category-url') || $element.attr('data-botri-category-url');
            var filterData = $element.data('botri-filter-data') || $element.attr('data-botri-filter-data');

            if (!categoryUrl) {
                alert('خطا: آدرس دسته‌بندی مشخص نشده است');
                return false;
            }

            if (typeof filterData === 'string') {
                try {
                    filterData = JSON.parse(filterData);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    return false;
                }
            }

            if (filterData && typeof filterData === 'object') {
                var existingFilters = JSON.parse(getCookie('botri_nonseo_filters'));
                var mergedFilters = Object.assign({}, existingFilters, filterData);
                setCookie('botri_nonseo_filters', JSON.stringify(mergedFilters), 1);
            }

            showFullPageLoading();
            window.location.href = categoryUrl;
            return false;
        });
    }

    // ========================================
    // چک‌باکس فیلترها
    // ========================================
    $(document).on('change', '.botri-nonseo-checkbox', function() {
        var $this = $(this);
        var tax = $this.data('tax');
        var slug = $this.data('slug');
        var isChecked = $this.is(':checked');

        var filters = JSON.parse(getCookie('botri_nonseo_filters'));
        var key = 'filter_' + tax;
        var values = filters[key] ? filters[key].split(',') : [];

        if (isChecked) {
            if (!values.includes(slug)) values.push(slug);
        } else {
            values = values.filter(function(v) { return v !== slug; });
        }

        if (values.length > 0) {
            filters[key] = values.join(',');
        } else {
            delete filters[key];
        }

        setCookie('botri_nonseo_filters', JSON.stringify(filters), 1);
        showFullPageLoading();
        setTimeout(function() { location.reload(); }, 300);
    });

    // ========================================
    // فیلتر قیمت
    // ========================================
    $(document).on('click', '.widget_price_filter button[type="submit"], .botri-price-submit', function(e) {
        e.preventDefault();

        var $form = $(this).closest('form, .botri-filter-options, .botri-price-filter-wrapper');
        var min = $form.find('input[name="min_price"], .botri-min-price').val();
        var max = $form.find('input[name="max_price"], .botri-max-price').val();

        if (!min || !max) return;

        var filters = JSON.parse(getCookie('botri_nonseo_filters'));
        filters['min_price'] = parseInt(min);
        filters['max_price'] = parseInt(max);
        setCookie('botri_nonseo_filters', JSON.stringify(filters), 1);

        showFullPageLoading();
        setTimeout(function() { location.reload(); }, 300);
    });

    // ========================================
    // حذف فیلترها
    // ========================================
    $(document).on('click', '.botri-remove-nonseo', function(e) {
        e.preventDefault();

        var $this = $(this);
        var key = $this.data('key');
        var slug = $this.data('slug') || null;

        var filters = JSON.parse(getCookie('botri_nonseo_filters'));

        if (slug && filters[key]) {
            var values = filters[key].split(',').filter(function(v) { return v !== slug; });
            if (values.length > 0) {
                filters[key] = values.join(',');
            } else {
                delete filters[key];
            }
        } else if (key === 'price') {
            delete filters['min_price'];
            delete filters['max_price'];
        } else {
            delete filters[key];
        }

        setCookie('botri_nonseo_filters', JSON.stringify(filters), 1);
        showFullPageLoading();
        setTimeout(function() { location.reload(); }, 300);
    });

    $(document).on('click', '.botri-clear-all', function(e) {
        e.preventDefault();
        setCookie('botri_nonseo_filters', '{}', -1);
        showFullPageLoading();
        setTimeout(function() { location.reload(); }, 300);
    });

    // ========================================
    // اسلایدر قیمت
    // ========================================
    function initPriceSliders() {
        if (typeof jQuery.ui === 'undefined' || typeof jQuery.ui.slider === 'undefined') return;

        $('.botri-price-slider-el').each(function() {
            var $slider = $(this);
            var sliderId = $slider.attr('id');

            if (!sliderId || $slider.hasClass('ui-slider')) return;

            var isRTL = $('html').attr('dir') === 'rtl';
            var minPrice = parseInt($slider.data('min')) || 0;
            var maxPrice = parseInt($slider.data('max')) || 1000000;
            var currentMin = parseInt($slider.data('current-min')) || minPrice;
            var currentMax = parseInt($slider.data('current-max')) || maxPrice;

            var sliderConfig = {
                range: true,
                min: minPrice,
                max: maxPrice,
                values: [currentMin, currentMax],
                slide: function(event, ui) {
                    $('#' + sliderId + '-min, #botri-min-price').val(ui.values[0]);
                    $('#' + sliderId + '-max, #botri-max-price').val(ui.values[1]);
                    $('#' + sliderId + '-range, #botri-price-range').html(
                        formatPrice(ui.values[0]) + ' - ' + formatPrice(ui.values[1])
                    );
                }
            };

            if (isRTL) sliderConfig.isRTL = true;

            try {
                $slider.slider(sliderConfig);
                console.log('✅ Price slider initialized:', sliderId);
            } catch(e) {
                console.error('Slider error:', e);
            }
        });
    }

    // ========================================
    // Initialize
    // ========================================
    function initBotriScroll() {
        console.log('🔱 Initializing Botri Scroll');

        currentPage = 1;
        isLoading = false;
        hasMoreProducts = true;
        scrollListenerActive = false;
        foundContainer = null;

        removePagination();
        disableLoadMoreButtons();
        initFilterLinks();
        initPriceSliders();

        setTimeout(function() {
            console.log('🔍 Attempting to find container...');
            var testContainer = getProductsContainer();
            if (testContainer && testContainer.length) {
                console.log('✅ Container ready, initializing scroll');
                initInfiniteScroll();
            } else {
                console.error('❌ Container still not found after delay');
                setTimeout(function() {
                    console.log('🔄 Retry finding container...');
                    foundContainer = null;
                    initInfiniteScroll();
                }, 2000);
            }
        }, 2000);
    }

    // Document Ready
    $(document).ready(function() {
        console.log('📄 Document ready');
        if (typeof botri_ajax !== 'undefined') {
            console.log('✅ botri_ajax loaded');
        } else {
            console.error('❌ botri_ajax is undefined!');
        }
        setTimeout(function() {
            initBotriScroll();
        }, 500);
    });

    // Elementor
    $(window).on('elementor/frontend/init', function() {
        console.log('⚡ Elementor detected');
        setTimeout(function() {
            foundContainer = null;
            removePagination();
            disableLoadMoreButtons();
            initFilterLinks();
            initPriceSliders();
        }, 1000);
    });

    // Fallback
    setTimeout(function() {
        removePagination();
        disableLoadMoreButtons();
    }, 3000);

    // ========================================
    // مانیتور DOM
    // ✅ FIX v2.3: debounce + subtree:false + بررسی صفحه
    // ========================================
    var mutationTimer = null;
    var observer = new MutationObserver(function() {
        if (!isShopOrCategoryPage()) return;
        clearTimeout(mutationTimer);
        mutationTimer = setTimeout(function() {
            removePagination();
            disableLoadMoreButtons();
        }, 200);
    });

    observer.observe(document.body, {
        childList: true,
        subtree: false
    });

})(jQuery);