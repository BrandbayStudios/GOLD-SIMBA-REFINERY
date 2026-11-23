// Google reviews: the studio's review link, and its real rating and latest reviews from Google.
// Rating and reviews come from the Places API when GOOGLE_PLACES_API_KEY is set; otherwise from
// the figures the studio copies from its Google profile into Settings. Nothing is shown until set.
const lib = require('./lib');

const REFRESH_MS = 12 * 60 * 60 * 1000;

function reviewUrl(settings = lib.getSettings()) {
  if (settings.google_review_url) return settings.google_review_url;
  if (settings.google_place_id) return `https://search.google.com/local/writereview?placeid=${encodeURIComponent(settings.google_place_id)}`;
  return '';
}

function profileUrl(settings = lib.getSettings()) {
  if (settings.google_place_id) return `https://www.google.com/maps/place/?q=place_id:${encodeURIComponent(settings.google_place_id)}`;
  return reviewUrl(settings);
}

// What the site shows: null until there is a real rating to show.
function summary(settings = lib.getSettings()) {
  let reviews = [];
  try { reviews = JSON.parse(settings.google_reviews_json || '[]'); } catch { reviews = []; }
  const rating = Number(settings.google_rating);
  const count = parseInt(settings.google_review_count, 10);
  return {
    reviewUrl: reviewUrl(settings),
    profileUrl: profileUrl(settings),
    rating: rating > 0 && rating <= 5 ? Math.round(rating * 10) / 10 : null,
    count: count > 0 ? count : null,
    reviews,
  };
}

async function refresh() {
  const key = process.env.GOOGLE_PLACES_API_KEY;
  const placeId = lib.getSettings().google_place_id;
  if (!key || !placeId) return false;
  try {
    const res = await fetch(`https://places.googleapis.com/v1/places/${encodeURIComponent(placeId)}`, {
      headers: { 'X-Goog-Api-Key': key, 'X-Goog-FieldMask': 'rating,userRatingCount,reviews' },
    });
    if (!res.ok) throw new Error(`Places API ${res.status}`);
    const place = await res.json();
    if (place.rating) lib.setSetting('google_rating', String(place.rating));
    if (place.userRatingCount) lib.setSetting('google_review_count', String(place.userRatingCount));
    const reviews = (place.reviews || [])
      .filter((r) => r.rating >= 4 && (r.text?.text || r.originalText?.text))
      .slice(0, 3)
      .map((r) => ({
        author: r.authorAttribution?.displayName || 'Google user',
        authorUrl: r.authorAttribution?.uri || '',
        rating: r.rating,
        text: (r.text?.text || r.originalText?.text).slice(0, 600),
        when: r.relativePublishTimeDescription || '',
      }));
    lib.setSetting('google_reviews_json', JSON.stringify(reviews));
    return true;
  } catch (err) {
    console.error('Google reviews refresh failed:', err.message);
    return false;
  }
}

function startAutoRefresh() {
  if (!process.env.GOOGLE_PLACES_API_KEY) return;
  refresh();
  setInterval(refresh, REFRESH_MS).unref();
}

module.exports = { reviewUrl, profileUrl, summary, refresh, startAutoRefresh };
