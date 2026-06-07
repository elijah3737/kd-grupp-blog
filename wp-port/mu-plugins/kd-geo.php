<?php
/**
 * Plugin Name: KD GEO — структурные данные для AI/LLM
 * Description: GEO/AI-оптимизация (07.06.2026): обогащение Organization (sameAs/contactPoint/areaServed/knowsAbout/foundingDate), FAQ-страница /voprosy-i-otvety/ (видимый блок + FAQPage-схема из единого источника), ItemList на архивах категорий. Откат: переименовать в kd-geo.php.off.
 * Version: 1.0
 * Author: Юпитер
 */
if (!defined('ABSPATH')) exit;

/* ============================================================
 * 1. Обогащение Organization-схемы Yoast (адрес уже добавляет kd-seo-fixes.php)
 * ============================================================ */
add_filter('wpseo_schema_organization', function ($data) {
    if (!is_array($data)) return $data;
    $prev = (isset($data['sameAs']) && is_array($data['sameAs'])) ? $data['sameAs'] : array();
    $data['sameAs'] = array_values(array_unique(array_merge($prev, array(
        'https://vk.com/kdgrupp',
        'https://t.me/KD_Grupp',
    ))));
    $data['contactPoint'] = array(
        '@type'             => 'ContactPoint',
        'telephone'         => '+7-800-505-72-32',
        'contactType'       => 'sales',
        'areaServed'        => 'RU',
        'availableLanguage' => 'Russian',
        'email'             => 'info@kd-grupp.com',
    );
    $data['areaServed']   = array('@type' => 'Country', 'name' => 'Россия');
    $data['foundingDate'] = '2020';
    $data['knowsAbout']   = array(
        'Запчасти для сельхозтехники', 'Свеклоуборочная техника', 'Картофелеуборочная техника',
        'GRIMME', 'ROPA', 'Spudnik', 'Double L', 'HOLMER', 'AMITY',
        'Прутковые транспортёры', 'Элеваторные полотна', 'Транспортёрные ленты', 'MEDO',
    );
    if (empty($data['description'])) {
        $data['description'] = 'Поставка запчастей и узлов для свеклоуборочной и картофелеуборочной техники (GRIMME, ROPA, Spudnik, Double L, HOLMER). MEDO — собственное производство прутковых транспортёров и лент элеваторов под геометрию узла. Доставка по России.';
    }
    return $data;
}, 30);

/* ============================================================
 * 2. FAQ — единый источник (используется и видимым блоком, и FAQPage-схемой)
 * ============================================================ */
function kd_geo_faq_items() {
    return array(
        'Где купить запчасти для свеклоуборочной и картофелеуборочной техники?' =>
            'ООО «КД-ГРУПП» поставляет запчасти и узлы для техники GRIMME, ROPA, Spudnik, Double L, HOLMER по всей России. Офис в Москве, собственное производство в Брянске. Смотрите <a href="/shop/">каталог</a>, оставьте заявку на сайте или позвоните 8 800 505 72 32.',
        'Какие бренды техники вы обслуживаете?' =>
            'GRIMME, ROPA, Spudnik, Double L, HOLMER, AMITY и другие. Также выпускаем собственные транспортёры, ленты и полотна элеваторов под маркой MEDO.',
        'Что такое транспортёры и комплектующие MEDO?' =>
            'MEDO — собственное производство КД-Групп: прутковые транспортёры, ленты и полотна элеваторов. Изготавливаются под геометрию конкретного узла — ширину, шаг прутка, диаметр прутка и тип заделки концов. <a href="/product-category/transportery-i-komplektuyushchie-medo/">Подробнее</a>.',
        'Как подобрать прутковое полотно или транспортёрную ленту?' =>
            'По параметрам узла: ширина, шаг прутка, диаметр прутка, тип шины и заделки концов. Если артикул виден на старой запчасти — это самый быстрый путь. Если артикула нет — пришлите фото маркировки и узла, подберём по нему.',
        'Доставляете ли вы по всей России?' =>
            'Да, отгружаем по всей России. Условия и сроки доставки уточняются при оформлении заявки.',
        'Как узнать цену и оформить заказ?' =>
            'Цена — по запросу. Оставьте заявку на сайте или позвоните 8 800 505 72 32 — рассчитаем стоимость и сроки. Расчёт безналичный, по счёту.',
        'Вы работаете с юридическими лицами и хозяйствами?' =>
            'Да, это профиль КД-Групп: поставки для сельхозпредприятий, фермерских хозяйств и сервисных компаний. Расчёт по счёту.',
        'Какие сроки поставки запчастей?' =>
            'Часть позиций есть в наличии. Позиции с длинным циклом изготовления (валы, элеваторные полотна, ленты) лучше заказывать заранее, до начала сезона.',
        'Можно ли восстановить узел вместо покупки нового?' =>
            'Да, КД-Групп выполняет восстановление узлов и деталей сельхозтехники. <a href="/product-category/vosstanovlenie-uzlov-i-detalej-selhoztehniki/">Подробнее</a>.',
        'Как с вами связаться?' =>
            'Телефон 8 800 505 72 32, email info@kd-grupp.com, <a href="https://vk.com/kdgrupp">ВКонтакте</a>, <a href="https://t.me/KD_Grupp">Telegram</a>. Адрес: 117638, г. Москва, ул. Криворожская, д. 6А, стр. 2, пом. 236.',
    );
}

