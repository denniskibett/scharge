<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
  <title>mtickets — seamless tickets for events, flights, travel & sports</title>
  <!-- Google Fonts + simple CSS reset -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Outfit', sans-serif;
      background: #fafcff;
      color: #1a1f2e;
      line-height: 1.4;
      scroll-behavior: smooth;
    }

    .container {
      max-width: 1280px;
      margin: 0 auto;
      padding: 0 32px;
    }

    /* header & nav */
    .navbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px 0;
      flex-wrap: wrap;
      gap: 20px;
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 10px;
      font-weight: 800;
      font-size: 1.8rem;
      letter-spacing: -0.02em;
    }

    .logo-icon {
      background: linear-gradient(135deg, #2563eb, #1e40af);
      width: 40px;
      height: 40px;
      border-radius: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 1.4rem;
      box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
    }

    .logo span {
      background: linear-gradient(135deg, #1e293b, #2563eb);
      background-clip: text;
      -webkit-background-clip: text;
      color: transparent;
    }

    .nav-links {
      display: flex;
      gap: 32px;
      font-weight: 500;
    }

    .nav-links a {
      text-decoration: none;
      color: #334155;
      transition: color 0.2s;
      font-size: 1rem;
    }

    .nav-links a:hover {
      color: #2563eb;
    }

    .btn-outline-light {
      background: white;
      border: 1px solid #cbd5e1;
      padding: 8px 20px;
      border-radius: 40px;
      font-weight: 600;
      color: #1e293b;
      transition: all 0.2s;
    }

    .btn-outline-light:hover {
      border-color: #2563eb;
      background: #f0f9ff;
    }

    /* hero section */
    .hero {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 48px;
      padding: 60px 0 40px;
    }

    .hero-content {
      flex: 1.2;
    }

    .hero-badge {
      background: #e0f2fe;
      color: #0369a1;
      display: inline-block;
      padding: 6px 14px;
      border-radius: 60px;
      font-size: 0.85rem;
      font-weight: 600;
      margin-bottom: 24px;
    }

    .hero h1 {
      font-size: 3.5rem;
      font-weight: 800;
      line-height: 1.2;
      letter-spacing: -0.02em;
      background: linear-gradient(to right, #0f172a, #2563eb);
      background-clip: text;
      -webkit-background-clip: text;
      color: transparent;
      margin-bottom: 20px;
    }

    .hero p {
      font-size: 1.2rem;
      color: #475569;
      max-width: 540px;
      margin-bottom: 32px;
    }

    .hero-buttons {
      display: flex;
      gap: 16px;
      flex-wrap: wrap;
    }

    .btn-primary {
      background: #2563eb;
      color: white;
      border: none;
      padding: 14px 32px;
      border-radius: 48px;
      font-weight: 600;
      font-size: 1rem;
      cursor: pointer;
      transition: all 0.2s;
      box-shadow: 0 4px 6px -2px rgba(37,99,235,0.2);
    }

    .btn-primary:hover {
      background: #1d4ed8;
      transform: translateY(-2px);
    }

    .btn-secondary {
      background: white;
      border: 1px solid #cbd5e1;
      padding: 14px 32px;
      border-radius: 48px;
      font-weight: 600;
      transition: all 0.2s;
    }

    .hero-visual {
      flex: 0.9;
      background: linear-gradient(145deg, #ffffff, #f1f5f9);
      border-radius: 48px;
      padding: 20px;
      box-shadow: 0 25px 50px -12px rgba(0,0,0,0.15);
    }

    .mock-card {
      background: white;
      border-radius: 32px;
      padding: 18px;
      box-shadow: 0 6px 14px rgba(0,0,0,0.03);
      border: 1px solid #eef2ff;
    }

    .mock-line {
      display: flex;
      justify-content: space-between;
      margin-bottom: 12px;
    }

    /* services grid */
    .services-section {
      padding: 80px 0;
    }

    .section-title {
      text-align: center;
      font-size: 2.5rem;
      font-weight: 800;
      margin-bottom: 14px;
    }

    .section-sub {
      text-align: center;
      color: #5b6e8c;
      max-width: 640px;
      margin: 0 auto 56px auto;
      font-size: 1.1rem;
    }

    .services-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 32px;
    }

    .service-card {
      background: white;
      border-radius: 36px;
      padding: 32px 24px;
      transition: all 0.3s ease;
      border: 1px solid #e9edf2;
      box-shadow: 0 8px 20px rgba(0,0,0,0.02);
    }

    .service-card:hover {
      transform: translateY(-10px);
      border-color: #cbdffc;
      box-shadow: 0 20px 30px -12px rgba(37,99,235,0.12);
    }

    .service-icon {
      width: 64px;
      height: 64px;
      background: #eef4ff;
      border-radius: 28px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
      color: #2563eb;
      margin-bottom: 24px;
    }

    .service-card h3 {
      font-size: 1.6rem;
      font-weight: 700;
      margin-bottom: 12px;
    }

    .service-card p {
      color: #4a5a7a;
      margin-bottom: 20px;
      line-height: 1.5;
    }

    .badge-list {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin: 16px 0 8px;
    }

    .badge {
      background: #f1f5f9;
      padding: 4px 12px;
      border-radius: 30px;
      font-size: 0.75rem;
      font-weight: 500;
      color: #1e293b;
    }

    /* how it works (simple) */
    .steps {
      background: linear-gradient(to bottom, #ffffff, #f8fafc);
      padding: 70px 0;
      border-radius: 60px 60px 0 0;
    }

    .steps-flex {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 40px;
      margin-top: 40px;
    }

    .step-item {
      text-align: center;
      flex: 1;
      min-width: 180px;
    }

    .step-number {
      background: #eef2ff;
      color: #2563eb;
      width: 48px;
      height: 48px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      font-weight: 800;
      border-radius: 60px;
      margin: 0 auto 20px;
    }

    /* CTA */
    .cta-section {
      background: #0f172a;
      border-radius: 48px;
      margin: 40px 0 70px;
      padding: 56px 48px;
      text-align: center;
      color: white;
    }

    .cta-section h2 {
      font-size: 2.2rem;
      font-weight: 700;
      margin-bottom: 16px;
    }

    .cta-section .btn-primary {
      background: #3b82f6;
      margin-top: 20px;
      box-shadow: none;
    }

    /* footer */
    footer {
      border-top: 1px solid #e2e8f0;
      padding: 40px 0;
      text-align: center;
      color: #64748b;
    }

    @media (max-width: 780px) {
      .container {
        padding: 0 20px;
      }
      .hero h1 {
        font-size: 2.4rem;
      }
      .service-card h3 {
        font-size: 1.4rem;
      }
      .navbar {
        flex-direction: column;
      }
    }
  </style>
</head>
<body>

<header class="container">
  <div class="navbar">
    <div class="logo">
      <div class="logo-icon"><i class="fas fa-ticket-alt"></i></div>
      <span>mtickets</span>
    </div>
    <div class="nav-links">
      <a href="#">Home</a>
      <a href="#">Tickets</a>
      <a href="#">Deals</a>
      <a href="#">Support</a>
    </div>
    <div>
      <a href="#" class="btn-outline-light"><i class="fas fa-user-circle"></i> Sign in</a>
    </div>
  </div>
</header>

<main>
  <!-- Hero / landing intro -->
  <div class="container">
    <div class="hero">
      <div class="hero-content">
        <div class="hero-badge"><i class="fas fa-rocket"></i> trusted by 2M+ users</div>
        <h1>One place for all your <br>journeys & experiences</h1>
        <p>Effortlessly book event tickets, flights, train & bus journeys, movies, and sports events — all in one modern platform.</p>
        <div class="hero-buttons">
          <button class="btn-primary">Explore Tickets →</button>
          <button class="btn-secondary">How it works</button>
        </div>
      </div>
      <div class="hero-visual">
        <div class="mock-card">
          <div class="mock-line"><span><i class="fas fa-calendar-alt"></i> Tomorrowland 2025</span> <span><strong>€129</strong></span></div>
          <div class="mock-line"><span><i class="fas fa-plane"></i> JFK → CDG</span> <span>$489</span></div>
          <div class="mock-line"><span><i class="fas fa-train"></i> Eurostar London→Paris</span> <span>€79</span></div>
          <div class="mock-line"><span><i class="fas fa-futbol"></i> Champions League Final</span> <span>€210</span></div>
          <div style="height: 2px; background:#eef2ff; margin:12px 0"></div>
          <div style="font-size:0.8rem; color:#2563eb;"><i class="fas fa-bolt"></i> instant e-tickets · best price guarantee</div>
        </div>
      </div>
    </div>
  </div>

  <!-- 4 SERVICES: EVENT TICKETS, AIRLINE, TRAIN & BUS, MOVIES & SPORTS -->
  <div class="services-section container">
    <h2 class="section-title">Your gateway to unforgettable moments</h2>
    <p class="section-sub">We bring you the world's biggest music festivals, flights, rail, cinema blockbusters and thrilling matches — all in one seamless flow.</p>
    <div class="services-grid">
      
      <!-- service 1: event tickets (concerts, festivals) -->
      <div class="service-card">
        <div class="service-icon"><i class="fas fa-music"></i></div>
        <h3>Event Tickets</h3>
        <p>Concerts, festivals, theater & nightlife. Get premium access to sold-out shows and VIP experiences.</p>
        <div class="badge-list">
          <span class="badge">Coachella</span>
          <span class="badge">Lollapalooza</span>
          <span class="badge">Broadway</span>
        </div>
        <div style="margin-top: 16px;"><i class="fas fa-check-circle" style="color:#22c55e;"></i> <span style="font-size:0.9rem;">official resale trusted</span></div>
      </div>

      <!-- service 2: airline tickets -->
      <div class="service-card">
        <div class="service-icon"><i class="fas fa-plane-departure"></i></div>
        <h3>Airline Tickets</h3>
        <p>Compare 600+ airlines, find cheap flights, and enjoy flexible cancellations. Earn miles on every booking.</p>
        <div class="badge-list">
          <span class="badge">Delta</span>
          <span class="badge">Emirates</span>
          <span class="badge">United</span>
        </div>
        <div style="margin-top: 16px;"><i class="fas fa-shield-alt"></i> flight protection included</div>
      </div>

      <!-- service 3: train and bus tickets -->
      <div class="service-card">
        <div class="service-icon"><i class="fas fa-train"></i></div>
        <h3>Train & Bus</h3>
        <p>Intercity, high-speed rail and green coach travel. Compare schedules, get digital passes & real-time updates.</p>
        <div class="badge-list">
          <span class="badge">Amtrak</span>
          <span class="badge">SNCF</span>
          <span class="badge">FlixBus</span>
          <span class="badge">Eurostar</span>
        </div>
        <div><i class="fas fa-map-marked-alt"></i> Smart route planner</div>
      </div>

      <!-- service 4: movies and sports -->
      <div class="service-card">
        <div class="service-icon"><i class="fas fa-basketball-ball"></i></div>
        <h3>Movies & Sports</h3>
        <p>Cinema tickets, NBA, Premier League, Grand Slam tennis — catch the action live or IMAX exclusives.</p>
        <div class="badge-list">
          <span class="badge">IMAX</span>
          <span class="badge">NBA Finals</span>
          <span class="badge">FIFA WC</span>
        </div>
        <div><i class="fas fa-vr-cardboard"></i> 360° stadium view</div>
      </div>
    </div>
  </div>

  <!-- why mtickets - extra value + modern features (scraped vibe improved) -->
  <div class="container" style="margin-bottom: 48px;">
    <div style="background: linear-gradient(120deg, #f1f5ff, #ffffff); border-radius: 48px; padding: 48px 32px;">
      <div style="display: flex; flex-wrap: wrap; gap: 40px; justify-content: space-between; align-items: center;">
        <div style="flex: 1.2;">
          <h2 style="font-size: 1.9rem; font-weight: 700;">Why modern travelers & fans choose mtickets</h2>
          <ul style="margin-top: 24px; list-style: none;">
            <li style="margin-bottom: 16px;"><i class="fas fa-check-circle" style="color:#2563eb; margin-right: 12px;"></i> <strong>Smart price alerts</strong> – never overpay for flights or events</li>
            <li style="margin-bottom: 16px;"><i class="fas fa-qrcode"></i> <strong style="margin-left: 12px;">Digital vault</strong> – all tickets stored & synced across devices</li>
            <li style="margin-bottom: 16px;"><i class="fas fa-headset"></i> <strong style="margin-left: 12px;">24/7 concierge</strong> – real humans + AI to solve issues instantly</li>
            <li><i class="fas fa-globe"></i> <strong style="margin-left: 12px;">Global inventory</strong> – 150+ countries, 12k+ venues & partners</li>
          </ul>
        </div>
        <div style="flex: 0.9; text-align: center;">
          <i class="fas fa-chart-line" style="font-size: 5rem; color: #2563eb; opacity: 0.7;"></i>
          <p style="margin-top: 12px; font-weight: 500;">⭐ 4.8/5 from 34k+ reviews</p>
        </div>
      </div>
    </div>
  </div>

  <!-- quick how it works (seamless booking) -->
  <div class="steps">
    <div class="container">
      <h2 class="section-title">Book in seconds, enjoy in real life</h2>
      <p class="section-sub">From search to e-ticket, mtickets makes it effortless</p>
      <div class="steps-flex">
        <div class="step-item"><div class="step-number">1</div><h4>Search & compare</h4><p>Filter by date, price, category</p></div>
        <div class="step-item"><div class="step-number">2</div><h4>Secure checkout</h4><p>Apple Pay, card, PayPal</p></div>
        <div class="step-item"><div class="step-number">3</div><h4>Instant delivery</h4><p>QR or NFC tickets on the fly</p></div>
        <div class="step-item"><div class="step-number">4</div><h4>Enjoy & save</h4><p>Earn mtickets points for next trip</p></div>
      </div>
    </div>
  </div>

  <!-- large featured products / dynamic scraping section – shows actual categories we 'scrape' info from current mtickets.com (demo) -->
  <div class="container">
    <div style="margin: 72px 0 40px;">
      <div style="display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap;">
        <h2 style="font-size: 1.8rem; font-weight: 700;">🔥 Trending picks (live from mtickets)</h2>
        <a href="#" style="color:#2563eb; font-weight: 500;">view all →</a>
      </div>
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 24px; margin-top: 32px;">
        <div style="background:white; border-radius: 28px; padding: 20px; box-shadow: 0 8px 20px rgba(0,0,0,0.02); border:1px solid #edf2f7;">
          <i class="fas fa-ticket-alt" style="color:#2563eb; font-size: 1.6rem;"></i>
          <h4 style="margin: 12px 0 6px;">Coldplay · Music of the Spheres</h4>
          <span>Event tickets from $89</span>
        </div>
        <div style="background:white; border-radius: 28px; padding: 20px; box-shadow: 0 8px 20px rgba(0,0,0,0.02); border:1px solid #edf2f7;">
          <i class="fas fa-plane"></i>
          <h4 style="margin: 12px 0 6px;">NYC → London</h4>
          <span>Return from $429 · Virgin & Delta</span>
        </div>
        <div style="background:white; border-radius: 28px; padding: 20px; box-shadow: 0 8px 20px rgba(0,0,0,0.02); border:1px solid #edf2f7;">
          <i class="fas fa-film"></i>
          <h4 style="margin: 12px 0 6px;">Dune: Part Two (IMAX)</h4>
          <span>Movies tickets, premium seats</span>
        </div>
        <div style="background:white; border-radius: 28px; padding: 20px; box-shadow: 0 8px 20px rgba(0,0,0,0.02); border:1px solid #edf2f7;">
          <i class="fas fa-futbol"></i>
          <h4 style="margin: 12px 0 6px;">UEFA Champions League Final</h4>
          <span>Sports · official hospitality</span>
        </div>
      </div>
    </div>
  </div>

  <!-- CTA section final -->
  <div class="container">
    <div class="cta-section">
      <h2>Ready to explore more?</h2>
      <p style="font-size: 1.1rem; margin-bottom: 8px;">Join millions of users who book smarter with mtickets.</p>
      <button class="btn-primary" style="background:#fff; color:#0f172a; box-shadow: none;"><i class="fas fa-arrow-right"></i> Get started – it's free</button>
      <div style="margin-top: 24px; font-size: 0.85rem;">No booking fees on selected events & travel 🎉</div>
    </div>
  </div>
</main>

<footer>
  <div class="container">
    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px;">
      <div>© 2025 mtickets.com — reimagined ticketing ecosystem</div>
      <div style="display: flex; gap: 24px;">
        <a href="#" style="color: #4b5563;"><i class="fab fa-twitter"></i></a>
        <a href="#" style="color: #4b5563;"><i class="fab fa-instagram"></i></a>
        <a href="#" style="color: #4b5563;"><i class="fab fa-linkedin"></i></a>
      </div>
    </div>
    <div style="margin-top: 28px; font-size: 0.75rem; border-top: 1px solid #eef2ff; padding-top: 24px;">
      <p>Inspired by mtickets' original categories: event tickets, airline tickets, train & bus tickets, movies & sports tickets. A modernized concept showcasing seamless multi-sector booking experience.</p>
    </div>
  </div>
</footer>

<!-- subtle interactive hover / demo note: no dynamic backend but smooth ui -->
</body>
</html>