const config = require('./src/config');
const { get } = require('./src/db');
const { seed } = require('./scripts/seed');
const app = require('./src/app');

// First boot on an empty database: load the studio's content so the site is never blank.
if (!get('SELECT id FROM service_categories LIMIT 1')) {
  seed();
  console.log('Seeded initial BrandBay content.');
}

app.listen(config.port, () => {
  console.log(`BrandBay Studios running at ${config.baseUrl}`);
});
