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
/* ===== #13 — тонкие архивы pa_model («запчасти по модели») с <3 товаров: noindex.
   Динамически — пустые/тонкие модели вон из индекса, живые (>=3) остаются. ===== */
function kd_seo_is_thin_model() {
    if (!is_tax('pa_model-sh-tehniki')) return false;
    $t = get_queried_object();
    return ($t instanceof WP_Term && (int) $t->count < 3);
}
add_filter('wpseo_robots_array', function ($robots) {
    if (kd_seo_is_legacy_catalog() || kd_seo_is_thin_model()) { $robots['index'] = 'noindex'; }
    return $robots;
});
add_filter('wpseo_robots', function ($robots) { // совместимость со старым Yoast
    if (kd_seo_is_legacy_catalog() || kd_seo_is_thin_model()) return 'noindex, follow';
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

/* ============================================================
 * PageSpeed-фиксы (аудит 07.06.2026): шрифт, контраст, a11y
 * ============================================================ */

/* A4 — Google Fonts: display=block -> swap (убираем FOIT, текст виден сразу) */
add_filter('style_loader_src', function ($src) {
    if (is_string($src) && strpos($src, 'fonts.googleapis.com') !== false && strpos($src, 'display=block') !== false) {
        $src = str_replace('display=block', 'display=swap', $src);
    }
    return $src;
}, 20);

/* A1 + B4 — инлайн-CSS: не искажать фото слайдера + контраст бренд-зелёного до AA */
add_action('wp_head', function () { ?>
<style id="kd-ps-css">
.slider img.attachment-large{object-fit:cover}
.header__link.active{color:#286e4c!important}
.footer__btn,.footer__btn.button--border{color:#286e4c!important}
</style>
<?php }, 6);

/* B1-B3 — a11y: aria-label кнопок/ссылок-иконок/CTA + alt декор-картинок (JS, тему не трогаем) */
add_action('wp_footer', function () { ?>
<script id="kd-a11y">
(function(){function L(e,t){if(e&&!e.getAttribute('aria-label')&&!e.getAttribute('title')&&!(e.textContent||'').trim())e.setAttribute('aria-label',t);}
document.addEventListener('DOMContentLoaded',function(){
 document.querySelectorAll('a').forEach(function(a){
  if(a.getAttribute('aria-label')||a.getAttribute('title'))return;
  var t=(a.textContent||'').trim(),im=a.querySelector('img[alt]');
  if(t||(im&&(im.getAttribute('alt')||'').trim()))return;
  var h=(a.getAttribute('href')||'').toLowerCase(),s=a.getAttribute('data-src')||'',c=a.className||'',l='';
  if(h.indexOf('vk.com')>-1)l='ВКонтакте';else if(h.indexOf('avito')>-1)l='Авито';
  else if(h.indexOf('t.me')>-1||h.indexOf('telegram')>-1)l='Telegram';
  else if(h.indexOf('wa.me')>-1||h.indexOf('whatsapp')>-1)l='WhatsApp';
  else if(h.indexOf('mailto:')>-1)l='Эл. почта';else if(h.indexOf('tel:')>-1)l='Позвонить';
  else if(s.indexOf('modal_order')>-1||c.indexOf('benefit__content')>-1||c.indexOf('footer__btn')>-1)l='Оставить заявку';
  else if(a.querySelector('svg,img,i'))l='Ссылка';
  if(l)a.setAttribute('aria-label',l);
 });
 L(document.querySelector('.burger'),'Меню');L(document.querySelector('#searchsubmit'),'Искать');
 document.querySelectorAll('button').forEach(function(b){if(!b.getAttribute('aria-label')&&!(b.textContent||'').trim()&&!b.getAttribute('title'))b.setAttribute('aria-label','Кнопка');});
 document.querySelectorAll('img:not([alt])').forEach(function(i){i.setAttribute('alt','');i.setAttribute('role','presentation');});
});})();
</script>
<?php }, 20);
