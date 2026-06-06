/* КД-Групп блог — фильтр ленты по категориям (чипы).
   Скоуп: страница ленты (/news/). Шапку/бургер не трогаем — это тема. */
(function () {
  var chips = document.querySelectorAll('.kd-blog .chip');
  if (!chips.length) return;
  chips.forEach(function (c) {
    c.addEventListener('click', function () {
      var active = document.querySelector('.kd-blog .chip.active');
      if (active) active.classList.remove('active');
      c.classList.add('active');
      var f = c.dataset.filter, shown = 0;
      document.querySelectorAll('.kd-blog [data-tags]').forEach(function (el) {
        var ok = f === 'all' || el.dataset.tags.split(' ').indexOf(f) !== -1;
        el.classList.toggle('is-hidden', !ok);
        if (ok) shown++;
      });
      var note = document.querySelector('.kd-blog .empty-note');
      if (note) note.hidden = shown > 0;
    });
  });
})();
