# EventHub Zambia — Android app

A Trusted Web Activity (TWA): an Android app that opens https://events.tiltsolutions.tech
full screen. The website is the app; updating the site updates the app with no new release.

- Package name: `tech.tiltsolutions.eventhub`
- Built with [android-browser-helper](https://github.com/GoogleChrome/android-browser-helper);
  phones without a supporting browser fall back to a WebView.

## Build

Needs JDK 17+ and the Android SDK (platform 36). Put the signing details in
`keystore.properties` (not committed):

```
storeFile=/path/to/eventhub-upload.jks
storePassword=...
keyAlias=eventhub
keyPassword=...
```

Then:

```
./gradlew bundleRelease   # app/build/outputs/bundle/release/app-release.aab  (upload to Play)
./gradlew assembleRelease # app/build/outputs/apk/release/app-release.apk    (install on a phone to test)
```

For each new release, raise `versionCode` (and `versionName`) in `app/build.gradle`.

## Full-screen verification (Digital Asset Links)

The site must serve `/.well-known/assetlinks.json` (in Laravel: `public/.well-known/assetlinks.json`)
listing the SHA-256 fingerprint of every key that signs the app. Without it the app still works but
shows a browser address bar. `store-listing/assetlinks.json` has the upload key's fingerprint. After
enabling Play App Signing, add the "App signing key certificate" SHA-256 from Play Console →
Test and release → App integrity to the same list.
