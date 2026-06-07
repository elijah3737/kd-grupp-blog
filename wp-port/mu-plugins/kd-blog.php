<?php
/**
 * Plugin Name: KD Blog — лента и оформление статей
 * Description: Дизайн блога КД-Групп: лента на /news/ (через шаблон page-blog.php) и оформление одиночных записей (через the_content). Всё заскоупено под .kd-blog. Рубрики: novosti, keysy, podbor, to.
 * Version: 1.0
 * Author: Юпитер
 *
 * Откат: переименовать файл в kd-blog.php.off — записи вернутся к стандартному виду Impreza.
 */
if (!defined('ABSPATH')) exit;

/* ===== Конфиг ===== */
if (!defined('KD_BLOG_PAGE_ID')) define('KD_BLOG_PAGE_ID', 160); // страница-лента (/news/)

function kd_blog_slugs(){ return array('novosti','keysy','podbor','to'); }
function kd_blog_labels(){
  return array('novosti'=>'Новости','keysy'=>'Кейсы','podbor'=>'Подбор запчастей','to'=>'Обслуживание и ТО');
}

/* term_id 4 рубрик (кэш в option kd_blog_term_ids; иначе резолвим по слагам) */
function kd_blog_term_ids(){
  $ids = get_option('kd_blog_term_ids');
  if (is_array($ids) && $ids) return array_map('intval', $ids);
  $ids = array();
  foreach (kd_blog_slugs() as $s){ $t = get_term_by('slug', $s, 'category'); if ($t) $ids[] = (int)$t->term_id; }
  return $ids;
}

/* пост относится к блогу? */
function kd_is_blog_post($id = null){
  $id = $id ?: get_the_ID();
  if (!$id || get_post_type($id) !== 'post') return false;
  $terms = kd_blog_term_ids();
  if (!$terms) return false;
  return (bool) array_intersect($terms, wp_get_post_categories($id));
}

/* одна категория-чип: Yoast primary -> приоритет -> novosti */
function kd__chip($slug){ $l = kd_blog_labels(); return array('slug'=>$slug, 'label'=>isset($l[$slug]) ? $l[$slug] : 'Новости'); }
function kd_post_chip($id){
  $cats = wp_get_post_categories($id, array('fields'=>'all'));
  $by_slug = array();
  foreach ($cats as $c) $by_slug[$c->slug] = $c;
  $pid = (int) get_post_meta($id, '_yoast_wpseo_primary_category', true);
  if ($pid) foreach ($cats as $c) if ($c->term_id == $pid && in_array($c->slug, kd_blog_slugs(), true)) return kd__chip($c->slug);
  foreach (array('keysy','to','podbor','novosti') as $s) if (isset($by_slug[$s])) return kd__chip($s);
  return kd__chip('novosti');
}

/* дата по-русски (родительный падеж), без TZ-сдвигов */
function kd_ru_date($id){
  static $m = array(1=>'января',2=>'февраля',3=>'марта',4=>'апреля',5=>'мая',6=>'июня',7=>'июля',8=>'августа',9=>'сентября',10=>'октября',11=>'ноября',12=>'декабря');
  $d  = substr((string) get_post_field('post_date', $id), 0, 10); // YYYY-MM-DD
  $ts = strtotime($d);
  if (!$ts) return '';
  return (int)date('j',$ts).' '.$m[(int)date('n',$ts)].' '.date('Y',$ts);
}

/* время чтения (Unicode-счёт слов) */
function kd_reading_time($id){
  $txt = wp_strip_all_tags(get_post_field('post_content', $id));
  preg_match_all('/[\p{L}\p{N}]+/u', $txt, $mm);
  $min = max(1, (int) ceil(count($mm[0]) / 180));
  return 'Чтение ~'.$min.' мин';
}

/* выжимка */
function kd_excerpt($id, $n = 170){
  $p = get_post($id);
  if (!$p) return '';
  $raw = ($p->post_excerpt !== '') ? $p->post_excerpt : $p->post_content;
  $t = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags(strip_shortcodes($raw))));
  if (mb_strlen($t) > $n){ $t = mb_substr($t, 0, $n); $t = preg_replace('/\s+\S*$/u', '', $t).'…'; }
  return $t;
}

/* .ph: картинка-обложка или градиент-заглушка */
function kd_ph_html($id, $eager = false){
  $chip = kd_post_chip($id);
  $out  = '<div class="ph"><span class="tag on-img">'.esc_html($chip['label']).'</span>';
  if (has_post_thumbnail($id)){
    $out .= get_the_post_thumbnail($id, 'large', array(
      'class'=>'ph-img', 'loading'=>$eager ? 'eager' : 'lazy', 'alt'=>esc_attr(get_the_title($id)),
    ));
  }
  return $out.'</div>';
}

/* карточка ленты */
function kd_card_html($id, $eager = false){
  $chip = kd_post_chip($id);
  return '<article class="card reveal d3" data-tags="'.esc_attr($chip['slug']).'">'
    . kd_ph_html($id, $eager)
    . '<div class="body"><div class="meta"><span>'.esc_html(kd_ru_date($id)).'</span></div>'
    . '<h3>'.esc_html(get_the_title($id)).'</h3>'
    . '<p>'.esc_html(kd_excerpt($id, 110)).'</p>'
    . '<a class="more" href="'.esc_url(get_permalink($id)).'">Подробнее →</a></div></article>';
}

