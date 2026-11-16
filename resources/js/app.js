import Alpine from 'alpinejs';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;
const LUSAKA = [-15.4167, 28.2833];

async function postJson(url, body) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(body),
    });
    if (!res.ok) throw new Error(`Request failed (${res.status})`);
    return res.json();
}

async function getJson(url) {
    const res = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
    if (!res.ok) throw new Error(`Request failed (${res.status})`);
    return res.json();
}

function pin(color, label = '') {
    return L.divIcon({ className: '', html: `<div class="hn-marker" style="background:${color}">${label}</div>`, iconSize: [34, 34], iconAnchor: [17, 17] });
}

function baseMap(el, center = LUSAKA, zoom = 12) {
    const map = L.map(el, { scrollWheelZoom: false }).setView(center, zoom);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);
    return map;
}

function geolocate() {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) return reject(new Error('Location is not supported on this device.'));
        navigator.geolocation.getCurrentPosition(
            (p) => resolve({ lat: p.coords.latitude, lng: p.coords.longitude }),
            (e) => reject(new Error(e.code === 1 ? 'Location permission was denied. Drop a pin on the map instead.' : 'Could not get your location. Drop a pin on the map instead.')),
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 },
        );
    });
}

/** Map + "use my location" picker that writes into hidden latitude/longitude inputs. */
Alpine.data('locationPicker', (cfg = {}) => ({
    lat: cfg.lat ?? null,
    lng: cfg.lng ?? null,
    busy: false,
    error: '',
    map: null,
    marker: null,
    init() {
        this.$nextTick(() => {
            this.map = baseMap(this.$refs.map, this.lat ? [this.lat, this.lng] : LUSAKA, this.lat ? 15 : 12);
            if (this.lat) this.place(this.lat, this.lng, false);
            this.map.on('click', (e) => this.place(e.latlng.lat, e.latlng.lng, true));
            if (cfg.auto && !this.lat) this.locate();
        });
    },
    place(lat, lng, reverse) {
        this.lat = +lat.toFixed(7);
        this.lng = +lng.toFixed(7);
        if (this.marker) this.marker.setLatLng([lat, lng]);
        else {
            this.marker = L.marker([lat, lng], { draggable: true, icon: pin('#FF8A24', '●') }).addTo(this.map);
            this.marker.on('dragend', () => { const p = this.marker.getLatLng(); this.place(p.lat, p.lng, true); });
        }
        this.map.setView([lat, lng], Math.max(this.map.getZoom(), 15));
        if (reverse && cfg.addressField) this.reverse(lat, lng);
    },
    async locate() {
        this.busy = true; this.error = '';
        try { const p = await geolocate(); this.place(p.lat, p.lng, true); }
        catch (e) { this.error = e.message; }
        finally { this.busy = false; }
    },
    async reverse(lat, lng) {
        const field = document.querySelector(cfg.addressField);
        if (!field || field.dataset.touched === '1') return;
        try {
            const r = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=17&lat=${lat}&lon=${lng}`, { headers: { Accept: 'application/json' } });
            const j = await r.json();
            if (j?.display_name) field.value = j.display_name.split(',').slice(0, 4).join(',').trim();
        } catch { /* address stays manual */ }
    },
}));

/** Live tracking map for a request. Polls the server and reloads when the status changes. */
Alpine.data('trackMap', (cfg) => ({
    data: null,
    map: null,
    me: null,
    pro: null,
    line: null,
    init() {
        this.$nextTick(() => {
            const c = cfg.customer?.lat ? [cfg.customer.lat, cfg.customer.lng] : LUSAKA;
            this.map = baseMap(this.$refs.map, c, 14);
            if (cfg.customer?.lat) this.me = L.marker(c, { icon: pin('#FF8A24', '●') }).addTo(this.map).bindPopup('Your location');
            this.refresh();
            this.timer = setInterval(() => this.refresh(), cfg.interval || 10000);
        });
    },
    async refresh() {
        try {
            const d = await getJson(cfg.url);
            if (this.data && (d.status !== this.data.status || d.quotes !== this.data.quotes)) { window.location.reload(); return; }
            this.data = d;
            if (d.provider?.lat) {
                const p = [d.provider.lat, d.provider.lng];
                if (this.pro) this.pro.setLatLng(p);
                else this.pro = L.marker(p, { icon: pin('#101D35', '⚒') }).addTo(this.map).bindPopup(d.provider.name);
                if (this.me) {
                    const pts = [this.me.getLatLng(), p];
                    if (this.line) this.line.setLatLngs(pts); else this.line = L.polyline(pts, { color: '#FF8A24', dashArray: '6 8', weight: 3 }).addTo(this.map);
                    this.map.fitBounds(L.latLngBounds(pts).pad(0.3));
                }
            }
        } catch { /* keep last state */ }
    },
}));

/** Static map with a set of markers (admin overview, job location). */
Alpine.data('pointsMap', (cfg) => ({
    init() {
        this.$nextTick(() => {
            const map = baseMap(this.$refs.map, LUSAKA, cfg.zoom || 12);
            const bounds = [];
            (cfg.points || []).forEach((p) => {
                const color = p.priority === 'emergency' ? '#dc2626' : p.color || '#FF8A24';
                L.marker([p.lat, p.lng], { icon: pin(color, p.label || '!') }).addTo(map)
                    .bindPopup(p.url ? `<a href="${p.url}"><b>${p.title || p.ref}</b></a><br>${p.subtitle || ''}` : `<b>${p.title || ''}</b><br>${p.subtitle || ''}`);
                bounds.push([p.lat, p.lng]);
            });
            (cfg.providers || []).forEach((p) => {
                L.marker([p.lat, p.lng], { icon: pin('#101D35', '⚒') }).addTo(map).bindPopup(`<b>${p.name}</b><br>Online provider`);
                bounds.push([p.lat, p.lng]);
            });
            if (bounds.length > 1) map.fitBounds(bounds, { padding: [30, 30], maxZoom: 15 });
            else if (bounds.length === 1) map.setView(bounds[0], 15);
        });
    },
}));

/** Notification bell: polls unread count and shows a toast for new alerts (job alerts for providers). */
Alpine.data('notifBell', (cfg) => ({
    unread: cfg.unread || 0,
    toast: null,
    init() {
        setInterval(async () => {
            try {
                const d = await getJson(cfg.url);
                if (d.unread > this.unread && d.latest) {
                    this.toast = d.latest;
                    if (navigator.vibrate && d.latest.kind === 'emergency') navigator.vibrate([300, 100, 300]);
                    setTimeout(() => (this.toast = null), 9000);
                }
                this.unread = d.unread;
            } catch { /* offline */ }
        }, cfg.interval || 20000);
    },
}));

/** In-job chat between customer and provider. */
Alpine.data('jobChat', (cfg) => ({
    messages: [],
    body: '',
    sending: false,
    init() { this.load(); setInterval(() => this.load(), 12000); },
    async load() {
        try { this.messages = (await getJson(cfg.url)).data; this.$nextTick(() => { const b = this.$refs.box; if (b) b.scrollTop = b.scrollHeight; }); } catch { }
    },
    async send() {
        if (!this.body.trim()) return;
        this.sending = true;
        try { await postJson(cfg.url, { body: this.body }); this.body = ''; await this.load(); } finally { this.sending = false; }
    },
}));

/** Shares the provider's GPS position while they have an active job so customers can follow arrival progress. */
Alpine.data('shareLocation', (cfg) => ({
    status: 'idle',
    last: null,
    watch: null,
    init() { if (cfg.auto) this.start(); },
    start() {
        if (!navigator.geolocation) { this.status = 'unsupported'; return; }
        this.status = 'starting';
        let lastSent = 0;
        this.watch = navigator.geolocation.watchPosition(async (p) => {
            this.status = 'sharing';
            if (Date.now() - lastSent < 15000) return;
            lastSent = Date.now();
            try { await postJson(cfg.url, { lat: p.coords.latitude, lng: p.coords.longitude }); this.last = new Date().toLocaleTimeString(); } catch { }
        }, () => { this.status = 'denied'; }, { enableHighAccuracy: true, maximumAge: 10000 });
    },
    stop() { if (this.watch !== null) navigator.geolocation.clearWatch(this.watch); this.status = 'idle'; },
}));

/** Adds live distance / ETA to provider cards based on the visitor's location. */
Alpine.data('nearby', (cfg) => ({
    busy: false,
    error: '',
    located: false,
    async locate() {
        this.busy = true; this.error = '';
        try {
            const p = await geolocate();
            const d = await getJson(`${cfg.url}?lat=${p.lat}&lng=${p.lng}`);
            d.data.forEach((pr) => {
                document.querySelectorAll(`[data-provider-distance="${pr.id}"]`).forEach((el) => {
                    el.textContent = pr.distance_km !== null ? `${pr.distance_km} km away · ~${pr.eta_minutes} min` : '';
                });
            });
            this.located = true;
        } catch (e) { this.error = e.message; }
        finally { this.busy = false; }
    },
}));

/** Service request form: dependent category → problem dropdown. */
Alpine.data('requestForm', (cfg) => ({
    categories: cfg.categories,
    categoryId: cfg.categoryId ? String(cfg.categoryId) : '',
    serviceId: cfg.serviceId ? String(cfg.serviceId) : '',
    priority: cfg.priority || 'normal',
    previews: [],
    get services() {
        const c = this.categories.find((x) => String(x.id) === this.categoryId);
        return c ? c.services : [];
    },
    pickCategory(id) { this.categoryId = String(id); this.serviceId = ''; },
    preview(e) {
        this.previews = [...e.target.files].slice(0, 5).map((f) => URL.createObjectURL(f));
    },
}));

window.Alpine = Alpine;
Alpine.start();
