<!doctype html>
<html lang="en">
<head>
    <?= jeepnigo_security_head() ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="JeepniGo helps commuters find rides, check availability, and travel with less uncertainty.">
    <title>JeepniGo — Move smarter</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="assets/css/landing-modern.css" rel="stylesheet">
</head>
<body>
<a class="skip-link" href="#main-content">Skip to content</a>

<nav class="site-nav navbar navbar-expand-lg fixed-top" aria-label="Main navigation">
    <div class="container">
        <a class="navbar-brand" href="#home" aria-label="JeepniGo home">
            <img src="img/logo12.png" alt="" width="40" height="40">
            <span>JeepniGo</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <li class="nav-item"><a class="nav-link" href="#home">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="#how-it-works">How It Works</a></li>
                <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                <li class="nav-item nav-cta"><button class="btn btn-primary" data-open-login>Get Started</button></li>
            </ul>
        </div>
    </div>
</nav>

<main id="main-content">
    <section class="hero" id="home">
        <div class="container hero-grid">
            <div class="hero-copy">
                <span class="eyebrow"><i class="bi bi-bus-front"></i> Smarter public transport</span>
                <h1>Your commute,<br><span>without the uncertainty.</span></h1>
                <p>JeepniGo helps commuters discover rides, check availability, and move through the city with less waiting and more confidence.</p>
                <div class="hero-actions">
                    <button class="btn btn-primary btn-lg" data-open-login>Get Started <i class="bi bi-arrow-right"></i></button>
                    <a class="btn btn-secondary btn-lg" href="#how-it-works">Learn More</a>
                </div>
                <ul class="hero-points" aria-label="JeepniGo benefits">
                    <li><i class="bi bi-check-circle-fill"></i> Clearer ride information</li>
                    <li><i class="bi bi-check-circle-fill"></i> Easier trip planning</li>
                </ul>
            </div>
            <div class="scene-shell" id="mobility-scene" aria-label="Animated city routes and public transport visualization">
                <canvas id="hero-canvas" aria-hidden="true"></canvas>
                <div class="scene-fallback" aria-hidden="true">
                    <div class="fallback-grid"></div>
                    <span class="fallback-route route-a"></span>
                    <span class="fallback-route route-b"></span>
                    <span class="fallback-node pickup"></span>
                    <span class="fallback-node destination"></span>
                    <span class="fallback-vehicle"><i class="bi bi-bus-front-fill"></i></span>
                </div>
                <div class="scene-label scene-label-live"><span></span> Route active</div>
                <div class="scene-label scene-label-eta"><strong>Ride nearby</strong><small>Check availability</small></div>
            </div>
        </div>
    </section>

    <section class="section section-muted" id="problems">
        <div class="container">
            <div class="section-heading"><span>THE DAILY CHALLENGE</span><h2>Commuting should involve less guesswork.</h2><p>Common uncertainties make even familiar journeys harder to plan.</p></div>
            <div class="card-grid three">
                <article class="info-card"><div class="icon amber"><i class="bi bi-clock"></i></div><h3>Long waiting times</h3><p>Without timely ride information, commuters lose valuable time at stops.</p></article>
                <article class="info-card"><div class="icon cyan"><i class="bi bi-question-circle"></i></div><h3>Uncertain availability</h3><p>It can be difficult to know when the next suitable jeepney will arrive.</p></article>
                <article class="info-card"><div class="icon green"><i class="bi bi-geo-alt"></i></div><h3>Unclear pickup points</h3><p>Finding the right place to wait adds friction to an everyday trip.</p></article>
            </div>
        </div>
    </section>

    <section class="section" id="how-it-works">
        <div class="container">
            <div class="section-heading centered"><span>HOW IT WORKS</span><h2>Three simple steps to a clearer ride.</h2></div>
            <ol class="steps">
                <li><span>01</span><div class="step-icon"><i class="bi bi-search"></i></div><h3>Find</h3><p>Choose your route and pickup point.</p></li>
                <li><span>02</span><div class="step-icon"><i class="bi bi-eye"></i></div><h3>Check</h3><p>Review ride availability and trip details.</p></li>
                <li><span>03</span><div class="step-icon"><i class="bi bi-bus-front"></i></div><h3>Ride</h3><p>Board, pay your fare, and continue confidently.</p></li>
            </ol>
        </div>
    </section>

    <section class="section section-navy" id="features">
        <div class="container feature-layout">
            <div class="section-heading light"><span>JEEPNIGO FEATURES</span><h2>Useful tools for the complete commute.</h2><p>Every feature below is already part of the JeepniGo passenger experience.</p><button class="btn btn-light" data-open-login>Explore JeepniGo</button></div>
            <div class="feature-grid">
                <article><i class="bi bi-signpost-split"></i><div><h3>Route and stop guidance</h3><p>Browse supported routes, stops, and fare information.</p></div></article>
                <article><i class="bi bi-calendar-check"></i><div><h3>Ride reservations</h3><p>Request a ride and follow its current reservation status.</p></div></article>
                <article><i class="bi bi-clock-history"></i><div><h3>Arrival estimates</h3><p>Receive ETA updates during an active reservation.</p></div></article>
                <article><i class="bi bi-receipt"></i><div><h3>Fare records</h3><p>Complete supported fare actions and keep payment details together.</p></div></article>
            </div>
        </div>
    </section>

    <section class="section" id="about">
        <div class="container why-grid">
            <div><span class="eyebrow">WHY JEEPNIGO</span><h2>Designed around the realities of everyday mobility.</h2><p>JeepniGo connects commuters with the information and actions they need before and during a ride—without adding unnecessary complexity.</p></div>
            <div class="why-list">
                <article><i class="bi bi-compass"></i><div><h3>Plan more easily</h3><p>Bring route, availability, and fare information into one journey.</p></div></article>
                <article><i class="bi bi-shield-check"></i><div><h3>Ride with confidence</h3><p>Follow clear trip states from waiting through boarding.</p></div></article>
                <article><i class="bi bi-phone"></i><div><h3>Built for convenience</h3><p>Use essential commute tools from a mobile-friendly experience.</p></div></article>
            </div>
        </div>
    </section>

    <section class="section cta-section">
        <div class="container cta-panel"><div><span>YOUR NEXT RIDE</span><h2>Ready for a smarter commute?</h2><p>Join JeepniGo and take more uncertainty out of your everyday journey.</p></div><button class="btn btn-primary btn-lg" data-open-signup>Create an account <i class="bi bi-arrow-right"></i></button></div>
    </section>
