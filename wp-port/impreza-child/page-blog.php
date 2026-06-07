<?php
/**
 * Template Name: Блог (лента)
 * Лента блога КД-Групп: page-head + чипы-фильтр + featured + сетка карточек.
 * Тянет записи из 4 рубрик (novosti/keysy/podbor/to) через хелперы из mu-plugins/kd-blog.php.
 * НЕ использует [us_grid] (он давал зависание /news/ из-за рекурсии Yoast).
 */
if (!defined('ABSPATH')) exit;
get_header();

$term_ids = function_exists('kd_blog_term_ids') ? kd_blog_term_ids() : array();
$ids = array();
if ($term_ids){
  $q = new WP_Query(array(
    'post_type'      => 'post',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'category__in'   => $term_ids,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'ignore_sticky_posts' => true,
    'no_found_rows'  => true,
  ));
  $ids = wp_list_pluck($q->posts, 'ID');
  wp_reset_postdata();
}
$featured = $ids ? array_shift($ids) : 0;
?>
<main class="kd-blog">

  <section class="page-head">
    <div class="container">
      <h1 class="reveal d2">Новости компании, кейсы и экспертиза по сельхозтехнике</h1>
      <p class="reveal d3">Производство транспортёров MEDO, восстановление узлов, поставки запчастей и обслуживание картофеле- и свеклоуборочной техники.</p>
    </div>
  </section>

  <div class="container">
    <div class="crumbs"><a href="<?php echo esc_url(home_url('/')); ?>">Главная</a><span class="sep">/</span><span>Блог</span></div>
  </div>

  <div class="container">
    <div class="section">

      <div class="chips">
        <span class="chip active" data-filter="all">Все материалы</span>
        <span class="chip" data-filter="novosti">Новости</span>
        <span class="chip" data-filter="keysy">Кейсы</span>
        <span class="chip" data-filter="podbor">Подбор запчастей</span>
        <span class="chip" data-filter="to">Обслуживание и ТО</span>
      </div>

      <?php if ($featured) echo kd_featured_html($featured); ?>

      <div class="card-grid">
        <?php foreach ($ids as $cid) echo kd_card_html($cid); ?>
      </div>

      <p class="empty-note" hidden>В этой категории пока нет материалов.</p>

    </div>
  </div>

  <section class="cta-band">
    <div class="container">
      <div>
        <h3>Не нашли нужную деталь в каталоге?</h3>
        <p>Изготовим под заказ или восстановим имеющийся узел. Работаем по всей России.</p>
      </div>
      <a class="btn" href="mailto:info@kd-grupp.com?subject=Заявка%20с%20сайта">Оставить заявку</a>
    </div>
  </section>

</main>
<?php
get_footer();
