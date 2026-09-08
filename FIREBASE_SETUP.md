# Firebase setup for EventHub Zambia

This connects real vendor/customer sign-ups and the admin dashboard to a
Firebase project. This runs **alongside** the existing Laravel API in
`backend/` — it doesn't replace it. Nothing here requires a server: it's
all client-side (Firebase Auth + Firestore), so it works on the same
static hosting (e.g. GitHub Pages) the frontend already uses.

## 1. Create the Firebase project

1. Go to <https://console.firebase.google.com> and sign in with your
   Google account.
2. Click **Add project**, give it a name (e.g. `eventhub-zambia`), and
   finish the wizard (Google Analytics is optional — skip it if unsure).

## 2. Register a Web app

1. In your new project, click the **gear icon -> Project settings**.
2. Under **Your apps**, click the **</>** (Web) icon.
3. Give it a nickname (e.g. `eventhub-web`) and click **Register app**.
   You do **not** need Firebase Hosting for this.
4. Firebase shows you a `firebaseConfig` object like:
   ```js
   const firebaseConfig = {
     apiKey: "AIza...",
     authDomain: "eventhub-zambia.firebaseapp.com",
     projectId: "eventhub-zambia",
     storageBucket: "eventhub-zambia.appspot.com",
     messagingSenderId: "123456789",
     appId: "1:123456789:web:abc123"
   };
   ```
   Copy these six values into **`assets/js/firebase-config.js`** in this
   project, replacing the `YOUR_...` placeholders. These values are safe
   to commit — they identify your project, they are not secret keys.

## 3. Turn on Authentication

1. In the left sidebar, click **Build -> Authentication -> Get started**.
2. Under **Sign-in method**, enable **Email/Password**.
3. While you're here, also enable **Facebook** (needed for the Facebook
   login button — see the Meta section below) if you want that live now.
   You can always come back to this after step 8.

## 4. Create the Firestore database

1. Click **Build -> Firestore Database -> Create database**.
2. Choose **Start in production mode** (we'll paste our own rules next).
3. Pick a location close to your users (e.g. an EU or `africa-south1`
   region if offered) and click **Enable**.

## 5. Publish the Firestore security rules

1. Still in Firestore, click the **Rules** tab.
2. Delete the default contents and paste in everything from
   **`firestore.rules`** in this project.
3. Click **Publish**.

Without this step, Firestore's default rules block all reads/writes and
nothing in the app will work.

## 6. Enable Storage (for vendor photo uploads)

1. Click **Build -> Storage -> Get started**, keep the default location,
   and click **Done**.
2. Click the **Rules** tab and paste in everything from
   **`storage.rules`** in this project, then **Publish**.

Without this, vendor logo/portfolio uploads on `vendor-register.html`
will fail (the account still gets created — only the photo step is
skipped, with a message logged to the console).

## 7. Set up Facebook login (Meta)

The site uses Firebase Auth's built-in Facebook provider, so there's no
Meta SDK to load yourself — just a Meta app to register:

1. Go to <https://developers.facebook.com/apps> and click **Create App**.
   Choose the **Consumer** (or **None/Other**) use case when asked.
2. In your new app, add the **Facebook Login** product.
3. Under **Facebook Login -> Settings**, add this to **Valid OAuth
   Redirect URIs** (replace with your actual Firebase project id):
   `https://YOUR_PROJECT_ID.firebaseapp.com/__/auth/handler`
4. Under **App Settings -> Basic**, copy the **App ID** and **App Secret**.
5. Back in the Firebase console: **Authentication -> Sign-in method ->
   Facebook**, toggle it **on**, paste in the App ID and App Secret, and
   copy the **OAuth redirect URI** Firebase shows you (it should match
   what you added in step 3) — click **Save**.
6. While your Meta app is in **Development mode**, only accounts you've
   added as testers/admins on the Meta app can log in with it. Submit
   the app for **App Review** (requesting the `email` and `public_profile`
   permissions — these usually don't need review) and switch it to
   **Live** to let real customers use it.

There's no separate config file to edit for this — the App ID/Secret
live in the Firebase console, not in this repo.

## 8. Make yourself an admin

There's no sign-up form for admins (on purpose — anyone could otherwise
register themselves as one). Instead:

1. Open `register.html` in your browser and create a normal account
   (any name/email/password) — or use `vendor-register.html` — just to
   get a Firebase Auth user created.
2. In the Firebase console, go to **Build -> Authentication -> Users**
   and copy the **User UID** of the account you just created.
3. Go to **Build -> Firestore Database -> Data**, click **Start collection**,
   name it `admins`, and add one document:
   - **Document ID**: paste the UID you copied
   - Add a field: `email` (string) = your email address
4. Click **Save**. That account can now log in at `login.html` and will
   be redirected to `admin-dashboard.html` with real data instead of the
   demo banner.

## 9. Try it

- `register.html` → creates a real customer in Firestore (`customers`
  collection) and signs them in. The **Facebook** button also works
  once step 7 is done, auto-creating a customer profile on first use.
- `vendor-register.html` → creates a real vendor (`vendors` collection,
  `status: "pending"`) and signs them in. The Location step has real
  address autocomplete and a draggable pin (see `GOOGLE_SETUP.md`), and
  the Portfolio step uploads real photos to Firebase Storage.
- `login.html` → signs in (email/password or Facebook) and redirects
  based on role (admin / vendor / customer).
- `admin-dashboard.html` → once signed in as the admin account from step
  8, the **Dashboard**, **Vendor Approvals**, **Vendors** and
  **Customers** tabs all show real, live Firestore data: total counts,
  pending approvals (approve/reject buttons update Firestore directly),
  the full vendor list, and the full customer list.
- `vendors.html` → once a vendor has been approved in the admin
  dashboard, they appear on the public listing (tagged **New**,
  prepended above the bundled demo cards) whenever the Laravel API
  isn't reachable. Click **View** on one to open its real profile at
  `vendor-profile.html?fb=<uid>`, with a working **Request Quotation**
  form (stored in Firestore under that vendor) and their real uploaded
  photos/logo/map pin if they added any.
- `vendors.html` also shows real **Discovered** businesses from Google
  Places when you use **Near Me** or filter by city — see
  `GOOGLE_SETUP.md` for that piece specifically.

## What this does *not* cover yet

- If your Laravel API *is* running and reachable, it takes priority on
  `vendors.html` and the Firebase vendors won't be shown alongside it —
  the two data sources aren't merged.
- `vendor-dashboard.html` still shows demo data rather than the signed-in
  vendor's own Firestore profile, so a vendor can't yet see the
  enquiries submitted through their real profile, or edit their photos
  after the initial registration.
- A Firebase vendor's profile has no packages or reviews (no editor or
  reviews collection built yet) — those sections show an honest "not
  added yet" state instead.
- Bookings and reviews aren't modeled in Firestore at all — those KPI
  cards on the admin dashboard still show static/demo numbers (or live
  Laravel numbers if that backend is running).
- Password reset and email verification are still non-functional
  placeholders. The Google sign-in button is too — only Facebook is
  wired up (see `GOOGLE_SETUP.md`/this file for what each API covers).
- Instagram doesn't have its own "Login with Instagram" like Facebook
  does through Firebase Auth — that's a separate, more involved Meta
  product with its own review process, not built here.

Ask if you'd like any of these built out next.
