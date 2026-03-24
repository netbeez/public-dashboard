<?php
$targetId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$targetLastUpdated = Process_Data::get_target_last_updated_label($selected_time_window, $targetId);
$agentCount = isset($data_obj->{'agent_ids'}) && is_array($data_obj->{'agent_ids'}) ? count($data_obj->{'agent_ids'}) : 0;

include "templates/header.php";
?>

<main class="main detail-page">
    <section class="hero-panel compact">
        <div class="hero-copy">
            <p class="eyebrow">Application detail</p>
            <h1><?php echo htmlspecialchars($name); ?></h1>
            <p class="hero-description"><?php echo htmlspecialchars($description); ?></p>
        </div>
        <div class="hero-metrics detail-metrics">
            <article class="metric-card">
                <span class="metric-label">Last updated</span>
                <strong class="metric-value metric-value-small"><?php echo htmlspecialchars($targetLastUpdated); ?></strong>
            </article>
            <article class="metric-card">
                <span class="metric-label">Agents in scope</span>
                <strong class="metric-value"><?php echo (int) $agentCount; ?></strong>
            </article>
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
                <p class="section-kicker">Per location</p>
                <h4>Average response time per location over the past <span class="desc-highlight"><?php echo get_time_interval_label(); ?></span></h4>
            </div>
            <div class="description">
                <p>The timestamp above is sourced from the latest HTTP sample found in the selected time window.</p>
            </div>
        </div>
        <div id="agent-bar-graph-container"></div>
    </section>

    <section class="panel-section">
        <div class="section-header">
            <div>
                <p class="section-kicker">Aggregate trend</p>
                <h4>Aggregate average response time from all locations over the past <span class="desc-highlight"><?php echo get_time_interval_label(); ?></span></h4>
            </div>
        </div>
        <div id="response-time-graph-container"></div>
    </section>
</main>

<?php include "templates/footer.php"; ?>
