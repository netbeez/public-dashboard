<?php
$dashboard = Process_Data::build_main_dashboard_snapshot($selected_time_window);
$hero = $dashboard['hero'];
$agentRows = $dashboard['agent_rows'];
$targetRows = $dashboard['target_rows'];
$ispRows = $dashboard['isp_rows'];
$groupRows = $dashboard['group_rows'];
$remoteWorkerSummary = $dashboard['remote_worker_summary'];

include "templates/header.php";
?>

<main class="main dashboard-home">
    <section class="hero-panel">
        <div class="hero-copy">
            <p class="eyebrow">Network status overview</p>
            <h1>Operational visibility across locations, applications, providers, and agent groups.</h1>
            <p class="hero-description">
                Updated for a cleaner monitoring experience while preserving the same routes, time-window controls, and deployment model.
            </p>
        </div>
        <div class="hero-metrics">
            <article class="metric-card">
                <span class="metric-label">Locations</span>
                <strong class="metric-value"><?php echo (int) $hero['location_count']; ?></strong>
                <span class="metric-meta"><?php echo (int) $hero['location_operational_count']; ?> operational</span>
            </article>
            <article class="metric-card">
                <span class="metric-label">Applications</span>
                <strong class="metric-value"><?php echo (int) $hero['application_count']; ?></strong>
                <span class="metric-meta"><?php echo (int) $hero['application_healthy_count']; ?> healthy</span>
            </article>
            <article class="metric-card">
                <span class="metric-label">Providers</span>
                <strong class="metric-value"><?php echo (int) $hero['isp_count']; ?></strong>
                <span class="metric-meta">live ISP rollup</span>
            </article>
            <article class="metric-card">
                <span class="metric-label">Agent groups</span>
                <strong class="metric-value"><?php echo (int) $hero['group_count']; ?></strong>
                <span class="metric-meta">normalized memberships</span>
            </article>
        </div>
    </section>

    <?php if (!empty($dashboard['errors'])): ?>
        <section class="panel-section panel-section-alert">
            <div class="section-header">
                <h4>Supplemental data notice</h4>
                <div class="description">
                    <p><?php echo htmlspecialchars(implode(' ', $dashboard['errors'])); ?></p>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="panel-section">
        <div class="section-header">
            <div>
                <p class="section-kicker">Locations</p>
                <h4>Your Network Locations</h4>
            </div>
            <div class="description">
                <p>Latest connectivity, uptime, and speed measurements for the monitored agents in this public dashboard.</p>
            </div>
        </div>

        <div class="table-shell">
            <table class="dashboard-table">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Availability</th>
                    <th>Download</th>
                    <th>Upload</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($agentRows as $row): ?>
                    <?php $statusMeta = get_agent_status_meta($row['status'] ?? null); ?>
                    <tr>
                        <td>
                            <a class="table-link" href="?view=view_agent&amp;id=<?php echo (int) $row['agent_id']; ?>&amp;status=<?php echo rawurlencode((string) ($row['status'] ? '1' : '0')); ?>&amp;alert_count=<?php echo (int) ($row['open_alerts_count'] ?? 0); ?>">
                                <?php echo htmlspecialchars((string) ($row['agent_name'] ?? 'Unknown agent')); ?>
                            </a>
                        </td>
                        <td><span class="status-pill <?php echo $statusMeta['class']; ?>"><?php echo htmlspecialchars($statusMeta['label']); ?></span></td>
                        <td><?php echo format_dashboard_metric($row['availability'] ?? null, '%', 2); ?></td>
                        <td><?php echo format_dashboard_metric($row['download_speed'] ?? null, 'Mbps', 2); ?></td>
                        <td><?php echo format_dashboard_metric($row['upload_speed'] ?? null, 'Mbps', 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if ((int) ($remoteWorkerSummary['total_quantity'] ?? 0) > 0): ?>
        <section class="panel-section">
            <div class="section-header">
                <div>
                    <p class="section-kicker">Remote Workers</p>
                    <h4>Remote worker status</h4>
                </div>
                <div class="description">
                    <p>Summary of remote worker agents selected for this dashboard, including those with active incidents.</p>
                </div>
            </div>

            <div class="summary-grid">
                <article class="summary-card">
                    <div class="summary-card-header">
                        <h5>Remote Workers</h5>
                        <span class="summary-count"><?php echo (int) $remoteWorkerSummary['total_quantity']; ?> total</span>
                    </div>
                    <dl class="summary-stats">
                        <div><dt>Total quantity</dt><dd><?php echo (int) $remoteWorkerSummary['total_quantity']; ?></dd></div>
                        <div><dt>Total online</dt><dd><?php echo (int) $remoteWorkerSummary['total_online']; ?></dd></div>
                        <div><dt>Total offline</dt><dd><?php echo (int) $remoteWorkerSummary['total_offline']; ?></dd></div>
                        <div><dt>With active incidents</dt><dd><?php echo (int) $remoteWorkerSummary['total_with_active_incidents']; ?></dd></div>
                    </dl>
                </article>
            </div>
        </section>
    <?php endif; ?>

    <section class="panel-section">
        <div class="section-header">
            <div>
                <p class="section-kicker">Applications</p>
                <h4>Your Applications</h4>
            </div>
            <div class="description">
                <p>Application health and average HTTP response time over the selected monitoring window.</p>
            </div>
        </div>

        <div class="table-shell">
            <table class="dashboard-table">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Response time</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($targetRows as $row): ?>
                    <?php $statusMeta = get_target_status_meta($row['status'] ?? null); ?>
                    <tr>
                        <td>
                            <a class="table-link" href="?view=view_target&amp;id=<?php echo (int) $row['target_id']; ?>&amp;status=<?php echo (int) ($row['status'] ?? 0); ?>&amp;alert_count=<?php echo (int) ($row['open_alerts_count'] ?? 0); ?>">
                                <?php echo htmlspecialchars((string) ($row['target_name'] ?? 'Unknown application')); ?>
                            </a>
                        </td>
                        <td><span class="status-pill <?php echo $statusMeta['class']; ?>"><?php echo htmlspecialchars($statusMeta['label']); ?></span></td>
                        <td><?php echo format_dashboard_metric($row['response_time'] ?? null, 's', 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel-section">
        <div class="section-header">
            <div>
                <p class="section-kicker">Internet Service Providers</p>
                <h4>Provider performance rollup</h4>
            </div>
            <div class="description">
                <p>Aggregated by ISP from the selected agents. Last observed provider sample: <span class="desc-highlight"><?php echo htmlspecialchars(format_timestamp_ms($dashboard['latest_sample_ts'])); ?></span></p>
            </div>
        </div>

        <div class="summary-grid">
            <?php foreach ($ispRows as $row): ?>
                <article class="summary-card">
                    <div class="summary-card-header">
                        <h5><?php echo htmlspecialchars($row['isp']); ?></h5>
                        <span class="summary-count"><?php echo (int) $row['agent_count']; ?> agents</span>
                    </div>
                    <dl class="summary-stats">
                        <div><dt>Online</dt><dd><?php echo (int) $row['online_count']; ?></dd></div>
                        <div><dt>Offline</dt><dd><?php echo (int) $row['offline_count']; ?></dd></div>
                        <div><dt>Avg download</dt><dd><?php echo htmlspecialchars(format_dashboard_metric($row['avg_download'], 'Mbps', 2)); ?></dd></div>
                        <div><dt>Avg upload</dt><dd><?php echo htmlspecialchars(format_dashboard_metric($row['avg_upload'], 'Mbps', 2)); ?></dd></div>
                        <div><dt>Avg latency</dt><dd><?php echo htmlspecialchars(format_dashboard_metric($row['avg_latency'], 'ms', 2)); ?></dd></div>
                        <div><dt>Avg uptime</dt><dd><?php echo htmlspecialchars(format_dashboard_metric($row['avg_uptime'], '%', 2)); ?></dd></div>
                    </dl>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="panel-section">
        <div class="section-header">
            <div>
                <p class="section-kicker">Agent Groups</p>
                <h4>Group-level performance</h4>
            </div>
            <div class="description">
                <p>Normalized from modern group inventory and agent-detail fallbacks so mixed payload shapes still resolve to a useful group view.</p>
            </div>
        </div>

        <div class="table-shell">
            <table class="dashboard-table">
                <thead>
                <tr>
                    <th>Group</th>
                    <th>Agents</th>
                    <th>Online</th>
                    <th>Offline</th>
                    <th>Avg download</th>
                    <th>Avg upload</th>
                    <th>Avg latency</th>
                    <th>Avg uptime</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($groupRows as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['group']); ?></td>
                        <td><?php echo (int) $row['agent_count']; ?></td>
                        <td><?php echo (int) $row['online_count']; ?></td>
                        <td><?php echo (int) $row['offline_count']; ?></td>
                        <td><?php echo htmlspecialchars(format_dashboard_metric($row['avg_download'], 'Mbps', 2)); ?></td>
                        <td><?php echo htmlspecialchars(format_dashboard_metric($row['avg_upload'], 'Mbps', 2)); ?></td>
                        <td><?php echo htmlspecialchars(format_dashboard_metric($row['avg_latency'], 'ms', 2)); ?></td>
                        <td><?php echo htmlspecialchars(format_dashboard_metric($row['avg_uptime'], '%', 2)); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php include "templates/footer.php"; ?>
