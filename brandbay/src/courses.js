// Short courses BrandBay teaches. Fees are starting prices in Kwacha; edit here and redeploy.
// Certificates are BrandBay certificates and not national qualifications.
const COURSES = [
  {
    slug: 'graphic-design-basics', name: 'Graphic Design Basics', category: 'Creative & Brand', icon: 'pen',
    duration: '4 weeks · 2 evenings a week', fee: 1200, level: 'Beginner',
    tagline: 'Design a logo, a flyer and a social media post from scratch.',
    topics: ['Colour, type and layout basics', 'Canva and Photoshop essentials', 'Designing a logo', 'Flyers, posters and social posts', 'Preparing files for print'],
    forWho: 'Students, job seekers and small business owners',
  },
  {
    slug: 'logo-and-brand-identity', name: 'Logo and Brand Identity', category: 'Creative & Brand', icon: 'spark',
    duration: '2 weekends', fee: 2000, level: 'Beginner to intermediate',
    tagline: 'Build a small brand kit: logo, colours, fonts, business card and letterhead.',
    topics: ['What makes a brand', 'Logo concepts and sketching', 'Colour and typography choices', 'Business card and letterhead', 'Brand guidelines and mock-ups'],
    forWho: 'Founders and designers who want to brand a business properly',
  },
  {
    slug: 'full-corporate-branding', name: 'Full Corporate Branding', category: 'Creative & Brand', icon: 'star',
    duration: '1 week', fee: 3500, level: 'Intermediate',
    tagline: 'Brand a whole organisation: identity, stationery, vehicles, signage and guidelines.',
    topics: ['Brand strategy and positioning', 'Logo and visual identity system', 'Stationery, ID cards and email signatures', 'Vehicle, signage and uniform branding', 'Brand guidelines and roll-out plan'],
    forWho: 'Designers, marketing officers and owners branding a company',
  },
  {
    slug: 'build-your-first-website', name: 'Build Your First Website', category: 'Tech & Web', icon: 'code',
    duration: '4 weeks', fee: 3000, level: 'Beginner',
    tagline: 'Launch a simple business website on WordPress or a website builder.',
    topics: ['Domains and hosting', 'Planning pages and content', 'Building with WordPress or a builder', 'Contact forms and WhatsApp buttons', 'Basic search engine optimisation'],
    forWho: 'Business owners and beginners with no coding experience',
  },
  {
    slug: 'computer-essentials-skills', name: 'Computer Essentials Skills', category: 'Tech & Web', icon: 'check',
    duration: '3 weeks', fee: 1500, level: 'Beginner',
    tagline: 'Office apps, email, safe internet use and cloud storage.',
    topics: ['Typing and file management', 'Word, Excel and PowerPoint basics', 'Email and online forms', 'Cloud storage and sharing', 'Staying safe online'],
    forWho: 'Anyone new to computers',
  },
  {
    slug: 'cyber-safety-for-everyone', name: 'Cyber Safety for Everyone', category: 'Tech & Web', icon: 'star',
    duration: '1 day', fee: 400, level: 'Beginner',
    tagline: 'Protect accounts, phones and information from scams and hackers.',
    topics: ['How attackers work', 'Passwords and two-factor authentication', 'Spotting phishing and fake messages', 'Phone and social media safety', 'What to do after an incident'],
    forWho: 'Companies, schools, churches, NGOs and public institutions. Fee is per person; group rates available.',
  },
  {
    slug: 'social-media-management', name: 'Social Media Management', category: 'Marketing & Business', icon: 'spark',
    duration: '3 weeks', fee: 1000, level: 'Beginner',
    tagline: 'Plan, design and schedule a month of posts and read the results.',
    topics: ['Choosing platforms and goals', 'Content calendars', 'Designing posts and short videos', 'Scheduling tools', 'Reading basic analytics'],
    forWho: 'Staff and freelancers who manage pages for businesses',
  },
  {
    slug: 'digital-marketing-for-small-business', name: 'Digital Marketing for Small Business', category: 'Marketing & Business', icon: 'arrow',
    duration: '2 weekends', fee: 800, level: 'Beginner',
    tagline: 'Google Business profile, WhatsApp Business, simple ads and customer reviews.',
    topics: ['Google Business profile setup', 'WhatsApp Business tools', 'Running a simple Facebook and Instagram ad', 'Getting and using customer reviews', 'Tracking what works'],
    forWho: 'Small business owners and shop managers',
  },
];

