<?php
/**
 * The target page template
 */


include "templates/header.php"; ?>

<div class="main">
    <div class="panel-section">
        <div class="section-header">
            <h4>Alerts on <?php echo $name; ?></h4>
            <div class="description">
                <?php current_alert_info(); ?>
            </div>
        </div>
        <div id="alert-graph-container"></div>
    </div>

    <div class="panel-section">
        <div class="section-header">
            <h4>Average response time on <?php echo $name; ?> per Location over the past <span class="desc-highlight"><?php echo get_time_interval_label(); ?></span></h4>
        </div>
        <div id="agent-bar-graph-container"></div>
    </div>

    <div class="panel-section">
        <h4>Aggregate average response time on <?php echo $name; ?> from all Locations over the past <span class="desc-highlight"><?php echo get_time_interval_label(); ?></span></h4>
        <div id="response-time-graph-container"></div>
    </div>

</div>

<?php include "templates/footer.php"; ?>