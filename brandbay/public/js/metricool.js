// Metricool visitor tracking. Kept in its own file because the site's security policy blocks inline scripts.
(function () {
  var head = document.getElementsByTagName('head')[0];
  var script = document.createElement('script');
  script.src = 'https://tracker.metricool.com/resources/be.js';
  script.onload = function () {
    if (window.beTracker) window.beTracker.t({ hash: 'a7a76f0071c29d9099021f6dc3848a4a' });
  };
  head.appendChild(script);
})();
