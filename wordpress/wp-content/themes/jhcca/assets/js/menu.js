(function () {
  var nav = document.getElementById('topmenu'), btn = document.getElementById('simple-menu');
  if (!nav || !btn) return;
  btn.addEventListener('click', function (e) {
    e.preventDefault();
    var open = nav.classList.toggle('open');
    btn.setAttribute('aria-expanded', open);
  });
  nav.querySelectorAll('.menu-item-has-children > a').forEach(function (a) {
    a.addEventListener('click', function (e) {
      if (window.innerWidth > 900) return;
      var li = a.parentNode;
      if (!li.classList.contains('open')) { e.preventDefault(); li.classList.add('open'); }
    });
  });
})();
