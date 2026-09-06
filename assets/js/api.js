/* ==========================================================================
   EventHub API client
   Talks to the Laravel backend in /backend. All static pages keep working
   with their bundled demo data if the API is unreachable (progressive
   enhancement) — every call site should try the API and fall back quietly.
   ========================================================================== */

(function () {
  var API_BASE = window.EH_API_BASE || 'http://localhost:8000/api/v1';
  var API_ROOT = API_BASE.replace(/\/v1\/?$/, '');

  function getToken() { return localStorage.getItem('eh_token'); }
  function setToken(t) { t ? localStorage.setItem('eh_token', t) : localStorage.removeItem('eh_token'); }
  function getUser() {
    try { return JSON.parse(localStorage.getItem('eh_user') || 'null'); } catch (e) { return null; }
  }
  function setUser(u) { u ? localStorage.setItem('eh_user', JSON.stringify(u)) : localStorage.removeItem('eh_user'); }
  function getVendorProfile() {
    try { return JSON.parse(localStorage.getItem('eh_vendor_profile') || 'null'); } catch (e) { return null; }
  }
  function setVendorProfile(v) { v ? localStorage.setItem('eh_vendor_profile', JSON.stringify(v)) : localStorage.removeItem('eh_vendor_profile'); }
  function isLoggedIn() { return !!getToken(); }
  function logout() { setToken(null); setUser(null); setVendorProfile(null); }

  function apiFetch(path, opts) {
    opts = opts || {};
    var headers = Object.assign({ Accept: 'application/json' }, opts.headers || {});
    var body = opts.body;
    if (body && !(body instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
      body = JSON.stringify(body);
    }
    var token = getToken();
    if (token) headers['Authorization'] = 'Bearer ' + token;

    return fetch(API_BASE + path, Object.assign({}, opts, { headers: headers, body: body }))
      .then(function (res) {
        return res.json().catch(function () { return null; }).then(function (data) {
          if (!res.ok) {
            var err = new Error((data && (data.message || (data.errors && JSON.stringify(data.errors)))) || 'Request failed');
            err.status = res.status;
            err.data = data;
            throw err;
          }
          return data;
        });
      });
  }

  window.EH_API = {
    BASE: API_BASE,
    getToken: getToken, setToken: setToken,
    getUser: getUser, setUser: setUser,
    getVendorProfile: getVendorProfile, setVendorProfile: setVendorProfile,
    isLoggedIn: isLoggedIn, logout: logout,
    fetch: apiFetch,

    ping: function () {
      return fetch(API_ROOT + '/ping').then(function (res) { return res.json(); });
    },
    categories: function () { return apiFetch('/categories'); },
    locations: function () { return apiFetch('/locations'); },
    vendors: function (params) {
      var qs = new URLSearchParams(params || {}).toString();
      return apiFetch('/vendors' + (qs ? '?' + qs : ''));
    },
    vendor: function (slugOrId) { return apiFetch('/vendors/' + encodeURIComponent(slugOrId)); },
    vendorReviews: function (slugOrId) { return apiFetch('/vendors/' + encodeURIComponent(slugOrId) + '/reviews'); },
    submitReview: function (slugOrId, payload) {
      return apiFetch('/vendors/' + encodeURIComponent(slugOrId) + '/reviews', { method: 'POST', body: payload });
    },
    submitEnquiry: function (slugOrId, payload) {
      return apiFetch('/vendors/' + encodeURIComponent(slugOrId) + '/enquiries', { method: 'POST', body: payload });
    },
    toggleFavourite: function (slugOrId) {
      return apiFetch('/vendors/' + encodeURIComponent(slugOrId) + '/favourite', { method: 'POST' });
    },
    myFavourites: function () { return apiFetch('/favourites'); },

    login: function (email, password) { return apiFetch('/auth/login', { method: 'POST', body: { email: email, password: password } }); },
    registerCustomer: function (payload) { return apiFetch('/auth/register', { method: 'POST', body: payload }); },
    registerVendor: function (payload) { return apiFetch('/auth/register-vendor', { method: 'POST', body: payload }); },
    me: function () { return apiFetch('/auth/me'); },
    logoutApi: function () { return apiFetch('/auth/logout', { method: 'POST' }); },

    vendorStats: function () { return apiFetch('/vendor/stats'); },
    vendorEnquiries: function (params) {
      var qs = new URLSearchParams(params || {}).toString();
      return apiFetch('/vendor/enquiries' + (qs ? '?' + qs : ''));
    },
    vendorUpdateEnquiryStatus: function (id, status) {
      return apiFetch('/vendor/enquiries/' + id, { method: 'PATCH', body: { status: status } });
    },
    vendorBookings: function () { return apiFetch('/vendor/bookings'); },
    vendorUpdateProfile: function (payload) { return apiFetch('/vendor/profile', { method: 'PUT', body: payload }); },

    adminStats: function () { return apiFetch('/admin/stats'); },
    adminVendors: function (params) {
      var qs = new URLSearchParams(params || {}).toString();
      return apiFetch('/admin/vendors' + (qs ? '?' + qs : ''));
    },
    adminUpdateVendorStatus: function (id, status) {
      return apiFetch('/admin/vendors/' + id + '/status', { method: 'PATCH', body: { status: status } });
    },
    adminCustomers: function () { return apiFetch('/admin/customers'); },
    adminClaims: function (params) {
      var qs = new URLSearchParams(params || {}).toString();
      return apiFetch('/admin/claims' + (qs ? '?' + qs : ''));
    },
    adminUpdateClaimStatus: function (id, status) {
      return apiFetch('/admin/claims/' + id, { method: 'PATCH', body: { status: status } });
    },
    adminReviews: function () { return apiFetch('/admin/reviews'); },
    adminDeleteReview: function (id) { return apiFetch('/admin/reviews/' + id, { method: 'DELETE' }); },
    adminEnquiries: function () { return apiFetch('/admin/enquiries'); },
    adminBookings: function () { return apiFetch('/admin/bookings'); },
    adminReports: function () { return apiFetch('/admin/reports'); },
  };
})();
