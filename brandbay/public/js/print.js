// Print button and ?print=1 auto-print for invoice pages.
(function () {
  document.querySelectorAll('[data-print]').forEach((b) => b.addEventListener('click', () => window.print()));
  if (document.body.hasAttribute('data-autoprint')) window.addEventListener('load', () => setTimeout(() => window.print(), 300));
})();