/* featured-карточка */
function kd_featured_html($id){
  $chip = kd_post_chip($id);
  return '<article class="featured reveal d2" data-tags="'.esc_attr($chip['slug']).'">'
    . kd_ph_html($id, true)
    . '<div class="body"><div class="meta"><span>'.esc_html(kd_ru_date($id)).'</span>'
    . '<span class="dot"></span><span>'.esc_html($chip['label']).'</span></div>'
    . '<h2>'.esc_html(get_the_title($id)).'</h2>'
    . '<p>'.esc_html(kd_excerpt($id, 210)).'</p>'
    . '<a class="btn" href="'.esc_url(get_permalink($id)).'">Читать</a></div></article>';
}

/* «Читайте также»: 3 свежих той же рубрики, добор из любых блоговых */
function kd_related_html($id){
  $chip = kd_post_chip($id);
  $term = get_term_by('slug', $chip['slug'], 'category');
  $ids  = array();
  if ($term){
    $q = new WP_Query(array('post_type'=>'post','post_status'=>'publish','posts_per_page'=>3,
      'post__not_in'=>array($id),'cat'=>(int)$term->term_id,'no_found_rows'=>true,'ignore_sticky_posts'=>true));
    $ids = wp_list_pluck($q->posts, 'ID'); wp_reset_postdata();
  }
  if (count($ids) < 3){
    $q = new WP_Query(array('post_type'=>'post','post_status'=>'publish','posts_per_page'=>6,
      'post__not_in'=>array_merge(array($id), $ids),'category__in'=>kd_blog_term_ids(),
      'no_found_rows'=>true,'ignore_sticky_posts'=>true));
    foreach ($q->posts as $p){ if (count($ids) >= 3) break; $ids[] = $p->ID; }
    wp_reset_postdata();
  }
  if (!$ids) return '';
  $cards = '';
  foreach ($ids as $rid) $cards .= kd_card_html($rid);
  return '<div class="container related"><h2>Читайте также</h2><div class="card-grid">'.$cards.'</div></div>';
}

/* ===== Одиночная запись: оборачиваем тело в дизайн блога ===== */
function kd_single_content($content){
  if (is_admin() || !is_singular('post') || !in_the_loop() || !is_main_query()) return $content;
  $id = get_the_ID();
  if (!kd_is_blog_post($id)) return $content;

  $chip     = kd_post_chip($id);
  $blog_url = get_permalink(KD_BLOG_PAGE_ID);

  // убрать встроенную в тело CTA-плашку (по запросу клиента)
  $content = preg_replace('#<section[^>]*class="[^"]*cta-band[^"]*"[^>]*>.*?</section>#is', '', $content);

  $head = '<div class="article-head"><div class="container">'
        . '<div class="crumbs reveal d1" style="padding:0 0 6px">'
        . '<a href="'.esc_url(home_url('/')).'">Главная</a><span class="sep">/</span>'
        . '<a href="'.esc_url($blog_url).'">Блог</a><span class="sep">/</span><span>'.esc_html($chip['label']).'</span>'
        . '</div>'
        . '<span class="tag reveal d1">'.esc_html($chip['label']).'</span>'
        . '<h1 class="reveal d2">'.esc_html(get_the_title($id)).'</h1>'
        . '<div class="meta reveal d3"><span>'.esc_html(kd_ru_date($id)).'</span>'
        . '<span class="dot"></span><span>'.esc_html(kd_reading_time($id)).'</span></div>'
        . '</div></div>';

  $hero = kd_hero_html($id);
  $body = '<article class="article-wrap prose">'.$content.'</article>';
  // CTA-плашку в статьях не показываем (по запросу клиента)

  return '<div class="kd-blog kd-blog--single">'.$head.$hero.$body.kd_related_html($id).'</div>';
}
add_filter('the_content', 'kd_single_content', 20);

function kd_hero_html($id){
  if (!has_post_thumbnail($id)) return '';
  $img = get_the_post_thumbnail($id, 'large', array('alt'=>esc_attr(get_the_title($id))));
  return '<div class="container"><div class="article-hero reveal d2">'.$img.'</div></div>';
}

/* ===== CSS/JS только на блоге ===== */
function kd_blog_assets(){
  $is_hub    = is_page(KD_BLOG_PAGE_ID);
  $is_single = (is_singular('post') && kd_is_blog_post());
  if (!$is_hub && !$is_single) return;

  $uri = get_stylesheet_directory_uri().'/assets';
  $dir = get_stylesheet_directory().'/assets';

  wp_enqueue_style('kd-manrope', 'https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap', array(), null);
  wp_enqueue_style('kd-blog', $uri.'/css/kd-blog.css', array(), @filemtime($dir.'/css/kd-blog.css') ?: '1.0');

  if ($is_hub){
    wp_enqueue_script('kd-blog-filter', $uri.'/js/kd-blog-filter.js', array(), @filemtime($dir.'/js/kd-blog-filter.js') ?: '1.0', true);
  }
}
add_action('wp_enqueue_scripts', 'kd_blog_assets', 20);

/* ===== Убрать дубль H1 на блоговых статьях: родной заголовок Impreza (h1.entry-title).
   Наш H1 в article-head имеет class="reveal …" и не затрагивается. Скоуп — только блоговые записи. ===== */
function kd_strip_impreza_h1($html){
    return preg_replace('#<h1\b[^>]*\bclass="[^"]*\bentry-title\b[^"]*"[^>]*>.*?</h1>#is', '', $html, 1);
}
add_action('template_redirect', function(){
    if (is_singular('post') && function_exists('kd_is_blog_post') && kd_is_blog_post()) {
        ob_start('kd_strip_impreza_h1');
    }
}, 11);
