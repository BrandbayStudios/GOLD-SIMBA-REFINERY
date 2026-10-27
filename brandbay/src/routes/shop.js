const express = require('express');
const { all, get, run, transaction } = require('../db');
const lib = require('../lib');
const mailer = require('../mailer');
const config = require('../config');
const seo = require('../seo');
const { flash } = require('../middleware');

const router = express.Router();

const MAX_QTY = 999;

// The session cart only stores product ids and quantities; prices are always re-read from the database.
function cartDetails(req) {
  const cart = req.session.cart || [];
  const lines = [];
  for (const entry of cart) {
    const product = get('SELECT * FROM products WHERE id = ? AND published = 1', entry.id);
    if (!product) continue;
    lines.push({ product, qty: entry.qty, total: product.price * entry.qty });
  }
  const subtotal = lines.reduce((s, l) => s + l.total, 0);
  return { lines, subtotal };
}

function setCart(req, lines) {
  req.session.cart = lines.filter((l) => l.qty > 0).map((l) => ({ id: l.id, qty: Math.min(MAX_QTY, l.qty) }));
}

router.get('/store', (req, res) => {
  const category = lib.clean(req.query.category, 60);
  const products = category
    ? all('SELECT * FROM products WHERE published = 1 AND category = ? ORDER BY sort_order, id', category)
    : all('SELECT * FROM products WHERE published = 1 ORDER BY sort_order, id');
  const categories = all("SELECT DISTINCT category FROM products WHERE published = 1 AND category IS NOT NULL AND category != '' ORDER BY category").map((r) => r.category);
  res.render('store/index', {
    title: 'Printing & Design Store in Lusaka',
    description: 'Order business cards, flyers, posters, pull-up banners, brochures, stickers and logo design packages online from BrandBay Innovation Studios, Lusaka. Pay by Mobile Money or bank transfer.',
    schema: [seo.breadcrumbs([['Store', '/store']])],
    products,
    categories,
    category,
  });
});

router.get('/store/:slug', (req, res, next) => {
  const product = get('SELECT * FROM products WHERE slug = ? AND published = 1', req.params.slug);
  if (!product) return next();
  res.render('store/product', {
    title: `${product.name}${product.category === 'Printing' ? ' Printing' : ''} in Lusaka`,
    description: product.description,
    ogImage: product.image,
    schema: [seo.product(product), seo.breadcrumbs([['Store', '/store'], [product.name, `/store/${product.slug}`]])],
    product,
    related: all('SELECT * FROM products WHERE published = 1 AND id != ? ORDER BY (category = ?) DESC, sort_order LIMIT 3', product.id, product.category || ''),
  });
});

router.get('/cart', (req, res) => {
  res.render('store/cart', { title: 'Your cart', noindex: true, ...cartDetails(req) });
});

router.post('/cart/add', (req, res) => {
  const id = Number(req.body.product_id);
  const qty = Math.max(1, Math.min(MAX_QTY, parseInt(req.body.qty, 10) || 1));
  const product = get('SELECT id, name, stock FROM products WHERE id = ? AND published = 1', id);
  if (!product) {
    flash(req, 'error', 'That product is no longer available.');
    return res.redirect('/store');
  }
  const cart = (req.session.cart || []).map((l) => ({ ...l }));
  const line = cart.find((l) => l.id === id);
  if (line) line.qty += qty;
  else cart.push({ id, qty });
  setCart(req, cart);
  flash(req, 'success', `${product.name} added to your cart.`);
  res.redirect('/cart');
});

router.post('/cart/update', (req, res) => {
  const cart = (req.session.cart || []).map((l) => ({
    id: l.id,
    qty: req.body[`qty_${l.id}`] === undefined ? l.qty : Math.max(0, parseInt(req.body[`qty_${l.id}`], 10) || 0),
  }));
  if (req.body.remove) {
    const removeId = Number(req.body.remove);
    for (const l of cart) if (l.id === removeId) l.qty = 0;
  }
  setCart(req, cart);
  res.redirect('/cart');
});

