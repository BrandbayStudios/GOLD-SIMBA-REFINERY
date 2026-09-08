# Google Maps / Places setup for EventHub Zambia

This powers three things on the site, all client-side (no server needed):

- **Interactive maps** on vendor profiles and the contact page.
- **Address autocomplete + a draggable pin** on `vendor-register.html`'s
  Location step, so a vendor's exact coordinates get saved.
- **"Discovered" vendors** on `vendors.html` — real nearby event-service
  businesses pulled from Google Places, shown as unclaimed listings with
  a "Claim This Business" button that pre-fills `vendor-register.html`.

## 1. Create a Google Cloud project (or reuse one)

1. Go to <https://console.cloud.google.com> and create a project (or pick
   an existing one).
2. **Billing must be enabled** on the project — the Maps Platform APIs
   require it, even though Google gives a recurring monthly credit that
   covers moderate usage for free. Go to **Billing** in the left sidebar
   and link a billing account.

## 2. Enable the two APIs this project uses

In **APIs & Services -> Library**, search for and **Enable** each of:

- **Maps JavaScript API**
- **Places API**

## 3. Create and restrict an API key

1. Go to **APIs & Services -> Credentials -> Create Credentials -> API key**.
2. Click the new key to edit its restrictions:
   - **Application restrictions**: HTTP referrers — add your site's
     domain(s), e.g. `https://yourdomain.com/*` (and
     `http://localhost:*/*` while testing locally).
   - **API restrictions**: restrict the key to just **Maps JavaScript
     API** and **Places API**.
3. Copy the key.

## 4. Add the key to the project

Paste it into **`assets/js/google-config.js`**:

```js
window.EH_GOOGLE_MAPS_API_KEY = "YOUR_KEY_HERE";
```

That's it — no other file needs to change. Every page that uses maps
already loads `google-config.js` + `assets/js/google-maps.js`.

## 5. Try it

- `contact.html` and any vendor profile page now show a real interactive
  map instead of a static embed.
- `vendor-register.html`'s Location step: start typing an address and
  pick a suggestion — the pin map recenters automatically, and you can
  still drag the pin to fine-tune it.
- `vendors.html`: use **Near Me**, or pick a city filter and click
  **Apply Filters** — nearby real businesses (wedding venues,
  photographers, caterers, decorators) appear as **Discovered** cards
  with a **Claim This Business** button.

## Notes / limits

- Without a configured key, every one of these features fails
  *gracefully* — maps show a plain "Map unavailable" message, the
  address field still works as a normal text input, and Discovered
  vendors simply don't appear. Nothing crashes.
- The Discovered-vendor search runs a handful of Places "Nearby Search"
  calls per unique location per browser session (results are cached in
  `sessionStorage` to avoid repeating them), capped at 12 results. Watch
  your usage in Cloud Console if this gets heavy traffic.
- The API key is visible in your page source — that's normal and
  expected for browser Maps/Places keys; the HTTP-referrer restriction
  in step 3 is what actually protects it from being used elsewhere.
