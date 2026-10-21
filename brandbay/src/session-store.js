const session = require('express-session');
const { run, get } = require('./db');

const DAY = 24 * 60 * 60 * 1000;

// Minimal express-session store backed by the app's SQLite database.
class SqliteStore extends session.Store {
  constructor() {
    super();
    this.prune = setInterval(() => run('DELETE FROM sessions WHERE expires < ?', Date.now()), 60 * 60 * 1000);
    this.prune.unref();
  }

  expiry(sess) {
    const exp = sess?.cookie?.expires;
    return exp ? new Date(exp).getTime() : Date.now() + DAY;
  }

  get(sid, cb) {
    try {
      const row = get('SELECT data, expires FROM sessions WHERE sid = ?', sid);
      if (!row || row.expires < Date.now()) return cb(null, null);
      cb(null, JSON.parse(row.data));
    } catch (err) {
      cb(err);
    }
  }

  set(sid, sess, cb) {
    try {
      run(
        'INSERT INTO sessions (sid, data, expires) VALUES (?, ?, ?) ON CONFLICT(sid) DO UPDATE SET data = excluded.data, expires = excluded.expires',
        sid, JSON.stringify(sess), this.expiry(sess),
      );
      cb?.(null);
    } catch (err) {
      cb?.(err);
    }
  }

  touch(sid, sess, cb) {
    try {
      run('UPDATE sessions SET expires = ? WHERE sid = ?', this.expiry(sess), sid);
      cb?.(null);
    } catch (err) {
      cb?.(err);
    }
  }

  destroy(sid, cb) {
    try {
      run('DELETE FROM sessions WHERE sid = ?', sid);
      cb?.(null);
    } catch (err) {
      cb?.(err);
    }
  }
}

module.exports = SqliteStore;
