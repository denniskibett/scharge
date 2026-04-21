<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Scharge | Collect Service Charge, Rent & Security Deposits</title>
    <meta name="description" content="Kenya's #1 property collection platform. Automate service charge, rent, and security deposit collection. Pay expenses directly. 0% setup fee.">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <style>
        * {
            font-family: 'Outfit', sans-serif;
        }
        
        /* Hero background with overlay */
        .hero-section {
            background-image: linear-gradient(135deg, rgba(0,0,0,0.8) 0%, rgba(220,38,38,0.75) 100%), url('https://images.unsplash.com/photo-1560518883-ce09059eeffa?w=1600');
            background-size: cover;
            background-position: center;
        }
        
        /* Glass card effect */
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
        
        /* Feature icons */
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
        
        /* Buttons */
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
        
        /* Pricing cards */
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
        
        /* Stats counter */
        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            line-height: 1.2;
        }
        
        /* Range slider */
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
        
        /* Scroll to top */
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
        
        .scroll-top.show {
            opacity: 1;
            visibility: visible;
        }
        
        .scroll-top:hover {
            background: #B91C1C;
            transform: translateY(-3px);
        }
        
        /* FAQ */
        .faq-question svg {
            transition: transform 0.2s ease;
        }
        
        .rotate-180 {
            transform: rotate(180deg);
        }
        
        /* Animations */
        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-fade-up {
            animation: fadeUp 0.6s ease forwards;
        }
    </style>