router.get('/checkout', (req, res) => {
  const cart = cartDetails(req);
  if (!cart.lines.length) return res.redirect('/cart');
  res.render('store/checkout', {
    title: 'Checkout',
    noindex: true,
    ...cart,
    form: { name: req.user?.name, email: req.user?.email, phone: req.user?.phone, delivery_method: 'collect', payment_method: 'mobile_money' },
  });
});

router.post('/checkout', async (req, res) => {
  const cart = cartDetails(req);
  if (!cart.lines.length) return res.redirect('/cart');
  const form = {
    name: lib.clean(req.body.name, 120),
    email: lib.clean(req.body.email, 200),
    phone: lib.clean(req.body.phone, 40),
    address: lib.clean(req.body.address, 500),
    delivery_method: req.body.delivery_method === 'delivery' ? 'delivery' : 'collect',
    payment_method: lib.PAYMENT_METHODS[req.body.payment_method] ? req.body.payment_method : '',
    notes: lib.clean(req.body.notes, 2000),
  };
  const errors = [];
  if (!form.name) errors.push('Please enter your name.');
  if (!lib.isEmail(form.email)) errors.push('Please enter a valid email address.');
  if (!form.phone) errors.push('Please enter a phone number so we can confirm your order.');
  if (form.delivery_method === 'delivery' && !form.address) errors.push('Please enter a delivery address.');
  if (!form.payment_method) errors.push('Please choose a payment method.');
  for (const l of cart.lines) {
    if (l.product.stock !== null && l.qty > l.product.stock) errors.push(`Only ${l.product.stock} × ${l.product.name} left in stock.`);
  }
  if (errors.length) return res.status(422).render('store/checkout', { title: 'Checkout', noindex: true, ...cart, form, errors });

  const userId = req.user?.id ?? get('SELECT id FROM users WHERE email = ?', form.email)?.id ?? null;
  const order = transaction(() => {
    const { lastInsertRowid: id } = run(
      `INSERT INTO orders (user_id, name, email, phone, address, delivery_method, payment_method, notes, subtotal)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      userId, form.name, form.email, form.phone, form.address, form.delivery_method, form.payment_method, form.notes, cart.subtotal,
    );
    for (const l of cart.lines) {
      run('INSERT INTO order_items (order_id, product_id, name, unit_price, quantity, line_total) VALUES (?, ?, ?, ?, ?, ?)',
        id, l.product.id, l.product.name, l.product.price, l.qty, l.total);
      if (l.product.stock !== null) run('UPDATE products SET stock = stock - ? WHERE id = ?', l.qty, l.product.id);
    }
    const reference = lib.makeReference('ORD', id);
    run('UPDATE orders SET reference = ? WHERE id = ?', reference, id);
    return { id, reference };
  });

  const summary = cart.lines.map((l) => `${l.qty} × ${l.product.name} — ${lib.money(l.total)}`).join('\n');
  mailer.notifyStudio(`New order ${order.reference} (${lib.money(cart.subtotal)})`,
    `${form.name} <${form.email}> ${form.phone}\n${form.delivery_method === 'delivery' ? `Deliver to: ${form.address}` : 'Collect'}\nPayment: ${lib.PAYMENT_METHODS[form.payment_method]}\n\n${summary}\n\nNotes: ${form.notes}\n\n${config.baseUrl}/admin/orders/${order.id}`);
  mailer.send({
    to: form.email,
    subject: `Order received (${order.reference})`,
    text: `Hi ${form.name},\n\nThank you for your order with BrandBay Innovation Studios.\n\n${summary}\nTotal: ${lib.money(cart.subtotal)}\n\n${lib.getSettings().payment_instructions || ''}\n\nReference: ${order.reference}`,
  });

  req.session.cart = [];
  req.session.lastOrder = order.id;
  res.redirect('/checkout/complete');
});

router.get('/checkout/complete', (req, res) => {
  const order = req.session.lastOrder && get('SELECT * FROM orders WHERE id = ?', req.session.lastOrder);
  if (!order) return res.redirect('/store');
  const items = all('SELECT * FROM order_items WHERE order_id = ?', order.id);
  res.render('store/complete', { title: 'Order received', noindex: true, order, items });
});

module.exports = router;
