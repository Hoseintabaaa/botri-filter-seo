<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Botri_Elementor_SEO_Filters_Widget extends \Elementor\Widget_Base {
    public function get_name() { return 'botri_seo_filters'; }
    public function get_title() { return 'Botri SEO Filters'; }
    public function get_icon() { return 'eicon-filter'; }
    public function get_categories() { return [ 'woocommerce-elements' ]; }

    protected function register_controls() {
        $this->start_controls_section(
            'section_settings',
            [ 'label' => 'تنظیمات عمومی' ]
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
        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        if ( ! is_product_category() ) return;
        $cat = get_queried_object();
        $rules = get_posts( [ 'post_type' => Botri_Filter_SEO_Manager::CPT, 'numberposts' => -1 ] );
        if ( ! $rules ) return;

        $filters_by_tax = [];
        foreach ( $rules as $r ) {
            $tax_input = get_post_meta( $r->ID, '_taxonomy', true );
            $term = get_post_meta( $r->ID, '_term', true );
            $cats = (array) get_post_meta( $r->ID, '_cats', true );

            if ( in_array( $cat->term_id, $cats ) && $tax_input && $term ) {
                $tax_real = ( 0 === strpos( $tax_input, 'pa_' ) ) ? $tax_input : 'pa_' . $tax_input;
                $q_key = ltrim( preg_replace( '/^pa_/', '', $tax_real ) );

                $term_obj = get_term_by( 'slug', $term, $tax_real );
                if ( $term_obj ) {
                    $filters_by_tax[ $q_key ]['label'] = get_taxonomy( $tax_real )->label ?? $q_key;
                    $filters_by_tax[ $q_key ]['terms'][] = $term_obj;
                }
            }
        }

        if ( empty( $filters_by_tax ) ) return;

        echo '<div class="botri-elementor-seo-filters">';
        if ( 'yes' === $settings['show_title'] ) {
            echo '<h3 class="botri-filter-title">فیلتر محصولات (SEO)</h3>';
        }
        foreach ( $filters_by_tax as $tax => $group ) {
            echo '<details class="botri-filter-group botri-seo">';
            echo '<summary>' . esc_html( $group['label'] ) . '</summary>';
            echo '<div class="botri-filter-options">';
            foreach ( $group['terms'] as $t ) {
                $url = add_query_arg( $tax, $t->slug, get_term_link( $cat ) );
                $is_active = ( isset( $_GET[ $tax ] ) && $_GET[ $tax ] === $t->slug );
                $active    = $is_active ? 'active' : '';
                // toggle: کلیک روی فیلتر فعال → برگشت به دسته‌بندی بدون فیلتر
                $href = $is_active ? esc_url( get_term_link( $cat ) ) : esc_url( $url );
                echo '<a href="' . $href . '" class="botri-filter-item botri-seo-item ' . $active . '">' . esc_html( $t->name ) . '</a>';
            }
            echo '</div></details>';
        }
        echo '</div>';
    }
}