/* EventHub — event listing row renderer (used on home and events pages) */
window.EH_eventRow = function (e) {
  var d = new Date(e.date + 'T12:00:00');
  var day = d.getDate(), mon = d.toLocaleString('en-GB', { month: 'short' }), wd = d.toLocaleString('en-GB', { weekday: 'long' });
  return '<article class="ev">' +
    '<div class="ev-date"><b>' + day + '</b><span>' + mon + ' ' + d.getFullYear() + '</span></div>' +
    '<div class="ev-img"><img loading="lazy" src="' + e.img + '" alt=""></div>' +
    '<div><span class="tag">' + e.kind + '</span><h3 style="margin-top:8px">' + EH.esc(e.title) + '</h3><p>' + EH.esc(e.blurb) + '</p>' +
    '<div class="ev-meta"><i class="fa-solid fa-location-dot"></i> ' + EH.esc(e.venue) + ', ' + e.city + ' · ' + wd + ' · ' + (e.price ? EH.kwacha(e.price) + ' entry' : 'Free entry') + '</div></div>' +
    '<a class="btn btn-primary btn-sm" href="events.html#' + e.id + '" data-rsvp="' + e.id + '">' + (e.price ? 'Get tickets' : 'Reserve a spot') + '</a>' +
    '</article>';
};
