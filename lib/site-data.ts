export const siteNav = [
  { label: "Home", href: "/" },
  { label: "Services", href: "/services" },
  { label: "Packages", href: "/packages" },
  { label: "Track", href: "/track" },
  { label: "Gallery", href: "/gallery" },
  { label: "About", href: "/about" },
];

export const packages = [
  {
    name: "Food Pack Lunch",
    price: "PHP 129+",
    minimum: "10 packs",
    summary: "Packed meals for school, office, and family events with rice, viand, vegetables or noodles, and water.",
    image: "/images/packages/food-pack-lunch.jpg",
    inclusions: ["Rice meal", "Distilled water", "Choice of viands", "Optional softdrink add-on"],
  },
  {
    name: "Catering Package",
    price: "PHP 280+",
    minimum: "50 persons",
    summary: "Buffet catering package with flexible beef, pork, chicken, fish, vegetable, noodle, dessert, rice, and drink choices.",
    image: "/images/packages/catering-packages.jpg",
    inclusions: ["Buffet set up", "Tables and chairs", "Rice and drinks", "Four-hour catering service"],
  },
  {
    name: "Food Tray Menu",
    price: "PHP 600+",
    minimum: "12-15 pax per tray",
    summary: "Party trays for beef, pork, chicken, seafood, pasta, vegetables, noodles, and desserts.",
    image: "/images/packages/food-tray.jpg",
    inclusions: ["Tray good for sharing", "Wide menu selection", "Pickup or delivery coordination", "Add-on lechon belly options"],
  },
  {
    name: "Pica Pica",
    price: "PHP 4,500",
    minimum: "30 pax",
    summary: "Snack package with mini burgers, sandwiches, hotdogs, pasta, breads, fruits, juice, and party bites.",
    image: "/images/packages/pica-pica.jpg",
    inclusions: ["Mini snacks", "Fresh fruits", "Juice", "Party table presentation"],
  },
  {
    name: "Bellychon Package",
    price: "PHP 4,000+",
    minimum: "15 pax per tray",
    summary: "Lechon belly bundles with classic party favorites such as garlic chicken, beef steak, sotanghon, and lumpia.",
    image: "/images/packages/belly-lechon.jpg",
    inclusions: ["Lechon belly", "Party tray sides", "Good for group events", "Custom package options"],
  },
];

export const services = [
  "Buffet catering for weddings, birthdays, baptisms, debuts, and family events",
  "Corporate, office, school, and church event meals",
  "Food trays for smaller gatherings and shared party tables",
  "Packed lunch meals with minimum pack requirements",
  "Pica-pica snack tables for birthdays and casual celebrations",
  "Lechon belly and premium add-ons for event upgrades",
  "Manual transportation quotation based on venue location",
  "Special request review for halal, no pork, vegetarian, and allergy notes",
];

export const galleryImages = [
  "/images/packages/catering-packages.jpg",
  "/images/packages/food-pack-lunch.jpg",
  "/images/packages/food-tray.jpg",
  "/images/packages/inclusions.jpg",
  "/images/packages/pica-pica.jpg",
  "/images/packages/belly-lechon.jpg",
];

export const reservations = [
  {
    id: "ZK-1048",
    client: "Mara Santos",
    event: "Wedding Reception",
    date: "Jun 18, 2026",
    package: "Signature",
    guests: 120,
    status: "Confirmed",
    deposit: "Paid",
    owner: "Nico",
  },
  {
    id: "ZK-1049",
    client: "Northline Studio",
    event: "Product Launch",
    date: "Jun 21, 2026",
    package: "Atelier",
    guests: 85,
    status: "Proposal",
    deposit: "Pending",
    owner: "Ari",
  },
  {
    id: "ZK-1050",
    client: "Elaine Rivera",
    event: "Birthday Dinner",
    date: "Jun 25, 2026",
    package: "Gather",
    guests: 36,
    status: "New",
    deposit: "Unpaid",
    owner: "Sam",
  },
  {
    id: "ZK-1051",
    client: "Acme Finance",
    event: "Town Hall",
    date: "Jul 2, 2026",
    package: "Signature",
    guests: 180,
    status: "Confirmed",
    deposit: "Paid",
    owner: "Nico",
  },
];

export const menuItems = [
  { item: "Charcoal chicken roulade", category: "Main", cost: "$9.80", margin: "61%" },
  { item: "Roasted market vegetables", category: "Side", cost: "$3.40", margin: "72%" },
  { item: "Citrus panna cotta", category: "Dessert", cost: "$4.10", margin: "68%" },
  { item: "Smoked mushroom tart", category: "Starter", cost: "$2.90", margin: "74%" },
];

export const customers = [
  { name: "Mara Santos", email: "mara@example.com", events: 2, value: "$12,840" },
  { name: "Northline Studio", email: "events@northline.example", events: 4, value: "$28,200" },
  { name: "Elaine Rivera", email: "elaine@example.com", events: 1, value: "$2,160" },
  { name: "Acme Finance", email: "ops@acme.example", events: 6, value: "$46,900" },
];

export const inventory = [
  { item: "Dinner plates", available: 420, reserved: 180, status: "Healthy" },
  { item: "Black linen sets", available: 130, reserved: 92, status: "Watch" },
  { item: "Chafing dishes", available: 34, reserved: 20, status: "Healthy" },
  { item: "Glassware sets", available: 260, reserved: 210, status: "Low" },
];
