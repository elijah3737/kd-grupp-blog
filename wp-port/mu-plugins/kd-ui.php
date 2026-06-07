<?php
/**
 * Plugin Name: KD UI — косметические правки фронта
 * Description: Точечные CSS-правки внешнего вида по запросам клиента. Главная: сокращён большой зазор между секцией каталога и блоком «О компании» (120→60px, десктоп). Откат: переименовать в kd-ui.php.off.
 * Version: 1.0
 * Author: Юпитер
 */
if (!defined('ABSPATH')) exit;

add_action('wp_head', function () {
    if (!is_front_page() && !is_home()) return;
    ?>
<style id="kd-ui-css">
/* Главная: меньше пустоты между блоком «Перейти в каталог» и секцией «О компании» */
@media (min-width:768px){
  body.home section.product{margin-bottom:60px !important}
}
</style>
<?php }, 9);
