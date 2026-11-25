const nodemailer = require('nodemailer');
const config = require('./config');
const alerts = require('./alerts');

const transport = config.mail.host
  ? nodemailer.createTransport({
      host: config.mail.host,
      port: config.mail.port,
      secure: config.mail.secure,
      auth: config.mail.user ? { user: config.mail.user, pass: config.mail.pass } : undefined,
    })
  : null;

// Sends mail when SMTP is configured; otherwise logs it so nothing is lost in development.
// Never throws: a mail failure must not break the request that triggered it.
async function send({ to, subject, text }) {
  if (!to) return;
  if (!transport) {
    if (process.env.NODE_ENV !== 'test') console.log(`[mail:not-configured] to=${to} subject="${subject}"\n${text}\n`);
    return;
  }
  try {
    await transport.sendMail({ from: config.mail.from, to, subject, text });
  } catch (err) {
    console.error('[mail] failed to send:', err.message);
  }
}

function notifyStudio(subject, text) {
  alerts.notify(subject, text); // WhatsApp, if set up in Admin → Settings; never blocks or throws
  return send({ to: config.mail.notify, subject, text });
}

module.exports = { send, notifyStudio, enabled: Boolean(transport) };
