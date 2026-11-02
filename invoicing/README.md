# Gold SIMBA Refinery — Invoicing

Staff tool for **quotations, invoices, receipts and client records**, styled to match the main site.

## Features
- **Clients**: name, company, type (miner/trader/exporter…), phone, email, NRC/passport, TPIN, address and notes. Each client page shows totals invoiced, paid and owing, and has a printable **statement of account**.
- **Quotations**: line items with qty, unit (g, kg, oz, sample…) and price, plus discount and VAT. Status runs draft → sent → accepted/declined, and quotes expire automatically. Converting a quotation to an invoice takes one click.
- **Invoices**: status (unpaid, partial, overdue, paid, cancelled) is worked out from the payments recorded. Invoices carry bank / mobile-money details, and a PAID stamp appears once settled.
- **Receipts**: record a full or part payment (cash, bank, mobile money…) to issue a numbered receipt that shows the amount in words and the remaining balance.
- **Printing**: Print / Save PDF lays out a clean A4 page. Email opens a pre-filled message.
- Auto-numbering (`QUO-2026-0001`, `INV-…`, `RCT-…`), CSV export, a ZMW/USD price list of refinery services, and a logo upload.

## Using it
Upload the `invoicing/` folder to the website (e.g. `alfarisgoldrefinery.com/invoicing/`) and open it. No build step is needed. Set up company and bank details, VAT and prices under **Settings** first.

## Data & backups — important
Records are saved in the **browser's local storage on the device you use**. They are not shared between computers.
- Use **Settings → Download backup** regularly. Use **Restore** to move the data to another device.
- Clearing browser data erases the records unless you have a backup.
- The page has no login of its own. Use it only on a staff computer, or put the folder behind password protection on the host. The page is marked `noindex`.

All reading and writing goes through `load()` / `save()` in `invoicing.js`. To make the records shared and multi-user later, connect those two functions to the site's existing `/api` backend.
