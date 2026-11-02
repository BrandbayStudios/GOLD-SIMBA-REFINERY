/* Gold SIMBA Refinery — Invoicing (quotations, invoices, receipts, clients)
 * Self-contained: data lives in localStorage under STORE_KEY. Use Settings →
 * Backup to export/import JSON. All persistence goes through load()/save(),
 * so swapping to a server API later only touches those two functions. */
(function () {
  'use strict';

  var STORE_KEY = 'gsr_invoicing_v1';

  var DEFAULT_SERVICES = [
    ['Gold Refining', 'g'],
    ['Mineral Assaying & Testing', 'sample'],
    ['Gold Purity & Fineness Testing', 'sample'],
    ['Precious & Base Metal Analysis', 'sample'],
    ['Mineral Sample Preparation', 'sample'],
    ['Independent Laboratory Verification', 'sample'],
    ['Refinery & Laboratory Consultation', 'hour'],
    ['Mineral Valuation Support', 'service'],
    ['Quality Control & Compliance Testing', 'sample'],
    ['Buying on Behalf of Client (agency fee)', 'service'],
    ['Gold Biometric Security / Vault Custody', 'day'],
    ['Client Consultation', 'hour']
  ];
  var UNITS = ['g', 'kg', 'oz', 'pcs', 'sample', 'service', 'hour', 'day', 'lot'];
  var METHODS = ['Cash', 'Bank Transfer', 'Mobile Money', 'Cheque', 'Card', 'Other'];

  function defaults() {
    return {
      settings: {
        company: 'Gold SIMBA Refinery',
        tagline: 'Mineral Refining & Laboratory Testing — Zambia',
        address: 'Zambia',
        phone: '+260 571 007 701',
        email: 'info@goldsimbarefinery.com',
        website: 'alfarisgoldrefinery.com',
        tpin: '',
        logo: '',
        currency: 'ZMW',
        taxLabel: 'VAT',
        taxRate: 16,
        quoteValidDays: 14,
        invoiceDueDays: 7,
        quotePrefix: 'QUO-',
        invoicePrefix: 'INV-',
        receiptPrefix: 'RCT-',
        bank: 'Bank: \nAccount name: Gold SIMBA Refinery\nAccount no: \nBranch / Swift: \nMobile Money: ',
        terms: 'Payment due by the date shown. Samples and refined material are released only after full payment. Prices are subject to prevailing market rates for precious metals.',
        footer: 'Thank you for your business.',
        services: DEFAULT_SERVICES.map(function (s) { return { name: s[0], unit: s[1], price: 0 }; })
      },
      clients: [],
      docs: [],      // quotations + invoices
      receipts: [],  // payments
      counters: { quote: 0, invoice: 0, receipt: 0 }
    };
  }

  // ---------- storage ----------
  var db;
  function load() {
    try {
      var raw = localStorage.getItem(STORE_KEY);
      if (raw) {
        var d = JSON.parse(raw), base = defaults();
        d.settings = Object.assign(base.settings, d.settings || {});
        d.counters = Object.assign(base.counters, d.counters || {});
        ['clients', 'docs', 'receipts'].forEach(function (k) { if (!Array.isArray(d[k])) d[k] = []; });
        return d;
      }
    } catch (e) { console.error(e); }
    return defaults();
  }
  function save() {
    try { localStorage.setItem(STORE_KEY, JSON.stringify(db)); }
    catch (e) { alert('Could not save — browser storage may be full or blocked. Export a backup now.'); }
  }
  db = load();

  // ---------- helpers ----------
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  function esc(v) {
    return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function uid() { return Date.now().toString(36) + Math.random().toString(36).slice(2, 8); }
  function today() { var d = new Date(); d.setMinutes(d.getMinutes() - d.getTimezoneOffset()); return d.toISOString().slice(0, 10); }
  function addDays(iso, n) { var d = new Date(iso + 'T00:00:00'); d.setDate(d.getDate() + Number(n || 0)); d.setMinutes(d.getMinutes() - d.getTimezoneOffset()); return d.toISOString().slice(0, 10); }
  function fmtDate(iso) {
    if (!iso) return '—';
    var d = new Date(iso + 'T00:00:00');
    return isNaN(d) ? iso : d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
  }
  function r2(n) { return Math.round((Number(n) || 0) * 100) / 100; }
  function money(n, cur) {
    var v = r2(n);
    var s = Math.abs(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return (v < 0 ? '-' : '') + (cur || db.settings.currency) + ' ' + s;
  }
  function nextNumber(kind) {
    db.counters[kind] = (db.counters[kind] || 0) + 1;
    var p = db.settings[kind + 'Prefix'] || '';
    return p + new Date().getFullYear() + '-' + String(db.counters[kind]).padStart(4, '0');
  }
  function toast(msg) {
    var t = $('#toast'); t.textContent = msg; t.hidden = false;
    clearTimeout(toast._t); toast._t = setTimeout(function () { t.hidden = true; }, 2600);
  }
  function client(id) { return db.clients.find(function (c) { return c.id === id; }); }
  function docById(id) { return db.docs.find(function (d) { return d.id === id; }); }
  function receiptById(id) { return db.receipts.find(function (r) { return r.id === id; }); }

  // ---------- calculations ----------
  function calc(doc) {
    var sub = 0;
    (doc.items || []).forEach(function (it) { sub += r2((Number(it.qty) || 0) * (Number(it.price) || 0)); });
    sub = r2(sub);
    var disc = r2(Math.min(Number(doc.discount) || 0, sub));
    var taxable = r2(sub - disc);
    var tax = r2(taxable * (Number(doc.taxRate) || 0) / 100);
    var total = r2(taxable + tax);
    var paid = 0;
    if (doc.type === 'invoice') {
      db.receipts.forEach(function (r) { if (r.invoiceId === doc.id) paid += Number(r.amount) || 0; });
    }
    paid = r2(paid);
    return { sub: sub, disc: disc, tax: tax, total: total, paid: paid, balance: r2(total - paid) };
  }
  function status(doc) {
    var c = calc(doc);
    if (doc.type === 'quote') {
      if (doc.status === 'accepted' && doc.invoiceId) return 'invoiced';
      if ((doc.status === 'draft' || doc.status === 'sent') && doc.dueDate && doc.dueDate < today()) return 'expired';
      return doc.status || 'draft';
    }
    if (doc.status === 'cancelled') return 'cancelled';
    if (doc.status === 'draft') return 'draft';
    if (c.total > 0 && c.balance <= 0) return 'paid';
    if (c.paid > 0) return 'partial';
    if (doc.dueDate && doc.dueDate < today()) return 'overdue';
    return 'unpaid';
  }
  function badge(s) { return '<span class="badge b-' + s + '">' + esc(s) + '</span>'; }

  // number to words (for receipts)
  var ONES = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
  var TENS = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
  function words999(n) {
    var out = [];
    if (n >= 100) { out.push(ONES[Math.floor(n / 100)] + ' hundred'); n %= 100; if (n) out.push('and'); }
    if (n >= 20) { out.push(TENS[Math.floor(n / 10)] + (n % 10 ? '-' + ONES[n % 10] : '')); }
    else if (n > 0) out.push(ONES[n]);
    return out.join(' ');
  }
  function toWords(amount, cur) {
    amount = r2(amount);
    var whole = Math.floor(amount), cents = Math.round((amount - whole) * 100);
    var scales = ['', ' thousand', ' million', ' billion'], parts = [], i = 0;
    if (whole === 0) parts.push('zero');
    while (whole > 0 && i < scales.length) {
      var chunk = whole % 1000;
      if (chunk) parts.unshift(words999(chunk) + scales[i]);
      whole = Math.floor(whole / 1000); i++;
    }
    var names = { ZMW: ['Kwacha', 'Ngwee'], USD: ['US Dollars', 'Cents'] };
    var nm = names[cur] || [cur, 'cents'];
    var s = parts.join(' ') + ' ' + nm[0];
    if (cents) s += ' and ' + words999(cents) + ' ' + nm[1];
    s += ' only';
    return s.charAt(0).toUpperCase() + s.slice(1);
  }

  // ---------- router ----------
  var view = $('#view');
  function setTitle(t, actions) {
    $('#pageTitle').textContent = t;
    document.title = t + ' — Gold SIMBA Invoicing';
    $('#topActions').innerHTML = actions || '';
  }
  function go(h) { location.hash = h; }

  function route() {
    if (document.body.classList.contains('locked')) return;
    var parts = location.hash.replace(/^#\/?/, '').split('/');
    var page = parts[0] || 'dashboard', id = parts[1];
    $$('.side nav a').forEach(function (a) {
      var n = a.getAttribute('data-nav');
      a.classList.toggle('active', n === page || (page === 'quote' && n === 'quotes') || (page === 'invoice' && n === 'invoices') || (page === 'receipt' && n === 'receipts') || (page === 'client' && n === 'clients'));
    });
    $('#side').classList.remove('open');
    window.scrollTo(0, 0);
    switch (page) {
      case 'quotes': return listDocs('quote');
      case 'invoices': return listDocs('invoice');
      case 'receipts': return listReceipts();
      case 'clients': return listClients();
      case 'client': return showClient(id);
      case 'settings': return showSettings();
      case 'new': return editDoc(null, id, parts[2]);
      case 'edit': return editDoc(id);
      case 'quote': case 'invoice': return showDoc(id);
      case 'receipt': return showReceipt(id);
      default: return dashboard();
    }
  }
  window.addEventListener('hashchange', route);

  // ---------- dashboard ----------
  function dashboard() {
    setTitle('Dashboard', '<a class="btn" href="#/new/quote">New quotation</a><a class="btn btn-gold" href="#/new/invoice">New invoice</a>');
    var cur = db.settings.currency, month = today().slice(0, 7);
    var outstanding = {}, overdue = 0, collected = {}, openQuotes = 0;
    db.docs.forEach(function (d) {
      var s = status(d), c = calc(d);
      if (d.type === 'invoice' && (s === 'unpaid' || s === 'partial' || s === 'overdue')) {
        outstanding[d.currency] = r2((outstanding[d.currency] || 0) + c.balance);
        if (s === 'overdue') overdue++;
      }
      if (d.type === 'quote' && (s === 'draft' || s === 'sent')) openQuotes++;
    });
    db.receipts.forEach(function (r) {
      if ((r.date || '').slice(0, 7) === month) collected[r.currency] = r2((collected[r.currency] || 0) + Number(r.amount));
    });
    function multi(o) {
      var k = Object.keys(o).filter(function (x) { return o[x]; });
      return k.length ? k.map(function (x) { return money(o[x], x); }).join('<br>') : money(0, cur);
    }
    var recentInv = db.docs.filter(function (d) { return d.type === 'invoice'; }).sort(byDateDesc).slice(0, 6);
    var recentRc = db.receipts.slice().sort(byDateDesc).slice(0, 6);
    view.innerHTML =
      '<div class="cards">' +
      card('Outstanding', multi(outstanding), overdue + ' overdue invoice' + (overdue === 1 ? '' : 's')) +
      card('Collected this month', multi(collected), db.receipts.filter(function (r) { return (r.date || '').slice(0, 7) === month; }).length + ' receipts') +
      card('Open quotations', openQuotes, 'draft or sent') +
      card('Clients', db.clients.length, 'on file') +
      '</div>' +
      '<div class="section"><h3>Recent invoices</h3>' + docTable(recentInv, 'invoice') + '</div>' +
      '<div class="section"><h3>Recent receipts</h3>' + receiptTable(recentRc) + '</div>';
    bindRows();
  }
  function card(k, v, s) { return '<div class="card"><div class="k">' + esc(k) + '</div><div class="v">' + v + '</div><div class="s">' + esc(s) + '</div></div>'; }
  function byDateDesc(a, b) { return (b.date || '').localeCompare(a.date || '') || (b.created || 0) - (a.created || 0); }

  // ---------- lists ----------
  function docTable(list, type) {
    if (!list.length) return '<div class="table-wrap"><div class="empty">Nothing here yet.</div></div>';
    var isQ = type === 'quote';
    return '<div class="table-wrap"><table class="list"><thead><tr><th>Number</th><th>Client</th><th>Date</th><th>' + (isQ ? 'Valid until' : 'Due') + '</th><th>Status</th><th class="num">Total</th>' + (isQ ? '' : '<th class="num">Balance</th>') + '</tr></thead><tbody>' +
      list.map(function (d) {
        var c = calc(d), cl = client(d.clientId);
        return '<tr data-href="#/' + d.type + '/' + d.id + '"><td class="mono">' + esc(d.number) + '</td><td>' + esc(cl ? cl.name : '—') + '</td><td>' + fmtDate(d.date) + '</td><td>' + fmtDate(d.dueDate) + '</td><td>' + badge(status(d)) + '</td><td class="num">' + money(c.total, d.currency) + '</td>' + (isQ ? '' : '<td class="num">' + money(c.balance, d.currency) + '</td>') + '</tr>';
      }).join('') + '</tbody></table></div>';
  }
  function receiptTable(list) {
    if (!list.length) return '<div class="table-wrap"><div class="empty">No receipts yet.</div></div>';
    return '<div class="table-wrap"><table class="list"><thead><tr><th>Receipt</th><th>Client</th><th>Date</th><th>Invoice</th><th>Method</th><th class="num">Amount</th></tr></thead><tbody>' +
      list.map(function (r) {
        var cl = client(r.clientId), inv = docById(r.invoiceId);
        return '<tr data-href="#/receipt/' + r.id + '"><td class="mono">' + esc(r.number) + '</td><td>' + esc(cl ? cl.name : '—') + '</td><td>' + fmtDate(r.date) + '</td><td class="mono">' + esc(inv ? inv.number : '—') + '</td><td>' + esc(r.method) + '</td><td class="num">' + money(r.amount, r.currency) + '</td></tr>';
      }).join('') + '</tbody></table></div>';
  }
  function bindRows() {
    $$('tr[data-href]', view).forEach(function (tr) { tr.addEventListener('click', function () { go(tr.getAttribute('data-href')); }); });
  }

  function listDocs(type) {
    var isQ = type === 'quote';
    setTitle(isQ ? 'Quotations' : 'Invoices', '<a class="btn btn-gold" href="#/new/' + type + '">New ' + (isQ ? 'quotation' : 'invoice') + '</a>');
    var statuses = isQ ? ['draft', 'sent', 'accepted', 'invoiced', 'declined', 'expired'] : ['draft', 'unpaid', 'partial', 'overdue', 'paid', 'cancelled'];
    view.innerHTML = '<div class="toolbar"><input id="q" placeholder="Search number or client…"><select id="st"><option value="">All statuses</option>' +
      statuses.map(function (s) { return '<option>' + s + '</option>'; }).join('') + '</select><button class="btn" id="csv">Export CSV</button></div><div id="tbl"></div>';
    function render() {
      var q = $('#q').value.toLowerCase(), st = $('#st').value;
      var list = db.docs.filter(function (d) {
        if (d.type !== type) return false;
        if (st && status(d) !== st) return false;
        if (!q) return true;
        var cl = client(d.clientId);
        return (d.number + ' ' + (cl ? cl.name + ' ' + (cl.company || '') : '')).toLowerCase().indexOf(q) >= 0;
      }).sort(byDateDesc);
      $('#tbl').innerHTML = docTable(list, type);
      bindRows();
      return list;
    }
    $('#q').addEventListener('input', render);
    $('#st').addEventListener('change', render);
    $('#csv').addEventListener('click', function () {
      var rows = [['Number', 'Client', 'Date', isQ ? 'Valid until' : 'Due', 'Status', 'Currency', 'Subtotal', 'Discount', 'Tax', 'Total', 'Paid', 'Balance']];
      render().forEach(function (d) {
        var c = calc(d), cl = client(d.clientId);
        rows.push([d.number, cl ? cl.name : '', d.date, d.dueDate, status(d), d.currency, c.sub, c.disc, c.tax, c.total, c.paid, c.balance]);
      });
      downloadCSV(rows, (isQ ? 'quotations' : 'invoices') + '-' + today() + '.csv');
    });
    render();
  }

  function listReceipts() {
    setTitle('Receipts', '<button class="btn btn-gold" id="newRc">Record payment</button>');
    view.innerHTML = '<div class="toolbar"><input id="q" placeholder="Search receipt, client or invoice…"><button class="btn" id="csv">Export CSV</button></div><div id="tbl"></div>';
    function render() {
      var q = $('#q').value.toLowerCase();
      var list = db.receipts.filter(function (r) {
        if (!q) return true;
        var cl = client(r.clientId), inv = docById(r.invoiceId);
        return (r.number + ' ' + (cl ? cl.name : '') + ' ' + (inv ? inv.number : '') + ' ' + (r.reference || '')).toLowerCase().indexOf(q) >= 0;
      }).sort(byDateDesc);
      $('#tbl').innerHTML = receiptTable(list); bindRows();
      return list;
    }
    $('#q').addEventListener('input', render);
    $('#newRc').addEventListener('click', function () { paymentModal(null); });
    $('#csv').addEventListener('click', function () {
      var rows = [['Receipt', 'Client', 'Date', 'Invoice', 'Method', 'Reference', 'Currency', 'Amount']];
      render().forEach(function (r) {
        var cl = client(r.clientId), inv = docById(r.invoiceId);
        rows.push([r.number, cl ? cl.name : '', r.date, inv ? inv.number : '', r.method, r.reference, r.currency, r.amount]);
      });
      downloadCSV(rows, 'receipts-' + today() + '.csv');
    });
    render();
  }

  // ---------- clients ----------
  function listClients() {
    setTitle('Clients', '<button class="btn btn-gold" id="newCl">New client</button>');
    view.innerHTML = '<div class="toolbar"><input id="q" placeholder="Search name, company, phone, NRC…"><button class="btn" id="csv">Export CSV</button></div><div id="tbl"></div>';
    function balanceOf(c) {
      var b = {};
      db.docs.forEach(function (d) {
        if (d.clientId === c.id && d.type === 'invoice') {
          var s = status(d);
          if (s === 'unpaid' || s === 'partial' || s === 'overdue') b[d.currency] = r2((b[d.currency] || 0) + calc(d).balance);
        }
      });
      var k = Object.keys(b);
      return k.length ? k.map(function (x) { return money(b[x], x); }).join(', ') : '—';
    }
    function render() {
      var q = $('#q').value.toLowerCase();
      var list = db.clients.filter(function (c) {
        return !q || [c.name, c.company, c.phone, c.email, c.idNumber, c.type].join(' ').toLowerCase().indexOf(q) >= 0;
      }).sort(function (a, b) { return a.name.localeCompare(b.name); });
      $('#tbl').innerHTML = list.length ? '<div class="table-wrap"><table class="list"><thead><tr><th>Name</th><th>Company</th><th>Type</th><th>Phone</th><th>Email</th><th class="num">Owing</th></tr></thead><tbody>' +
        list.map(function (c) {
          return '<tr data-href="#/client/' + c.id + '"><td>' + esc(c.name) + '</td><td>' + esc(c.company || '—') + '</td><td>' + esc(c.type || '—') + '</td><td>' + esc(c.phone || '—') + '</td><td>' + esc(c.email || '—') + '</td><td class="num">' + balanceOf(c) + '</td></tr>';
        }).join('') + '</tbody></table></div>' : '<div class="table-wrap"><div class="empty">No clients yet. Add your first client to start quoting.</div></div>';
      bindRows();
      return list;
    }
    $('#q').addEventListener('input', render);
    $('#newCl').addEventListener('click', function () { clientModal(null, function (c) { go('#/client/' + c.id); }); });
    $('#csv').addEventListener('click', function () {
      var rows = [['Name', 'Company', 'Type', 'Phone', 'Email', 'NRC/Passport', 'TPIN', 'Address', 'Notes']];
      render().forEach(function (c) { rows.push([c.name, c.company, c.type, c.phone, c.email, c.idNumber, c.tpin, c.address, c.notes]); });
      downloadCSV(rows, 'clients-' + today() + '.csv');
    });
    render();
  }

  function clientModal(existing, done) {
    var c = existing || {};
    openModal(existing ? 'Edit client' : 'New client',
      '<form id="cf"><div class="grid">' +
      fld('name', 'Full name *', c.name, 'required') + fld('company', 'Company', c.company) +
      '<div class="field"><label>Client type</label><select name="type">' + ['', 'Miner', 'Trader', 'Investor', 'Exporter', 'Company', 'Individual', 'Other'].map(function (t) { return '<option' + (c.type === t ? ' selected' : '') + '>' + t + '</option>'; }).join('') + '</select></div>' +
      fld('phone', 'Phone', c.phone) + fld('email', 'Email', c.email, 'type="email"') +
      fld('idNumber', 'NRC / Passport no.', c.idNumber) + fld('tpin', 'TPIN', c.tpin) +
      '</div><div class="field"><label>Address</label><textarea name="address">' + esc(c.address) + '</textarea></div>' +
      '<div class="field"><label>Notes</label><textarea name="notes">' + esc(c.notes) + '</textarea></div>' +
      '<div class="edit-actions"><button type="button" class="btn" data-close>Cancel</button><button class="btn btn-gold">Save client</button></div></form>');
    $('#cf').addEventListener('submit', function (e) {
      e.preventDefault();
      var data = formData(e.target);
      if (!data.name.trim()) return;
      var target = existing || { id: uid(), created: Date.now() };
      Object.assign(target, data);
      if (!existing) db.clients.push(target);
      save(); closeModal(); toast('Client saved');
      if (done) done(target); else route();
    });
  }

  function showClient(id) {
    var c = client(id);
    if (!c) { setTitle('Client not found'); view.innerHTML = '<p>That client no longer exists. <a href="#/clients">Back to clients</a></p>'; return; }
    setTitle(c.name, '<button class="btn" id="edCl">Edit</button><button class="btn" id="stmt">Statement</button><a class="btn" href="#/new/quote/' + c.id + '">New quotation</a><a class="btn btn-gold" href="#/new/invoice/' + c.id + '">New invoice</a>');
    var docs = db.docs.filter(function (d) { return d.clientId === id; }).sort(byDateDesc);
    var rcs = db.receipts.filter(function (r) { return r.clientId === id; }).sort(byDateDesc);
    var billed = {}, paid = {};
    docs.forEach(function (d) { if (d.type === 'invoice' && d.status !== 'cancelled' && d.status !== 'draft') billed[d.currency] = r2((billed[d.currency] || 0) + calc(d).total); });
    rcs.forEach(function (r) { paid[r.currency] = r2((paid[r.currency] || 0) + Number(r.amount)); });
    var curs = Object.keys(Object.assign({}, billed, paid));
    if (!curs.length) curs = [db.settings.currency];
    view.innerHTML =
      '<div class="cards">' +
      card('Total invoiced', curs.map(function (k) { return money(billed[k] || 0, k); }).join('<br>'), docs.filter(function (d) { return d.type === 'invoice'; }).length + ' invoices') +
      card('Total paid', curs.map(function (k) { return money(paid[k] || 0, k); }).join('<br>'), rcs.length + ' receipts') +
      card('Balance owing', curs.map(function (k) { return money((billed[k] || 0) - (paid[k] || 0), k); }).join('<br>'), '') +
      '</div>' +
      '<div class="card section"><div class="grid">' +
      info('Company', c.company) + info('Type', c.type) + info('Phone', c.phone) + info('Email', c.email) + info('NRC / Passport', c.idNumber) + info('TPIN', c.tpin) +
      '</div>' + (c.address ? '<div style="margin-top:10px">' + info('Address', c.address) + '</div>' : '') + (c.notes ? '<div style="margin-top:10px">' + info('Notes', c.notes) + '</div>' : '') + '</div>' +
      '<div class="section"><h3>Invoices</h3>' + docTable(docs.filter(function (d) { return d.type === 'invoice'; }), 'invoice') + '</div>' +
      '<div class="section"><h3>Quotations</h3>' + docTable(docs.filter(function (d) { return d.type === 'quote'; }), 'quote') + '</div>' +
      '<div class="section"><h3>Receipts</h3>' + receiptTable(rcs) + '</div>' +
      '<button class="btn btn-danger" id="delCl">Delete client</button>';
    bindRows();
    $('#edCl').addEventListener('click', function () { clientModal(c); });
    $('#stmt').addEventListener('click', function () { showStatement(c); });
    $('#delCl').addEventListener('click', function () {
      if (docs.length || rcs.length) { alert('This client has quotations, invoices or receipts and cannot be deleted. Remove those first.'); return; }
      if (!confirm('Delete ' + c.name + '?')) return;
      db.clients = db.clients.filter(function (x) { return x.id !== id; });
      save(); toast('Client deleted'); go('#/clients');
    });
  }
  function info(k, v) { return '<div><label>' + esc(k) + '</label><div style="white-space:pre-line;color:var(--bone)">' + esc(v || '—') + '</div></div>'; }

  function showStatement(c) {
    setTitle('Statement — ' + c.name, '');
    var lines = [];
    db.docs.forEach(function (d) {
      if (d.clientId === c.id && d.type === 'invoice' && d.status !== 'cancelled' && d.status !== 'draft')
        lines.push({ date: d.date, ref: d.number, desc: 'Invoice', debit: calc(d).total, credit: 0, currency: d.currency });
    });
    db.receipts.forEach(function (r) {
      if (r.clientId === c.id) lines.push({ date: r.date, ref: r.number, desc: 'Payment — ' + r.method, debit: 0, credit: Number(r.amount), currency: r.currency });
    });
    lines.sort(function (a, b) { return (a.date || '').localeCompare(b.date || ''); });
    var curs = {};
    lines.forEach(function (l) { (curs[l.currency] = curs[l.currency] || []).push(l); });
    if (!Object.keys(curs).length) curs[db.settings.currency] = [];
    var body = Object.keys(curs).map(function (cur) {
      var bal = 0;
      return '<table class="p-items" style="margin-top:14px"><thead><tr><th>Date</th><th>Reference</th><th>Description</th><th class="num">Debit</th><th class="num">Credit</th><th class="num">Balance (' + esc(cur) + ')</th></tr></thead><tbody>' +
        (curs[cur].length ? curs[cur].map(function (l) {
          bal = r2(bal + l.debit - l.credit);
          return '<tr><td>' + fmtDate(l.date) + '</td><td class="mono">' + esc(l.ref) + '</td><td>' + esc(l.desc) + '</td><td class="num">' + (l.debit ? money(l.debit, cur) : '') + '</td><td class="num">' + (l.credit ? money(l.credit, cur) : '') + '</td><td class="num">' + money(bal, cur) + '</td></tr>';
        }).join('') : '<tr><td colspan="6">No transactions.</td></tr>') +
        '</tbody></table><div class="p-tot"><div class="row grand"><span>Balance due</span><span>' + money(bal, cur) + '</span></div></div>';
    }).join('');
    view.innerHTML = docActions('<a class="btn" href="#/client/' + c.id + '">Back</a>') +
      '<div class="paper">' + paperHead('Statement', 'As at ' + fmtDate(today())) +
      '<div class="p-meta"><div><div class="lbl">Account</div>' + clientBlock(c) + '</div></div>' + body + paperFoot() + '</div>';
    bindPrint();
  }

  // ---------- doc editor ----------
  function editDoc(id, newType, presetClient) {
    var s = db.settings, doc;
    if (id) {
      doc = JSON.parse(JSON.stringify(docById(id) || null));
      if (!doc) { go('#/dashboard'); return; }
    } else {
      var isQ = newType === 'quote';
      doc = {
        type: isQ ? 'quote' : 'invoice', clientId: presetClient || '', date: today(),
        dueDate: addDays(today(), isQ ? s.quoteValidDays : s.invoiceDueDays),
        currency: s.currency, items: [{ desc: '', qty: 1, unit: 'sample', price: 0 }],
        discount: 0, taxRate: s.taxRate, notes: '', terms: s.terms, status: isQ ? 'draft' : 'issued'
      };
    }
    var isQuote = doc.type === 'quote', label = isQuote ? 'quotation' : 'invoice';
    setTitle((id ? 'Edit ' : 'New ') + label + (doc.number ? ' ' + doc.number : ''), '');

    var clientOpts = '<option value="">Select client…</option>' + db.clients.slice().sort(function (a, b) { return a.name.localeCompare(b.name); })
      .map(function (c) { return '<option value="' + c.id + '"' + (c.id === doc.clientId ? ' selected' : '') + '>' + esc(c.name + (c.company ? ' — ' + c.company : '')) + '</option>'; }).join('');
    var svcList = '<datalist id="svcs">' + s.services.map(function (x) { return '<option value="' + esc(x.name) + '">'; }).join('') + '</datalist>';

    view.innerHTML = '<form id="df">' + svcList +
      '<div class="grid">' +
      '<div class="field span-2"><label>Client *</label><div style="display:flex;gap:8px"><select name="clientId" required>' + clientOpts + '</select><button type="button" class="btn" id="addCl">+ New</button></div></div>' +
      fld('date', isQuote ? 'Quotation date' : 'Invoice date', doc.date, 'type="date" required') +
      fld('dueDate', isQuote ? 'Valid until' : 'Due date', doc.dueDate, 'type="date"') +
      '<div class="field"><label>Currency</label><select name="currency">' + ['ZMW', 'USD'].concat(['ZMW', 'USD'].indexOf(doc.currency) < 0 ? [doc.currency] : []).map(function (c) { return '<option' + (c === doc.currency ? ' selected' : '') + '>' + c + '</option>'; }).join('') + '</select></div>' +
      fld('reference', 'Client reference / PO / Booking no.', doc.reference) +
      '</div>' +
      '<h3 style="margin:18px 0 8px;font-size:17px">Items</h3>' +
      '<div class="table-wrap" style="padding:8px"><table class="items"><thead><tr><th class="c-desc">Description</th><th class="c-qty">Qty</th><th class="c-unit">Unit</th><th class="c-price">Unit price</th><th class="num">Amount</th><th></th></tr></thead><tbody id="rows"></tbody></table>' +
      '<button type="button" class="btn btn-sm" id="addRow" style="margin:8px 6px">+ Add line</button></div>' +
      '<div class="totals">' +
      '<div class="row"><span>Subtotal</span><span id="tSub"></span></div>' +
      '<div class="row"><span>Discount</span><input name="discount" type="number" step="0.01" min="0" value="' + esc(doc.discount || 0) + '"></div>' +
      '<div class="row"><span>' + esc(s.taxLabel) + ' %</span><input name="taxRate" type="number" step="0.01" min="0" value="' + esc(doc.taxRate) + '"></div>' +
      '<div class="row"><span>' + esc(s.taxLabel) + '</span><span id="tTax"></span></div>' +
      '<div class="row grand"><span>Total</span><span id="tTot"></span></div>' +
      '</div>' +
      '<div class="grid" style="margin-top:20px">' +
      '<div class="field"><label>Notes (shown on document)</label><textarea name="notes">' + esc(doc.notes) + '</textarea></div>' +
      '<div class="field"><label>Terms & conditions</label><textarea name="terms">' + esc(doc.terms) + '</textarea></div>' +
      '</div>' +
      '<div class="edit-actions"><button type="button" class="btn" id="cancel">Cancel</button>' +
      (isQuote || id ? '' : '<button type="button" class="btn" id="saveDraft">Save as draft</button>') +
      '<button class="btn btn-gold">Save ' + label + '</button></div></form>';

    var rows = $('#rows');
    function rowHtml(it) {
      return '<tr><td class="c-desc-td"><input list="svcs" data-k="desc" value="' + esc(it.desc) + '" placeholder="Service or item"></td>' +
        '<td><input data-k="qty" type="number" step="any" min="0" value="' + esc(it.qty) + '"></td>' +
        '<td><select data-k="unit">' + UNITS.concat(UNITS.indexOf(it.unit) < 0 && it.unit ? [it.unit] : []).map(function (u) { return '<option' + (u === it.unit ? ' selected' : '') + '>' + u + '</option>'; }).join('') + '</select></td>' +
        '<td><input data-k="price" type="number" step="0.01" min="0" value="' + esc(it.price) + '"></td>' +
        '<td class="num amt"></td><td><button type="button" class="btn btn-sm btn-danger" data-del aria-label="Remove line">×</button></td></tr>';
    }
    rows.innerHTML = doc.items.map(rowHtml).join('');
    function readItems() {
      return $$('tr', rows).map(function (tr) {
        var o = {}; $$('[data-k]', tr).forEach(function (el) { o[el.getAttribute('data-k')] = el.value; });
        o.qty = Number(o.qty) || 0; o.price = Number(o.price) || 0; return o;
      });
    }
    function recalc() {
      var f = $('#df'), cur = f.currency.value;
      var tmp = { type: 'quote', items: readItems(), discount: f.discount.value, taxRate: f.taxRate.value };
      $$('tr', rows).forEach(function (tr, i) { $('.amt', tr).textContent = money(tmp.items[i].qty * tmp.items[i].price, cur); });
      var c = calc(tmp);
      $('#tSub').textContent = money(c.sub, cur); $('#tTax').textContent = money(c.tax, cur); $('#tTot').textContent = money(c.total, cur);
    }
    rows.addEventListener('input', function (e) {
      if (e.target.getAttribute('data-k') === 'desc') {
        var svc = s.services.find(function (x) { return x.name === e.target.value; });
        if (svc) {
          var tr = e.target.closest('tr');
          $('[data-k=unit]', tr).value = svc.unit;
          if (Number(svc.price) && !Number($('[data-k=price]', tr).value)) $('[data-k=price]', tr).value = svc.price;
        }
      }
      recalc();
    });
    rows.addEventListener('change', recalc);
    rows.addEventListener('click', function (e) {
      if (e.target.hasAttribute('data-del')) {
        if ($$('tr', rows).length > 1) e.target.closest('tr').remove(); else $$('input', e.target.closest('tr')).forEach(function (i) { i.value = ''; });
        recalc();
      }
    });
    $('#addRow').addEventListener('click', function () {
      rows.insertAdjacentHTML('beforeend', rowHtml({ desc: '', qty: 1, unit: 'sample', price: 0 }));
      $('tr:last-child input', rows).focus(); recalc();
    });
    $('#df').addEventListener('input', function (e) { if (!rows.contains(e.target)) recalc(); });
    $('#df').addEventListener('change', function (e) { if (e.target.name === 'currency') recalc(); });
    $('#addCl').addEventListener('click', function () {
      clientModal(null, function (c) {
        var sel = $('#df').clientId;
        sel.insertAdjacentHTML('beforeend', '<option value="' + c.id + '">' + esc(c.name) + '</option>');
        sel.value = c.id;
      });
    });
    $('#cancel').addEventListener('click', function () { history.back(); });
    function persist(asDraft) {
      var f = $('#df');
      if (!f.clientId.value) { alert('Please select a client.'); return; }
      var items = readItems().filter(function (it) { return it.desc.trim() || it.price; });
      if (!items.length) { alert('Add at least one line item.'); return; }
      var data = formData(f);
      var target = id ? docById(id) : { id: uid(), type: doc.type, created: Date.now(), number: nextNumber(doc.type) };
      Object.assign(target, {
        clientId: data.clientId, date: data.date, dueDate: data.dueDate, currency: data.currency, reference: data.reference,
        items: items, discount: Number(data.discount) || 0, taxRate: Number(data.taxRate) || 0, notes: data.notes, terms: data.terms
      });
      if (!id) target.status = doc.status;
      if (doc.type === 'invoice') {
        if (asDraft) target.status = 'draft';
        else if (target.status === 'draft') target.status = 'issued';
      }
      if (!id) db.docs.push(target);
      save(); toast(label.charAt(0).toUpperCase() + label.slice(1) + ' saved');
      go('#/' + target.type + '/' + target.id);
    }
    $('#df').addEventListener('submit', function (e) { e.preventDefault(); persist(false); });
    if ($('#saveDraft')) $('#saveDraft').addEventListener('click', function () { persist(true); });
    recalc();
  }

  // ---------- doc view (printable) ----------
  function showDoc(id) {
    var d = docById(id);
    if (!d) { setTitle('Not found'); view.innerHTML = '<p>Document not found.</p>'; return; }
    var isQ = d.type === 'quote', s = status(d), c = calc(d), cl = client(d.clientId) || {};
    setTitle((isQ ? 'Quotation ' : 'Invoice ') + d.number, '');
    var acts = '<a class="btn" href="#/edit/' + d.id + '">Edit</a>';
    if (isQ) {
      if (!d.invoiceId) {
        acts += '<select id="qStatus" class="btn" style="width:auto">' + ['draft', 'sent', 'accepted', 'declined'].map(function (x) { return '<option' + (x === d.status ? ' selected' : '') + '>' + x + '</option>'; }).join('') + '</select>';
        acts += '<button class="btn btn-gold" id="toInv">Convert to invoice</button>';
      } else {
        var inv = docById(d.invoiceId);
        if (inv) acts += '<a class="btn" href="#/invoice/' + inv.id + '">View invoice ' + esc(inv.number) + '</a>';
      }
    } else {
      if (d.status === 'draft') acts += '<button class="btn" id="issue">Mark as issued</button>';
      if (s !== 'cancelled' && s !== 'draft' && c.balance > 0) acts += '<button class="btn btn-gold" id="pay">Record payment</button>';
      if (s !== 'cancelled' && !c.paid) acts += '<button class="btn" id="cancelInv">Cancel invoice</button>';
      if (s === 'cancelled') acts += '<button class="btn" id="reopen">Reinstate</button>';
    }
    acts += '<button class="btn" id="dup">Duplicate</button><button class="btn" id="email">Email</button>';
    var canDelete = isQ ? !d.invoiceId : !c.paid;
    if (canDelete) acts += '<button class="btn btn-danger" id="del">Delete</button>';

    var payments = isQ ? [] : db.receipts.filter(function (r) { return r.invoiceId === d.id; }).sort(byDateDesc);
    var src = d.quoteId ? docById(d.quoteId) : null;

    view.innerHTML = docActions(acts) +
      '<div class="paper">' + paperHead(isQ ? 'Quotation' : 'Invoice', d.number) +
      '<div class="p-meta"><div><div class="lbl">' + (isQ ? 'Prepared for' : 'Bill to') + '</div>' + clientBlock(cl) + '</div>' +
      '<div><table>' +
      '<tr><td>Date</td><td>' + fmtDate(d.date) + '</td></tr>' +
      '<tr><td>' + (isQ ? 'Valid until' : 'Due date') + '</td><td>' + fmtDate(d.dueDate) + '</td></tr>' +
      (d.reference ? '<tr><td>Reference</td><td>' + esc(d.reference) + '</td></tr>' : '') +
      (src ? '<tr><td>Quotation</td><td>' + esc(src.number) + '</td></tr>' : '') +
      '<tr><td>Status</td><td style="text-transform:capitalize">' + esc(s) + '</td></tr>' +
      '</table></div></div>' +
      itemsTable(d) +
      '<div class="p-tot">' +
      '<div class="row"><span>Subtotal</span><span>' + money(c.sub, d.currency) + '</span></div>' +
      (c.disc ? '<div class="row"><span>Discount</span><span>-' + money(c.disc, d.currency) + '</span></div>' : '') +
      (Number(d.taxRate) ? '<div class="row"><span>' + esc(db.settings.taxLabel) + ' (' + esc(d.taxRate) + '%)</span><span>' + money(c.tax, d.currency) + '</span></div>' : '') +
      '<div class="row grand"><span>Total</span><span>' + money(c.total, d.currency) + '</span></div>' +
      (!isQ && c.paid ? '<div class="row"><span>Paid</span><span>-' + money(c.paid, d.currency) + '</span></div><div class="row grand"><span>Balance due</span><span>' + money(c.balance, d.currency) + '</span></div>' : '') +
      '</div>' +
      (s === 'paid' ? '<div class="p-stamp">PAID</div>' : '') +
      '<div class="p-notes">' +
      (d.notes ? '<div><h4>Notes</h4><div>' + esc(d.notes) + '</div></div>' : '') +
      (!isQ && db.settings.bank.trim() ? '<div><h4>Payment details</h4><div>' + esc(db.settings.bank) + '</div></div>' : '') +
      (d.terms ? '<div><h4>Terms & conditions</h4><div>' + esc(d.terms) + '</div></div>' : '') +
      '</div>' +
      (isQ ? '<div class="p-sign"><div>Authorised by (Gold SIMBA Refinery)</div><div>Accepted by client — signature & date</div></div>' : '') +
      paperFoot() + '</div>' +
      (payments.length ? '<div class="section" style="max-width:820px;margin:28px auto 0"><h3>Payments on this invoice</h3>' + receiptTable(payments) + '</div>' : '');
    bindRows(); bindPrint();

    on('#qStatus', 'change', function (e) { d.status = e.target.value; save(); toast('Status updated'); route(); });
    on('#toInv', 'click', function () {
      var inv = {
        id: uid(), type: 'invoice', created: Date.now(), number: nextNumber('invoice'), clientId: d.clientId,
        date: today(), dueDate: addDays(today(), db.settings.invoiceDueDays), currency: d.currency, reference: d.reference,
        items: JSON.parse(JSON.stringify(d.items)), discount: d.discount, taxRate: d.taxRate, notes: d.notes, terms: d.terms,
        status: 'issued', quoteId: d.id
      };
      db.docs.push(inv); d.status = 'accepted'; d.invoiceId = inv.id;
      save(); toast('Invoice ' + inv.number + ' created'); go('#/invoice/' + inv.id);
    });
    on('#issue', 'click', function () { d.status = 'issued'; save(); route(); });
    on('#pay', 'click', function () { paymentModal(d); });
    on('#cancelInv', 'click', function () { if (confirm('Cancel invoice ' + d.number + '?')) { d.status = 'cancelled'; save(); route(); } });
    on('#reopen', 'click', function () { d.status = 'issued'; save(); route(); });
    on('#dup', 'click', function () {
      var cp = JSON.parse(JSON.stringify(d));
      Object.assign(cp, { id: uid(), created: Date.now(), number: nextNumber(d.type), date: today(),
        dueDate: addDays(today(), isQ ? db.settings.quoteValidDays : db.settings.invoiceDueDays), status: isQ ? 'draft' : 'draft' });
      delete cp.invoiceId; delete cp.quoteId;
      db.docs.push(cp); save(); toast('Duplicated as ' + cp.number); go('#/edit/' + cp.id);
    });
    on('#email', 'click', function () {
      var subj = (isQ ? 'Quotation ' : 'Invoice ') + d.number + ' from ' + db.settings.company;
      var body = 'Dear ' + (cl.name || 'Client') + ',\n\nPlease find attached ' + (isQ ? 'quotation ' : 'invoice ') + d.number +
        ' for ' + money(isQ ? c.total : c.balance, d.currency) + (isQ ? ', valid until ' : ', due ') + fmtDate(d.dueDate) +
        '.\n\nKind regards,\n' + db.settings.company + '\n' + db.settings.phone;
      alert('Your email app will open. Use "Print / Save PDF" first and attach the PDF to the email.');
      location.href = 'mailto:' + encodeURIComponent(cl.email || '') + '?subject=' + encodeURIComponent(subj) + '&body=' + encodeURIComponent(body);
    });
    on('#del', 'click', function () {
      if (!confirm('Delete ' + d.number + '? This cannot be undone.')) return;
      db.docs = db.docs.filter(function (x) { return x.id !== d.id; });
      db.docs.forEach(function (x) { if (x.invoiceId === d.id) { delete x.invoiceId; x.status = 'sent'; } });
      save(); toast('Deleted'); go(isQ ? '#/quotes' : '#/invoices');
    });
  }
  function on(sel, ev, fn) { var el = $(sel); if (el) el.addEventListener(ev, fn); }

  function itemsTable(d) {
    return '<table class="p-items"><thead><tr><th style="width:36px">#</th><th>Description</th><th class="num">Qty</th><th>Unit</th><th class="num">Unit price</th><th class="num">Amount</th></tr></thead><tbody>' +
      d.items.map(function (it, i) {
        return '<tr><td>' + (i + 1) + '</td><td>' + esc(it.desc) + '</td><td class="num">' + esc(it.qty) + '</td><td>' + esc(it.unit) + '</td><td class="num">' + money(it.price, d.currency) + '</td><td class="num">' + money(r2(it.qty * it.price), d.currency) + '</td></tr>';
      }).join('') + '</tbody></table>';
  }
  function paperHead(title, number) {
    var s = db.settings;
    var lines = [s.tagline, s.address, [s.phone, s.email].filter(Boolean).join(' · '), s.website, s.tpin ? 'TPIN: ' + s.tpin : ''].filter(Boolean).join('\n');
    return '<div class="p-head"><div class="p-brand">' + (s.logo ? '<img src="' + esc(s.logo) + '" alt="">' : '') +
      '<div><div class="p-co">' + esc(s.company) + '</div><div class="p-co-sub">' + esc(lines) + '</div></div></div>' +
      '<div class="p-title"><h2>' + esc(title) + '</h2><div class="mono">' + esc(number) + '</div></div></div>';
  }
  function clientBlock(c) {
    return '<div style="font-weight:600">' + esc(c.name || '—') + '</div>' +
      (c.company ? '<div>' + esc(c.company) + '</div>' : '') +
      (c.address ? '<div style="white-space:pre-line">' + esc(c.address) + '</div>' : '') +
      (c.phone ? '<div>' + esc(c.phone) + '</div>' : '') +
      (c.email ? '<div>' + esc(c.email) + '</div>' : '') +
      (c.idNumber ? '<div>NRC/Passport: ' + esc(c.idNumber) + '</div>' : '') +
      (c.tpin ? '<div>TPIN: ' + esc(c.tpin) + '</div>' : '');
  }
  function paperFoot() { return '<div class="p-foot">' + esc(db.settings.footer) + '</div>'; }
  function docActions(extra) { return '<div class="doc-actions"><button class="btn btn-gold" data-print>Print / Save PDF</button>' + extra + '</div>'; }
  function bindPrint() { $$('[data-print]', view).forEach(function (b) { b.addEventListener('click', function () { window.print(); }); }); }

  // ---------- payments / receipts ----------
  function paymentModal(inv) {
    var open = db.docs.filter(function (d) {
      var s = status(d); return d.type === 'invoice' && (s === 'unpaid' || s === 'partial' || s === 'overdue');
    });
    if (!inv && !db.clients.length) { alert('Add a client first.'); return; }
    var invOpts = '<option value="">— No invoice (payment on account) —</option>' + open.map(function (d) {
      var cl = client(d.clientId);
      return '<option value="' + d.id + '"' + (inv && inv.id === d.id ? ' selected' : '') + '>' + esc(d.number + ' · ' + (cl ? cl.name : '') + ' · owing ' + money(calc(d).balance, d.currency)) + '</option>';
    }).join('');
    var clOpts = db.clients.slice().sort(function (a, b) { return a.name.localeCompare(b.name); }).map(function (c) {
      return '<option value="' + c.id + '"' + (inv && inv.clientId === c.id ? ' selected' : '') + '>' + esc(c.name) + '</option>';
    }).join('');
    var bal = inv ? calc(inv).balance : '';
    openModal('Record payment',
      '<form id="pf"><div class="field"><label>Invoice</label><select name="invoiceId">' + invOpts + '</select></div>' +
      '<div class="grid"><div class="field"><label>Client *</label><select name="clientId" required>' + clOpts + '</select></div>' +
      fld('date', 'Payment date', today(), 'type="date" required') +
      fld('amount', 'Amount *', bal, 'type="number" step="0.01" min="0.01" required') +
      '<div class="field"><label>Currency</label><select name="currency">' + ['ZMW', 'USD'].map(function (c) { return '<option' + (c === (inv ? inv.currency : db.settings.currency) ? ' selected' : '') + '>' + c + '</option>'; }).join('') + '</select></div>' +
      '<div class="field"><label>Method</label><select name="method">' + METHODS.map(function (m) { return '<option>' + m + '</option>'; }).join('') + '</select></div>' +
      fld('reference', 'Transaction ref.', '') +
      fld('receivedBy', 'Received by', '') +
      '</div><div class="field"><label>Notes</label><textarea name="notes"></textarea></div>' +
      '<div class="edit-actions"><button type="button" class="btn" data-close>Cancel</button><button class="btn btn-gold">Save & issue receipt</button></div></form>');
    var f = $('#pf');
    f.invoiceId.addEventListener('change', function () {
      var d = docById(f.invoiceId.value);
      if (d) { f.clientId.value = d.clientId; f.currency.value = d.currency; f.amount.value = calc(d).balance; }
    });
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      var data = formData(f), amt = r2(data.amount), d = docById(data.invoiceId);
      if (!(amt > 0)) { alert('Enter an amount.'); return; }
      if (d) {
        if (d.currency !== data.currency) { alert('Currency must match the invoice (' + d.currency + ').'); return; }
        var b = calc(d).balance;
        if (amt > b && !confirm('Amount exceeds the balance of ' + money(b, d.currency) + '. Record anyway?')) return;
        data.clientId = d.clientId;
      }
      var r = Object.assign({ id: uid(), created: Date.now(), number: nextNumber('receipt') }, data, { amount: amt, invoiceId: data.invoiceId || null });
      db.receipts.push(r); save(); closeModal(); toast('Receipt ' + r.number + ' issued');
      go('#/receipt/' + r.id);
    });
  }

  function showReceipt(id) {
    var r = receiptById(id);
    if (!r) { setTitle('Not found'); view.innerHTML = '<p>Receipt not found.</p>'; return; }
    var cl = client(r.clientId) || {}, inv = docById(r.invoiceId);
    setTitle('Receipt ' + r.number, '');
    var acts = (inv ? '<a class="btn" href="#/invoice/' + inv.id + '">View invoice</a>' : '') + '<button class="btn btn-danger" id="del">Void / delete</button>';
    var c = inv ? calc(inv) : null;
    view.innerHTML = docActions(acts) +
      '<div class="paper">' + paperHead('Receipt', r.number) +
      '<div class="p-meta"><div><div class="lbl">Received from</div>' + clientBlock(cl) + '</div>' +
      '<div><table><tr><td>Date</td><td>' + fmtDate(r.date) + '</td></tr>' +
      '<tr><td>Method</td><td>' + esc(r.method) + '</td></tr>' +
      (r.reference ? '<tr><td>Transaction ref.</td><td>' + esc(r.reference) + '</td></tr>' : '') +
      (inv ? '<tr><td>For invoice</td><td>' + esc(inv.number) + '</td></tr>' : '') +
      '</table></div></div>' +
      '<div class="p-amount-box"><div class="lbl" style="font:500 10.5px IBM Plex Mono,monospace;letter-spacing:.14em;text-transform:uppercase;color:#8a8073">Amount received</div>' +
      '<div class="big">' + money(r.amount, r.currency) + '</div><div style="font-style:italic">' + esc(toWords(r.amount, r.currency)) + '</div></div>' +
      (inv ? '<div class="p-tot" style="margin-left:0;width:340px"><div class="row"><span>Invoice total</span><span>' + money(c.total, inv.currency) + '</span></div>' +
        '<div class="row"><span>Total paid to date</span><span>' + money(c.paid, inv.currency) + '</span></div>' +
        '<div class="row grand"><span>Balance remaining</span><span>' + money(Math.max(c.balance, 0), inv.currency) + '</span></div></div>' : '<p>Payment received on account.</p>') +
      (r.notes ? '<div class="p-notes"><div><h4>Notes</h4><div>' + esc(r.notes) + '</div></div></div>' : '') +
      '<div class="p-sign"><div>Received by' + (r.receivedBy ? ': ' + esc(r.receivedBy) : '') + '</div><div>Customer signature</div></div>' +
      paperFoot() + '</div>';
    bindPrint();
    on('#del', 'click', function () {
      if (!confirm('Void and delete receipt ' + r.number + '? The invoice balance will go back up.')) return;
      db.receipts = db.receipts.filter(function (x) { return x.id !== r.id; });
      save(); toast('Receipt deleted'); go('#/receipts');
    });
  }

  // ---------- settings ----------
  function showSettings() {
    var s = db.settings;
    setTitle('Settings', '');
    view.innerHTML = '<form id="sf">' +
      '<div class="section card"><h3>Company details (shown on documents)</h3><div class="grid">' +
      fld('company', 'Company name', s.company) + fld('tagline', 'Tagline', s.tagline) + fld('phone', 'Phone', s.phone) +
      fld('email', 'Email', s.email) + fld('website', 'Website', s.website) + fld('tpin', 'TPIN', s.tpin) +
      '</div><div class="field"><label>Address</label><textarea name="address">' + esc(s.address) + '</textarea></div>' +
      '<div class="field"><label>Logo</label><div style="display:flex;gap:12px;align-items:center">' +
      (s.logo ? '<img src="' + esc(s.logo) + '" alt="" style="max-height:50px;background:#fff;padding:4px">' : '') +
      '<input type="file" id="logo" accept="image/*" style="width:auto">' + (s.logo ? '<button type="button" class="btn btn-sm" id="rmLogo">Remove</button>' : '') + '</div></div></div>' +
      '<div class="section card"><h3>Defaults</h3><div class="grid">' +
      '<div class="field"><label>Default currency</label><select name="currency">' + ['ZMW', 'USD'].map(function (c) { return '<option' + (c === s.currency ? ' selected' : '') + '>' + c + '</option>'; }).join('') + '</select></div>' +
      fld('taxLabel', 'Tax label', s.taxLabel) + fld('taxRate', 'Default tax %', s.taxRate, 'type="number" step="0.01" min="0"') +
      fld('quoteValidDays', 'Quotation valid (days)', s.quoteValidDays, 'type="number" min="0"') +
      fld('invoiceDueDays', 'Invoice due (days)', s.invoiceDueDays, 'type="number" min="0"') +
      fld('quotePrefix', 'Quotation prefix', s.quotePrefix) + fld('invoicePrefix', 'Invoice prefix', s.invoicePrefix) + fld('receiptPrefix', 'Receipt prefix', s.receiptPrefix) +
      '</div><div class="grid">' +
      '<div class="field"><label>Payment / bank details (on invoices)</label><textarea name="bank" rows="5">' + esc(s.bank) + '</textarea></div>' +
      '<div class="field"><label>Default terms & conditions</label><textarea name="terms" rows="5">' + esc(s.terms) + '</textarea></div>' +
      '</div>' + fld('footer', 'Document footer', s.footer) + '</div>' +
      '<div class="section card"><h3>Services & price list</h3><p style="margin-top:0;font-size:13px;color:var(--smoke)">These appear as suggestions when adding line items. Price 0 = enter manually each time.</p>' +
      '<table class="items"><thead><tr><th class="c-desc">Service</th><th class="c-unit">Unit</th><th class="c-price">Default price (' + esc(s.currency) + ')</th><th></th></tr></thead><tbody id="svcRows">' +
      s.services.map(svcRow).join('') + '</tbody></table><button type="button" class="btn btn-sm" id="addSvc" style="margin-top:8px">+ Add service</button></div>' +
      '<div class="edit-actions" style="justify-content:flex-start"><button class="btn btn-gold">Save settings</button></div></form>' +
      '<div class="section card" style="margin-top:30px"><h3>Backup & restore</h3>' +
      '<p style="margin-top:0;font-size:13px">All records are stored in this browser on this device. Download a backup at least weekly, and use Restore to move data to another computer.</p>' +
      '<div class="toolbar"><button class="btn" id="exp">Download backup (.json)</button><label class="btn" style="margin:0">Restore from backup<input type="file" id="imp" accept="application/json,.json" hidden></label>' +
      '<button class="btn btn-danger" id="wipe">Erase all data</button></div>' +
      '<p style="font-size:12px;color:var(--smoke)">' + db.clients.length + ' clients · ' + db.docs.filter(function (d) { return d.type === 'quote'; }).length + ' quotations · ' + db.docs.filter(function (d) { return d.type === 'invoice'; }).length + ' invoices · ' + db.receipts.length + ' receipts</p></div>';

    function svcRow(x) {
      return '<tr><td><input data-k="name" value="' + esc(x.name) + '"></td><td><select data-k="unit">' + UNITS.map(function (u) { return '<option' + (u === x.unit ? ' selected' : '') + '>' + u + '</option>'; }).join('') + '</select></td>' +
        '<td><input data-k="price" type="number" step="0.01" min="0" value="' + esc(x.price) + '"></td><td><button type="button" class="btn btn-sm btn-danger" data-del>×</button></td></tr>';
    }
    $('#addSvc').addEventListener('click', function () { $('#svcRows').insertAdjacentHTML('beforeend', svcRow({ name: '', unit: 'service', price: 0 })); });
    $('#svcRows').addEventListener('click', function (e) { if (e.target.hasAttribute('data-del')) e.target.closest('tr').remove(); });
    $('#logo').addEventListener('change', function (e) {
      var file = e.target.files[0]; if (!file) return;
      if (file.size > 400000) { alert('Please use a logo under 400 KB.'); return; }
      var rd = new FileReader();
      rd.onload = function () { db.settings.logo = rd.result; save(); toast('Logo updated'); route(); };
      rd.readAsDataURL(file);
    });
    on('#rmLogo', 'click', function () { db.settings.logo = ''; save(); route(); });
    $('#sf').addEventListener('submit', function (e) {
      e.preventDefault();
      var data = formData(e.target);
      ['taxRate', 'quoteValidDays', 'invoiceDueDays'].forEach(function (k) { data[k] = Number(data[k]) || 0; });
      data.services = $$('#svcRows tr').map(function (tr) {
        return { name: $('[data-k=name]', tr).value.trim(), unit: $('[data-k=unit]', tr).value, price: Number($('[data-k=price]', tr).value) || 0 };
      }).filter(function (x) { return x.name; });
      Object.assign(db.settings, data); save(); toast('Settings saved'); route();
    });
    $('#exp').addEventListener('click', function () {
      download(new Blob([JSON.stringify(db, null, 2)], { type: 'application/json' }), 'gold-simba-invoicing-backup-' + today() + '.json');
    });
    $('#imp').addEventListener('change', function (e) {
      var file = e.target.files[0]; if (!file) return;
      var rd = new FileReader();
      rd.onload = function () {
        try {
          var d = JSON.parse(rd.result);
          if (!d || !Array.isArray(d.docs) || !Array.isArray(d.clients)) throw new Error('Not a valid backup file');
          if (!confirm('Replace ALL current data with this backup (' + d.clients.length + ' clients, ' + d.docs.length + ' documents)?')) return;
          localStorage.setItem(STORE_KEY, JSON.stringify(d)); db = load(); save();
          if (db.auth) localStorage.setItem('gsr_invoicing_auth', JSON.stringify(db.auth));
          toast('Backup restored'); route();
        } catch (err) { alert('Could not restore: ' + err.message); }
      };
      rd.readAsText(file);
    });
    $('#wipe').addEventListener('click', function () {
      if (prompt('Type ERASE to permanently delete all invoicing data in this browser. Download a backup first!') !== 'ERASE') return;
      db = defaults(); save(); toast('All data erased'); route();
    });
  }

  // ---------- generic ui ----------
  function fld(name, lbl, val, attrs) {
    return '<div class="field"><label for="f_' + name + '">' + esc(lbl) + '</label><input id="f_' + name + '" name="' + name + '" value="' + esc(val == null ? '' : val) + '" ' + (attrs || '') + '></div>';
  }
  function formData(form) {
    var o = {};
    $$('[name]', form).forEach(function (el) { if (el.type !== 'file') o[el.name] = el.value; });
    return o;
  }
  function openModal(title, html) {
    $('#modalTitle').textContent = title; $('#modalBody').innerHTML = html; $('#modal').hidden = false;
    var first = $('#modalBody input, #modalBody select'); if (first) first.focus();
  }
  function closeModal() { $('#modal').hidden = true; $('#modalBody').innerHTML = ''; }
  $('#modal').addEventListener('click', function (e) { if (e.target.id === 'modal' || e.target.hasAttribute('data-close')) closeModal(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !$('#modal').hidden) closeModal(); });
  $('#menuBtn').addEventListener('click', function () { $('#side').classList.toggle('open'); });

  function downloadCSV(rows, name) {
    var csv = rows.map(function (r) { return r.map(function (v) { v = v == null ? '' : String(v); return /[",\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; }).join(','); }).join('\r\n');
    download(new Blob(['﻿' + csv], { type: 'text/csv' }), name);
  }
  function download(blob, name) {
    var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = name;
    document.body.appendChild(a); a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 500);
  }

  // ---------- staff login ----------
  // Only a salted SHA-256 hash of the password is kept here, never the password.
  // A password changed in Settings is saved (as a hash) under AUTH_KEY in this browser.
  var AUTH_KEY = 'gsr_invoicing_auth';
  var SESSION_KEY = 'gsr_invoicing_session';
  var IDLE_MS = 30 * 60 * 1000;
  var DEFAULT_USERS = { admin: '447bae246126e09e905683cd86f74580bee49e1840f49f5c2d70b1d8e8aa7a10' };

  function sha256(str) {
    var K = [0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5, 0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3, 0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174, 0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da, 0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967, 0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13, 0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85, 0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070, 0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3, 0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208, 0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2];
    var bytes = Array.prototype.slice.call(new TextEncoder().encode(str));
    var bitLen = bytes.length * 8;
    bytes.push(0x80);
    while (bytes.length % 64 !== 56) bytes.push(0);
    for (var s = 56; s >= 0; s -= 8) bytes.push(s >= 32 ? Math.floor(bitLen / Math.pow(2, s)) & 0xff : (bitLen >>> s) & 0xff);
    var H = [0x6a09e667, 0xbb67ae85, 0x3c6ef372, 0xa54ff53a, 0x510e527f, 0x9b05688c, 0x1f83d9ab, 0x5be0cd19], W = new Array(64);
    function rot(x, n) { return (x >>> n) | (x << (32 - n)); }
    for (var i = 0; i < bytes.length; i += 64) {
      for (var t = 0; t < 64; t++) {
        if (t < 16) W[t] = (bytes[i + t * 4] << 24) | (bytes[i + t * 4 + 1] << 16) | (bytes[i + t * 4 + 2] << 8) | bytes[i + t * 4 + 3];
        else {
          var s0 = rot(W[t - 15], 7) ^ rot(W[t - 15], 18) ^ (W[t - 15] >>> 3), s1 = rot(W[t - 2], 17) ^ rot(W[t - 2], 19) ^ (W[t - 2] >>> 10);
          W[t] = (W[t - 16] + s0 + W[t - 7] + s1) | 0;
        }
      }
      var a = H[0], b = H[1], c = H[2], d = H[3], e = H[4], f = H[5], g = H[6], h = H[7];
      for (t = 0; t < 64; t++) {
        var T1 = (h + (rot(e, 6) ^ rot(e, 11) ^ rot(e, 25)) + ((e & f) ^ (~e & g)) + K[t] + W[t]) | 0;
        var T2 = ((rot(a, 2) ^ rot(a, 13) ^ rot(a, 22)) + ((a & b) ^ (a & c) ^ (b & c))) | 0;
        h = g; g = f; f = e; e = (d + T1) | 0; d = c; c = b; b = a; a = (T1 + T2) | 0;
      }
      H = [H[0] + a, H[1] + b, H[2] + c, H[3] + d, H[4] + e, H[5] + f, H[6] + g, H[7] + h].map(function (x) { return x | 0; });
    }
    return H.map(function (x) { return ('00000000' + (x >>> 0).toString(16)).slice(-8); }).join('');
  }
  function hashPw(user, pw) { return sha256('gsr-invoicing|' + user.toLowerCase() + '|' + pw); }
  function users() {
    try { return Object.assign({}, DEFAULT_USERS, JSON.parse(localStorage.getItem(AUTH_KEY) || '{}')); }
    catch (e) { return Object.assign({}, DEFAULT_USERS); }
  }
  function getSession() {
    try {
      var s = JSON.parse(sessionStorage.getItem(SESSION_KEY) || 'null');
      if (s && s.user && Date.now() - s.last < IDLE_MS && users()[s.user]) return s;
    } catch (e) {}
    return null;
  }
  function touch() {
    var s = getSession();
    if (s) { s.last = Date.now(); try { sessionStorage.setItem(SESSION_KEY, JSON.stringify(s)); } catch (e) {} }
  }
  function lock(msg) {
    try { sessionStorage.removeItem(SESSION_KEY); } catch (e) {}
    view.innerHTML = ''; closeModal();
    document.body.classList.add('locked');
    $('#lp').value = '';
    var err = $('#loginErr'); err.hidden = !msg; err.textContent = msg || '';
    document.title = 'Staff sign-in — Gold SIMBA Invoicing';
    setTimeout(function () { ($('#lu').value ? $('#lp') : $('#lu')).focus(); }, 0);
  }
  function unlock(user) {
    document.body.classList.remove('locked');
    $('#whoami').textContent = user.toUpperCase();
    route();
  }
  var fails = 0, lockedUntil = 0;
  $('#loginForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var err = $('#loginErr');
    if (Date.now() < lockedUntil) {
      err.textContent = 'Too many attempts. Try again in ' + Math.ceil((lockedUntil - Date.now()) / 1000) + ' seconds.'; err.hidden = false; return;
    }
    var u = $('#lu').value.trim().toLowerCase(), p = $('#lp').value;
    var stored = users()[u];
    if (stored && stored === hashPw(u, p)) {
      fails = 0;
      try { sessionStorage.setItem(SESSION_KEY, JSON.stringify({ user: u, last: Date.now() })); } catch (x) {}
      err.hidden = true; $('#lp').value = '';
      unlock(u);
    } else {
      fails++;
      if (fails >= 5) { lockedUntil = Date.now() + 60000; fails = 0; }
      err.textContent = 'Incorrect username or password.'; err.hidden = false;
      $('#lp').value = ''; $('#lp').focus();
    }
  });
  $('#logoutBtn').addEventListener('click', function () { lock(''); });
  ['click', 'keydown', 'input'].forEach(function (ev) { document.addEventListener(ev, touch, true); });
  setInterval(function () {
    if (!document.body.classList.contains('locked') && !getSession()) lock('You were signed out after 30 minutes of inactivity.');
  }, 30000);

  // change-password form, rendered at the end of Settings
  var baseSettings = showSettings;
  showSettings = function () {
    baseSettings();
    var s = getSession();
    view.insertAdjacentHTML('beforeend',
      '<div class="section card"><h3>Change password</h3>' +
      '<p style="margin-top:0;font-size:13px">Signed in as <b>' + esc(s ? s.user.toUpperCase() : '') + '</b>. The new password applies to this browser; it is also kept inside backups you download from now on.</p>' +
      '<form id="pwf"><div class="grid">' +
      '<div class="field"><label>Current password</label><input type="password" name="cur" autocomplete="current-password" required></div>' +
      '<div class="field"><label>New password</label><input type="password" name="nw" autocomplete="new-password" minlength="8" required></div>' +
      '<div class="field"><label>Confirm new password</label><input type="password" name="cf" autocomplete="new-password" minlength="8" required></div>' +
      '</div><button class="btn btn-gold">Update password</button></form></div>');
    $('#pwf').addEventListener('submit', function (e) {
      e.preventDefault();
      var f = e.target, sess = getSession();
      if (!sess) return lock('Please sign in again.');
      if (users()[sess.user] !== hashPw(sess.user, f.cur.value)) { alert('Current password is incorrect.'); return; }
      if (f.nw.value.length < 8) { alert('Use at least 8 characters.'); return; }
      if (f.nw.value !== f.cf.value) { alert('New passwords do not match.'); return; }
      var custom = {};
      try { custom = JSON.parse(localStorage.getItem(AUTH_KEY) || '{}'); } catch (x) {}
      custom[sess.user] = hashPw(sess.user, f.nw.value);
      try { localStorage.setItem(AUTH_KEY, JSON.stringify(custom)); } catch (x) { alert('Could not save the new password.'); return; }
      db.auth = custom; save();
      f.reset(); toast('Password updated');
    });
  };

  // a backup restored onto another computer brings its changed password with it
  if (db.auth && !localStorage.getItem(AUTH_KEY)) {
    try { localStorage.setItem(AUTH_KEY, JSON.stringify(db.auth)); } catch (e) {}
  }

  var sess = getSession();
  if (sess) unlock(sess.user); else lock('');
})();
