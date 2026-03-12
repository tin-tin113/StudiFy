<?php
/**
 * STUDIFY – Landing Page
 * Clean, minimal, academic
 */
define('BASE_URL', './');
require_once 'config/db.php';

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
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Crect width='40' height='40' rx='10' fill='%2316A34A'/%3E%3Cpath d='M10 13.5c0-.6.4-1 1-1 1.5 0 4.2.5 9 2.5v13.5c-4.8-2-7.5-2.5-9-2.5-.6 0-1-.4-1-1V13.5z' fill='%23fff' opacity='.9'/%3E%3Cpath d='M30 13.5c0-.6-.4-1-1-1-1.5 0-4.2.5-9 2.5v13.5c4.8-2 7.5-2.5 9-2.5.6 0 1-.4 1-1V13.5z' fill='%23fff' opacity='.7'/%3E%3C/svg%3E">
</head>
<body class="landing-page">

    <!-- Navigation -->
    <nav class="landing-nav">
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
                <a href="auth/login.php" class="btn btn-secondary" style="font-size: 13px;">Login</a>
                <a href="auth/register.php" class="btn btn-primary" style="font-size: 13px;">Get Started</a>
            </div>
        </div>
    </nav>

    <!-- Hero -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 hero-content">
                    <h1>Organize Your Academic Life <span>Smarter</span></h1>
                    <p>Studify helps students manage tasks, track deadlines, and boost productivity with an intuitive dashboard, Pomodoro timer, and smart calendar.</p>
                    <div class="hero-buttons">
                        <a href="auth/register.php" class="btn-hero btn-hero-primary">
                            <i class="fas fa-arrow-right"></i> Start for Free
                        </a>
                        <a href="#features" class="btn-hero btn-hero-outline">
                            <i class="fas fa-arrow-down"></i> Learn More
                        </a>
                    </div>
                </div>
                <div class="col-lg-5 offset-lg-1 hero-visual">
                    <div class="mockup-card">
                        <h4><i class="fas fa-chart-line"></i> Your Progress</h4>
                        <div class="mockup-stat">
                            <div class="icon-box" style="background: var(--success-light); color: var(--success);">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div>
                                <div style="font-weight: 600; font-size: 16px; color: var(--text-primary);">12 Tasks</div>
                                <div style="color: var(--text-muted); font-size: 12px;">Completed this week</div>
                            </div>
                        </div>
                        <div class="mockup-stat">
                            <div class="icon-box" style="background: var(--warning-light); color: var(--warning);">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <div style="font-weight: 600; font-size: 16px; color: var(--text-primary);">6.5 Hours</div>
                                <div style="color: var(--text-muted); font-size: 12px;">Study time today</div>
                            </div>
                        </div>
                        <div class="mockup-stat">
                            <div class="icon-box" style="background: var(--primary-50); color: var(--primary);">
                                <i class="fas fa-trophy"></i>
                            </div>
                            <div>
                                <div style="font-weight: 600; font-size: 16px; color: var(--text-primary);">85%</div>
                                <div style="color: var(--text-muted); font-size: 12px;">Completion rate</div>
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
                <h2>Everything You Need to Succeed</h2>
                <p>Simple tools designed for student productivity and academic success.</p>
            </div>

            <div class="row g-4">
                <div class="col-md-4 fade-in-up">
                    <div class="card feature-card">
                        <div class="feature-icon" style="background: var(--primary-50); color: var(--primary);">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <h4>Task Management</h4>
                        <p>Create, organize, and track your assignments, projects, and deadlines with priority levels.</p>
                    </div>
                </div>
                <div class="col-md-4 fade-in-up">
                    <div class="card feature-card">
                        <div class="feature-icon" style="background: var(--info-light); color: var(--info);">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h4>Smart Calendar</h4>
                        <p>Visualize all your deadlines on an interactive calendar, color-coded by priority.</p>
                    </div>
                </div>
                <div class="col-md-4 fade-in-up">
                    <div class="card feature-card">
                        <div class="feature-icon" style="background: var(--danger-light); color: var(--danger);">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h4>Pomodoro Timer</h4>
                        <p>Stay focused with the built-in Pomodoro timer. Track sessions and build habits.</p>
                    </div>
                </div>
                <div class="col-md-4 fade-in-up">
                    <div class="card feature-card">
                        <div class="feature-icon" style="background: var(--warning-light); color: var(--warning);">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <h4>Analytics Dashboard</h4>
                        <p>Get insights into your productivity with charts showing task completion and study patterns.</p>
                    </div>
                </div>
                <div class="col-md-4 fade-in-up">
                    <div class="card feature-card">
                        <div class="feature-icon" style="background: var(--success-light); color: var(--success);">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <h4>Semester Organization</h4>
                        <p>Organize your subjects by semester. Keep everything structured throughout the year.</p>
                    </div>
                </div>
                <div class="col-md-4 fade-in-up">
                    <div class="card feature-card">
                        <div class="feature-icon" style="background: var(--bg-secondary); color: var(--text-secondary);">
                            <i class="fas fa-moon"></i>
                        </div>
                        <h4>Dark Mode</h4>
                        <p>Study comfortably day or night with automatic dark mode support.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="cta-section">
        <div class="container">
            <h2>Ready to Get Organized?</h2>
            <p>Join students who manage their academic life smarter with Studify.</p>
            <a href="auth/register.php" class="btn-hero btn-hero-primary" style="background: white; color: var(--primary); display: inline-flex;">
                <i class="fas fa-arrow-right"></i> Create Free Account
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="landing-footer">
        <div class="container">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> Studify – Student Task Management System</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
