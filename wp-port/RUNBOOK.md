# RUNBOOK — перенос блога в WordPress (kd-grupp.com)

Серверная фаза. Выполнять в ISPmanager web Shell (вход reg.ru SSO, пароли не вводить).
Аддитивно: до шага G клиентам ничего не видно. Откат — в конце.

```sh
# алиас wp-cli (тильда в --path не раскрывается → абсолютный путь)
WP() { /opt/php/8.2/bin/php $HOME/wp.phar --path=/var/www/u2312217/data/www/kd-grupp.com "$@"; }
cd /var/www/u2312217/data/www/kd-grupp.com
```

## A. Pre-flight + бэкап
```sh
df -h .                                   # место (история переполнения!)
WP core version; WP option get blogname   # связь с WP жива
WP db export $HOME/backup-kd-$(date +%F-%H%M).sql
cp -a wp-content/themes/impreza-child   $HOME/backup-impreza-child-$(date +%F-%H%M)
cp -a wp-content/mu-plugins             $HOME/backup-mu-$(date +%F-%H%M)
cp wp-config.php                        $HOME/backup-wp-config-$(date +%F-%H%M).php
```

## B. Загрузка файлов
Способ 1 (рекомендую): запушить `wp-port/` в GitHub-репо, затем curl raw на сервер.
```sh
R=https://raw.githubusercontent.com/elijah3737/kd-grupp-blog/main/wp-port
mkdir -p wp-content/themes/impreza-child/assets/css wp-content/themes/impreza-child/assets/js
curl -fsSL $R/impreza-child/page-blog.php             -o wp-content/themes/impreza-child/page-blog.php
curl -fsSL $R/impreza-child/assets/css/kd-blog.css    -o wp-content/themes/impreza-child/assets/css/kd-blog.css
curl -fsSL $R/impreza-child/assets/js/kd-blog-filter.js -o wp-content/themes/impreza-child/assets/js/kd-blog-filter.js
# mu-plugin заливаем как .off (спит), активируем после таксономии (шаг E)
curl -fsSL $R/mu-plugins/kd-blog.php                  -o wp-content/mu-plugins/kd-blog.php.off
```
Способ 2: ISPmanager Файловый менеджер — загрузить те же файлы в те же папки.

## C. Проверка (read-only) — НЕ пропускать
```sh
WP term list category --fields=term_id,name,slug,count
# 7 статей (рубрика Статьи=470): тело и обложка
for ID in 184945 184947 184949 184951 184953 184955 184957; do
  echo -n "$ID len="; WP post get $ID --field=post_content | wc -c
  echo -n "$ID title="; WP post get $ID --field=post_title
  echo -n "$ID thumb="; WP eval "echo has_post_thumbnail($ID)?'Y':'N';"; echo
done
# есть ли уже пост-кейс ROPA «за 6 дней» (article.html)?
WP post list --post_type=post --s="вернули в строй" --field=ID
```
Сверить: ID статей == ожидаемым; тело полное (>5000) или нет; есть ли обложки.

## D. 4 чистые рубрики + option
```sh
WP term create category "Новости"            --slug=novosti
WP term create category "Кейсы"              --slug=keysy
WP term create category "Подбор запчастей"   --slug=podbor
WP term create category "Обслуживание и ТО"  --slug=to
# узнать term_id и записать в option (для ленты и скоупа)
NOV=$(WP term list category --slug=novosti --field=term_id)
KEY=$(WP term list category --slug=keysy   --field=term_id)
POD=$(WP term list category --slug=podbor  --field=term_id)
TO=$(WP term list category --slug=to       --field=term_id)
WP option update kd_blog_term_ids "[$NOV,$KEY,$POD,$TO]" --format=json
WP option get kd_blog_term_ids --format=json
```

## E. Переразметка 28 записей (по ID — надёжнее имён)
`post term set ... --by=slug` ЗАМЕНЯЕТ рубрики (одна категория на запись).
```sh
# Новости (18)
for ID in 183272 183275 183962 184011 184058 184672 184678 184681 184684 184744 184746 184784 184797 184806 184811 184820 184890 184967; do WP post term set $ID category novosti --by=slug; done
# Кейсы (2 новости-кейса; третий — пост ROPA создадим в F)
for ID in 184826 184832; do WP post term set $ID category keysy --by=slug; done
# Подбор запчастей (4)
for ID in 184949 184951 184955 184957; do WP post term set $ID category podbor --by=slug; done
# Обслуживание и ТО (3)
for ID in 184945 184947 184953; do WP post term set $ID category to --by=slug; done
# Активируем mu-plugin (single-хук оживает)
mv wp-content/mu-plugins/kd-blog.php.off wp-content/mu-plugins/kd-blog.php
# Контроль: должно быть 18/2/4/3 (+ROPA в F → keysy=3)
WP term list category --fields=name,slug,count | grep -E "novosti|keysy|podbor|to"
```