</head>
<body class="bg-white">

    <!-- Navigation - Glassy Red (like Purdue but red) -->
    <nav class="fixed top-0 left-0 w-full z-50">
        <div class="container mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <!-- Logo -->
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <svg class="w-6 h-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <span class="text-2xl font-bold text-white tracking-tight">Scharge</span>
                    <span class="text-xs text-white/80 bg-white/20 px-2 py-0.5 rounded-full">Property</span>
                </div>
                
                <!-- Desktop Menu -->
                <div class="hidden md:flex items-center gap-8">
                    <a href="#home" class="text-white/90 hover:text-white font-medium transition">Home</a>
                    <a href="#features" class="text-white/90 hover:text-white font-medium transition">Features</a>
                    <a href="#pricing" class="text-white/90 hover:text-white font-medium transition">Pricing</a>
                    <a href="#testimonials" class="text-white/90 hover:text-white font-medium transition">Success</a>
                    <a href="#faq" class="text-white/90 hover:text-white font-medium transition">FAQ</a>
                </div>
                
                <!-- Auth Buttons -->
                <div class="hidden md:flex items-center gap-4">
                    <a href="/login" class="border border-white/30 text-white px-6 py-2 rounded-full font-semibold shadow-md hover:bg-red-700 transition">Login</a>
                    
                </div>
                
                <!-- Mobile Menu Button -->
                <button id="mobileMenuBtn" class="md:hidden text-white">
                    <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>
            
            <!-- Mobile Menu -->
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

    <!-- Hero Section - Large and bold like Purdue -->
    <section id="home" class="hero-section min-h-screen flex items-center pt-20">
        <div class="container mx-auto px-6 py-20">
            <div class="max-w-3xl">
                <div class="inline-flex items-center gap-2 bg-white/10 backdrop-blur-sm rounded-full px-5 py-2 mb-6">
                    <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
                    <span class="text-white text-sm font-medium">Trusted by 500+ property managers in Kenya</span>
                </div>
                <h1 class="text-5xl md:text-7xl font-bold text-white mb-6 leading-tight">
                    Collect service charge,<br/>rent & security deposits
                </h1>
                <p class="text-xl text-white/80 mb-8 leading-relaxed max-w-xl">
                    One platform to automate collections, pay expenses, and manage tenants. 
                    <span class="text-white font-semibold">Your first payment unlocks 2 extra months free.</span>
                </p>
                <div class="flex flex-col sm:flex-row gap-4">
                    <button class="btn-primary px-8 py-4 rounded-full text-white font-semibold text-lg">
                        Start collecting now
                    </button>
                    <button class="btn-outline px-8 py-4 rounded-full text-white font-semibold text-lg bg-transparent">
                        Watch 2-min demo
                    </button>
                </div>
                <p class="text-white/50 text-sm mt-6">No setup fee • No contract • Cancel anytime</p>
            </div>
        </div>
    </section>

    <!-- Stats Section - Large numbers like Purdue -->
    <section class="py-16 bg-white border-b border-gray-100">
        <div class="container mx-auto px-6">
            <div class="grid md:grid-cols-4 gap-8 text-center">
                <div class="stat-card">
                    <div class="stat-number text-red-600">500+</div>
                    <div class="text-gray-500 font-medium mt-1">Property Portfolios</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number text-red-600"> 2.5B+</div>
                    <div class="text-gray-500 font-medium mt-1">KES Collected Annually</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number text-red-600">99.9%</div>
                    <div class="text-gray-500 font-medium mt-1">Collection Accuracy</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number text-red-600">85%</div>
                    <div class="text-gray-500 font-medium mt-1">Less Admin Time</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section - Clean grid, big cards -->
    <section id="features" class="py-20 bg-gray-50">
        <div class="container mx-auto px-6">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <span class="text-red-600 font-semibold text-sm uppercase tracking-wide">Why property managers switch</span>
                <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mt-2 mb-4">Everything you need to run collections</h2>
                <p class="text-gray-500 text-lg">Stop losing 40% of service charge revenue to manual errors</p>
            </div>
            
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-7">
                <!-- Feature 1 -->
                <div class="glass-card p-7">
                    <div class="feature-icon">
                        <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M6 19h12a2 2 0 002-2V7a2 2 0 00-2-2H6a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14h.01M12 14h.01M16 14h.01" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Auto collection</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">Set recurring bills. Automatic M-Pesa, card, and bank transfers.</p>
                    <div class="mt-4 inline-block bg-green-50 text-green-700 text-xs font-semibold px-2 py-1 rounded">78% less arrears</div>
                </div>
                
                <!-- Feature 2 -->
                <div class="glass-card p-7">
                    <div class="feature-icon">
                        <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Unit transfer</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">Tenants move units without creating new accounts. All history preserved.</p>
                    <div class="mt-4 inline-block bg-green-50 text-green-700 text-xs font-semibold px-2 py-1 rounded">85% less admin time</div>
                </div>
                
                <!-- Feature 3 -->
                <div class="glass-card p-7">
                    <div class="feature-icon">
                        <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Live analytics</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">Real-time P&L per property. Collection rates. Arrears tracking.</p>
                    <div class="mt-4 inline-block bg-green-50 text-green-700 text-xs font-semibold px-2 py-1 rounded">Export to Excel</div>
                </div>
                
                <!-- Feature 4 -->
                <div class="glass-card p-7">
                    <div class="feature-icon">
                        <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Pay expenses</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">Pay utilities, maintenance, staff from collected funds.</p>
                    <div class="mt-4 inline-block bg-green-50 text-green-700 text-xs font-semibold px-2 py-1 rounded">24hr settlements</div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works - Simple steps -->
    <section class="py-20 bg-white">
        <div class="container mx-auto px-6">
            <div class="text-center max-w-2xl mx-auto mb-14">
                <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-3">How it works</h2>
                <p class="text-gray-500 text-lg">Set up in under 10 minutes</p>
            </div>
            <div class="grid md:grid-cols-3 gap-10 max-w-4xl mx-auto">
                <div class="text-center">
                    <div class="w-20 h-20 bg-red-600 text-white rounded-2xl flex items-center justify-center text-3xl font-bold mx-auto mb-5 shadow-lg">1</div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Add properties</h3>
                    <p class="text-gray-500">Upload units, set service charges, invite tenants</p>
                </div>
                <div class="text-center">
                    <div class="w-20 h-20 bg-red-600 text-white rounded-2xl flex items-center justify-center text-3xl font-bold mx-auto mb-5 shadow-lg">2</div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Auto-collect</h3>
                    <p class="text-gray-500">Tenants pay via M-Pesa. Automatic reminders sent</p>
                </div>
                <div class="text-center">
                    <div class="w-20 h-20 bg-red-600 text-white rounded-2xl flex items-center justify-center text-3xl font-bold mx-auto mb-5 shadow-lg">3</div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Pay & track</h3>
                    <p class="text-gray-500">Pay expenses from collected funds. View live reports</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section with Calculator -->
    <section id="pricing" class="py-20 bg-gray-50">
        <div class="container mx-auto px-6">
            <div class="text-center max-w-2xl mx-auto mb-14">
                <span class="text-red-600 font-semibold text-sm uppercase tracking-wide">Simple pricing</span>
                <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mt-2 mb-3">You pay only when you collect</h2>
                <p class="text-gray-500 text-lg">No monthly fees. No setup costs.</p>
            </div>
            
            <!-- Calculator -->
            <div class="max-w-2xl mx-auto bg-white rounded-2xl p-8 shadow-md mb-16">
                <h3 class="text-xl font-bold text-gray-900 mb-5 text-center">Calculate your savings</h3>
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
                        <label class="text-gray-700 font-medium block mb-2">Average service charge (KES)</label>
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

    <!-- Testimonial Section - Like Purdue -->
    <section id="testimonials" class="py-20 bg-white">
        <!-- Pricing Cards -->
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
    </section>

    <!-- FAQ Section -->
    <section id="faq" class="py-20 bg-gray-50">
        <div class="container mx-auto px-6">
            <div class="text-center max-w-2xl mx-auto mb-14">
                <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-3">Quick answers</h2>
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
            </div>
        </div>
    </section>

    <!-- Final CTA - Bold -->
    <section class="py-20 bg-red-600">
        <div class="container mx-auto px-6 text-center">
            <h2 class="text-4xl md:text-5xl font-bold text-white mb-4">Ready to stop losing revenue?</h2>
            <p class="text-xl text-red-100 mb-8 max-w-2xl mx-auto">Join 500+ property managers already using Scharge</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <button class="bg-white text-red-600 px-10 py-4 rounded-full font-bold text-lg shadow-lg hover:bg-gray-100 transition">Start free trial</button>
                <button class="border-2 border-white text-white px-10 py-4 rounded-full font-bold text-lg hover:bg-white hover:text-red-600 transition">Book a demo</button>
            </div>
            <p class="text-red-100 text-sm mt-8">The Atrium, 1st floor, Lenana Rd, Nairobi • hello@scharge.com • +254 700 123 456</p>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-red-600 text-white py-8">
        <div class="container mx-auto px-6 text-center">
            <p class="text-white-400 text-sm">&copy; 2026 Scharge. All rights reserved.</p>
        </div>
    </footer>

    <!-- Scroll to Top -->
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