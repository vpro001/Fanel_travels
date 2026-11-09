// Small touches used on every page. Everything still works and reads fine without this file.

var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// ---------- Sections fade in as they scroll into view ----------
var revealItems = document.querySelectorAll('.reveal');
if ('IntersectionObserver' in window && !reduceMotion) {
  var revealer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        revealer.unobserve(entry.target);
      }
    });
  }, { rootMargin: '0px 0px -10% 0px' });
  revealItems.forEach(function (el) { revealer.observe(el); });
  // Safety net: never leave anything hidden if the observer doesn't fire
  setTimeout(function () { revealItems.forEach(function (el) { el.classList.add('is-visible'); }); }, 4000);
} else {
  revealItems.forEach(function (el) { el.classList.add('is-visible'); });
}

// ---------- Poster viewer: tap a flyer to see it large ----------
var viewer = document.getElementById('poster-viewer');
if (viewer && viewer.showModal) {
  document.querySelectorAll('[data-poster]').forEach(function (link) {
    link.addEventListener('click', function (e) {
      e.preventDefault();
      var img = viewer.querySelector('img');
      img.src = link.getAttribute('href');
      img.alt = link.dataset.caption;
      viewer.querySelector('[data-caption]').textContent = link.dataset.caption;
      var button = viewer.querySelector('[data-link]');
      button.href = link.dataset.link;
      button.textContent = link.dataset.linkText || 'Apply now';
      var summary = viewer.querySelector('[data-summary]');
      summary.textContent = link.dataset.summary || '';
      summary.hidden = !link.dataset.summary;
      viewer.showModal();
    });
  });
  // Clicking the dark area around the poster closes it
  viewer.addEventListener('click', function (e) {
    if (e.target === viewer) viewer.close();
  });
}

// ---------- Arrow buttons that scroll a poster row ----------
document.querySelectorAll('[data-scroll]').forEach(function (button) {
  button.addEventListener('click', function () {
    var row = document.querySelector(button.dataset.scroll);
    row.scrollBy({ left: button.dataset.dir * row.clientWidth * 0.8, behavior: reduceMotion ? 'auto' : 'smooth' });
  });
});
