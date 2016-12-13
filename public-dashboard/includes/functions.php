<?php
/**
 * Created by PhpStorm.
 * User: Allison
 * Date: 11/17/2016
 * Time: 5:17 PM
 */

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

    if ($query_string['view'] == 'view_agent' || $query_string['view'] == 'view_target'){
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


//Get graphs for current page
function get_graphs($view){
    parse_str($_SERVER['QUERY_STRING'], $query_string);
    global $selected_time_window;
    global $data_obj;

    if($view == 'view_agent'){ ?>
        <script type="text/javascript" src="js/SingleAreaPlot.js"></script>
        <script type="text/javascript" src="js/UpDownPlot.js"></script>
        <script type="text/javascript" src="js/AlertLaneGraph.js"></script>

        <script type="text/javascript">
            $( document ).ready(function() {

                var agentGraphObj = '<?php echo Process_Data::build_agent_graphs_data_object($selected_time_window, $query_string['id']); ?>';
                var alertGraphObj = '<?php echo Process_Data::build_agent_swimlane_data($selected_time_window, $query_string['id'], $data_obj->{'nb_targets'}); ?>';

                var latencyPlot = new SingleAreaGraph($("#latency-graph-container"), $("#latency-graph-container").width(), agentGraphObj, {label:"Latency", unit: "ms", variable_accessor: "latency", class: "latency"}, <?php echo $selected_time_window; ?>);
                var UpDownPlot = new UpDownAreaGraph($("#up-down-graph-container"), $("#up-down-graph-container").width(), agentGraphObj, <?php echo $selected_time_window; ?>);
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

                AlertLaneGraph($("#alert-graph-container"), $("#alert-graph-container").width(), alertGraphObj);
                var responseTimePlot = new SingleAreaGraph($("#response-time-graph-container"), $("#response-time-graph-container").width(), targetGraphObj, {label:"Response Time", unit: "s", variable_accessor: "response_time", class: "response-time"}, <?php echo $selected_time_window; ?>);
                var perAgentAveragePlot = new BarGraph($("#agent-bar-graph-container"), $("#agent-bar-graph-container").width(), barGraphObj, {label:"Response Time", unit: "s", variable_accessor: "response_time", class: "response-time"});
            });
        </script>
    <?php } else { ?>
        <script type="text/javascript" src="js/TargetSortTable.js"></script>
        <script type="text/javascript" src="js/AgentSortTable.js"></script>

        <script type="text/javascript">
            $( document ).ready(function() {
                var agentTableObj = '<?php echo Process_Data::build_agent_table_data($selected_time_window); ?>';
                var targetTableObj = '<?php echo Process_Data::build_target_table_data($selected_time_window); ?>';
                AgentSortTable(agentTableObj);
                TargetSortTable(targetTableObj);
            });
        </script>
    <?php }
}