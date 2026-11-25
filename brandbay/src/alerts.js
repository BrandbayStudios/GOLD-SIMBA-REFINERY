// WhatsApp alerts to the studio owner for every new lead (contact message, quote request, estimate, order).
// Two providers, chosen in Admin → Settings:
//   callmebot: free, for the owner's own number (https://www.callmebot.com/blog/free-api-whatsapp-messages/)
//   meta:      WhatsApp Business Cloud API with an approved message template
// Credentials live in the settings table (never in code). Sending never throws and never delays a request.
const lib = require('./lib');

const TIMEOUT_MS = 8000;
const digits = (v) => String(v || '').replace(/\D/g, '');

function settings() {
  const s = lib.getSettings();
  return {
    provider: s.whatsapp_alert_provider || 'off',
    to: digits(s.whatsapp_alert_to),
    callmebotKey: s.callmebot_apikey || '',
    metaToken: s.meta_wa_token || '',
    metaPhoneId: digits(s.meta_wa_phone_id),
    metaTemplate: s.meta_wa_template || '',
    metaLang: s.meta_wa_lang || 'en',
  };
}

const enabled = () => {
  const c = settings();
  if (!c.to) return false;
  if (c.provider === 'callmebot') return Boolean(c.callmebotKey);
  if (c.provider === 'meta') return Boolean(c.metaToken && c.metaPhoneId && c.metaTemplate);
  return false;
};

// A short alert: the headline, the first few details and the admin link.
function format(subject, text) {
  const lines = String(text || '').split('\n').map((l) => l.trim()).filter(Boolean);
  const link = lines.find((l) => /^https?:\/\//.test(l)) || '';
  const body = lines.filter((l) => l !== link).join('\n').slice(0, 450);
  return { headline: subject, details: body, link };
}

async function deliver(c, { headline, details, link }) {
  const signal = AbortSignal.timeout(TIMEOUT_MS);
  if (c.provider === 'callmebot') {
    const text = `🔔 ${headline}\n${details}${link ? `\n${link}` : ''}`;
    const url = `https://api.callmebot.com/whatsapp.php?phone=${encodeURIComponent(c.to)}&text=${encodeURIComponent(text)}&apikey=${encodeURIComponent(c.callmebotKey)}`;
    const res = await fetch(url, { signal });
    if (!res.ok) throw new Error(`CallMeBot answered ${res.status}`);
    return;
  }
  // Template parameters cannot contain line breaks, so details are joined on one line.
  const one = (v) => String(v).replace(/\s*\n\s*/g, ' | ').replace(/ {2,}/g, ' ').slice(0, 900);
  const res = await fetch(`https://graph.facebook.com/v21.0/${c.metaPhoneId}/messages`, {
    method: 'POST',
    signal,
    headers: { Authorization: `Bearer ${c.metaToken}`, 'Content-Type': 'application/json' },
    body: JSON.stringify({
      messaging_product: 'whatsapp',
      to: c.to,
      type: 'template',
      template: {
        name: c.metaTemplate,
        language: { code: c.metaLang },
        components: [{ type: 'body', parameters: [{ type: 'text', text: one(headline) }, { type: 'text', text: one(`${details}${link ? ` | ${link}` : ''}`) }] }],
      },
    }),
  });
  if (!res.ok) throw new Error(`WhatsApp Cloud API answered ${res.status}`);
}

// Fire-and-forget for real leads.
function notify(subject, text) {
  if (!enabled()) return Promise.resolve({ ok: false, skipped: true });
  return deliver(settings(), format(subject, text))
    .then(() => ({ ok: true }))
    .catch((err) => {
      if (process.env.NODE_ENV !== 'test') console.error('[alerts] WhatsApp alert failed:', err.message);
      return { ok: false, error: err.message };
    });
}

// Used by the Settings page so the owner can see whether it works.
async function test() {
  if (!enabled()) return { ok: false, error: 'Choose a provider and fill in its details first, then save.' };
  return notify('Test alert from BrandBay', `If you can read this, new leads will reach you on WhatsApp.\n${require('./config').baseUrl}/admin/requests`);
}

module.exports = { notify, test, enabled, format };
