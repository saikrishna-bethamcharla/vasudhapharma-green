/*!
 * Lazy decorative video loading for Vasudha Pharma pages.
 *
 * Every background/loop video on the homepage used to autoplay at page load,
 * which pulled ~13 MB before a visitor scrolled anywhere (and far more on mobile
 * data). Videos marked with data-lazy-video now ship with preload="none" and no
 * autoplay: they are fetched and played only when they scroll into view, paused
 * when they leave, and never fetched at all on phones, data-saver or when the
 * visitor prefers reduced motion - the poster frame stays in place instead.
 *
 * The above-the-fold .vp-globe-bg-video keeps its own autoplay (it is ~100 KB);
 * this script only pauses it while it is off-screen.
 */
(function () {
  'use strict';

  var lazyVideos = Array.prototype.slice.call(document.querySelectorAll('video[data-lazy-video]'));
  var bannerVideos = Array.prototype.slice.call(document.querySelectorAll('video.vp-globe-bg-video'));
  if (!lazyVideos.length && !bannerVideos.length) return;

  var conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
  var saveData = !!(conn && (conn.saveData || /(^|-)2g$/.test(conn.effectiveType || '')));
  var smallScreen = window.matchMedia('(max-width: 768px)').matches;
  var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Phones, data-saver and reduced-motion visitors keep the poster: no video bytes.
  var deferred = (saveData || smallScreen || reducedMotion) ? [] : lazyVideos;

  function play(video) {
    if (!video.querySelector('source') && !video.getAttribute('src')) return;
    if (video.preload === 'none') video.preload = 'auto';
    var attempt = video.play();
    if (attempt && typeof attempt.catch === 'function') attempt.catch(function () {});
  }

  if (!('IntersectionObserver' in window)) {
    deferred.forEach(play);
    return;
  }

  var observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        play(entry.target);
      } else if (!entry.target.paused) {
        entry.target.pause();
      }
    });
  }, { rootMargin: '200px 0px', threshold: 0.15 });

  // Lazy videos are observed only when they are allowed to load at all.
  deferred.forEach(function (video) { observer.observe(video); });
  // The banner is already playing; observing it just saves work off-screen.
  bannerVideos.forEach(function (video) { observer.observe(video); });
})();
