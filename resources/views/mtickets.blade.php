<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
  <title>mtickets — Your Life, Your Tickets: Events, Travel & More</title>
  <!-- Google Fonts + simple CSS reset -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <!-- Swiper JS (for massive slider) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
  <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    html {
      scroll-behavior: smooth;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: white;
      color: #1a2c1a;
      line-height: 1.4;
    }

    /* Core palette */
    :root {
      --mt-green: rgb(53, 168, 57);
      --mt-green-dark: #2a7a2e;
      --mt-navy: oklch(0.35 0.08 240.87);
      --mt-navy-light: oklch(0.45 0.07 240.87);
      --mt-soft-bg: #f8faf8;
      --mt-card-bg: #ffffff;
    }

    .container {
      max-width: 1280px;
      margin: 0 auto;
      padding: 0 32px;
    }

    /* header & nav - NO BG (transparent), glass buttons */
    .navbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 24px 0;
      flex-wrap: wrap;
      gap: 20px;
      position: relative;
      z-index: 30;
      background: transparent;
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
      background: var(--mt-green);
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
      background: linear-gradient(135deg, var(--mt-navy), var(--mt-green));
      background-clip: text;
      -webkit-background-clip: text;
      color: transparent;
    }

    /* navigation links container */
    .nav-links {
      display: flex;
      gap: 28px;
      font-weight: 500;
      flex-wrap: wrap;
    }

    .nav-links a {
      text-decoration: none;
      color: #1f2a1f;
      transition: color 0.2s;
      font-size: 0.95rem;
      font-weight: 600;
      cursor: pointer;
    }

    .nav-links a:hover {
      color: var(--mt-green);
    }

    /* GLASS BUTTON (sign in) */
    .glass-btn {
      background: rgba(255, 255, 255, 0.75);
      backdrop-filter: blur(12px);
      border: 1px solid rgba(255, 255, 255, 0.5);
      padding: 8px 22px;
      border-radius: 48px;
      font-weight: 600;
      color: var(--mt-navy);
      transition: all 0.25s ease;
      cursor: pointer;
      font-family: inherit;
      box-shadow: 0 2px 8px rgba(0,0,0,0.02);
    }

    .glass-btn:hover {
      background: var(--mt-green);
      color: white;
      border-color: var(--mt-green);
      transform: translateY(-2px);
    }

    /* ----- MASSIVE FULL-SCREEN SLIDER ----- */
    .hero-slider-full {
      position: relative;
      width: 100vw;
      left: 50%;
      right: 50%;
      margin-left: -50vw;
      margin-right: -50vw;
      margin-top: -80px;
      margin-bottom: 60px;
      overflow: hidden;
      height: 100vh;
      min-height: 700px;
    }

    .swiper {
      width: 100%;
      height: 100%;
    }

    .swiper-slide {
      position: relative;
      width: 100%;
      height: 100%;
      overflow: hidden;
    }

    .slide-bg {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
      transform: scale(1);
      transition: transform 6s ease-out;
    }

    .swiper-slide-active .slide-bg {
      transform: scale(1.05);
    }

    .slide-overlay {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: linear-gradient(135deg, rgba(0,0,0,0.5) 0%, rgba(0,0,0,0.3) 100%);
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      text-align: center;
      color: white;
      padding: 0 24px;
    }

    .slide-category {
      background: var(--mt-green);
      display: inline-block;
      padding: 8px 28px;
      border-radius: 60px;
      font-size: 0.9rem;
      font-weight: 700;
      letter-spacing: 1px;
      margin-bottom: 24px;
      box-shadow: 0 6px 14px rgba(0,0,0,0.2);
      animation: fadeInUp 0.7s ease-out;
    }

    .slide-overlay h2 {
      font-size: 4rem;
      font-weight: 800;
      margin-bottom: 20px;
      max-width: 80%;
      text-shadow: 0 4px 20px rgba(0,0,0,0.4);
      line-height: 1.2;
      animation: fadeInUp 0.7s ease-out 0.1s both;
    }

    .slide-desc {
      font-size: 1.25rem;
      opacity: 0.95;
      margin-bottom: 36px;
      max-width: 55%;
      text-shadow: 0 2px 10px rgba(0,0,0,0.3);
      animation: fadeInUp 0.7s ease-out 0.2s both;
    }

    .slide-btn {
      background: white;
      color: var(--mt-navy);
      border: none;
      padding: 14px 44px;
      border-radius: 60px;
      font-weight: 700;
      font-size: 1rem;
      display: inline-flex;
      align-items: center;
      gap: 12px;
      transition: 0.25s;
      cursor: pointer;
      box-shadow: 0 12px 24px rgba(0,0,0,0.2);
      animation: fadeInUp 0.7s ease-out 0.3s both;
    }

    .slide-btn:hover {
      background: var(--mt-green);
      color: white;
      transform: translateY(-4px);
    }

    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(30px);}
      to { opacity: 1; transform: translateY(0);}
    }

    .swiper-button-next, .swiper-button-prev { display: none !important; }
    .swiper-pagination-bullet { background: white; opacity: 0.6; width: 10px; height: 10px; }
    .swiper-pagination-bullet-active { background: var(--mt-green) !important; opacity: 1; transform: scale(1.2); }

    /* welcome message centered */
    .welcome-message {
      text-align: center;
      margin: 30px 0 56px;
    }
    .welcome-message h1 {
      font-size: 3rem;
      font-weight: 800;
      background: linear-gradient(135deg, var(--mt-navy), var(--mt-green));
      background-clip: text;
      -webkit-background-clip: text;
      color: transparent;
    }
    .welcome-message p {
      font-size: 1.2rem;
      color: #4c6a4c;
      max-width: 680px;
      margin: 16px auto 0;
    }

    /* category anchor offset for fixed header */
    .section-anchor {
      scroll-margin-top: 100px;
    }

    /* category header style */
    .category-header {
      display: flex;
      align-items: baseline;
      justify-content: space-between;
      flex-wrap: wrap;
      margin-bottom: 28px;
      margin-top: 20px;
      border-bottom: 2px solid #eef3ea;
      padding-bottom: 12px;
    }
    .category-header h2 {
      font-size: 2rem;
      font-weight: 700;
      color: var(--mt-navy);
      display: inline-flex;
      align-items: center;
      gap: 12px;
    }
    .category-header a {
      color: var(--mt-green);
      font-weight: 500;
      text-decoration: none;
    }

    /* EVENT CARDS: image takes 90% */
    .event-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 32px;
      margin-bottom: 56px;
    }

    .event-card {
      background: var(--mt-card-bg);
      border-radius: 32px;
      overflow: hidden;
      box-shadow: 0 15px 30px -12px rgba(0,0,0,0.05);
      transition: all 0.3s ease;
      border: 1px solid #eef3ea;
      cursor: pointer;
    }
    .event-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 25px 35px -14px rgba(53, 168, 57, 0.2);
      border-color: var(--mt-green);
    }
    .event-img {
      width: 100%;
      aspect-ratio: 1 / 0.85;
      object-fit: cover;
      display: block;
    }
    .event-info {
      padding: 20px 22px 26px;
      background: white;
    }
    .event-cat {
      display: inline-block;
      background: #e9f5e8;
      color: var(--mt-green-dark);
      font-size: 0.7rem;
      font-weight: 700;
      padding: 5px 14px;
      border-radius: 40px;
      margin-bottom: 12px;
    }
    .event-title {
      font-size: 1.35rem;
      font-weight: 700;
      margin-bottom: 8px;
      color: #1e2a1e;
    }
    .event-meta {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 16px;
    }
    .price {
      font-weight: 800;
      color: var(--mt-green);
      font-size: 1.25rem;
    }
    .book-link {
      background: var(--mt-navy);
      color: white;
      padding: 8px 18px;
      border-radius: 40px;
      font-size: 0.8rem;
      font-weight: 600;
      transition: 0.2s;
      cursor: pointer;
      border: none;
    }
    .book-link:hover { background: var(--mt-green); }

    /* customer values */
    .customer-values {
      background: var(--mt-soft-bg);
      border-radius: 60px;
      margin: 40px auto;
      padding: 48px 36px;
      display: flex;
      flex-wrap: wrap;
      gap: 32px;
      justify-content: center;
      text-align: center;
    }
    .value-item { flex: 1; min-width: 180px; text-align: center; }
    .value-icon {
      background: white;
      width: 70px;
      height: 70px;
      margin: 0 auto 16px;
      border-radius: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
      color: var(--mt-green);
      box-shadow: 0 6px 12px rgba(0,0,0,0.03);
    }

    /* trusted logos */
    .trusted-logos {
      margin: 60px auto 50px;
      text-align: center;
    }
    .trusted-logos h4 {
      font-size: 1rem;
      font-weight: 500;
      color: #6b8c6b;
      letter-spacing: 1px;
      margin-bottom: 32px;
      text-transform: uppercase;
    }
    .logo-grid {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      align-items: center;
      gap: 48px;
    }
    .brand-logo-item {
      font-size: 2rem;
      color: #8daa8d;
      transition: all 0.2s;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
    }
    .brand-logo-item i { font-size: 2.6rem; }
    .brand-logo-item span { font-size: 0.8rem; font-weight: 500; color: #4f6b4f; }
    .brand-logo-item:hover { color: var(--mt-green); transform: translateY(-4px); }

    /* CTA */
    .cta-section {
      background: var(--mt-navy);
      border-radius: 48px;
      margin: 30px 0 70px;
      padding: 56px 48px;
      text-align: center;
      color: white;
    }
    .btn-cta {
      background: var(--mt-green);
      color: white;
      border: none;
      padding: 14px 40px;
      border-radius: 60px;
      font-weight: 700;
      margin-top: 24px;
      font-size: 1rem;
      cursor: pointer;
    }
    .btn-cta:hover { background: #3fc544; transform: scale(1.02); }

    footer {
      border-top: 1px solid #e6f0e4;
      padding: 40px 0;
      text-align: center;
      color: #6e8b6e;
      background: white;
    }

    @media (max-width: 780px) {
      .container { padding: 0 20px; }
      .hero-slider-full { height: 85vh; min-height: 550px; margin-top: -60px; }
      .slide-overlay h2 { font-size: 2rem; max-width: 95%; }
      .slide-desc { max-width: 85%; font-size: 1rem; }
      .welcome-message h1 { font-size: 2rem; }
      .navbar { flex-direction: column; }
      .nav-links { justify-content: center; gap: 18px; }
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
      <a href="#events-section">Events</a>
      <a href="#airline-section">Airline</a>
      <a href="#trainbus-section">Train & Bus</a>
      <a href="#sports-section">Sports</a>
      <a href="#stream-section">Stream</a>
    </div>
    <div>
      <button class="glass-btn"><i class="fas fa-user-circle"></i> Sign in</button>
    </div>
  </div>
</header>

<main>
  <!-- MASSIVE FULL-SCREEN SLIDER -->
  <div class="hero-slider-full">
    <div class="swiper mySwiper">
      <div class="swiper-wrapper">
        <div class="swiper-slide">
          <img class="slide-bg" src="https://picsum.photos/id/106/2400/1600" alt="Music festival">
          <div class="slide-overlay">
            <span class="slide-category"><i class="fas fa-music"></i> EVENT · FESTIVAL</span>
            <h2>Glastonbury 2025 | The ultimate music pilgrimage</h2>
            <p class="slide-desc">Iconic performances, art & community. Secure your weekend passes now.</p>
            <button class="slide-btn">Grab tickets →</button>
          </div>
        </div>
        <div class="swiper-slide">
          <img class="slide-bg" src="https://picsum.photos/id/13/2400/1600" alt="Airplane travel">
          <div class="slide-overlay">
            <span class="slide-category"><i class="fas fa-plane-departure"></i> AIRLINE · FLIGHTS</span>
            <h2>Fly smarter: Summer escapes from $39</h2>
            <p class="slide-desc">Compare 500+ airlines, flexible change policies + carbon offset.</p>
            <button class="slide-btn">Find cheap flights →</button>
          </div>
        </div>
        <div class="swiper-slide">
          <img class="slide-bg" src="https://picsum.photos/id/15/2400/1600" alt="Scenic train">
          <div class="slide-overlay">
            <span class="slide-category"><i class="fas fa-train"></i> TRAIN & BUS</span>
            <h2>Eco travel: coast to coast by rail</h2>
            <p class="slide-desc">Seamless digital passes, real-time updates and the best routes.</p>
            <button class="slide-btn">Explore routes →</button>
          </div>
        </div>
        <div class="swiper-slide">
          <img class="slide-bg" src="https://picsum.photos/id/24/2400/1600" alt="Stadium sports">
          <div class="slide-overlay">
            <span class="slide-category"><i class="fas fa-futbol"></i> SPORTS</span>
            <h2>Champions League Final + NBA Live</h2>
            <p class="slide-desc">Feel the roar of live matches, premium seats available.</p>
            <button class="slide-btn">Book now →</button>
          </div>
        </div>
      </div>
      <div class="swiper-pagination"></div>
    </div>
  </div>

  <div class="container welcome-message">
    <h1>One place for all your journeys & experiences.</h1>
    <p>We're here to fill your life with joy — from last-minute concert tickets to weekend getaways and family movie nights.</p>
  </div>

  <!-- ========== EVENT TICKETS SECTION ========== -->
  <div id="events-section" class="container section-anchor">
    <div class="category-header">
      <h2><i class="fas fa-music" style="color: var(--mt-green);"></i> Events & Festivals</h2>
      <a href="#">View all →</a>
    </div>
    <div class="event-grid">
      <div class="event-card"><img class="event-img" src="https://picsum.photos/id/29/600/510" alt="Concert"><div class="event-info"><span class="event-cat">Concert</span><div class="event-title">Coldplay: Music Of The Spheres</div><div class="event-meta"><span class="price">from $79</span><button class="book-link">Tickets</button></div></div></div>
      <div class="event-card"><img class="event-img" src="https://picsum.photos/id/96/600/510" alt="Festival"><div class="event-info"><span class="event-cat">Festival</span><div class="event-title">Austin City Limits 3-Day Pass</div><div class="event-meta"><span class="price">$295</span><button class="book-link">Get pass</button></div></div></div>
      <div class="event-card"><img class="event-img" src="https://picsum.photos/id/107/600/510" alt="Theatre"><div class="event-info"><span class="event-cat">Theatre</span><div class="event-title">Hamilton Broadway Week</div><div class="event-meta"><span class="price">$89+</span><button class="book-link">Book now</button></div></div></div>
    </div>
  </div>

  <!-- ========== AIRLINE TICKETS SECTION ========== -->
  <div id="airline-section" class="container section-anchor">
    <div class="category-header">
      <h2><i class="fas fa-plane-departure" style="color: var(--mt-green);"></i> Airline Tickets</h2>
      <a href="#">View all →</a>
    </div>
    <div class="event-grid">
      <div class="event-card"><img class="event-img" src="https://picsum.photos/id/122/600/510" alt="Flight NYC"><div class="event-info"><span class="event-cat">International</span><div class="event-title">NYC → Tokyo | Japan Airlines</div><div class="event-meta"><span class="price">$689*</span><button class="book-link">Book flight</button></div></div></div>
      <div class="event-card"><img class="event-img" src="https://picsum.photos/id/127/600/510" alt="Flight Europe"><div class="event-info"><span class="event-cat">European routes</span><div class="event-title">London → Paris | British Airways</div><div class="event-meta"><span class="price">€89</span><button class="book-link">Reserve</button></div></div></div>
      <div class="event-card"><img class="event-img" src="https://picsum.photos/id/20/600/510" alt="Flight deal"><div class="event-info"><span class="event-cat">Deal</span><div class="event-title">LAX → Miami | Round trip $129</div><div class="event-meta"><span class="price">$129</span><button class="book-link">Grab</button></div></div></div>
    </div>
  </div>

  <!-- ========== TRAIN & BUS SECTION ========== -->
  <div id="trainbus-section" class="container section-anchor">
    <div class="category-header">
      <h2><i class="fas fa-train" style="color: var(--mt-green);"></i> Train & Bus Travel</h2>
      <a href="#">View all →</a>
    </div>
    <div class="event-grid">
      <div class="event-card"><img class="event-img" src="https://picsum.photos/id/121/600/510" alt="Eurostar"><div class="event-info"><span class="event-cat">High-speed rail</span><div class="event-title">Eurostar London → Paris</div><div class="event-meta"><span class="price">€49</span><button class="book-link">Reserve seat</button></div></div></div>
      <div class="event-card"><img class="event-img" src="https://picsum.photos/id/111/600/510" alt="Bus"><div class="event-info"><span class="event-cat">Green travel</span><div class="event-title">FlixBus: Berlin → Amsterdam</div><div class="event-meta"><span class="price">€24</span><button class="book-link">Book bus</button></div></div></div>
      <div class="event-card"><img class="event-img" src="https://picsum.photos/id/58/600/510" alt="Amtrak"><div class="event-info"><span class="event-cat">Scenic route</span><div class="event-title">Amtrak California Zephyr</div><div class="event-meta"><span class="price">$142</span><button class="book-link">Reserve</button></div></div></div>
    </div>
  </div>

  <!-- ========== SPORTS SECTION ========== -->
  <div id="sports-section" class="container section-anchor">
    <div class="category-header">
      <h2><i class="fas fa-basketball-ball" style="color: var(--mt-green);"></i> Sports Events</h2>
      <a href="#">View all →</a>
    </div>
    <div class="event-grid">
      <div class="event-card"><img class="event-img" src="https://picsum.photos/id/124/600/510" alt="NBA"><div class="event-info"><span class="event-cat">Basketball</span><div class="event-title">NBA Finals: Lakers vs Celtics</div><div class="event-meta"><span class="price">$210+</span><button class="book-link">Get tickets</button></div></div></div>
      <div class="event-card"><img class="event-img" src="https://picsum.photos/id/131/600/510" alt="Soccer"><div class="event-info"><span class="event-cat">Football</span><div class="event-title">UEFA Champions League Final</div><div class="event-meta"><span class="price">€159</span><button class="book-link">Hospitality</button></div></div></div>
      <div class="event-card"><img class="event-img" src="https://picsum.photos/id/130/600/510" alt="Tennis"><div class="event-info"><span class="event-cat">Grand Slam</span><div class="event-title">Wimbledon 2025 Centre Court</div><div class="event-meta"><span class="price">£89</span><button class="book-link">Book</button></div></div></div>
    </div>
  </div>

  <!-- ========== STREAM (CINEMA / LIVE STREAM) SECTION ========== -->
  <div id="stream-section" class="container section-anchor">
    <div class="category-header">
      <h2><i class="fas fa-film" style="color: var(--mt-green);"></i> Movies & Stream</h2>
      <a href="#">View all →</a>
    </div>
    <div class="event-grid">
      <div class="event-card"><img class="event-img" src="https://picsum.photos/id/42/600/510" alt="IMAX"><div class="event-info"><span class="event-cat">IMAX</span><div class="event-title">Dune: Part Two · Premium Laser</div><div class="event-meta"><span class="price">$22.50</span><button class="book-link">Pick seat</button></div></div></div>
      <div class="event-card"><img class="event-img" src="https://picsum.photos/id/90/600/510" alt="Stream"><div class="event-info"><span class="event-cat">Live stream</span><div class="event-title">Global Citizen Festival HD</div><div class="event-meta"><span class="price">$14.99</span><button class="book-link">Stream now</button></div></div></div>
      <div class="event-card"><img class="event-img" src="https://picsum.photos/id/38/600/510" alt="Theatre"><div class="event-info"><span class="event-cat">Ballet</span><div class="event-title">The Nutcracker Live Recording</div><div class="event-meta"><span class="price">$19</span><button class="book-link">Rent</button></div></div></div>
    </div>
  </div>

  <!-- Customer values -->
  <div class="container customer-values">
    <div class="value-item"><div class="value-icon"><i class="fas fa-heart"></i></div><h3>For you, not for bots</h3><p>Real humans + smart AI 24/7</p></div>
    <div class="value-item"><div class="value-icon"><i class="fas fa-wallet"></i></div><h3>Price promise</h3><p>Best price or refund difference</p></div>
    <div class="value-item"><div class="value-icon"><i class="fas fa-mobile-alt"></i></div><h3>All tickets in one app</h3><p>Digital vault + offline access</p></div>
    <div class="value-item"><div class="value-icon"><i class="fas fa-gem"></i></div><h3>Earn mtickets rewards</h3><p>Points for free upgrades</p></div>
  </div>

  <!-- Trusted Logos -->
  <div class="container trusted-logos">
    <h4>Trusted by the world's best creators & partners</h4>
    <div class="logo-grid">
      <div class="brand-logo-item"><i class="fab fa-airbnb"></i><span>Airbnb</span></div>
      <div class="brand-logo-item"><i class="fab fa-stripe"></i><span>Stripe</span></div>
      <div class="brand-logo-item"><i class="fas fa-futbol"></i><span>UEFA</span></div>
      <div class="brand-logo-item"><i class="fas fa-train"></i><span>SNCF</span></div>
      <div class="brand-logo-item"><i class="fas fa-film"></i><span>IMAX</span></div>
      <div class="brand-logo-item"><i class="fas fa-music"></i><span>Live Nation</span></div>
    </div>
  </div>

  <!-- CTA -->
  <div class="container">
    <div class="cta-section">
      <h2>Ready to make memories?</h2>
      <p style="font-size: 1.1rem; margin-top: 8px;">Join millions of happy fans & travelers — mtickets puts you first.</p>
      <button class="btn-cta"><i class="fas fa-smile-wink"></i> Start exploring for free</button>
      <div style="margin-top: 20px; font-size: 0.85rem;">Zero hidden fees on thousands of events + 5% back in mtickets points</div>
    </div>
  </div>
</main>

<footer>
  <div class="container">
    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px;">
      <div>© 2025 mtickets — designed for you, powered by passion</div>
      <div style="display: flex; gap: 24px;">
        <a href="#" style="color: #6e8b6e;"><i class="fab fa-twitter"></i></a>
        <a href="#" style="color: #6e8b6e;"><i class="fab fa-instagram"></i></a>
        <a href="#" style="color: #6e8b6e;"><i class="fab fa-tiktok"></i></a>
      </div>
    </div>
    <div style="margin-top: 28px; font-size: 0.75rem; border-top: 1px solid #ecf5ea; padding-top: 24px;">
      <p>Your experiences matter — mtickets seamlessly combines event tickets, airline tickets, train & bus journeys, movies & sports for a truly customer-first ecosystem.</p>
    </div>
  </div>
</footer>

<script>
  // Swiper with autoplay, no arrows
  const swiper = new Swiper('.mySwiper', {
    loop: true,
    autoplay: { delay: 6000, disableOnInteraction: false },
    pagination: { el: '.swiper-pagination', clickable: true },
    speed: 1000,
  });

  // smooth click handling for nav links (already smooth via css scroll-behavior)
  // but also add demo alert for booking
  const btns = document.querySelectorAll('.book-link, .slide-btn, .btn-cta, .glass-btn');
  btns.forEach(btn => {
    btn.addEventListener('click', (e) => {
      if(btn.classList.contains('glass-btn') || btn.classList.contains('book-link') || btn.classList.contains('slide-btn') || btn.classList.contains('btn-cta')) {
        e.preventDefault();
        alert("✨ Welcome to mtickets! Your adventure awaits — secure checkout, best price guarantee.");
      }
    });
  });
</script>
</body>
</html>