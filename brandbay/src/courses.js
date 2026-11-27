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
    duration: '2 weekends', fee: 900, level: 'Beginner to intermediate',
    tagline: 'Build a small brand kit: logo, colours, fonts, business card and letterhead.',
    topics: ['What makes a brand', 'Logo concepts and sketching', 'Colour and typography choices', 'Business card and letterhead', 'Brand guidelines and mock-ups'],
    forWho: 'Founders and designers who want to brand a business properly',
  },
  {
    slug: 'vehicle-and-signage-design', name: 'Vehicle and Signage Design', category: 'Creative & Brand', icon: 'star',
    duration: '1 week', fee: 1500, level: 'Intermediate',
    tagline: 'Design vehicle wraps and signs and prepare print-ready files.',
    topics: ['Measuring vehicles and signs', 'Templates and bleed', 'Designing for visibility', 'Vinyl, banners and materials', 'Mock-ups and client approval'],
    forWho: 'Designers and print shop staff',
  },
  {
    slug: 'build-your-first-website', name: 'Build Your First Website', category: 'Tech & Web', icon: 'code',
    duration: '4 weeks', fee: 1500, level: 'Beginner',
    tagline: 'Launch a simple business website on WordPress or a website builder.',
    topics: ['Domains and hosting', 'Planning pages and content', 'Building with WordPress or a builder', 'Contact forms and WhatsApp buttons', 'Basic search engine optimisation'],
    forWho: 'Business owners and beginners with no coding experience',
  },
  {
    slug: 'computer-and-digital-skills', name: 'Computer and Digital Skills', category: 'Tech & Web', icon: 'check',
    duration: '3 weeks', fee: 600, level: 'Beginner',
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

const bySlug = (slug) => COURSES.find((c) => c.slug === slug);
const courseService = (c) => `Course — ${c.name} (from K${c.fee.toLocaleString('en-US')})`;
const categories = () => [...new Set(COURSES.map((c) => c.category))].map((name) => ({ name, courses: COURSES.filter((c) => c.category === name) }));

module.exports = { COURSES, bySlug, courseService, categories };
