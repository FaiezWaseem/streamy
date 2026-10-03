(() => {
  const pending = new Map();
  function imageFor(node) {
    return node instanceof Element ? node.closest('a[href*="watch.php?id="]')?.querySelector('img[data-gif]') : null;
  }
  function start(img) {
    if (!img?.dataset.gif || pending.has(img) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    img.dataset.static ||= img.getAttribute('src');
    pending.set(img, setTimeout(() => { img.src = img.dataset.gif; }, 220));
  }
  function stop(img) {
    if (!img) return;
    clearTimeout(pending.get(img));
    pending.delete(img);
    if (img.dataset.static) img.src = img.dataset.static;
  }
  document.addEventListener('mouseover', (event) => {
    const img = imageFor(event.target);
    if (img && imageFor(event.relatedTarget) !== img) start(img);
  });
  document.addEventListener('mouseout', (event) => {
    const img = imageFor(event.target);
    if (img && imageFor(event.relatedTarget) !== img) stop(img);
  });
  document.addEventListener('focusin', (event) => start(imageFor(event.target)));
  document.addEventListener('focusout', (event) => stop(imageFor(event.target)));
  document.addEventListener('error', (event) => {
    if (event.target instanceof HTMLImageElement && pending.has(event.target)) stop(event.target);
  }, true);
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) Array.from(pending.keys()).forEach(stop);
  });
})();
