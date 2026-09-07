/* ==========================================================================
   EventHub — Firebase data layer (Auth + Firestore)
   --------------------------------------------------------------------------
   Runs alongside the existing Laravel API (assets/js/api.js) rather than
   replacing it. This file stores/reads real vendors and customers in
   Firestore and authenticates them with Firebase Auth, so the admin
   dashboard can show real registered counts.

   Collections:
     customers/{uid}  { name, phone, email, role:'customer', createdAt }
     vendors/{uid}     { businessName, ownerName, email, phone, whatsapp,
                          category, description, city, area, address,
                          priceFrom, priceUnit, facebookUrl, instagramUrl,
                          websiteUrl, status:'pending'|'approved'|'rejected',
                          role:'vendor', createdAt }
     admins/{uid}      { email }  — presence of this doc grants admin role.
                          There is no sign-up UI for this: create the doc
                          by hand in the Firestore console for your own uid
                          after registering once (see FIREBASE_SETUP.md).

   Requires firebase-config.js and the Firebase compat SDKs to be loaded
   first (see the <script> order in each page).
   ========================================================================== */

(function () {
  if (typeof firebase === 'undefined') {
    console.error('EventHub Firebase: SDK not loaded (check the <script> tags order).');
    return;
  }
  if (!window.EH_FIREBASE_CONFIG) {
    console.error('EventHub Firebase: firebase-config.js not loaded.');
    return;
  }
  if (window.EH_FIREBASE_CONFIG.apiKey === 'YOUR_API_KEY') {
    console.warn('EventHub Firebase: assets/js/firebase-config.js still has placeholder values. ' +
      'Fill it in with your Firebase project config (see FIREBASE_SETUP.md) before using registration, login or the admin dashboard.');
  }

  firebase.initializeApp(window.EH_FIREBASE_CONFIG);
  var auth = firebase.auth();
  var db = firebase.firestore();

  function friendlyError(err) {
    var map = {
      'auth/email-already-in-use': 'An account with that email already exists.',
      'auth/invalid-email': 'That email address looks invalid.',
      'auth/weak-password': 'Password must be at least 6 characters.',
      'auth/user-not-found': 'No account found with that email.',
      'auth/wrong-password': 'Incorrect password.',
      'auth/invalid-credential': 'Incorrect email or password.',
      'auth/too-many-requests': 'Too many attempts. Please wait a moment and try again.',
      'auth/network-request-failed': 'Network error — check your connection and that firebase-config.js is filled in.'
    };
    return new Error((err && map[err.code]) || (err && err.message) || 'Something went wrong.');
  }

  function serialize(doc) {
    var data = doc.data();
    data.id = doc.id;
    return data;
  }

  var EH_FB = {
    auth: auth,
    db: db,

    /** Fires immediately with the current user (or null), then on every change. */
    onAuthChange: function (cb) { return auth.onAuthStateChanged(cb); },

    logout: function () { return auth.signOut(); },

    /** @returns {Promise<{uid, profile}>} */
    registerCustomer: function (data) {
      return auth.createUserWithEmailAndPassword(data.email, data.password).then(function (cred) {
        var profile = {
          name: data.name,
          phone: data.phone,
          email: data.email,
          role: 'customer',
          createdAt: firebase.firestore.FieldValue.serverTimestamp()
        };
        return db.collection('customers').doc(cred.user.uid).set(profile)
          .then(function () { return cred.user.updateProfile({ displayName: data.name }); })
          .then(function () { return { uid: cred.user.uid, profile: profile }; });
      }).catch(function (err) { throw friendlyError(err); });
    },

    /** @returns {Promise<{uid, profile}>} */
    registerVendor: function (data) {
      return auth.createUserWithEmailAndPassword(data.email, data.password).then(function (cred) {
        var profile = {
          businessName: data.business_name,
          ownerName: data.owner_name,
          email: data.email,
          phone: data.phone,
          whatsapp: data.whatsapp || null,
          category: data.category || null,
          description: data.description || null,
          city: data.city || null,
          area: data.area || null,
          address: data.address || null,
          priceFrom: data.price_from ? Number(data.price_from) : null,
          priceUnit: data.price_unit || null,
          facebookUrl: data.facebook_url || null,
          instagramUrl: data.instagram_url || null,
          websiteUrl: data.website_url || null,
          status: 'pending',
          role: 'vendor',
          createdAt: firebase.firestore.FieldValue.serverTimestamp()
        };
        return db.collection('vendors').doc(cred.user.uid).set(profile)
          .then(function () { return cred.user.updateProfile({ displayName: data.business_name }); })
          .then(function () { return { uid: cred.user.uid, profile: profile }; });
      }).catch(function (err) { throw friendlyError(err); });
    },

    /** @returns {Promise<{uid, email, role}>} role is 'admin'|'vendor'|'customer'|null */
    login: function (email, password) {
      return auth.signInWithEmailAndPassword(email, password).then(function (cred) {
        return EH_FB.resolveRole(cred.user.uid).then(function (role) {
          return { uid: cred.user.uid, email: cred.user.email, role: role };
        });
      }).catch(function (err) { throw friendlyError(err); });
    },

    /** Looks up which collection a uid belongs to. admins is checked first. */
    resolveRole: function (uid) {
      return db.collection('admins').doc(uid).get().then(function (snap) {
        if (snap.exists) return 'admin';
        return db.collection('vendors').doc(uid).get().then(function (vSnap) {
          if (vSnap.exists) return 'vendor';
          return db.collection('customers').doc(uid).get().then(function (cSnap) {
            return cSnap.exists ? 'customer' : null;
          });
        });
      });
    },

    getVendorProfile: function (uid) {
      return db.collection('vendors').doc(uid).get().then(function (snap) { return snap.exists ? serialize(snap) : null; });
    },

    getCustomerProfile: function (uid) {
      return db.collection('customers').doc(uid).get().then(function (snap) { return snap.exists ? serialize(snap) : null; });
    },

    /** Platform-wide counts for the admin dashboard KPI cards. */
    getPlatformStats: function () {
      return Promise.all([
        db.collection('customers').get(),
        db.collection('vendors').get()
      ]).then(function (results) {
        var customers = results[0].docs;
        var vendors = results[1].docs.map(serialize);
        return {
          totalCustomers: customers.length,
          totalVendors: vendors.length,
          pendingVendors: vendors.filter(function (v) { return v.status === 'pending'; }).length,
          approvedVendors: vendors.filter(function (v) { return v.status === 'approved'; }).length,
          rejectedVendors: vendors.filter(function (v) { return v.status === 'rejected'; }).length
        };
      });
    },

    /** @param {string} [statusFilter] 'pending'|'approved'|'rejected' */
    listVendors: function (statusFilter) {
      var ref = db.collection('vendors');
      if (statusFilter) ref = ref.where('status', '==', statusFilter);
      return ref.get().then(function (snap) {
        return snap.docs.map(serialize).sort(function (a, b) {
          var at = a.createdAt ? a.createdAt.toMillis() : 0;
          var bt = b.createdAt ? b.createdAt.toMillis() : 0;
          return bt - at;
        });
      });
    },

    listCustomers: function () {
      return db.collection('customers').get().then(function (snap) {
        return snap.docs.map(serialize).sort(function (a, b) {
          var at = a.createdAt ? a.createdAt.toMillis() : 0;
          var bt = b.createdAt ? b.createdAt.toMillis() : 0;
          return bt - at;
        });
      });
    },

    /** Stores a quote request under vendors/{uid}/enquiries/{auto-id}. */
    submitEnquiry: function (vendorUid, data) {
      return db.collection('vendors').doc(vendorUid).collection('enquiries').add({
        guestName: data.guest_name,
        guestPhone: data.guest_phone,
        eventDate: data.event_date || null,
        guestCount: data.guest_count ? Number(data.guest_count) : null,
        message: data.message || null,
        createdAt: firebase.firestore.FieldValue.serverTimestamp()
      }).catch(function (err) { throw friendlyError(err); });
    },

    updateVendorStatus: function (uid, status) {
      return db.collection('vendors').doc(uid).update({ status: status });
    },

    deleteVendor: function (uid) {
      return db.collection('vendors').doc(uid).delete();
    },

    deleteCustomer: function (uid) {
      return db.collection('customers').doc(uid).delete();
    }
  };

  window.EH_FB = EH_FB;
  document.dispatchEvent(new Event('eh-fb-ready'));
})();
