<?php
/**
 * Plugin Name: KD Legal — cookie-баннер + политика
 * Description: 152-ФЗ: информационный cookie-баннер (Принять, согласие в cookie на год) + ссылка на Политику конфиденциальности в подвале сайта. Текст политики — страница /politika-konfidencialnosti/. Откат: переименовать файл в kd-legal.php.off.
 * Version: 1.0
 * Author: Юпитер
 */
if (!defined('ABSPATH')) exit;

if (!defined('KD_PRIVACY_URL')) define('KD_PRIVACY_URL', '/politika-konfidencialnosti/');

add_action('wp_footer', function () {
    if (is_admin()) return;
    $url = esc_url(KD_PRIVACY_URL);
    ?>
<style id="kd-legal-css">
#kd-cookie{position:fixed;left:16px;right:16px;bottom:16px;z-index:99990;max-width:1180px;margin:0 auto;background:#fff;border:1px solid #d9e6df;border-radius:12px;box-shadow:0 8px 30px rgba(20,40,30,.18);padding:15px 20px;display:none;align-items:center;gap:14px 18px;flex-wrap:wrap;font-size:14px;line-height:1.5;color:#2c3543}
#kd-cookie.kd-show{display:flex}
#kd-cookie p{margin:0;flex:1 1 320px}
#kd-cookie a{color:#286e4c;text-decoration:underline}
#kd-cookie .kd-cc-btn{flex:0 0 auto;background:#286e4c;color:#fff;border:0;border-radius:8px;padding:11px 28px;font-size:14px;font-weight:600;cursor:pointer;line-height:1.2}
#kd-cookie .kd-cc-btn:hover{background:#225e41}
.kd-footer-policy{margin:10px 0 0;font-size:13px;opacity:.9;text-align:center}
.kd-footer-policy a{color:inherit;text-decoration:underline}
@media(max-width:600px){#kd-cookie .kd-cc-btn{flex:1 1 100%}}
</style>
<div id="kd-cookie" role="dialog" aria-label="Уведомление об использовании файлов cookie">
  <p>Мы используем файлы cookie и сервис Яндекс.Метрика для работы сайта и обезличенной статистики. Продолжая пользоваться сайтом, вы соглашаетесь с этим. Подробнее — в <a href="<?php echo $url; ?>">Политике обработки персональных данных</a>.</p>
  <button type="button" class="kd-cc-btn" id="kd-cc-accept">Принять</button>
</div>
<script id="kd-legal-js">
(function(){
 function gc(n){var m=document.cookie.match('(?:^|; )'+n.replace(/([.$?*|{}()\[\]\\\/\+^])/g,'\\$1')+'=([^;]*)');return m?m[1]:null;}
 function sc(n,v,d){var e=new Date();e.setTime(e.getTime()+d*864e5);document.cookie=n+'='+v+';expires='+e.toUTCString()+';path=/;SameSite=Lax';}
 function ready(fn){if(document.readyState!=='loading')fn();else document.addEventListener('DOMContentLoaded',fn);}
 ready(function(){
  if(gc('kd_cookie_consent')!=='1'){
   var b=document.getElementById('kd-cookie');
   if(b){b.classList.add('kd-show');
    var a=document.getElementById('kd-cc-accept');
    if(a)a.addEventListener('click',function(){sc('kd_cookie_consent','1',365);b.classList.remove('kd-show');});
   }
  }
  if(!document.querySelector('.kd-footer-policy') && !document.querySelector('.l-footer a[href*="politika-konfidencialnosti"], footer a[href*="politika-konfidencialnosti"]')){
   var f=document.querySelector('.l-footer')||document.querySelector('footer.l-footer')||document.querySelector('footer')||document.querySelector('.footer');
   if(f){var w=document.createElement('div');w.className='kd-footer-policy';
    w.innerHTML='<a href="<?php echo $url; ?>">Политика конфиденциальности</a>';
    f.appendChild(w);}
  }
 });
})();
</script>
    <?php
}, 25);
