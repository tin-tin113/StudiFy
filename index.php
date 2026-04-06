<?php
/**
 * STUDIFY – Landing Page
 * Modern, animated, convincing
 */
define('BASE_URL', './');
require_once 'config/db.php';

// Fallback health endpoint for hosts using front-controller routing.
$request_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($request_path === '/health' || $request_path === '/health.php') {
    header('Content-Type: application/json');
    $ok = ($conn instanceof mysqli && $conn->ping());
    http_response_code($ok ? 200 : 503);
    echo json_encode([
        'ok' => $ok,
        'service' => 'studify',
        'timestamp' => gmdate('c'),
        'db' => $ok ? 'ok' : 'down',
    ]);
    exit();
}

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/admin_dashboard.php");
    } else {
        header("Location: student/dashboard.php");
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Studify – Student Task Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Crect width='40' height='40' rx='10' fill='%2316A34A'/%3E%3Cpath d='M10 13.5c0-.6.4-1 1-1 1.5 0 4.2.5 9 2.5v13.5c-4.8-2-7.5-2.5-9-2.5-.6 0-1-.4-1-1V13.5z' fill='%23fff' opacity='.9'/%3E%3Cpath d='M30 13.5c0-.6-.4-1-1-1-1.5 0-4.2.5-9 2.5v13.5c4.8-2 7.5-2.5 9-2.5.6 0 1-.4 1-1V13.5z' fill='%23fff' opacity='.7'/%3E%3C/svg%3E">
    <noscript><style>.fade-in-up { opacity: 1 !important; transform: none !important; }</style></noscript>
</head>
<body class="landing-page">

    <!-- Navigation -->
    <nav class="landing-nav" id="landingNav">
        <div class="container d-flex justify-content-between align-items-center">
            <a href="index.php" class="nav-brand">
                <div class="icon">
                    <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="40" height="40" rx="10" fill="#16A34A"/>
                        <path d="M20 12c-2.5 0-5 .8-5 .8v14.4s2.5-.8 5-.8 5 .8 5 .8V12.8s-2.5-.8-5-.8z" fill="#fff" opacity=".15"/>
                        <path d="M10 13.5c0-.6.4-1 1-1 1.5 0 4.2.5 9 2.5v13.5c-4.8-2-7.5-2.5-9-2.5-.6 0-1-.4-1-1V13.5z" fill="#fff" opacity=".9"/>
                        <path d="M30 13.5c0-.6-.4-1-1-1-1.5 0-4.2.5-9 2.5v13.5c4.8-2 7.5-2.5 9-2.5.6 0 1-.4 1-1V13.5z" fill="#fff" opacity=".7"/>
                        <line x1="20" y1="14.5" x2="20" y2="28.5" stroke="#16A34A" stroke-width=".6" opacity=".5"/>
                    </svg>
                </div>
                Studi<span style="color: var(--primary);">fy</span>
            </a>
            <div class="d-flex gap-2 align-items-center">
                <a href="#features" class="d-none d-md-inline-block" style="font-size:13px; color:var(--text-secondary); text-decoration:none; margin-right:12px; font-weight:500;">Features</a>
                <a href="#how-it-works" class="d-none d-md-inline-block" style="font-size:13px; color:var(--text-secondary); text-decoration:none; margin-right:12px; font-weight:500;">How It Works</a>
                <a href="auth/login.php" class="btn btn-secondary" style="font-size: 13px;">Login</a>
                <a href="auth/register.php" class="btn btn-primary" style="font-size: 13px;">Get Started</a>
            </div>
        </div>
    </nav>

    <!-- Hero -->
    <section class="hero-section">
        <div class="landing-glow landing-glow-1"></div>
        <div class="landing-glow landing-glow-2"></div>
        <div class="container" style="position:relative; z-index:2;">
            <div class="text-center" style="max-width: 720px; margin: 0 auto;">
                <div class="hero-badge slide-up">
                    <i class="fas fa-graduation-cap"></i> Built for Students, by Students
                </div>
                <h1 class="slide-up delay-1">Stop Stressing About<br class="d-none d-md-inline">Deadlines. <span>Start Studying Smarter.</span></h1>
                <p class="slide-up delay-2" style="max-width: 540px; margin: 0 auto 32px;">
                    Studify is the all-in-one academic planner that organizes your semesters, tracks your tasks, times your study sessions, and keeps you accountable — so nothing slips through the cracks.
                </p>
                <div class="hero-buttons justify-content-center slide-up delay-3">
                    <a href="auth/register.php" class="btn-hero btn-hero-primary">
                        <i class="fas fa-rocket"></i> Create Free Account
                    </a>
                    <a href="#features" class="btn-hero btn-hero-outline">
                        <i class="fas fa-play-circle"></i> See What's Inside
                    </a>
                </div>
                <div class="hero-trust slide-up delay-4">
                    <div class="hero-trust-avatars">
                        <div class="hero-trust-avatar" style="background:#16A34A;">S</div>
                        <div class="hero-trust-avatar" style="background:#2563EB;">A</div>
                        <div class="hero-trust-avatar" style="background:#EAB308;">M</div>
                        <div class="hero-trust-avatar" style="background:#DC2626;">J</div>
                    </div>
                    <span>Trusted by <strong>IS students</strong> to manage their academic workload</span>
                </div>
            </div>

            <!-- Floating Dashboard Preview -->
            <div class="hero-preview slide-up delay-5">
                <div class="hero-preview-window">
                    <div class="hero-preview-topbar">
                        <span class="preview-dot" style="background:#FF5F57;"></span>
                        <span class="preview-dot" style="background:#FFBD2E;"></span>
                        <span class="preview-dot" style="background:#28C840;"></span>
                        <span class="preview-url">studify.app/dashboard</span>
                    </div>
                    <div class="hero-preview-body">
                        <div class="preview-sidebar">
                            <div class="preview-sidebar-item active"><i class="fas fa-home"></i></div>
                            <div class="preview-sidebar-item"><i class="fas fa-tasks"></i></div>
                            <div class="preview-sidebar-item"><i class="fas fa-calendar"></i></div>
                            <div class="preview-sidebar-item"><i class="fas fa-clock"></i></div>
                            <div class="preview-sidebar-item"><i class="fas fa-chart-bar"></i></div>
                        </div>
                        <div class="preview-main">
                            <div class="preview-stats-row">
                                <div class="preview-stat-card">
                                    <div class="preview-stat-icon" style="background:var(--primary-50);color:var(--primary);"><i class="fas fa-check-circle"></i></div>
                                    <div><strong>24</strong><small>Completed</small></div>
                                </div>
                                <div class="preview-stat-card">
                                    <div class="preview-stat-icon" style="background:var(--warning-light);color:var(--warning);"><i class="fas fa-hourglass-half"></i></div>
                                    <div><strong>8</strong><small>Pending</small></div>
                                </div>
                                <div class="preview-stat-card">
                                    <div class="preview-stat-icon" style="background:var(--info-light);color:var(--info);"><i class="fas fa-fire"></i></div>
                                    <div><strong>5 days</strong><small>Study Streak</small></div>
                                </div>
                                <div class="preview-stat-card">
                                    <div class="preview-stat-icon" style="background:var(--danger-light);color:var(--danger);"><i class="fas fa-bullseye"></i></div>
                                    <div><strong>87%</strong><small>Completion</small></div>
                                </div>
                            </div>
                            <div class="preview-tasks">
                                <div class="preview-task">
                                    <span class="preview-task-check done"><i class="fas fa-check"></i></span>
                                    <span class="preview-task-text done">Database ER Diagram</span>
                                    <span class="preview-task-badge" style="background:var(--success-light);color:var(--success);">Done</span>
                                </div>
                                <div class="preview-task">
                                    <span class="preview-task-check"></span>
                                    <span class="preview-task-text">Thesis Chapter 3 Draft</span>
                                    <span class="preview-task-badge" style="background:var(--danger-light);color:var(--danger);">High</span>
                                </div>
                                <div class="preview-task">
                                    <span class="preview-task-check"></span>
                                    <span class="preview-task-text">Review OOP Concepts</span>
                                    <span class="preview-task-badge" style="background:var(--warning-light);color:var(--warning);">Medium</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features -->
    <section class="features-section" id="features">
        <div class="container">
            <div class="section-header fade-in-up">
                <div class="hero-badge" style="margin: 0 auto 16px;"><i class="fas fa-star"></i> Core Features</div>
                <h2>Everything You Need to Ace Your Semester</h2>
                <p>Six powerful tools working together so you never miss a deadline or lose focus again.</p>
            </div>

            <div class="row g-3 g-md-4">
                <div class="col-6 col-md-4 fade-in-up">
                    <div class="feature-card">
                        <div class="feature-icon" style="background: var(--primary-50); color: var(--primary);">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <h4>Semester & Subject Organizer</h4>
                        <p>Structure your year into semesters and subjects. Every task, note, and session maps to your real academic schedule.</p>
                    </div>
                </div>
                <div class="col-6 col-md-4 fade-in-up">
                    <div class="feature-card">
                        <div class="feature-icon" style="background: var(--info-light); color: var(--info);">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <h4>Smart Task Manager</h4>
                        <p>Create tasks with deadlines, priorities, and types. Filter, sort, and toggle status with one click — no page reloads.</p>
                    </div>
                </div>
                <div class="col-6 col-md-4 fade-in-up">
                    <div class="feature-card">
                        <div class="feature-icon" style="background: var(--danger-light); color: var(--danger);">
                            <i class="fas fa-stopwatch"></i>
                        </div>
                        <h4>Pomodoro Focus Timer</h4>
                        <p>25-minute focus sessions with break intervals. Every session is logged so you can see exactly where your hours go.</p>
                    </div>
                </div>
                <div class="col-6 col-md-4 fade-in-up">
                    <div class="feature-card">
                        <div class="feature-icon" style="background: var(--warning-light); color: var(--warning);">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h4>Visual Calendar</h4>
                        <p>See every deadline on an interactive calendar, color-coded by priority. Drag tasks to reschedule instantly.</p>
                    </div>
                </div>
                <div class="col-6 col-md-4 fade-in-up">
                    <div class="feature-card">
                        <div class="feature-icon" style="background: var(--success-light); color: var(--success);">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h4>Study Analytics</h4>
                        <p>Track your study streaks, weekly hours, and productivity trends. Data-driven insights to optimize your study habits.</p>
                    </div>
                </div>
                <div class="col-6 col-md-4 fade-in-up">
                    <div class="feature-card">
                        <div class="feature-icon" style="background: #F3E8FF; color: #7C3AED;">
                            <i class="fas fa-user-friends"></i>
                        </div>
                        <h4>Study Buddy System</h4>
                        <p>Pair with a classmate for accountability. Exchange nudges, track each other's progress, and stay motivated together.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="how-section" id="how-it-works">
        <div class="container">
            <div class="section-header fade-in-up">
                <div class="hero-badge" style="margin: 0 auto 16px;"><i class="fas fa-route"></i> How It Works</div>
                <h2>Three Steps to Academic Control</h2>
                <p>Get started in under a minute. No setup wizards, no complicated onboarding.</p>
            </div>

            <div class="row g-3 g-md-4 align-items-stretch">
                <div class="col-4 fade-in-up">
                    <div class="how-card">
                        <div class="how-number">1</div>
                        <div class="how-icon"><i class="fas fa-user-plus"></i></div>
                        <h4>Create Your Account</h4>
                        <p>Sign up with your name and school email. Your workspace is ready immediately — no downloads, no installs.</p>
                    </div>
                </div>
                <div class="col-4 fade-in-up">
                    <div class="how-card">
                        <div class="how-number">2</div>
                        <div class="how-icon"><i class="fas fa-sitemap"></i></div>
                        <h4>Set Up Your Semester</h4>
                        <p>Create a semester, add your subjects, then drop in tasks with deadlines and priorities. The structure mirrors your real schedule.</p>
                    </div>
                </div>
                <div class="col-4 fade-in-up">
                    <div class="how-card">
                        <div class="how-number">3</div>
                        <div class="how-icon"><i class="fas fa-rocket"></i></div>
                        <h4>Study & Track Progress</h4>
                        <p>Start Pomodoro sessions, check off tasks, and watch your analytics grow. Your dashboard shows exactly where you stand.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Highlights / Stats Bar -->
    <section class="highlights-section fade-in-up">
        <div class="container">
            <div class="highlights-grid">
                <div class="highlight-item">
                    <div class="highlight-icon"><i class="fas fa-shield-alt"></i></div>
                    <h5>Secure by Design</h5>
                    <p>Bcrypt passwords, CSRF protection, prepared SQL statements — your data is safe.</p>
                </div>
                <div class="highlight-item">
                    <div class="highlight-icon"><i class="fas fa-moon"></i></div>
                    <h5>Dark Mode Ready</h5>
                    <p>Easy on the eyes during late-night study sessions with a built-in dark theme.</p>
                </div>
                <div class="highlight-item">
                    <div class="highlight-icon"><i class="fas fa-mobile-alt"></i></div>
                    <h5>Works Everywhere</h5>
                    <p>Fully responsive. Use it on your laptop, tablet, or phone — no app needed.</p>
                </div>
                <div class="highlight-item">
                    <div class="highlight-icon"><i class="fas fa-bolt"></i></div>
                    <h5>Fast & Lightweight</h5>
                    <p>No heavy frameworks. Pure PHP backend loads in under a second on any server.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="cta-section">
        <div class="container" style="position:relative; z-index:2;">
            <div class="cta-badge"><i class="fas fa-gift"></i> 100% Free</div>
            <h2>Your Best Semester Starts Here</h2>
            <p>No credit card. No trial period. Just sign up and start organizing your academic life today.</p>
            <div class="cta-buttons">
                <a href="auth/register.php" class="btn-hero btn-hero-primary" style="background:white; color:var(--primary); display:inline-flex; font-size:15px; padding: 14px 32px;">
                    <i class="fas fa-arrow-right"></i> Create Free Account
                </a>
                <a href="auth/login.php" class="btn-hero btn-hero-outline" style="border-color:rgba(255,255,255,0.3); color:white; display:inline-flex; font-size:15px; padding: 14px 32px;">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="landing-footer">
        <div class="container">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> Studify – Student Task Management System</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(a => {
        a.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
    </script>
</body>
</html>
