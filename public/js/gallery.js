/* Galeri karya — lightbox dengan navigasi keyboard */
(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    var box = document.querySelector('[data-lightbox]');
    if (!box) return;
    var items = Array.prototype.slice.call(document.querySelectorAll('[data-gallery-item]'));
    var img = box.querySelector('[data-lightbox-img]'), title = box.querySelector('[data-lightbox-title]');
    var edit = box.querySelector('[data-lightbox-edit]'), down = box.querySelector('[data-lightbox-download]');
    var idx = 0, opener = null;

    function show(i) {
      idx = (i + items.length) % items.length;
      var it = items[idx];
      img.src = it.dataset.src; img.alt = it.dataset.title; title.textContent = it.dataset.title;
      edit.href = it.dataset.edit; down.href = it.dataset.download;
    }
    function open(i, from) { opener = from; show(i); box.hidden = false; document.body.classList.add('no-scroll'); box.querySelector('[data-lightbox-close]').focus(); }
    function close() { box.hidden = true; document.body.classList.remove('no-scroll'); if (opener) opener.focus(); }

    items.forEach(function (it, i) { it.querySelector('[data-gallery-open]').addEventListener('click', function (e) { open(i, e.currentTarget); }); });
    box.querySelector('[data-lightbox-close]').addEventListener('click', close);
    box.querySelector('[data-lightbox-prev]').addEventListener('click', function () { show(idx - 1); });
    box.querySelector('[data-lightbox-next]').addEventListener('click', function () { show(idx + 1); });
    box.addEventListener('click', function (e) { if (e.target === box) close(); });
    document.addEventListener('keydown', function (e) {
      if (box.hidden) return;
      if (e.key === 'Escape') close();
      else if (e.key === 'ArrowLeft') show(idx - 1);
      else if (e.key === 'ArrowRight') show(idx + 1);
    });
  });
})();
