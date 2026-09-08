<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Scharge | Modern Property Management & Rent Collection</title>
    <meta name="description" content="All-in-one property management: automate rent, service charge, security deposits, tenant communication, and maintenance tracking.">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <style>
        * { font-family: 'Outfit', sans-serif; }
        
        .hero-section {
            background-image: linear-gradient(135deg, rgba(0,0,0,0.85) 0%, rgba(220,38,38,0.8) 100%), url('https://images.unsplash.com/photo-1560518883-ce09059eeffa?w=1600');
            background-size: cover;
            background-position: center;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 1.5rem;
            transition: all 0.3s ease;
            border: 1px solid rgba(0,0,0,0.05);
        }
        .glass-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 25px 35px -12px rgba(0,0,0,0.15);
        }
        
        .feature-icon {
            background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%);
            width: 4rem;
            height: 4rem;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.25rem;
        }
        
        .btn-primary {
            background: #DC2626;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(220,38,38,0.3);
        }
        .btn-primary:hover {
            background: #B91C1C;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(220,38,38,0.4);
        }
        
        .btn-outline {
            border: 1.5px solid white;
            transition: all 0.2s ease;
        }
        .btn-outline:hover {
            background: white;
            color: #DC2626;
            transform: translateY(-2px);
        }
        
        .pricing-card {
            background: white;
            border-radius: 1.5rem;
            transition: all 0.3s ease;
            border: 1px solid #f0f0f0;
        }
        .pricing-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 35px -12px rgba(220,38,38,0.2);
            border-color: #DC2626;
        }
        .popular-card {
            border: 2px solid #DC2626;
            box-shadow: 0 20px 30px -12px rgba(220,38,38,0.25);
        }
        
        .stat-number { font-size: 3rem; font-weight: 800; line-height: 1.2; }
        
        .unit-slider {
            -webkit-appearance: none;
            width: 100%;
            height: 6px;
            background: #FEE2E2;
            border-radius: 3px;
            outline: none;
        }
        .unit-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 20px;
            height: 20px;
            background: #DC2626;
            border-radius: 50%;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(220,38,38,0.4);
        }
        
        .scroll-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #DC2626;
            color: white;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            opacity: 0;
            visibility: hidden;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .scroll-top.show { opacity: 1; visibility: visible; }
        .scroll-top:hover { background: #B91C1C; transform: translateY(-3px); }
        
        .faq-question svg { transition: transform 0.2s ease; }
        .rotate-180 { transform: rotate(180deg); }
        
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-up { animation: fadeUp 0.6s ease forwards; }
    </style>
</head>
<body class="bg-white">

    <!-- Navigation -->
    <nav class="fixed top-0 left-0 w-full z-50">
        <div class="container mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <svg class="w-6 h-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <span class="text-2xl font-bold text-white tracking-tight">Scharge</span>
                    <span class="text-xs text-white/80 bg-white/20 px-2 py-0.5 rounded-full">Property</span>
                </div>
                
                <div class="hidden md:flex items-center gap-8">
                    <a href="#home" class="text-white/90 hover:text-white font-medium transition">Home</a>
                    <a href="#features" class="text-white/90 hover:text-white font-medium transition">Features</a>
                    <a href="#pricing" class="text-white/90 hover:text-white font-medium transition">Pricing</a>
                    <a href="#testimonials" class="text-white/90 hover:text-white font-medium transition">Success</a>
                    <a href="#faq" class="text-white/90 hover:text-white font-medium transition">FAQ</a>
                </div>
                
                <div class="hidden md:flex items-center gap-4">
                    <a href="/login" class="border border-white/30 text-white px-6 py-2 rounded-full font-semibold shadow-md hover:bg-red-700 transition">Login</a>
                    <a href="/signup" class="bg-white text-red-600 px-6 py-2 rounded-full font-semibold shadow-md hover:bg-gray-100 transition">Sign up</a>
                </div>
                
                <button id="mobileMenuBtn" class="md:hidden text-white">
                    <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>
            
            <div id="mobileMenu" class="hidden md:hidden mt-5 pb-4 space-y-3 bg-red-600 rounded-lg shadow-lg px-5">
                <a href="#home" class="block text-white/90 hover:text-white py-2">Home</a>
                <a href="#features" class="block text-white/90 hover:text-white py-2">Features</a>
                <a href="#pricing" class="block text-white/90 hover:text-white py-2">Pricing</a>
                <a href="#testimonials" class="block text-white/90 hover:text-white py-2">Success</a>
                <a href="#faq" class="block text-white/90 hover:text-white py-2">FAQ</a>
                <div class="flex gap-3 pt-3">
                    <a href="/login" class="flex-1 bg-white text-red-600 px-6 py-2 rounded-full font-semibold">Log in</a>
                    <a href="/signup" class="flex-1 bg-red-600 text-white px-6 py-2 rounded-full font-semibold hover:bg-red-700 transition">Sign up</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section min-h-screen flex items-center pt-20">
        <div class="container mx-auto px-6 py-20">
            <div class="max-w-3xl">
                <div class="inline-flex items-center gap-2 bg-white/10 backdrop-blur-sm rounded-full px-5 py-2 mb-6">
                    <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
                    <span class="text-white text-sm font-medium">Trusted by 500+ property managers in Kenya</span>
                </div>
                <h1 class="text-5xl md:text-7xl font-bold text-white mb-6 leading-tight">
                    All-in-one property <br/>management platform
                </h1>
                <p class="text-xl text-white/80 mb-8 leading-relaxed max-w-xl">
                    Collect rent, service charge, and security deposits. Manage tenants, track maintenance, and generate financial reports—all from one dashboard.
                    <span class="text-white font-semibold">Your first payment unlocks 2 extra months free.</span>
                </p>
                <div class="flex flex-col sm:flex-row gap-4">
                    <button class="btn-primary px-8 py-4 rounded-full text-white font-semibold text-lg">
                        Start free trial
                    </button>
                    <button class="btn-outline px-8 py-4 rounded-full text-white font-semibold text-lg bg-transparent">
                        Book a demo
                    </button>
                </div>
                <p class="text-white/50 text-sm mt-6">No setup fee • No contract • Cancel anytime</p>
            </div>
        </div>
    </section>

    <!-- Stats -->
    <section class="py-16 bg-white border-b border-gray-100">
        <div class="container mx-auto px-6">
            <div class="grid md:grid-cols-4 gap-8 text-center">
                <div><div class="stat-number text-red-600">500+</div><div class="text-gray-500 font-medium mt-1">Property Portfolios</div></div>
                <div><div class="stat-number text-red-600">KES 2.5B+</div><div class="text-gray-500 font-medium mt-1">Collected Annually</div></div>
                <div><div class="stat-number text-red-600">99.9%</div><div class="text-gray-500 font-medium mt-1">Collection Accuracy</div></div>
                <div><div class="stat-number text-red-600">85%</div><div class="text-gray-500 font-medium mt-1">Less Admin Time</div></div>
            </div>
        </div>
    </section>

    <!-- Features (expanded to match Nyumbani.ke) -->
    <section id="features" class="py-20 bg-gray-50">
        <div class="container mx-auto px-6">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <span class="text-red-600 font-semibold text-sm uppercase tracking-wide">Everything you need</span>
                <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mt-2 mb-4">Full-service property management</h2>
                <p class="text-gray-500 text-lg">From tenant onboarding to financial reporting—all in one place</p>
            </div>
            
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-7">
                <!-- Feature 1: Rent & Service Charge Collection -->
                <div class="glass-card p-7">
                    <div class="feature-icon">
                        <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M6 19h12a2 2 0 002-2V7a2 2 0 00-2-2H6a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14h.01M12 14h.01M16 14h.01" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Rent & Service Charge</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">Automate recurring rent and service charge collection via M-Pesa, cards, and bank transfers. Send reminders and reduce arrears.</p>
                </div>
                
                <!-- Feature 2: Security Deposits -->
                <div class="glass-card p-7">
                    <div class="feature-icon">
                        <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Security Deposits</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">Collect and hold tenant security deposits securely. Track interest, manage refunds, and generate statements.</p>
                </div>
                
                <!-- Feature 3: Tenant Management -->
                <div class="glass-card p-7">
                    <div class="feature-icon">
                        <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Tenant Portal</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">Tenants can view balances, pay online, request maintenance, and communicate directly with you.</p>
                </div>
                
                <!-- Feature 4: Maintenance Tracking -->
                <div class="glass-card p-7">
                    <div class="feature-icon">
                        <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Maintenance</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">Log maintenance requests, assign to vendors, track costs, and approve payments—all in one system.</p>
                </div>

                <!-- Feature 5: Financial Reporting -->
                <div class="glass-card p-7">
                    <div class="feature-icon">
                        <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Financial Reports</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">Get real-time P&L, collection rates, arrears reports, and expense tracking per property.</p>
                </div>

                <!-- Feature 6: Expense Management -->
                <div class="glass-card p-7">
                    <div class="feature-icon">
                        <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Pay Expenses</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">Pay utilities, maintenance, and staff directly from collected funds. 24-hour settlements.</p>
                </div>

                <!-- Feature 7: Unit Transfer -->
                <div class="glass-card p-7">
                    <div class="feature-icon">
                        <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Unit Transfer</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">Tenants move units without creating new accounts. All history and deposits follow them.</p>
                </div>

                <!-- Feature 8: Communications -->
                <div class="glass-card p-7">
                    <div class="feature-icon">
                        <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Communications</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">Send broadcast messages, payment reminders, and notices via SMS and email.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="py-20 bg-white">
        <div class="container mx-auto px-6">
            <div class="text-center max-w-2xl mx-auto mb-14">
                <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-3">Get started in minutes</h2>
                <p class="text-gray-500 text-lg">No training required</p>
            </div>
            <div class="grid md:grid-cols-3 gap-10 max-w-4xl mx-auto">
                <div class="text-center">
                    <div class="w-20 h-20 bg-red-600 text-white rounded-2xl flex items-center justify-center text-3xl font-bold mx-auto mb-5 shadow-lg">1</div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Add properties & units</h3>
                    <p class="text-gray-500">Upload your property portfolio, set charges, and invite tenants</p>
                </div>
                <div class="text-center">
                    <div class="w-20 h-20 bg-red-600 text-white rounded-2xl flex items-center justify-center text-3xl font-bold mx-auto mb-5 shadow-lg">2</div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Collect & manage</h3>
                    <p class="text-gray-500">Tenants pay via M-Pesa, card, or bank. Track everything live</p>
                </div>
                <div class="text-center">
                    <div class="w-20 h-20 bg-red-600 text-white rounded-2xl flex items-center justify-center text-3xl font-bold mx-auto mb-5 shadow-lg">3</div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Report & grow</h3>
                    <p class="text-gray-500">Get insights, pay expenses, and scale your portfolio</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing with calculator -->
    <section id="pricing" class="py-20 bg-gray-50">
        <div class="container mx-auto px-6">
            <div class="text-center max-w-2xl mx-auto mb-14">
                <span class="text-red-600 font-semibold text-sm uppercase tracking-wide">Simple pricing</span>
                <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mt-2 mb-3">You pay only when you collect</h2>
                <p class="text-gray-500 text-lg">No monthly fees. No setup costs.</p>
            </div>
            
            <!-- Calculator -->
            <div class="max-w-2xl mx-auto bg-white rounded-2xl p-8 shadow-md mb-16">
                <h3 class="text-xl font-bold text-gray-900 mb-5 text-center">Estimate your savings</h3>
                <div class="space-y-5">
                    <div>
                        <label class="text-gray-700 font-medium block mb-2">Number of units</label>
                        <input type="range" id="unitSlider" class="unit-slider" min="1" max="500" value="50">
                        <div class="flex justify-between mt-2">
                            <span class="text-sm text-gray-400">1</span>
                            <span class="text-base font-bold text-red-600" id="unitValue">50 units</span>
                            <span class="text-sm text-gray-400">500+</span>
                        </div>
                    </div>
                    <div>
                        <label class="text-gray-700 font-medium block mb-2">Average monthly rent + service charge (KES)</label>
                        <input type="number" id="serviceCharge" class="w-full px-4 py-3 border border-gray-200 rounded-xl" value="5000">
                    </div>
                    <div class="bg-red-50 rounded-xl p-5 space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Monthly collection volume</span>
                            <span class="font-bold text-gray-900 text-lg" id="monthlyVolume">KES 250,000</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Scharge fee (1.5%)</span>
                            <span class="font-bold text-red-600 text-lg" id="schargeFee">KES 3,750</span>
                        </div>
                        <div class="flex justify-between items-center pt-3 border-t border-red-200">
                            <span class="font-bold text-gray-900">You save vs manual collection</span>
                            <span class="font-bold text-green-600 text-xl" id="savings">KES 96,250</span>
                        </div>
                    </div>
                    <p class="text-xs text-gray-400 text-center">*Manual collection average loss: 40% due to late payments & errors</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Cards -->
    <section class="py-10 bg-gray-50">
        <div class="container mx-auto px-6">
            <div class="grid md:grid-cols-4 gap-6 max-w-5xl mx-auto">
                <div class="pricing-card p-6 text-center">
                    <div class="text-sm font-semibold text-gray-400 mb-1">1-30 units</div>
                    <div class="text-4xl font-bold text-gray-900 mb-1">1.8%</div>
                    <p class="text-xs text-gray-500 mb-5">per collection</p>
                    <button class="w-full border border-red-600 text-red-600 py-2.5 rounded-full font-semibold hover:bg-red-600 hover:text-white transition">Select</button>
                </div>
                <div class="pricing-card popular-card p-6 text-center relative">
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-red-600 text-white text-xs px-3 py-1 rounded-full font-semibold">Most popular</div>
                    <div class="text-sm font-semibold text-gray-400 mb-1">31-100 units</div>
                    <div class="text-4xl font-bold text-gray-900 mb-1">1.5%</div>
                    <p class="text-xs text-gray-500 mb-5">per collection</p>
                    <button class="w-full bg-red-600 text-white py-2.5 rounded-full font-semibold hover:bg-red-700 transition shadow-md">Select</button>
                </div>
                <div class="pricing-card p-6 text-center">
                    <div class="text-sm font-semibold text-gray-400 mb-1">101-200 units</div>
                    <div class="text-4xl font-bold text-gray-900 mb-1">1.2%</div>
                    <p class="text-xs text-gray-500 mb-5">per collection</p>
                    <button class="w-full border border-red-600 text-red-600 py-2.5 rounded-full font-semibold hover:bg-red-600 hover:text-white transition">Select</button>
                </div>
                <div class="pricing-card p-6 text-center">
                    <div class="text-sm font-semibold text-gray-400 mb-1">200+ units</div>
                    <div class="text-4xl font-bold text-gray-900 mb-1">0.9%</div>
                    <p class="text-xs text-gray-500 mb-5">per collection</p>
                    <button class="w-full border border-red-600 text-red-600 py-2.5 rounded-full font-semibold hover:bg-red-600 hover:text-white transition">Contact sales</button>
                </div>
            </div>
            <p class="text-center text-gray-400 text-sm mt-8">No setup fee • No monthly minimum • Cancel anytime</p>
        </div>
    </section>

    <!-- Testimonials -->
    <section id="testimonials" class="py-20 bg-white">
        <div class="container mx-auto px-6 text-center">
            <h2 class="text-4xl font-bold text-gray-900 mb-12">What property managers say</h2>
            <div class="grid md:grid-cols-3 gap-8 max-w-5xl mx-auto">
                <div class="bg-gray-50 p-8 rounded-2xl text-left">
                    <div class="text-red-600 text-2xl">“</div>
                    <p class="text-gray-600 italic">Scharge has cut our admin time by 80%. We collect rent and service charges seamlessly, and tenants love the portal.</p>
                    <p class="font-bold text-gray-900 mt-4">— Jane M., Nairobi</p>
                </div>
                <div class="bg-gray-50 p-8 rounded-2xl text-left">
                    <div class="text-red-600 text-2xl">“</div>
                    <p class="text-gray-600 italic">The security deposit feature is a game-changer. We track everything and refunds are now instant and accurate.</p>
                    <p class="font-bold text-gray-900 mt-4">— David K., Mombasa</p>
                </div>
                <div class="bg-gray-50 p-8 rounded-2xl text-left">
                    <div class="text-red-600 text-2xl">“</div>
                    <p class="text-gray-600 italic">We manage 150+ units. Scharge gives us real-time P&L, and we pay expenses directly from collected funds.</p>
                    <p class="font-bold text-gray-900 mt-4">— Grace W., Kisumu</p>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section id="faq" class="py-20 bg-gray-50">
        <div class="container mx-auto px-6">
            <div class="text-center max-w-2xl mx-auto mb-14">
                <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-3">Frequently asked questions</h2>
            </div>
            <div class="max-w-3xl mx-auto space-y-4">
                <div class="bg-white rounded-xl p-5 shadow-sm">
                    <button class="faq-question w-full text-left flex justify-between items-center">
                        <span class="font-semibold text-gray-900">What payment methods do tenants use?</span>
                        <svg class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div class="faq-answer hidden pt-3"><p class="text-gray-500">M-Pesa, Airtel Money, credit/debit cards, bank transfers, and standing orders.</p></div>
                </div>
                <div class="bg-white rounded-xl p-5 shadow-sm">
                    <button class="faq-question w-full text-left flex justify-between items-center">
                        <span class="font-semibold text-gray-900">How does the "2 extra months free" work?</span>
                        <svg class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div class="faq-answer hidden pt-3"><p class="text-gray-500">Your first successful collection triggers 2 months of waived platform fees. You pay nothing for months 2 and 3.</p></div>
                </div>
                <div class="bg-white rounded-xl p-5 shadow-sm">
                    <button class="faq-question w-full text-left flex justify-between items-center">
                        <span class="font-semibold text-gray-900">Can tenants transfer units without new accounts?</span>
                        <svg class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div class="faq-answer hidden pt-3"><p class="text-gray-500">Yes. One-click unit transfer. All payment history and deposits follow the tenant.</p></div>
                </div>
                <div class="bg-white rounded-xl p-5 shadow-sm">
                    <button class="faq-question w-full text-left flex justify-between items-center">
                        <span class="font-semibold text-gray-900">Is my money safe?</span>
                        <svg class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div class="faq-answer hidden pt-3"><p class="text-gray-500">PCI-DSS Level 1 certified. Funds held in escrow with partner banks.</p></div>
                </div>
                <div class="bg-white rounded-xl p-5 shadow-sm">
                    <button class="faq-question w-full text-left flex justify-between items-center">
                        <span class="font-semibold text-gray-900">Do you offer maintenance tracking?</span>
                        <svg class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div class="faq-answer hidden pt-3"><p class="text-gray-500">Yes. Tenants can request maintenance, you can assign vendors, track costs, and approve payments.</p></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Final CTA -->
    <section class="py-20 bg-red-600">
        <div class="container mx-auto px-6 text-center">
            <h2 class="text-4xl md:text-5xl font-bold text-white mb-4">Ready to simplify property management?</h2>
            <p class="text-xl text-red-100 mb-8 max-w-2xl mx-auto">Join 500+ property managers already using Scharge</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <button class="bg-white text-red-600 px-10 py-4 rounded-full font-bold text-lg shadow-lg hover:bg-gray-100 transition">Start free trial</button>
                <button class="border-2 border-white text-white px-10 py-4 rounded-full font-bold text-lg hover:bg-white hover:text-red-600 transition">Book a demo</button>
            </div>
            <p class="text-red-100 text-sm mt-8">The Atrium, 1st floor, Lenana Rd, Nairobi • hello@scharge.com • +254 700 123 456</p>
        </div>
    </section>

    <footer class="bg-red-600 text-white py-8">
        <div class="container mx-auto px-6 text-center">
            <p class="text-white-400 text-sm">&copy; 2026 Scharge. All rights reserved.</p>
        </div>
    </footer>

    <div class="scroll-top" id="scrollTop">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
        </svg>
    </div>

    <script>
        // Mobile menu
        const mobileBtn = document.getElementById('mobileMenuBtn');
        const mobileMenu = document.getElementById('mobileMenu');
        if(mobileBtn) mobileBtn.addEventListener('click', () => mobileMenu.classList.toggle('hidden'));
        
        // Smooth scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if(target) { target.scrollIntoView({ behavior: 'smooth' }); if(mobileMenu) mobileMenu.classList.add('hidden'); }
            });
        });
        
        // Scroll top
        const scrollTop = document.getElementById('scrollTop');
        window.addEventListener('scroll', () => { window.scrollY > 300 ? scrollTop.classList.add('show') : scrollTop.classList.remove('show'); });
        scrollTop.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
        
        // FAQ
        document.querySelectorAll('.faq-question').forEach(btn => {
            btn.addEventListener('click', () => {
                const answer = btn.nextElementSibling;
                const icon = btn.querySelector('svg');
                answer.classList.toggle('hidden');
                icon.classList.toggle('rotate-180');
            });
        });
        
        // Calculator
        const slider = document.getElementById('unitSlider');
        const unitVal = document.getElementById('unitValue');
        const charge = document.getElementById('serviceCharge');
        const monthly = document.getElementById('monthlyVolume');
        const feeSpan = document.getElementById('schargeFee');
        const savingsSpan = document.getElementById('savings');
        
        function updateCalc() {
            let units = parseInt(slider.value);
            let avg = parseFloat(charge.value) || 0;
            let total = units * avg;
            let percent = units <= 30 ? 0.018 : (units <= 100 ? 0.015 : (units <= 200 ? 0.012 : 0.009));
            let fee = total * percent;
            let manualLoss = total * 0.4;
            let save = manualLoss - fee;
            
            unitVal.innerText = units + ' units';
            monthly.innerText = 'KES ' + total.toLocaleString();
            feeSpan.innerText = 'KES ' + Math.round(fee).toLocaleString();
            savingsSpan.innerText = 'KES ' + Math.round(save).toLocaleString();
        }
        
        if(slider) { slider.addEventListener('input', updateCalc); charge.addEventListener('input', updateCalc); updateCalc(); }
        
        // Animation on scroll
        const cards = document.querySelectorAll('.glass-card, .pricing-card');
        const obs = new IntersectionObserver((entries) => {
            entries.forEach(entry => { if(entry.isIntersecting) entry.target.classList.add('animate-fade-up'); });
        }, { threshold: 0.1 });
        cards.forEach(c => obs.observe(c));
    </script>
</body>
</html>