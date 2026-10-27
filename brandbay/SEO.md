# SEO for brandbaystudios.online

## 1. Audit of the current site (Hostinger Website Builder, October 2026)

| Problem | Why it matters |
|---|---|
| Google returns **no results for "BrandBay Innovation Studios"**, even for the exact name | The site is effectively invisible; competitors show up through directories (TechBehemoths, Sortlist, DesignRush) |
| **The contact email doesn't work.** The site lists `info@brandbaystudio.online` (no "s"), but that domain does not exist. `brandbaystudios.online` has no email (MX) records either | Enquiries sent by email are lost. Mismatched contact details also weaken local SEO |
| No `sitemap.xml` (404), and `robots.txt` returns an HTML page | Google has no map of the site |
| Home, Projects, Gallery and Store have **no H1**. About wraps a whole section of text in one H1, and Services/Contact use "Get In Touch With Us" as the H1 | Google can't tell what each page is about |
| Titles are generic ("Elevate Your Business", "Graphic & Web Design"), Store and Contact share the same title and description, and no page mentions **Lusaka** or **Zambia** | Local searches such as "logo design Lusaka" can't match the site |
| `www.brandbaystudios.online` serves a duplicate of the site instead of redirecting | Splits ranking signals between two addresses |
| Pages are 115–355 KB of HTML each, before images | Slow on mobile data |
| Structured data is only a name, URL and description | No address, phone, services or location for Google to use |
| The business name varies: "Brandbay Innovation Studios", "BrandBay Studio", and "BrandBay Innovation Studio." on the logo | Inconsistent names make it harder for Google to link listings to one business |

## 2. Built into the new web app

- **Titles and descriptions:** a unique, keyword-targeted title (≤ ~60 characters) and description (≤ 160 characters) on every page, built around Lusaka and Zambia: "Branding & Web Design Agency in Lusaka, Zambia", "Logo Design… in Lusaka, Zambia" and so on.
- **Headings:** exactly one H1 per page.
- **Unique content:** each of the 6 service pages has its own Lusaka- and Zambia-specific introduction.
- **Structured data (JSON-LD):**
  - `ProfessionalService` on every page, with address, phone, email, logo, area served and the full service catalogue
  - `Service` on service pages, `CreativeWork` on projects, `BlogPosting` on articles, `Product` with price on store items
  - `FAQPage` on the contact page and `BreadcrumbList` on inner pages
- **Crawling:**
  - `/sitemap.xml` lists every public page, plus image entries for all 35 logos and posters, so they can appear in Google Images.
  - `/robots.txt` blocks admin, account, cart, checkout and invoice pages.
  - Private and utility pages carry `noindex`, and public pages carry a canonical URL.
  - `www.` permanently redirects to the main domain.
- **Social sharing:** Open Graph and Twitter tags with the logo share image, so links look good on WhatsApp, Facebook and LinkedIn.
- **Speed:** gzip compression (homepage HTML 41 KB → 7 KB), WebP images, lazy loading and long cache times on static files.
- **Verification:** Google Search Console and Bing verification codes can be pasted into *Admin → Settings*.
- **Also included in this update:** two security patches to dependencies (multer, body-parser).

## 3. What you need to do (in this order)

1. **Fix email first.** Set up a mailbox on the real domain (e.g. `info@brandbaystudios.online` in Hostinger → Emails). Then update the email in *Admin → Settings*, your Google Business Profile, social media bios and printed materials, so the same address appears everywhere.
2. **Pick one business name and use it everywhere.** We recommend **BrandBay Innovation Studios** (the name used on the site and in the structured data). Use the same name, address and phone number on every listing.
3. **Deploy the new site** to brandbaystudios.online with `BASE_URL=https://brandbaystudios.online` set, so canonical URLs and the sitemap use the live domain.
4. **Google Search Console** (search.google.com/search-console):
   - Add the domain and paste the verification code into *Admin → Settings*.
   - Submit `https://brandbaystudios.online/sitemap.xml`.
   - Request indexing for the home page and the 6 service pages.
5. **Bing Webmaster Tools:** import the site from Search Console and submit the same sitemap.
6. **Google Business Profile** (business.google.com). This is the biggest win for "near me" and Lusaka searches.
   - Category: *Graphic designer* (primary), plus *Marketing agency*, *Website designer* and *Print shop*.
   - Use the Cairo Road address, phone +260 972 015 689, and website `https://brandbaystudios.online`.
   - Add your services, logo, cover photo and 10+ portfolio images.
   - Ask every happy client for a Google review. Eugine Mavunga and Wilkins Sikazwe are a good start.
7. **Social profiles:** put your Facebook, Instagram and LinkedIn URLs in *Admin → Settings* (they feed `sameAs` in the structured data), and link back to the website from each profile.
8. **Directory listings** (free backlinks plus visibility where competitors already appear): TechBehemoths (Lusaka branding and logo design), Sortlist, DesignRush, Clutch, Behance (portfolio), and Zambian business directories. Use identical name, address and phone details on each.

## 4. Content plan: one article a month in *Admin → Blog posts*

Write for questions Zambian business owners actually search, and link each article to the matching service page.

- How to register a business with PACRA in Zambia (step-by-step) → Compliance & Business Registration
- ZPPA supplier registration: how to qualify for government tenders → Compliance
- How much does a logo cost in Zambia? → Graphic Design & Brand Identity
- What every small business website in Zambia needs (with Mobile Money payments) → Technological Solutions
- Facebook vs Instagram vs TikTok ads for Zambian businesses → Digital Advertising & Marketing
- Pull-up banner and flyer printing guide: sizes, paper and costs in Lusaka → Printing
- Case studies: turn each project (Altura, Mauya Loans, Daily Devotion…) into a short story covering the brief, the process and the result. Add the story in *Admin → Projects → Case study*.

## 5. Keywords to target

Primary: *branding agency Lusaka*, *logo design Lusaka / Zambia*, *graphic designer Lusaka*, *website design Zambia*, *digital marketing agency Zambia*, *printing Lusaka*, *business cards Lusaka*, *pull up banners Lusaka*, *PACRA registration help*, *ZPPA registration*.

Track them monthly in Search Console (Performance → Queries).
