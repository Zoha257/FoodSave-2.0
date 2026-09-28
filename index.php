<?php
require_once __DIR__ . '/includes/config.php';
$homeStats=['meals'=>0,'donors'=>0,'ngos'=>0,'success'=>0];
try{$pdo=getDBConnection();$homeStats['donors']=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE user_type='donor' AND status='active'")->fetchColumn();$homeStats['ngos']=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE user_type='ngo' AND status='active'")->fetchColumn();$homeStats['meals']=(int)$pdo->query("SELECT COUNT(*) FROM food_donations WHERE status IN ('picked_up','completed')")->fetchColumn();$total=(int)$pdo->query("SELECT COUNT(*) FROM food_donations")->fetchColumn();$done=(int)$pdo->query("SELECT COUNT(*) FROM food_donations WHERE status='completed'")->fetchColumn();$homeStats['success']=$total?round($done/$total*100):0;}catch(Exception $e){}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoodSave — Rescue surplus food, feed your community</title>
    <meta name="description" content="FoodSave connects food donors with NGOs, volunteers and drivers so surplus food reaches people instead of landfill.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="assets/css/style.css?v=3" rel="stylesheet">
    <link href="assets/css/landing.css?v=15" rel="stylesheet">
</head>
<body class="home-page">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg lp-nav">
        <div class="container">
            <a class="lp-brand" href="index.php"><span class="lp-brand__mark"><i class="fas fa-leaf"></i></span>FoodSave</a>
            <button class="navbar-toggler lp-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <i class="fas fa-bars"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-lg-auto">
                    <li class="nav-item"><a class="nav-link" href="#how">How it works</a></li>
                    <li class="nav-item"><a class="nav-link" href="#roles">Who it's for</a></li>
                    <li class="nav-item"><a class="nav-link" href="#impact">Impact</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                </ul>
                <div class="lp-nav__actions">
                    <div class="dropdown">
                        <a class="btn btn-secondary dropdown-toggle" href="#" id="loginDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">Log in</a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="loginDropdown">
                            <li><a class="dropdown-item" href="donor/login.php"><i class="fas fa-hand-holding-heart"></i>Donor</a></li>
                            <li><a class="dropdown-item" href="ngo/login.php"><i class="fas fa-building"></i>NGO</a></li>
                            <li><a class="dropdown-item" href="volunteer/login.php"><i class="fas fa-hands-helping"></i>Volunteer</a></li>
                            <li><a class="dropdown-item" href="driver/login.php"><i class="fas fa-truck"></i>Driver</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="admin/login.php"><i class="fas fa-user-shield"></i>Admin</a></li>
                        </ul>
                    </div>
                    <div class="dropdown">
                        <a class="btn btn-primary dropdown-toggle" href="#" id="registerDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">Get started</a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="registerDropdown">
                            <li><a class="dropdown-item" href="donor/register.php"><i class="fas fa-hand-holding-heart"></i>Register as Donor</a></li>
                            <li><a class="dropdown-item" href="ngo/register.php"><i class="fas fa-building"></i>Register as NGO</a></li>
                            <li><a class="dropdown-item" href="volunteer/register.php"><i class="fas fa-hands-helping"></i>Register as Volunteer</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <main>
        <!-- Hero -->
        <section class="lp-hero">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <span class="lp-eyebrow"><i class="fas fa-seedling"></i> Food rescue, made simple</span>
                        <h1 class="lp-hero__title">Turn surplus food into <em>shared meals.</em></h1>
                        <p class="lp-hero__lead">FoodSave connects restaurants, grocers and households with trusted NGOs, volunteers and drivers, so good food reaches people who need it instead of going to waste.</p>
                        <div class="lp-hero__ctas">
                            <a href="donor/register.php" class="btn btn-primary btn-lg"><i class="fas fa-hand-holding-heart"></i>Donate food</a>
                            <a href="ngo/register.php" class="btn btn-secondary btn-lg">Partner as an NGO <i class="fas fa-arrow-right"></i></a>
                        </div>
                        <ul class="lp-hero__trust">
                            <li><i class="fas fa-shield-heart"></i>Food-safety guidelines built in</li>
                            <li><i class="fas fa-bell"></i>Expiry alerts</li>
                            <li><i class="fas fa-route"></i>Live delivery tracking</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- Impact -->
        <section id="impact" class="lp-impact">
            <div class="container">
                <div class="lp-impact__grid">
                    <div class="lp-stat">
                        <span class="lp-stat__value"><?php echo number_format($homeStats['meals']); ?></span>
                        <span class="lp-stat__label">Donations rescued</span>
                    </div>
                    <div class="lp-stat">
                        <span class="lp-stat__value"><?php echo number_format($homeStats['donors']); ?></span>
                        <span class="lp-stat__label">Active donors</span>
                    </div>
                    <div class="lp-stat">
                        <span class="lp-stat__value"><?php echo number_format($homeStats['ngos']); ?></span>
                        <span class="lp-stat__label">Partner NGOs</span>
                    </div>
                    <div class="lp-stat">
                        <span class="lp-stat__value"><?php echo (int)$homeStats['success']; ?>%</span>
                        <span class="lp-stat__label">Completion rate</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- How it works -->
        <section id="how" class="lp-section">
            <div class="container">
                <div class="lp-section__head">
                    <span class="lp-eyebrow">How it works</span>
                    <h2>From surplus to someone's plate in three steps</h2>
                    <p>Millions of meals are thrown away while families face food insecurity. FoodSave makes the handoff quick, safe and trackable.</p>
                </div>
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="lp-step">
                            <span class="lp-step__num">01</span>
                            <span class="lp-step__icon"><i class="fas fa-box-open"></i></span>
                            <h3>List surplus food</h3>
                            <p>Donors add items with quantity, expiry date and pickup location in under a minute.</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="lp-step">
                            <span class="lp-step__num">02</span>
                            <span class="lp-step__icon"><i class="fas fa-magnifying-glass-location"></i></span>
                            <h3>Match with an NGO</h3>
                            <p>NGOs browse by category, distance and expiry, then request a pickup with one click.</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="lp-step">
                            <span class="lp-step__num">03</span>
                            <span class="lp-step__icon"><i class="fas fa-truck-fast"></i></span>
                            <h3>Deliver &amp; track</h3>
                            <p>Volunteers and drivers pick up and deliver, with live status updates at every stage.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Roles -->
        <section id="roles" class="lp-section lp-section--tint">
            <div class="container">
                <div class="lp-section__head">
                    <span class="lp-eyebrow">Who it's for</span>
                    <h2>One platform for the whole food-rescue chain</h2>
                </div>
                <div class="row g-4">
                    <div class="col-sm-6 col-lg-3">
                        <a class="lp-role" href="donor/register.php">
                            <span class="lp-role__icon"><i class="fas fa-hand-holding-heart"></i></span>
                            <h3>Donors</h3>
                            <p>Restaurants, grocers and individuals list surplus and approve pickups.</p>
                            <span class="lp-role__link">Start donating <i class="fas fa-arrow-right"></i></span>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="lp-role" href="ngo/register.php">
                            <span class="lp-role__icon lp-role__icon--blue"><i class="fas fa-building"></i></span>
                            <h3>NGOs</h3>
                            <p>Find nearby donations, manage inventory and coordinate volunteers.</p>
                            <span class="lp-role__link">Become a partner <i class="fas fa-arrow-right"></i></span>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="lp-role" href="volunteer/register.php">
                            <span class="lp-role__icon lp-role__icon--amber"><i class="fas fa-hands-helping"></i></span>
                            <h3>Volunteers</h3>
                            <p>Pick up opportunities that fit your schedule and track your impact.</p>
                            <span class="lp-role__link">Join as volunteer <i class="fas fa-arrow-right"></i></span>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a class="lp-role" href="driver/login.php">
                            <span class="lp-role__icon lp-role__icon--violet"><i class="fas fa-truck"></i></span>
                            <h3>Drivers</h3>
                            <p>Accept delivery runs, follow routes and update status on the go.</p>
                            <span class="lp-role__link">Driver sign in <i class="fas fa-arrow-right"></i></span>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features -->
        <section id="features" class="lp-section">
            <div class="container">
                <div class="row g-5 align-items-center">
                    <div class="col-lg-5">
                        <span class="lp-eyebrow">Built for real operations</span>
                        <h2 class="lp-h2">Everything you need to share more, waste less</h2>
                        <p class="lp-muted">Tools that keep food safe, pickups on time and every stakeholder in the loop.</p>
                        <a href="donor/register.php" class="btn btn-primary mt-2">Create a free account <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <div class="col-lg-7">
                        <div class="lp-features">
                            <div class="lp-feature"><i class="fas fa-list-check"></i><div><h3>Easy listing</h3><p>Quantity, category, expiry and pickup window in one form.</p></div></div>
                            <div class="lp-feature"><i class="fas fa-map-location-dot"></i><div><h3>Map-based search</h3><p>Filter donations by location, category and freshness.</p></div></div>
                            <div class="lp-feature"><i class="fas fa-robot"></i><div><h3>AI expiry risk</h3><p>Prioritise items most likely to spoil first.</p></div></div>
                            <div class="lp-feature"><i class="fas fa-clipboard-check"></i><div><h3>Food-safety guidance</h3><p>Built-in handling guidelines for donors and NGOs.</p></div></div>
                            <div class="lp-feature"><i class="fas fa-bell"></i><div><h3>Smart notifications</h3><p>Pickup requests, approvals and expiry alerts in real time.</p></div></div>
                            <div class="lp-feature"><i class="fas fa-receipt"></i><div><h3>Pickup receipts</h3><p>Printable records for every completed handoff.</p></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="lp-cta-wrap">
            <div class="container">
                <div class="lp-cta">
                    <div>
                        <h2>Start helping today</h2>
                        <p>Join FoodSave in reducing food waste and fighting hunger in your community.</p>
                    </div>
                    <div class="lp-cta__actions">
                        <a href="donor/register.php" class="btn btn-lg lp-btn-light"><i class="fas fa-hand-holding-heart"></i>Donate food</a>
                        <a href="volunteer/register.php" class="btn btn-lg lp-btn-ghost">Volunteer with us</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="lp-footer">
        <div class="container">
            <div class="lp-footer__top">
                <div>
                    <a class="lp-brand" href="index.php"><span class="lp-brand__mark"><i class="fas fa-leaf"></i></span>FoodSave</a>
                    <p>Reducing food waste, one donation at a time.</p>
                </div>
                <div class="lp-footer__links">
                    <div>
                        <h4>Get involved</h4>
                        <a href="donor/register.php">Donate food</a>
                        <a href="ngo/register.php">Partner as NGO</a>
                        <a href="volunteer/register.php">Volunteer</a>
                    </div>
                    <div>
                        <h4>Platform</h4>
                        <a href="#how">How it works</a>
                        <a href="#roles">Who it's for</a>
                        <a href="contact.php">Contact</a>
                    </div>
                </div>
            </div>
            <div class="lp-footer__bottom">
                <span>&copy; <?php echo date("Y"); ?> FoodSave. All rights reserved.</span>
                <div class="lp-social">
                    <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
