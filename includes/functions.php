<?php
/**
 * Created by PhpStorm.
 * User: Allison
 * Date: 11/17/2016
 * Time: 5:17 PM
 */

if (!isset($GLOBALS['nb_request_profiler']) || !is_array($GLOBALS['nb_request_profiler'])) {
    $GLOBALS['nb_request_profiler'] = array();
}

function nb_profiler_record($layer, $url, $durationMs, $httpCode = null, $meta = array()){
    if (!is_array($GLOBALS['nb_request_profiler'])) {
        $GLOBALS['nb_request_profiler'] = array();
    }

    $GLOBALS['nb_request_profiler'][] = array(
        'layer' => (string) $layer,
        'url' => (string) $url,
        'duration_ms' => round((float) $durationMs, 2),
        'http_code' => $httpCode,
        'meta' => is_array($meta) ? $meta : array(),
    );
}

function nb_profiler_entries(){
    return is_array($GLOBALS['nb_request_profiler']) ? $GLOBALS['nb_request_profiler'] : array();
}

function nb_profiler_summary(){
    $entries = nb_profiler_entries();
    $total = 0.0;

    foreach ($entries as $entry) {
        $total += (float) ($entry['duration_ms'] ?? 0);
    }

    $sorted = $entries;
    usort($sorted, function($left, $right){
        return ((float) ($right['duration_ms'] ?? 0)) <=> ((float) ($left['duration_ms'] ?? 0));
    });

    return array(
        'count' => count($entries),
        'total_ms' => round($total, 2),
        'slowest' => array_slice($sorted, 0, 20),
    );
}

