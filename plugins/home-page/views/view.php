<link rel="stylesheet" type="text/css" href="<?=plugin_http_path('assets/css/style.css')?>">

<main id="theme-main" class="hp-root">
    <!-- Animated background elements -->
    <div class="hp-bg-orbit"></div>
    <div class="hp-bg-orbit hp-bg-orbit--secondary"></div>
    <div class="hp-grid-overlay"></div>

    <section class="hp-hero">
        <div class="hp-hero-inner">
            <div class="hp-hero-left" data-animate="fade-up">
                <div class="hp-pill">Smart • Secure • Seamless</div>
                <h1 class="hp-title">
                    <?=APP_NAME?>
                    <span class="hp-title-accent">Digital Banking Hub</span>
                </h1>
                <p class="hp-subtitle">
                    Experience a new standard of neighbourhood banking real-time dashboards,
                    cashdrawer precision, and enterprise-grade control in one sleek interface.
                </p>

                <div class="hp-cta-row">
                    <a href="<?=ROOT?>/login" class="hp-btn hp-btn-primary">
                        <span>Sign in to Back Office</span>
                    </a>
                    <button type="button" class="hp-btn hp-btn-ghost" data-scroll-target="#hp-features">
                        <span>Explore Platform</span>
                    </button>
                </div>

                <div class="hp-trust-row">
                    <div class="hp-dot-pulse"></div>
                    <span>Optimised for daily operations, managers and tellers.</span>
                </div>
            </div>

            <div class="hp-hero-right" data-animate="fade-left">
                <div class="hp-glass-card hp-metric-card">
                    <div class="hp-metric-label">Live Cashdrawer Snapshot</div>
                    <div class="hp-metric-value">GHS 25,130.80</div>
                    <div class="hp-metric-trend hp-metric-trend--up">+3.4% today</div>
                    <div class="hp-metric-mini-grid">
                        <div class="hp-metric-mini">
                            <span class="hp-metric-mini-label">Tellers Online</span>
                            <span class="hp-metric-mini-value">14</span>
                        </div>
                        <div class="hp-metric-mini">
                            <span class="hp-metric-mini-label">Open Drawers</span>
                            <span class="hp-metric-mini-value">7</span>
                        </div>
                        <div class="hp-metric-mini">
                            <span class="hp-metric-mini-label">FX Positions</span>
                            <span class="hp-metric-mini-value">CFA, USD</span>
                        </div>
                    </div>
                </div>

                <div class="hp-glass-card hp-activity-card" data-animate="fade-up-delay">
                    <div class="hp-activity-header">
                        <span class="hp-activity-title">Live Activity</span>
                        <span class="hp-activity-status">Streaming</span>
                    </div>
                    <ul class="hp-activity-list">
                        <li><span class="hp-dot hp-dot--success"></span> Cashdrawer balanced  Branch East</li>
                        <li><span class="hp-dot hp-dot--warning"></span> 2 requests pending approval</li>
                        <li><span class="hp-dot hp-dot--info"></span> FX exchange just processed</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <section id="hp-features" class="hp-section hp-section--light">
        <div class="hp-section-header" data-animate="fade-up">
            <h2>What you run on <?=APP_NAME?> </h2>
            <p>A focused suite of tools for operations, cashdrawers and team oversight organised in one back office.</p>
        </div>

        <div class="hp-feature-grid" data-animate="fade-up">
            <article class="hp-feature">
                <div class="hp-feature-icon hp-feature-icon--ops">
                    <span class="hp-feature-icon-letter">O</span>
                </div>
                <div class="hp-feature-body">
                    <h3>Operations Control</h3>
                    <p>Branch and head-office dashboards for monitoring activity, approvals and performance in real time.</p>
                </div>
            </article>
            <article class="hp-feature">
                <div class="hp-feature-icon hp-feature-icon--cash">
                    <span class="hp-feature-icon-letter">C</span>
                </div>
                <div class="hp-feature-body">
                    <h3>Cashdrawer &amp; FX</h3>
                    <p>Live cash positions, teller drawers and FX movements with audit-ready histories and variance tracking.</p>
                </div>
            </article>
            <article class="hp-feature">
                <div class="hp-feature-icon hp-feature-icon--team">
                    <span class="hp-feature-icon-letter">T</span>
                </div>
                <div class="hp-feature-body">
                    <h3>Teams &amp; Training</h3>
                    <p>Attendance, trainings and activity logs that give managers a clear view of who is ready and online.</p>
                </div>
            </article>
            <article class="hp-feature hp-feature--activity">
                <div class="hp-feature-icon hp-feature-icon--activity">
                    <span class="hp-feature-icon-letter">A</span>
                </div>
                <div class="hp-feature-body">
                    <h3>Activities</h3>
                    <p>Concise activity streams and alerts to help you review what changed, when, and who approved it.</p>
                </div>
            </article>
        </div>
    </section>

    <section class="hp-section hp-section--dark">
        <div class="hp-section-inner" data-animate="fade-up">
            <div class="hp-section-copy">
                <h2>Bring discipline to every transaction.</h2>
                <p>
                    Your teams deserve tools that feel as premium as the services you deliver. <?=APP_NAME?> brings
                    precision, performance and a modern experience to everyday banking operations.
                </p>
            </div>
            <div class="hp-section-cta">
                <a href="<?=ROOT?>/login" class="hp-btn hp-btn-primary hp-btn-primary--alt">
                    <span>Sign in to continue</span>
                </a>
            </div>
        </div>
    </section>
</main>

<script src="<?=plugin_http_path('assets/js/plugin.js')?>"></script>
