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

## 4. Create the Firestore database

1. Click **Build -> Firestore Database -> Create database**.
2. Choose **Start in production mode** (we'll paste our own rules next).
3. Pick a location close to your users (e.g. an EU or `africa-south1`
   region if offered) and click **Enable**.

## 5. Publish the security rules

1. Still in Firestore, click the **Rules** tab.
2. Delete the default contents and paste in everything from
   **`firestore.rules`** in this project.
3. Click **Publish**.

Without this step, Firestore's default rules block all reads/writes and
nothing in the app will work.

## 6. Make yourself an admin

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

## 7. Try it

- `register.html` → creates a real customer in Firestore (`customers`
  collection) and signs them in.
- `vendor-register.html` → creates a real vendor (`vendors` collection,
  `status: "pending"`) and signs them in.
- `login.html` → signs in and redirects based on role (admin / vendor /
  customer).
- `admin-dashboard.html` → once signed in as the admin account from step
  6, the **Dashboard**, **Vendor Approvals**, **Vendors** and
  **Customers** tabs all show real, live Firestore data: total counts,
  pending approvals (approve/reject buttons update Firestore directly),
  the full vendor list, and the full customer list.

## What this does *not* cover yet

- The public listings on `index.html` / `vendors.html` still show the
  bundled sample vendor cards — they are not yet wired to read real
  *approved* vendors from Firestore. That's a reasonable next step once
  you have real vendor data to show.
- `vendor-dashboard.html` still shows demo data rather than the signed-in
  vendor's own Firestore profile.
- Enquiries, bookings and reviews are not modeled in Firestore — those
  KPI cards on the admin dashboard still show static/demo numbers (or
  live Laravel numbers if that backend is running).
- Password reset, email verification, and Google/Facebook sign-in
  buttons are still non-functional placeholders.

Ask if you'd like any of these built out next.
