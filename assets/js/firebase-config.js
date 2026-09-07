/* ==========================================================================
   EventHub — Firebase project configuration
   --------------------------------------------------------------------------
   Fill this in with YOUR OWN Firebase project's values.

   Where to find them:
     1. Go to https://console.firebase.google.com and open your project
        (or create one — see FIREBASE_SETUP.md in the project root).
     2. Click the gear icon -> "Project settings".
     3. Under "Your apps", add a Web app (</> icon) if you haven't already.
     4. Copy the firebaseConfig object it shows you and paste the values below.

   These values are safe to commit / ship to the browser — they identify
   your project, they are not secret keys. Access is controlled separately
   by Firestore Security Rules (see firestore.rules) and Firebase Auth.
   ========================================================================== */

window.EH_FIREBASE_CONFIG = {
  apiKey: "YOUR_API_KEY",
  authDomain: "YOUR_PROJECT_ID.firebaseapp.com",
  projectId: "YOUR_PROJECT_ID",
  storageBucket: "YOUR_PROJECT_ID.appspot.com",
  messagingSenderId: "YOUR_SENDER_ID",
  appId: "YOUR_APP_ID"
};
