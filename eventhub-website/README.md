# EventHub Zambia website — front-end redesign

Files to drop into the Laravel app on events.tiltsolutions.tech (paths are relative to the
site root, i.e. Hostinger's `public_html`). They sit on top of the existing app: `app.css`
and `app.js` are unchanged and still power the modals, filters, quotes and login.

- `resources/views/home.blade.php` — new homepage (emerald & gold, arch photo gallery,
  category ribbon, spotlight tiles, timeline, busy footer, floating quick actions)
- `resources/views/layouts/app.blade.php` — loads the fonts, `theme.css`, `theme.js`,
  the web-app manifest and service worker
- `public/css/theme.css`, `public/js/theme.js` — the new look and interactions
- `public/images/eventhub/` — optimised photos (1920 px and 960 px WebP)
- `public/manifest.webmanifest`, `public/sw.js`, `public/offline.html`, `public/icons/` —
  installable web app
- `public/privacy.html` — privacy policy
- `public/.well-known/assetlinks.json` — links the Android app (`android-app/`) to the site
