/* ==========================================================================
   EventHub Zambia — catalogue data
   Bundled demo data so every page works without the API. When the Laravel
   API (backend/) is reachable, api.js results replace these on the pages
   that support it.
   ========================================================================== */
(function () {
  var IMG = function (id, w) { return 'https://images.unsplash.com/photo-' + id + '?q=80&w=' + (w || 900) + '&auto=format&fit=crop'; };

  var CATEGORIES = [
    { slug: 'venues', name: 'Venues & Event Centres', icon: 'fa-landmark', count: 210 },
    { slug: 'mcs-djs', name: 'MCs & DJs', icon: 'fa-microphone', count: 168 },
    { slug: 'photography', name: 'Photo & Video', icon: 'fa-camera', count: 184 },
    { slug: 'catering', name: 'Caterers & Chefs', icon: 'fa-utensils', count: 176 },
    { slug: 'decor', name: 'Event Decorators', icon: 'fa-wand-magic-sparkles', count: 131 },
    { slug: 'makeup', name: 'Makeup Artists', icon: 'fa-palette', count: 143 },
    { slug: 'cakes', name: 'Cakes', icon: 'fa-cake-candles', count: 75 },
    { slug: 'cars', name: 'Car Hire & Decoration', icon: 'fa-car', count: 97 },
    { slug: 'tents', name: 'Tents', icon: 'fa-campground', count: 58 },
    { slug: 'chairs-tables', name: 'Chairs & Tables', icon: 'fa-chair', count: 64 },
    { slug: 'sound', name: 'Sound & Lighting', icon: 'fa-volume-high', count: 71 },
    { slug: 'planners', name: 'Wedding Planners', icon: 'fa-ring', count: 46 },
    { slug: 'choreographers', name: 'Choreographers', icon: 'fa-music', count: 52 },
    { slug: 'barbers', name: 'Barbers & Hairdressers', icon: 'fa-scissors', count: 121 },
    { slug: 'tailors', name: 'Tailors & Designers', icon: 'fa-shirt', count: 88 },
    { slug: 'matrons', name: 'Matrons', icon: 'fa-crown', count: 34 },
    { slug: 'pots-chafing-dishes', name: 'Pots & Chafing Dishes', icon: 'fa-fire-burner', count: 29 },
    { slug: 'other', name: 'Other Event Services', icon: 'fa-ellipsis', count: 40 }
  ];

  var CITIES = ['Lusaka', 'Ndola', 'Kitwe', 'Livingstone', 'Kabwe', 'Chingola', 'Solwezi', 'Chipata'];

  var EVENT_TYPES = ['wedding', 'kitchen party', 'birthday', 'corporate event', 'graduation', 'funeral gathering', 'baby shower', 'anniversary'];

  function v(o) { o.reviews = o.reviews || []; return o; }

  var VENDORS = [
    v({ id: 'silverleaf-gardens', name: 'Silverleaf Gardens', cat: 'venues', city: 'Lusaka', area: 'Kabulonga', km: 2.4, rating: 4.8, count: 96, price: 8500, unit: 'event', verified: true, featured: true,
        img: IMG('1519167758481-83f550bb49b3'), phone: '+260971234567',
        about: 'A garden venue for up to 450 guests, with a covered pavilion, bridal suite and on-site parking for 120 cars. Popular for weddings and kitchen parties from October to April.',
        gallery: ['1519167758481-83f550bb49b3', '1519225421980-715cb0215aed', '1464366400600-7168b8af9bc3', '1478146059778-26028b07395a'],
        packages: [['Garden only', 8500, 'Venue hire 8am–11pm, 200 chairs, cleaning'], ['Garden + Pavilion', 14500, 'Adds covered pavilion, 450 chairs, generator backup'], ['All-inclusive', 32000, 'Venue, décor, catering for 200, MC']],
        reviews: [['Natasha M.', 5, 'The grounds were immaculate and the staff kept everything on time. Our guests still talk about the lights in the trees.'], ['Brian K.', 4, 'Great space. Parking filled up quickly after 6pm, so plan a shuttle if you have many guests.']] }),
    v({ id: 'dj-mwansa-live', name: 'DJ Mwansa Live', cat: 'mcs-djs', city: 'Lusaka', area: 'Woodlands', km: 4.1, rating: 4.9, count: 132, price: 3200, unit: 'event', verified: true, featured: true,
        img: IMG('1516450360452-9312f5e86fc7'), phone: '+260966112233',
        about: 'DJ and bilingual MC (English & Bemba) playing Zambian, Afrobeats, amapiano and gospel sets. Brings his own sound system for events up to 300 guests.',
        gallery: ['1516450360452-9312f5e86fc7', '1470229538611-16ba8c7ffbd7', '1511578314322-379afb476865'],
        packages: [['DJ set (5 hrs)', 3200, 'DJ, speakers, wireless mic'], ['DJ + MC', 5200, 'Adds MC for programme and games'], ['Full production', 9800, 'DJ, MC, stage lighting, dance floor lights']],
        reviews: [['Chilufya B.', 5, 'Kept the dance floor full until midnight and handled the programme perfectly.']] }),
    v({ id: 'chanda-lens-studio', name: 'Chanda Lens Studio', cat: 'photography', city: 'Lusaka', area: 'Rhodes Park', km: 3.0, rating: 4.9, count: 88, price: 6500, unit: 'day', verified: true, featured: true,
        img: IMG('1519741497674-611481863552'), phone: '+260977445566',
        about: 'Wedding and portrait photography with a documentary style. Same-week previews, full gallery in three weeks, drone coverage available.',
        gallery: ['1519741497674-611481863552', '1515934751635-c81c6bc9a2d8', '1519225421980-715cb0215aed', '1535141192574-5d4897c12636'],
        packages: [['Half day', 3800, '5 hours, 250 edited photos'], ['Full day', 6500, '10 hours, 600 photos, online gallery'], ['Photo + Film', 12500, 'Two shooters, highlight film, drone']],
        reviews: [['Mutale & Joe', 5, 'Every photo feels like us. The previews arrived two days after the wedding.']] }),
    v({ id: 'mama-beulahs-kitchen', name: "Mama Beulah's Kitchen", cat: 'catering', city: 'Lusaka', area: 'Chelston', km: 6.8, rating: 4.7, count: 210, price: 180, unit: 'guest', verified: true,featured: true,
        img: IMG('1555244162-803834f70033'), phone: '+260955667788',
        about: 'Zambian and continental buffets for 50 to 1,000 guests. Nshima, village chicken, ifisashi and a full grill station. Includes servers and chafing dishes.',
        gallery: ['1555244162-803834f70033', '1464349153735-7db50ed83c84'],
        packages: [['Traditional buffet', 180, 'Per guest, 2 relishes, 2 meats'], ['Premium buffet', 260, 'Per guest, adds grill and dessert station']],
        reviews: [['Grace P.', 5, 'Food for 400 people and nobody waited long. The ifisashi was the hit of the day.']] }),
    v({ id: 'golden-aisle-decor', name: 'Golden Aisle Decor', cat: 'decor', city: 'Ndola', area: 'Kansenshi', km: 1.6, rating: 4.8, count: 74, price: 7500, unit: 'event', verified: true, featured: true,
        img: IMG('1478146059778-26028b07395a'), phone: '+260968990011',
        about: 'Floral and draping design for weddings and corporate dinners across the Copperbelt. Fresh and silk flower options, backdrops, centrepieces and lighting.',
        gallery: ['1478146059778-26028b07395a', '1464366400600-7168b8af9bc3', '1519225421980-715cb0215aed'],
        packages: [['Essentials', 7500, 'Backdrop, head table, 10 centrepieces'], ['Signature', 15000, 'Full venue draping, 25 centrepieces, aisle']],
        reviews: [['Ruth N.', 5, 'They turned a plain hall into something out of a magazine.']] }),
    v({ id: 'bella-glow-makeup', name: 'Bella Glow Makeup', cat: 'makeup', city: 'Kitwe', area: 'Parklands', km: 2.2, rating: 4.9, count: 61, price: 1500, unit: 'person', verified: true,
        img: IMG('1512496015851-a90fb38ba796'), phone: '+260979001122',
        about: 'Bridal and occasion makeup for all skin tones. Trial sessions available. Travels across the Copperbelt with a team of three.',
        gallery: ['1512496015851-a90fb38ba796', '1596462502278-27bfdc403348'],
        packages: [['Bride', 1500, 'Trial plus wedding-day makeup, lashes'], ['Bridal party (6)', 5400, 'Bride plus five']],
        reviews: [['Mwila S.', 5, 'My makeup lasted from 7am to midnight through tears and dancing.']] }),
    v({ id: 'sweet-nectar-cakes', name: 'Sweet Nectar Cakes', cat: 'cakes', city: 'Lusaka', area: 'Avondale', km: 5.3, rating: 4.8, count: 119, price: 1200, unit: 'cake', verified: true,featured: true,
        img: IMG('1535141192574-5d4897c12636'), phone: '+260976554433',
        about: 'Custom wedding, birthday and kitchen party cakes. Fondant and buttercream. Free tasting box with every wedding order.',
        gallery: ['1535141192574-5d4897c12636', '1464349153735-7db50ed83c84'],
        packages: [['Birthday (2 tier)', 1200, 'Serves 40'], ['Wedding (4 tier)', 4800, 'Serves 200, delivery and setup']],
        reviews: [['Daliso T.', 5, 'Tasted as good as it looked, which is rare.']] }),
    v({ id: 'royal-ride-car-hire', name: 'Royal Ride Car Hire', cat: 'cars', city: 'Lusaka', area: 'Longacres', km: 3.7, rating: 4.6, count: 47, price: 2800, unit: 'day', verified: true,
        img: IMG('1503376780353-7e6692767b70'), phone: '+260954332211',
        about: 'Chauffeured wedding cars, from vintage Mercedes to Range Rovers, with ribbon and floral decoration included.',
        gallery: ['1503376780353-7e6692767b70'],
        packages: [['Bridal car', 2800, 'One car, chauffeur, décor, 6 hours'], ['Convoy of 4', 9500, 'Bridal car plus three escort cars']],
        reviews: [['Kelvin M.', 4, 'Cars were spotless and on time.']] }),
    v({ id: 'copperbelt-tents-chairs', name: 'Copperbelt Tents & Chairs', cat: 'tents', city: 'Kitwe', area: 'Nkana East', km: 4.4, rating: 4.5, count: 83, price: 2500, unit: 'event', verified: true,
        img: IMG('1510076857177-7470076d4098'), phone: '+260962110022',
        about: 'Marquee tents from 50 to 600 guests, chairs, tables, linen and lighting. Setup and collection included within Kitwe and Ndola.',
        gallery: ['1510076857177-7470076d4098'],
        packages: [['100-guest tent', 2500, 'Tent, 100 chairs, 10 tables'], ['300-guest marquee', 7200, 'Marquee, chairs, tables, linen, lights']],
        reviews: [['Peter C.', 5, 'Set up before 7am and collected the next morning without fuss.']] }),
    v({ id: 'livingstone-riverside-hall', name: 'Livingstone Riverside Hall', cat: 'venues', city: 'Livingstone', area: 'Zambezi River', km: 7.9, rating: 4.7, count: 58, price: 12000, unit: 'event', verified: true, featured: true,
        img: IMG('1519225421980-715cb0215aed'), phone: '+260963443322',
        about: 'A riverside hall and lawn on the Zambezi, ten minutes from Victoria Falls. Sunset ceremonies, 250 guests, and accommodation partners nearby.',
        gallery: ['1519225421980-715cb0215aed', '1515934751635-c81c6bc9a2d8'],
        packages: [['Lawn ceremony', 12000, 'Ceremony setup, 150 chairs'], ['Ceremony + reception', 24000, 'Lawn plus hall for 250']],
        reviews: [['Anna & Lubasi', 5, 'A sunset ceremony by the river. Nothing else compares.']] }),
    v({ id: 'zambezi-sound-light', name: 'Zambezi Sound & Light', cat: 'sound', city: 'Lusaka', area: 'Industrial Area', km: 8.2, rating: 4.6, count: 39, price: 4500, unit: 'event', verified: false,
        img: IMG('1470229538611-16ba8c7ffbd7'), phone: '+260950998877',
        about: 'PA systems, stage lighting, LED screens and technicians for concerts, conferences and weddings.',
        gallery: ['1470229538611-16ba8c7ffbd7'],
        packages: [['Conference PA', 4500, 'Speakers, 4 mics, technician'], ['Concert rig', 18000, 'Line array, stage lights, LED wall']],
        reviews: [] }),
    v({ id: 'bliss-weddings-zambia', name: 'Bliss Weddings Zambia', cat: 'planners', city: 'Lusaka', area: 'Ibex Hill', km: 5.9, rating: 5.0, count: 33, price: 15000, unit: 'wedding', verified: true, featured: true,
        img: IMG('1515934751635-c81c6bc9a2d8'), phone: '+260971556677',
        about: 'Full and partial wedding planning, from budget and vendor shortlist to on-the-day coordination. We work with vendors listed on EventHub.',
        gallery: ['1515934751635-c81c6bc9a2d8', '1519741497674-611481863552', '1478146059778-26028b07395a'],
        packages: [['Day-of coordination', 15000, 'Run sheet, vendor liaison, 2 coordinators'], ['Full planning', 38000, '6-month planning, budget, design, coordination']],
        reviews: [['Thandiwe Z.', 5, 'They handled 14 vendors so we did not have to. Worth every kwacha.']] }),
    v({ id: 'nalu-choreography', name: 'Nalu Choreography', cat: 'choreographers', city: 'Lusaka', area: 'Olympia', km: 4.8, rating: 4.8, count: 27, price: 2200, unit: 'package', verified: true,
        img: IMG('1504609813442-a8924e83f76e'), phone: '+260975332200',
        about: 'Entrance dances and bridal party routines. Four rehearsals at your venue or our studio.',
        gallery: ['1504609813442-a8924e83f76e'],
        packages: [['Couple routine', 2200, '4 rehearsals, song edit'], ['Bridal party', 4800, 'Up to 12 dancers, 6 rehearsals']],
        reviews: [] }),
    v({ id: 'kapya-tailoring', name: 'Kapya Tailoring House', cat: 'tailors', city: 'Ndola', area: 'Northrise', km: 3.1, rating: 4.7, count: 52, price: 900, unit: 'outfit', verified: true,
        img: IMG('1528578577235-b963df6db908'), phone: '+260967221100',
        about: 'Chitenge and modern outfits for brides, grooms and families. Matching sets for bridal parties of any size.',
        gallery: ['1528578577235-b963df6db908'],
        packages: [['Single outfit', 900, 'Measure, fit, one adjustment'], ['Family set (6)', 4800, 'Matching chitenge outfits']],
        reviews: [] }),
    v({ id: 'crown-matrons', name: 'Crown Matrons', cat: 'matrons', city: 'Lusaka', area: 'Kalingalinga', km: 3.9, rating: 4.9, count: 21, price: 2000, unit: 'event', verified: true,
        img: IMG('1513885535751-8b9238bd345a'), phone: '+260958771122',
        about: 'Experienced matrons for kitchen parties, bridal showers and traditional ceremonies, guiding the bride and the programme with care.',
        gallery: ['1513885535751-8b9238bd345a'],
        packages: [['Kitchen party', 2000, 'Programme lead and bride guidance']],
        reviews: [] }),
    v({ id: 'sharp-cuts-studio', name: 'Sharp Cuts Studio', cat: 'barbers', city: 'Lusaka', area: 'Cairo Road', km: 1.8, rating: 4.6, count: 74, price: 350, unit: 'person', verified: false,
        img: IMG('1621605815971-fbc98d665033'), phone: '+260969554411',
        about: 'Grooming for grooms and groomsmen on the morning of the event, at your hotel or venue.',
        gallery: ['1621605815971-fbc98d665033'],
        packages: [['Groom', 350, 'Cut, beard, hot towel'], ['Groom + 6', 1900, 'On-location for the party']],
        reviews: [] })
  ];

  var EVENTS = [
    { id: 'lusaka-wedding-expo', title: 'Lusaka Wedding & Events Expo', date: '2026-12-05', city: 'Lusaka', venue: 'Mulungushi International Conference Centre', price: 100, img: IMG('1519225421980-715cb0215aed'), kind: 'Expo', blurb: 'Meet 120 vendors in one day: tastings, live décor builds and show-only discounts.' },
    { id: 'copperbelt-vendor-meetup', title: 'Copperbelt Vendor Meetup', date: '2026-12-12', city: 'Kitwe', venue: 'Garden Court Kitwe', price: 0, img: IMG('1511578314322-379afb476865'), kind: 'Networking', blurb: 'An evening for event businesses on the Copperbelt to meet, share referrals and hear from top-rated vendors.' },
    { id: 'falls-sunset-showcase', title: 'Victoria Falls Sunset Wedding Showcase', date: '2027-01-16', city: 'Livingstone', venue: 'Livingstone Riverside Hall', price: 250, img: IMG('1515934751635-c81c6bc9a2d8'), kind: 'Showcase', blurb: 'A styled riverside ceremony and reception, staged by six local vendors for couples planning a destination wedding.' },
    { id: 'kitchen-party-masterclass', title: 'Kitchen Party Planning Masterclass', date: '2027-01-30', city: 'Lusaka', venue: 'EventHub Studio, Rhodes Park', price: 150, img: IMG('1555244162-803834f70033'), kind: 'Workshop', blurb: 'Matrons and planners walk through budgets, programmes and gifts for a kitchen party that runs on time.' },
    { id: 'ndola-bridal-fashion', title: 'Ndola Bridal Fashion Evening', date: '2027-02-13', city: 'Ndola', venue: 'Savoy Hotel Ndola', price: 200, img: IMG('1528578577235-b963df6db908'), kind: 'Fashion', blurb: 'Chitenge couture and modern gowns from Copperbelt designers, with fittings booked on the night.' }
  ];

  window.EH_DATA = {
    IMG: IMG, CATEGORIES: CATEGORIES, CITIES: CITIES, EVENT_TYPES: EVENT_TYPES, VENDORS: VENDORS, EVENTS: EVENTS,
    category: function (slug) { return CATEGORIES.find(function (c) { return c.slug === slug; }); },
    vendor: function (id) { return VENDORS.find(function (x) { return x.id === id; }); }
  };
})();