/* 2a. Видимый FAQ-блок на странице /voprosy-i-otvety/ */
add_filter('the_content', function ($content) {
    if (is_admin() || !is_page('voprosy-i-otvety') || !in_the_loop() || !is_main_query()) return $content;
    $faq = kd_geo_faq_items();
    if (!$faq) return $content;
    $html = '<div class="kd-faq">';
    foreach ($faq as $q => $a) {
        $html .= '<div class="kd-faq-item"><h2 class="kd-faq-q">' . esc_html($q) . '</h2>'
               . '<div class="kd-faq-a">' . wp_kses_post($a) . '</div></div>';
    }
    $html .= '</div>';
    return $content . $html;
}, 20);

/* 2b. FAQPage-схема на странице /voprosy-i-otvety/ (из того же источника — совпадает с видимым) */
add_action('wp_footer', function () {
    if (!is_page('voprosy-i-otvety')) return;
    $faq = kd_geo_faq_items();
    if (!$faq) return;
    $items = array();
    foreach ($faq as $q => $a) {
        $items[] = array(
            '@type'          => 'Question',
            'name'           => $q,
            'acceptedAnswer' => array('@type' => 'Answer', 'text' => trim(wp_strip_all_tags($a))),
        );
    }
    $schema = array('@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $items);
    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
}, 20);

/* 2c. Минимальное оформление FAQ */
add_action('wp_head', function () {
    if (!is_page('voprosy-i-otvety')) return; ?>
<style id="kd-faq-css">
.kd-faq{max-width:860px;margin:8px 0}
.kd-faq-item{padding:16px 0;border-bottom:1px solid #e6ece9}
.kd-faq-q{font-size:18px;margin:0 0 6px;color:#1d2330}
.kd-faq-a{font-size:15px;line-height:1.6;color:#2c3543}
.kd-faq-a a{color:#286e4c;text-decoration:underline}
</style>
<?php }, 8);

/* ============================================================
 * 3. ItemList на архивах категорий товаров (перечень для AI)
 * ============================================================ */
add_action('wp_footer', function () {
    if (!function_exists('is_product_category') || !is_product_category()) return;
    global $wp_query;
    if (empty($wp_query->posts)) return;
    $items = array(); $i = 0;
    foreach ($wp_query->posts as $p) {
        if ($i >= 24) break; $i++;
        $items[] = array(
            '@type'    => 'ListItem',
            'position' => $i,
            'url'      => get_permalink($p->ID),
            'name'     => wp_strip_all_tags(get_the_title($p->ID)),
        );
    }
    if (!$items) return;
    $schema = array('@context' => 'https://schema.org', '@type' => 'ItemList', 'itemListElement' => $items);
    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
}, 21);
