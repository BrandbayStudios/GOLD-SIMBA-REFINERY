// Usage: npm run create-admin -- you@example.com "Strong password" "Your Name"
// Creates an admin account, or promotes and resets the password of an existing one.
const bcrypt = require('bcryptjs');
const { run } = require('../src/db');
const { isEmail } = require('../src/lib');

const [email, password, name = 'BrandBay Admin'] = process.argv.slice(2);

if (!isEmail(email) || !password || password.length < 8) {
  console.error('Usage: npm run create-admin -- <email> <password (8+ chars)> [name]');
  process.exit(1);
}

run(
  `INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin')
   ON CONFLICT(email) DO UPDATE SET role = 'admin', password_hash = excluded.password_hash`,
  name, email.trim(), bcrypt.hashSync(password, 12),
);
console.log(`Admin account ready: ${email}`);