</main>

<footer class="site-footer">
    <div class="container footer-grid"><div class="footer-brand"><a href="#home"><img src="img/logo12.png" alt="" width="40" height="40"><strong>JeepniGo</strong></a><p>Helping communities move with clearer information and greater confidence.</p></div><nav aria-label="Footer navigation"><a href="#how-it-works">How It Works</a><a href="#features">Features</a><a href="#about">About</a></nav></div>
    <div class="container footer-bottom"><span>© <?= date('Y') ?> JeepniGo. All rights reserved.</span><button class="link-button" data-open-login>Member sign in</button></div>
</footer>

<div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content auth-modal"><div class="modal-header"><div><span class="modal-kicker">WELCOME BACK</span><h2 class="modal-title" id="loginTitle">Sign in to JeepniGo</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body">
        <form id="loginForm" method="post"><input type="hidden" name="action" value="login"><div class="mb-3"><label class="form-label" for="loginEmail">Email address</label><input class="form-control" id="loginEmail" type="email" name="email" autocomplete="email" required></div><div class="mb-4"><label class="form-label" for="loginPassword">Password</label><input class="form-control" id="loginPassword" type="password" name="password" autocomplete="current-password" required></div><button class="btn btn-primary w-100" type="submit">Sign In</button></form>
        <p class="auth-switch">New to JeepniGo? <button type="button" data-switch-signup>Create an account</button></p>
    </div></div></div>
</div>

<div class="modal fade" id="signupModal" tabindex="-1" aria-labelledby="signupTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content auth-modal"><div class="modal-header"><div><span class="modal-kicker">GET STARTED</span><h2 class="modal-title" id="signupTitle">Create your account</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body">
        <form id="signupForm" method="post"><input type="hidden" name="action" value="signup"><div class="row g-3 mb-3"><div class="col-sm-6"><label class="form-label" for="firstName">First name</label><input class="form-control" id="firstName" name="firstName" autocomplete="given-name" required></div><div class="col-sm-6"><label class="form-label" for="lastName">Last name</label><input class="form-control" id="lastName" name="lastName" autocomplete="family-name" required></div></div><div class="mb-3"><label class="form-label" for="signupEmail">Email address</label><input class="form-control" id="signupEmail" type="email" name="email" autocomplete="email" required></div><div class="row g-3 mb-4"><div class="col-sm-6"><label class="form-label" for="signupPassword">Password</label><input class="form-control" id="signupPassword" type="password" name="password" autocomplete="new-password" required></div><div class="col-sm-6"><label class="form-label" for="confirmPassword">Confirm password</label><input class="form-control" id="confirmPassword" type="password" name="confirm_password" autocomplete="new-password" required></div></div><button class="btn btn-primary w-100" type="submit">Create Account</button></form>
        <p class="auth-switch">Already have an account? <button type="button" data-switch-login>Sign in</button></p>
    </div></div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<script type="module" src="assets/js/landing-three.js"></script>
</body>
</html>