// One-week (5-day) workshops BrandBay delivers to institutions, at the client's venue or ours. Priced by quote.
const WORKSHOPS = [
  {
    slug: 'cyber-awareness-week', name: 'Cyber Security Awareness Week', icon: 'star',
    tagline: 'Protect people, phones, accounts and information from scams, hackers and leaks.',
    days: ['Day 1: threats, attackers and how attacks unfold', 'Day 2: passwords and two-factor lab', 'Day 3: phishing, fake messages and social engineering', 'Day 4: phones, social media and information handling', 'Day 5: cyber law, incident response drill and certificates'],
    forWho: 'Security services, ministries, councils, banks and schools',
  },
  {
    slug: 'digital-skills-week', name: 'Digital Skills for Staff Week', icon: 'check',
    tagline: 'Everyday computer, email and cloud skills so staff work faster and safer.',
    days: ['Day 1: computers, files and typing', 'Day 2: Word and document formatting', 'Day 3: Excel for records and reports', 'Day 4: email, calendars and cloud sharing', 'Day 5: PowerPoint, online safety and certificates'],
    forWho: 'Administrative and support staff',
  },
  {
    slug: 'communications-week', name: 'Communications and Social Media Week', icon: 'spark',
    tagline: 'Run the institution’s pages, announcements and public messaging professionally.',
    days: ['Day 1: audience, tone and message planning', 'Day 2: content calendars and writing for social media', 'Day 3: designing posts and short videos', 'Day 4: scheduling, moderation and handling criticism', 'Day 5: reporting results and certificates'],
    forWho: 'Public relations, communications and marketing teams',
  },
  {
    slug: 'design-and-branding-week', name: 'Design and Branding Week', icon: 'pen',
    tagline: 'Produce consistent flyers, posters, ID cards and reports in the institution’s own style.',
    days: ['Day 1: design principles, colour and type', 'Day 2: Canva and Photoshop essentials', 'Day 3: posters, flyers and banners', 'Day 4: brand guidelines, letterheads and templates', 'Day 5: preparing files for print and certificates'],
    forWho: 'Communications teams, schools and associations',
  },
  {
    slug: 'website-presence-week', name: 'Website and Online Presence Week', icon: 'code',
    tagline: 'Plan, update and secure the institution’s website and online listings.',
    days: ['Day 1: domains, hosting and website planning', 'Day 2: building pages with WordPress or a builder', 'Day 3: content, images and forms', 'Day 4: search visibility and Google Business profile', 'Day 5: security, maintenance and certificates'],
    forWho: 'ICT officers, communications staff and administrators',
  },
  {
    slug: 'digital-marketing-week', name: 'Digital Marketing and Customer Reach Week', icon: 'arrow',
    tagline: 'Reach more clients through WhatsApp Business, Google, social media and simple ads.',
    days: ['Day 1: goals, audience and offers', 'Day 2: WhatsApp Business and customer service', 'Day 3: Facebook and Instagram ads on a small budget', 'Day 4: Google reviews, listings and email', 'Day 5: tracking results and certificates'],
    forWho: 'SACCOs, cooperatives, SMEs and business associations',
  },
];

const workshopBySlug = (slug) => WORKSHOPS.find((w) => w.slug === slug);

const bySlug = (slug) => COURSES.find((c) => c.slug === slug);
const courseService = (c) => `Course — ${c.name} (from K${c.fee.toLocaleString('en-US')})`;
const categories = () => [...new Set(COURSES.map((c) => c.category))].map((name) => ({ name, courses: COURSES.filter((c) => c.category === name) }));

module.exports = { COURSES, WORKSHOPS, workshopBySlug, bySlug, courseService, categories };
