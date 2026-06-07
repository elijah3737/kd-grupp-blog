<?php
/**
 * Plugin Name: KD SEO Fixes
 * Description: Точечные SEO-правки КД-Групп (аудит 07.06.2026): alt-фоллбэк, og:type листингов, /novosti→/news 301, адрес+контакты в Organization-схеме Yoast, noindex легаси-CPT catalog + вон из карты сайта. Откат: переименовать файл в kd-seo-fixes.php.off.
 * Version: 1.0
 * Author: Юпитер
 */
if (!defined('ABSPATH')) exit;

/* ===== #6 — alt из заголовка вложения/записи, если пустой (image-SEO + a11y) ===== */
add_filter('wp_get_attachment_image_attributes', function ($attr, $attachment) {
    if (empty($attr['alt']) || trim($attr['alt']) === '') {
        $t = ($attachment instanceof WP_Post) ? $attachment->post_title : '';
        if (!$t && isset($attachment->ID)) $t = get_the_title($attachment->ID);
        if (!$t) { $pid = get_the_ID(); if ($pid) $t = get_the_title($pid); }
        if ($t) $attr['alt'] = wp_strip_all_tags($t);
    }
    return $attr;
}, 20, 2);

/* ===== #8 — og:type: листинги/архивы = website, товар = product ===== */
add_filter('wpseo_opengraph_type', function ($type) {
    if (is_singular('product')) return 'product';
    if ((function_exists('is_shop') && is_shop())
        || (function_exists('is_product_category') && is_product_category())
        || (function_exists('is_product_tag') && is_product_tag())
        || is_post_type_archive() || is_archive() || is_home() || is_search()) {
        return 'website';
    }
    return $type;
});

/* ===== #10 — /novosti/ -> /news/ (301) ===== */
add_action('template_redirect', function () {
    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    if ($path === 'novosti') { wp_safe_redirect(home_url('/news/'), 301); exit; }
}, 1);

/* ===== #12 — адрес/контакты в Organization-схеме (локальные сигналы) ===== */
add_filter('wpseo_schema_organization', function ($data) {
    if (!is_array($data)) return $data;
    $data['address'] = array(
        '@type'           => 'PostalAddress',
        'streetAddress'   => 'ул. Криворожская, д. 6А, стр. 2, пом. 236',
        'addressLocality' => 'Москва',
        'postalCode'      => '117638',
        'addressCountry'  => 'RU',
    );
    $data['telephone'] = '+7-800-505-72-32';
    $data['email']     = 'info@kd-grupp.com';
    return $data;
});

/* ===== #2 — легаси-CPT catalog: noindex (архив + одиночные) ===== */
function kd_seo_is_legacy_catalog() {
    return (is_singular('catalog') || is_post_type_archive('catalog'));
}
add_filter('wpseo_robots_array', function ($robots) {
    if (kd_seo_is_legacy_catalog()) { $robots['index'] = 'noindex'; }
    return $robots;
});
add_filter('wpseo_robots', function ($robots) { // совместимость со старым Yoast
    if (kd_seo_is_legacy_catalog()) return 'noindex, follow';
    return $robots;
});
/* и вон из карты сайта Yoast */
add_filter('wpseo_sitemap_exclude_post_type', function ($excluded, $post_type) {
    return ($post_type === 'catalog') ? true : $excluded;
}, 10, 2);

/* ===== #1 — корректная Product-схема (заменяет WPCode-сниппет 184940).
   Offer выводим ТОЛЬКО при наличии цены — иначе валидный Product без offers
   («цена по запросу»), без ошибки «Missing field price» в Search Console.
   Сниппет 184940 нужно деактивировать в WPCode, чтобы не было дубля. ===== */
add_action('wp_footer', function () {
    if (!is_singular('product') || !function_exists('wc_get_product')) return;
    $product = wc_get_product(get_the_ID());
    if (!$product) return;

    $schema = array(
        '@context' => 'https://schema.org/',
        '@type'    => 'Product',
        'name'     => $product->get_name(),
        'url'      => get_permalink($product->get_id()),
    );
    $sku = $product->get_sku();
    if ($sku) $schema['sku'] = $sku;

    $desc = $product->get_short_description() ?: $product->get_description();
    $desc = trim(wp_strip_all_tags(strip_shortcodes((string) $desc)));
    if ($desc) $schema['description'] = function_exists('mb_substr') ? mb_substr($desc, 0, 500) : substr($desc, 0, 500);

    $img = wp_get_attachment_url($product->get_image_id());
    if ($img) $schema['image'] = $img;

    $price = $product->get_price();
    if ($price !== '' && $price !== null && (float) $price > 0) {
        $schema['offers'] = array(
            '@type'         => 'Offer',
            'priceCurrency' => get_woocommerce_currency(),
            'price'         => (string) wc_get_price_to_display($product),
            'availability'  => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'url'           => get_permalink($product->get_id()),
            'seller'        => array('@type' => 'Organization', 'name' => 'КД Групп'),
        );
    }

    echo '<script type="application/ld+json">'
        . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        . '</script>' . "\n";
}, 20);