## F. 8 статей: контент + обложки + пост ROPA
Обложки уже в репо: `assets/cover-*.webp`, `assets/news/184832.jpg`. Залить во временную папку:
```sh
mkdir -p $HOME/kdcov; cd $HOME/kdcov
RA=https://raw.githubusercontent.com/elijah3737/kd-grupp-blog/main/assets
for f in cover-sezon cover-holmer cover-elevator cover-medo cover-remont cover-double-l cover-spudnik; do curl -fsSL $RA/$f.webp -o $f.webp; done
curl -fsSL $RA/news/184832.jpg -o ropa.jpg
cd /var/www/u2312217/data/www/kd-grupp.com
```
Обложки 7 статьям (ТОЛЬКО если в шаге C thumb=N):
```sh
WP media import $HOME/kdcov/cover-sezon.webp     --post_id=184945 --featured_image
WP media import $HOME/kdcov/cover-holmer.webp    --post_id=184947 --featured_image
WP media import $HOME/kdcov/cover-elevator.webp  --post_id=184949 --featured_image
WP media import $HOME/kdcov/cover-medo.webp      --post_id=184951 --featured_image
WP media import $HOME/kdcov/cover-remont.webp    --post_id=184953 --featured_image
WP media import $HOME/kdcov/cover-double-l.webp  --post_id=184955 --featured_image
WP media import $HOME/kdcov/cover-spudnik.webp   --post_id=184957 --featured_image
```
Контент 7 статьям (ТОЛЬКО если в шаге C тело неполное). Тела в репо `wp-port/article-bodies/<slug>.html`:
```sh
RB=https://raw.githubusercontent.com/elijah3737/kd-grupp-blog/main/wp-port/article-bodies
# пример для одной (повторить с нужным ID↔файл):
curl -fsSL $RB/podgotovka-kombajna-k-sezonu.html -o /tmp/b.html && WP post update 184945 --post_content="$(cat /tmp/b.html)"
# 184947=holmer-terra-dos-t3-obsluzhivanie 184949=prutkovye-elevatory-iznos-zamena
# 184951=transporternye-lenty-medo 184953=vosstanovlenie-uzlov-vs-novye
# 184955=zapchasti-double-l 184957=zapchasti-spudnik
```
Пост-кейс ROPA (если в C не нашёлся). ⚠️ Контент прототипный (вымышленные детали) — СНАЧАЛА согласовать с клиентом факты:
```sh
curl -fsSL $RB/article.html -o /tmp/ropa.html
NEW=$(WP post create --post_type=post --post_status=publish --post_title="Как мы вернули в строй очистительный вал ROPA за 6 дней" --post_name="vosstanovlenie-vala-ropa-6-dnej" --post_content="$(cat /tmp/ropa.html)" --porcelain)
WP post term set $NEW category keysy --by=slug
WP media import $HOME/kdcov/cover-remont.webp --post_id=$NEW --featured_image
```

## G. Тест → переключение
```sh
# 1) Точечный тест single ДО массового показа: открыть в браузере любой блоговый пост,
#    проверить: article-head, hero, prose, CTA, «Читайте также», РОВНО один <h1>.
# 2) Лента: временная превью-страница
PREV=$(WP post create --post_type=page --post_status=publish --post_title="blog-preview" --post_name=blog-preview --porcelain)
WP post meta update $PREV _wp_page_template page-blog.php
#    открыть /blog-preview/ — проверить hero+чипы+featured+28 карточек, фильтры.
# 3) Переключить страницу 160 (/news/) на ленту:
WP post get 160 --field=content > $HOME/backup-page160-content.html   # бэкап us_grid
WP post update 160 --post_title="Блог" --post_content=""              # убрать us_grid
WP post meta update 160 _wp_page_template page-blog.php
WP post delete $PREV --force                                          # убрать превью
```
В админке (Impreza, проще через UI):
- Страница «Блог» (160): Layout → Full Width, без сайдбара.
- Theme Options → Blog → Single: минимальный layout (без заголовка/featured/мета) — чтобы не было двойного H1/hero. Если нельзя полностью — добавить в kd-blog.css: `.single-post .w-post-elm.post_title,.single-post .w-post-elm.post_featured_image{display:none}` (проверить точные классы).
- Меню: пункт «Новости» → переименовать в «Блог» (URL /news/ оставить).
```sh
WP menu list
WP menu item list <menu-name-or-id>            # найти db_id пункта «Новости»
WP menu item update <db_id> --title="Блог"     # URL не меняем
```

## H. Кэш
```sh
WP eval 'if (class_exists("WPO_Page_Cache")) WPO_Page_Cache::instance()->purge();'
```

## I. Проверка (E2E)
- [ ] /news/ грузится <2 сек (без зависания us_grid); hero+чипы+featured+28 карточек.
- [ ] Фильтры по 4 рубрикам + «Все материалы».
- [ ] Single (по одному из каждой рубрики + один без обложки): article-head, hero/мягко-без-hero, prose, CTA, related(3); РОВНО один `<h1>`; дата по-русски; «Чтение ~N мин».
- [ ] Карточки ведут на /%postname%/; счётчики рубрик 18/3/4/3.
- [ ] Магазин/товар/главная не изменились; на странице магазина нет запроса kd-blog.css/Manrope.
- [ ] Меню: «Новости»→«Блог» (адрес /news/). Активны mu: kd-fix-wc-image-size, kd-banner-webp, kd-perf, kd-yoast-fix, kd-blog.
- [ ] Проверено разлогиненным после purge.

## Откат
```sh
mv wp-content/mu-plugins/kd-blog.php wp-content/mu-plugins/kd-blog.php.off   # single → обычный Impreza
WP post update 160 --post_title="Новости" --post_content="$(cat $HOME/backup-page160-content.html)"
WP post meta delete 160 _wp_page_template
WP menu item update <db_id> --title="Новости"
# рубрики — реверс по таблице из шага C; при крахе: WP db import $HOME/backup-kd-*.sql
WP eval 'if (class_exists("WPO_Page_Cache")) WPO_Page_Cache::instance()->purge();'
```
