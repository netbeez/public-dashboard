<?php include "templates/header.php"; ?>

<main class="main detail-page">
    <section class="hero-panel compact">
        <div class="hero-copy">
            <p class="eyebrow">Location detail</p>
            <h1><?php echo htmlspecialchars($name); ?></h1>
            <p class="hero-description"><?php echo htmlspecialchars($description); ?></p>
        </div>
    </section>

    <section class="panel-section">
        <div class="section-header">
            <div>
                <p class="section-kicker">Alerts</p>
                <h4>Alert pressure on <?php echo htmlspecialchars($name); ?></h4>
            </div>
            <div class="description">
                <?php current_alert_info(); ?>
            </div>
        </div>
        <div id="alert-graph-container"></div>
    </section>

    <section class="panel-section">
        <div class="section-header">
            <div>
                <p class="section-kicker">Bandwidth</p>
                <h4>Upload and download speed over the past <span class="desc-highlight"><?php echo get_time_interval_label(); ?></span></h4>
            </div>
        </div>
        <div id="up-down-graph-container"></div>
    </section>

    <section class="panel-section">
        <div class="section-header">
            <div>
                <p class="section-kicker">Latency</p>
                <h4>Latency over the past <span class="desc-highlight"><?php echo get_time_interval_label(); ?></span></h4>
            </div>
        </div>
        <div id="latency-graph-container"></div>
    </section>
</main>

<?php include "templates/footer.php"; ?>
