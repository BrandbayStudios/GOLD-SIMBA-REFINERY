# Hostinger deploy package

`brandbay-app.zip` is the `brandbay/` web app packaged for Hostinger's Node.js build
(Node 24, Express, entry file `server.js`). Hostinger clones this repository and builds
from the archive, because file upload and GitHub auto-deploy were unavailable on the account.
Regenerate it after changing the app: `git archive --format=zip -o deploy/brandbay-app.zip HEAD:brandbay`.