function render_debug_panel($hidden = true){
    $summary = nb_profiler_summary();
    $entries = $summary['slowest'];
    ?>
    <section id="debug-panel" class="debug-panel"<?php echo $hidden ? ' hidden' : ''; ?>>
        <div class="debug-panel-header">
            <div>
                <p class="section-kicker">Debug</p>
                <h4>Request timing profile</h4>
            </div>
            <div class="debug-summary">
                <span><strong><?php echo (int) $summary['count']; ?></strong> requests</span>
                <span><strong><?php echo number_format((float) $summary['total_ms'], 2); ?></strong> ms total</span>
            </div>
        </div>
        <div class="table-shell">
            <table class="dashboard-table debug-table">
                <thead>
                <tr>
                    <th>Layer</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th>URL</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($entries as $entry): ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string) ($entry['layer'] ?? 'unknown')); ?></td>
                        <td><?php echo number_format((float) ($entry['duration_ms'] ?? 0), 2); ?> ms</td>
                        <td><?php echo htmlspecialchars((string) (($entry['http_code'] ?? '') !== null ? ($entry['http_code'] ?? '') : '')); ?></td>
                        <td class="debug-url"><?php echo htmlspecialchars((string) ($entry['url'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php
}

//Handle Time Window & Time Window buttons
function show_selected_time_window_interval(){
    global $selected_time_window;

    if (!isset($_COOKIE["time_window"])){
        $current_time_window = $selected_time_window;
    } else {
        $current_time_window = $_COOKIE["time_window"];
    }

    ?>
    <script type="text/javascript">
        $( document ).ready(function() {
            var time_window = <?php echo $current_time_window; ?>;

            if (time_window === <?php echo day_time_window; ?>) {
                $(".button-group .button").removeClass("button-primary");
                $("#interval-one-day").addClass("button-primary");
                console.log("time window = <?php echo day_time_window; ?>");
            } else if (time_window === <?php echo week_time_window; ?>) {
                $(".button-group .button").removeClass("button-primary");
                $("#interval-one-week").addClass("button-primary");
                console.log("time window = <?php echo week_time_window; ?>");
            } else if (time_window === <?php echo month_time_window; ?>) {
                $(".button-group .button").removeClass("button-primary");
                $("#interval-one-month").addClass("button-primary");
                console.log("time window = <?php echo month_time_window; ?>");
            } else {
                $(".button-group .button").removeClass("button-primary");
                console.log("time window = ???");
            }

            $("#interval-one-day").on("click", function(){
                $(".button-group .button").removeClass("button-primary");
                $("#interval-one-day").addClass("button-primary");
                $("#loading").empty();
                $("#loading").append('<i class="fa fa-circle-o-notch fa-spin fa-fw"></i>');
            });

            $("#interval-one-week").on("click", function(){
                $(".button-group .button").removeClass("button-primary");
                $("#interval-one-week").addClass("button-primary");
                $("#loading").empty();
                $("#loading").append('<i class="fa fa-circle-o-notch fa-spin fa-fw"></i>');
            });

            $("#interval-one-month").on("click", function(){
                $(".button-group .button").removeClass("button-primary");
                $("#interval-one-month").addClass("button-primary");
                $("#loading").empty();
                $("#loading").append('<i class="fa fa-circle-o-notch fa-spin fa-fw"></i>');
            });
        });
    </script>
    <?php
}

//Updates time window interval cookie
function update_time_window_interval(){
    global $selected_time_window;

    if (isset($_POST['submit'])){
        $selected_time_window = $_POST['submit'];
        setcookie("time_window", $selected_time_window, (time() + 60), "/");
        $_COOKIE["time_window"] = $selected_time_window;
    } else {
        if (isset($_COOKIE["time_window"])){
            $selected_time_window = $_COOKIE["time_window"];
        }
    }

    return $selected_time_window;
}

//Gets current current page object
function query_current_page_object(){
    parse_str($_SERVER['QUERY_STRING'], $query_string);

    if ($query_string['view'] == 'view_agent'){
        return Process_Data::get_agent_object($query_string['id']);
    } else if ($query_string['view'] == 'view_target') {
        return Process_Data::get_target_object($query_string['id']);
    } else {
        return null;
    }
}


//Sets page title according to current item and page type
function get_page_title($current_item){
    parse_str($_SERVER['QUERY_STRING'], $query_string);

    if ($query_string && ($query_string['view'] == 'view_agent' || $query_string['view'] == 'view_target')){
        return $current_item;
    } else {
        return COMPANY_NAME . ' Network Status';
    }
}


//Outputs info about current alerts
function current_alert_info(){

    global $name;
    parse_str($_SERVER['QUERY_STRING'], $query_string);

    if(isset($query_string['alert_count'])){
        $alert_count = $query_string['alert_count'];
    } else {
        $alert_count = "???";
    }

    if(isset($query_string['alert_count'])) {

        if ($query_string['status'] == 1) {
            $class = "warning";
        } else if ($query_string['status'] == 2) {
            $class = "fail";
        } else {
            $class = "";
        }
    } else {
        $class = "";
    }

    if($alert_count == 1){
        $verb = "is";
        $alert = "alert";
    } else {
        $verb = "are";
        $alert = "alerts";
    }

    echo '<p>Right now, there ' . $verb . ' <span class="desc-highlight ' . $class . '">' . $alert_count . '</span> ' . $alert . ' affecting <span class="desc-highlight">' . $name . '</span>.</p>';
}


//Gets text label for time interval
function get_time_interval_label() {
    global $selected_time_window;

    if ($selected_time_window == day_time_window) {
        return "1 day";
    } else if ($selected_time_window == week_time_window) {
        return "1 week";
    } else if ($selected_time_window == month_time_window) {
        return "1 month";
    } else {
        return "an unknown amount of time";
    }
}

function get_body_class(){
    parse_str($_SERVER['QUERY_STRING'], $query_string);

    if ($query_string && isset($query_string['view'])) {
        return 'page-' . preg_replace('/[^a-z0-9_-]+/i', '-', (string) $query_string['view']);
    }

    return 'page-home';
}

function format_dashboard_metric($value, $suffix = '', $decimals = 2){
    if ($value === null || $value === '') {
        return 'N/A';
    }

    if (is_numeric($value)) {
        $formatted = number_format((float) $value, $decimals);
    } else {
        $formatted = (string) $value;
    }

    return $suffix !== '' ? $formatted . ' ' . $suffix : $formatted;
}

function get_agent_status_meta($status){
    if ($status === true) {
        return array('label' => 'Operational', 'class' => 'success');
    }

    if ($status === false) {
        return array('label' => 'Unreachable', 'class' => 'fail');
    }

    return array('label' => 'Unknown', 'class' => 'muted');
}

function get_target_status_meta($status){
    if ($status === "0" || $status === 0) {
        return array('label' => 'Healthy', 'class' => 'success');
    }

    if ($status === "1" || $status === 1) {
        return array('label' => 'Warnings', 'class' => 'warning');
    }

    if ($status === "2" || $status === 2) {
        return array('label' => 'Failures', 'class' => 'fail');
    }

    return array('label' => 'Unknown', 'class' => 'muted');
}

function format_timestamp_ms($timestampMs){
    $timestampMs = (int) $timestampMs;
    if ($timestampMs <= 0) {
        return 'No recent sample';
    }

    return gmdate('Y-m-d H:i:s', (int) floor($timestampMs / 1000)) . ' UTC';
}

function normalize_target_agent_ids($agentIds){
    $result = array();

    if (is_array($agentIds)) {
        foreach ($agentIds as $agentId) {
            if (is_numeric($agentId)) {
                $result[] = (int) $agentId;
            }
        }
    } else if (is_object($agentIds)) {
        foreach ($agentIds as $agentId) {
            if (is_numeric($agentId)) {
                $result[] = (int) $agentId;
            }
        }
    }

    return array_values(array_unique(array_filter($result, function($agentId){
        return $agentId > 0;
    })));
}


//Get graphs for current page
function get_graphs($view){
    parse_str($_SERVER['QUERY_STRING'], $query_string);
    global $selected_time_window;
    global $data_obj;

    if($view == 'view_agent'){ ?>
        <script type="text/javascript" src="js/SingleAreaPlot.js"></script>
        <script type="text/javascript" src="js/UpDownPlot.js"></script>
        <script type="text/javascript" src="js/SignalLinkQualityPlot.js"></script>
        <script type="text/javascript" src="js/AlertLaneGraph.js"></script>

        <script type="text/javascript">
            $( document ).ready(function() {

                var agentGraphObj = '<?php echo Process_Data::build_agent_graphs_data_object($selected_time_window, $query_string['id']); ?>';
                var alertGraphObj = '<?php echo Process_Data::build_agent_swimlane_data($selected_time_window, $query_string['id'], $data_obj->{'nb_targets'}); ?>';
                var agentAccessPointMetricsObj = '<?php echo Process_Data::build_agent_access_point_metrics_data_object($selected_time_window, $query_string['id']); ?>';

                var latencyPlot = new SingleAreaGraph($("#latency-graph-container"), $("#latency-graph-container").width(), agentGraphObj, {label:"Latency", unit: "ms", variable_accessor: "latency", class: "latency"}, <?php echo $selected_time_window; ?>);
                var upDownPlot = new UpDownAreaGraph($("#up-down-graph-container"), $("#up-down-graph-container").width(), agentGraphObj, <?php echo $selected_time_window; ?>);
                if(agentAccessPointMetricsObj.length > 0) {
                    $(".main").append(`
                        <div class="panel-section">
                            <div class="section-header">
                                <h4>Signal Strength and Link Quality over the past <span class="desc-highlight"><?php echo get_time_interval_label(); ?></span></h4>
                            </div>
                            <div id="signal-link-quality-graph-container"></div>
                        </div>
                    `);
                    var signalLinkQualityPlot = new SignalLinkQualityPlot($("#signal-link-quality-graph-container"), $("#signal-link-quality-graph-container").width(), agentAccessPointMetricsObj, <?php echo $selected_time_window; ?>);
                }
                AlertLaneGraph($("#alert-graph-container"), $("#alert-graph-container").width(), alertGraphObj);

            });
        </script>
    <?php } else if ($view == 'view_target') { ?>
        <script type="text/javascript" src="js/SingleAreaPlot.js"></script>
        <script type="text/javascript" src="js/BarGraph.js"></script>
        <script type="text/javascript" src="js/AlertLaneGraph.js"></script>

        <script type="text/javascript">
            $( document ).ready(function() {

                var barGraphObj = '<?php echo Process_Data::build_target_bar_graph_data_object($selected_time_window, $query_string['id']); ?>';
                var targetGraphObj = '<?php echo Process_Data::build_target_line_graph_data_object($selected_time_window, $query_string['id']); ?>';
                var alertGraphObj = '<?php echo Process_Data::build_target_swimlane_data($selected_time_window, $query_string['id'], $data_obj->{'agent_ids'}); ?>';
                var parsedBarGraph = JSON.parse(barGraphObj || "[]");
                var parsedTargetGraph = JSON.parse(targetGraphObj || "[]");
                var parsedAlertGraph = JSON.parse(alertGraphObj || "[]");

                if (parsedAlertGraph.length > 0) {
                    AlertLaneGraph($("#alert-graph-container"), $("#alert-graph-container").width(), alertGraphObj);
                } else {
                    $("#alert-graph-container").html('<div class="empty-state">No alert timeline data is available for the selected time window.</div>');
                }

                if (parsedTargetGraph.length > 0) {
                    var responseTimePlot = new SingleAreaGraph($("#response-time-graph-container"), $("#response-time-graph-container").width(), targetGraphObj, {label:"Response Time", unit: "s", variable_accessor: "response_time", class: "response-time"}, <?php echo $selected_time_window; ?>);
                } else {
                    $("#response-time-graph-container").html('<div class="empty-state">No aggregate HTTP samples were returned for this target in the selected time window.</div>');
                }

                if (parsedBarGraph.length > 0) {
                    var perAgentAveragePlot = new BarGraph($("#agent-bar-graph-container"), $("#agent-bar-graph-container").width(), barGraphObj, {label:"Response Time", unit: "s", variable_accessor: "response_time", class: "response-time"});
                } else {
                    $("#agent-bar-graph-container").html('<div class="empty-state">No per-location HTTP samples were returned for this target in the selected time window.</div>');
                }
            });
        </script>
    <?php }
}

function display_admin_footer_scripts(){
    ?>
        <script type="text/javascript" src="js/settings-form-handler.js"></script>
    <?php
}    

function get_refresh_interval($is_admin_page, $is_in_seconds){

    $directory = '';
    if(!$is_admin_page){
        $directory = 'admin/';
    }

    $settings_data = file_get_contents($directory . 'settings.json');
    $refresh_interval_in_minutes = json_decode($settings_data)->{'refresh_interval'};

    if($is_in_seconds){
        return $refresh_interval_in_minutes * 60;
    } else {
        return $refresh_interval_in_minutes;
    }
}
