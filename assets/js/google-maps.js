/* ==========================================================================
   EventHub — Google Maps / Places helper layer
   --------------------------------------------------------------------------
   Loads the Maps JavaScript API (with the Places library) on demand and
   exposes small helpers used across the site:

     EH_GMAPS.ready                                -> Promise, resolves when google.maps is usable
     EH_GMAPS.attachAutocomplete(input, onPlace)    -> address autocomplete
     EH_GMAPS.createPinMap(div, opts)               -> draggable-pin picker (vendor registration)
     EH_GMAPS.renderStaticMap(div, lat, lng, title) -> single-marker display map
     EH_GMAPS.searchNearbyVendors(lat, lng, cb)     -> Google Places nearby search
                                                        for the "Discovered vendors" feature

   Requires assets/js/google-config.js to be loaded first.
   ========================================================================== */

(function () {
  var LUSAKA = { lat: -15.3875, lng: 28.3228 };
  var EVENT_KEYWORDS = ['wedding venue', 'event photographer', 'caterer', 'event decorator'];

  var readyResolve, readyReject;
  var ready = new Promise(function (res, rej) { readyResolve = res; readyReject = rej; });
  // Every helper below attaches its own .catch() to `ready`, but if a page
  // never happens to call any of them (e.g. vendors.html without a "Near
  // Me"/city search this visit), a rejected `ready` would otherwise be an
  // unhandled promise rejection. This no-op catch keeps it always handled.
  ready.catch(function () {});

  function loadScript() {
    var key = window.EH_GOOGLE_MAPS_API_KEY;
    if (!key || key === 'YOUR_GOOGLE_MAPS_API_KEY') {
      console.warn('EventHub Google Maps: assets/js/google-config.js still has a placeholder API key. ' +
        'Maps, address autocomplete and Discovered vendors are disabled until you fill it in (see GOOGLE_SETUP.md).');
      readyReject(new Error('Google Maps API key not configured.'));
      return;
    }
    window.__ehGoogleMapsCallback = function () { readyResolve(window.google); };
    var script = document.createElement('script');
    script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(key) +
      '&libraries=places&callback=__ehGoogleMapsCallback&loading=async';
    script.async = true;
    script.onerror = function () { readyReject(new Error('Failed to load Google Maps script.')); };
    document.head.appendChild(script);
  }
  loadScript();

  function attachAutocomplete(input, onPlace) {
    ready.then(function (google) {
      var autocomplete = new google.maps.places.Autocomplete(input, {
        componentRestrictions: { country: 'zm' },
        fields: ['formatted_address', 'geometry', 'address_components', 'name']
      });
      autocomplete.addListener('place_changed', function () {
        var place = autocomplete.getPlace();
        if (!place.geometry) return;
        var city = '', area = '';
        (place.address_components || []).forEach(function (c) {
          if (c.types.indexOf('locality') > -1) city = c.long_name;
          if (!area && (c.types.indexOf('sublocality') > -1 || c.types.indexOf('neighborhood') > -1)) area = c.long_name;
        });
        onPlace({
          address: place.formatted_address || '',
          city: city,
          area: area,
          lat: place.geometry.location.lat(),
          lng: place.geometry.location.lng()
        });
      });
    }).catch(function () { /* address field still works as a plain text input */ });
  }

  function createPinMap(div, opts) {
    opts = opts || {};
    return ready.then(function (google) {
      var start = (opts.lat && opts.lng) ? { lat: opts.lat, lng: opts.lng } : LUSAKA;
      var map = new google.maps.Map(div, { center: start, zoom: 13 });
      var marker = new google.maps.Marker({ position: start, map: map, draggable: true });

      function emit() {
        var pos = marker.getPosition();
        if (opts.onChange) opts.onChange(pos.lat(), pos.lng());
      }
      marker.addListener('dragend', emit);
      map.addListener('click', function (e) {
        marker.setPosition(e.latLng);
        emit();
      });
      return {
        setPosition: function (lat, lng) {
          var pos = { lat: lat, lng: lng };
          marker.setPosition(pos);
          map.panTo(pos);
        }
      };
    });
  }

  function renderStaticMap(div, lat, lng, title) {
    return ready.then(function (google) {
      var pos = (lat && lng) ? { lat: lat, lng: lng } : LUSAKA;
      var map = new google.maps.Map(div, { center: pos, zoom: lat && lng ? 15 : 12 });
      new google.maps.Marker({ position: pos, map: map, title: title || '' });
    });
  }

  function searchNearbyVendors(lat, lng, cb) {
    var cacheKey = 'eh_discovered_' + lat.toFixed(2) + '_' + lng.toFixed(2);
    try {
      var cached = sessionStorage.getItem(cacheKey);
      if (cached) { cb(JSON.parse(cached)); return; }
    } catch (e) {}

    ready.then(function (google) {
      var service = new google.maps.places.PlacesService(document.createElement('div'));
      var seen = {};
      var results = [];
      var pending = EVENT_KEYWORDS.length;

      EVENT_KEYWORDS.forEach(function (keyword) {
        service.nearbySearch({
          location: { lat: lat, lng: lng },
          radius: 8000,
          keyword: keyword
        }, function (places, status) {
          if (status === google.maps.places.PlacesServiceStatus.OK && places) {
            places.forEach(function (p) {
              if (seen[p.place_id] || results.length >= 12) return;
              seen[p.place_id] = true;
              results.push({
                place_id: p.place_id,
                name: p.name,
                vicinity: p.vicinity,
                rating: p.rating || null,
                user_ratings_total: p.user_ratings_total || 0,
                photoUrl: (p.photos && p.photos[0]) ? p.photos[0].getUrl({ maxWidth: 800 }) : null
              });
            });
          }
          pending--;
          if (pending === 0) {
            try { sessionStorage.setItem(cacheKey, JSON.stringify(results)); } catch (e) {}
            cb(results);
          }
        });
      });
    }).catch(function () { cb([]); });
  }

  window.EH_GMAPS = {
    ready: ready,
    attachAutocomplete: attachAutocomplete,
    createPinMap: createPinMap,
    renderStaticMap: renderStaticMap,
    searchNearbyVendors: searchNearbyVendors,
    DEFAULT_CENTER: LUSAKA
  };
})();
