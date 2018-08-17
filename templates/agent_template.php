<?php
/**
 * The agent page template
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
            <h4>Upload/Download Speed over the past <span class="desc-highlight"><?php echo get_time_interval_label(); ?></span></h4>
        </div>
        <div id="up-down-graph-container"></div>
    </div>

    <div class="panel-section">
        <div class="section-header">
            <h4>Latency over the past <span class="desc-highlight"><?php echo get_time_interval_label(); ?></span></h4>
        </div>
        <div id="latency-graph-container"></div>
    </div>
</div>

<?php include "templates/footer.php"; ?>