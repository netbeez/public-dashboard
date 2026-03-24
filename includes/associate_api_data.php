<?php
/**
 * This class of functions handles the processing of the API data and building data objects for the graphs
 */

require_once __DIR__ . "/modern_api.php";

class Process_Data {
    public static $settings_data = null;

    //Data object variables for populating the Agent table
    public static $agent_table_object = null;
    public static $agent_ids = null;

    //Data object variables for populating the Target table
    public static $target_table_object = null;
    public static $target_ids = null;
    public static $current_alerts_summary = null;

    public static function initialize_settings($settings_file, $empty){
        $settings_data_encoded = file_get_contents($settings_file);
        Process_Data::$settings_data = json_decode($settings_data_encoded);

        if($empty) {
            Process_Data::$agent_ids = array();
        } else {
            Process_Data::$agent_ids = Process_Data::$settings_data->{'selected_agents'};
        }
        # Process_Data::$agent_ids_under_maintenance = Process_Data::$settings_data->{'agents_under_maintenance'};
        
        if($empty) {
            Process_Data::$target_ids = array();
        } else {
            Process_Data::$target_ids = Process_Data::$settings_data->{'selected_targets'};
        }
        # Process_Data::$target_ids_under_maintenance = Process_Data::$settings_data->{'targets_under_maintenance'};
    }

    /* - - - - Functions for Accessing Data by ID - - - - */

    //Gets Agent object by ID
    public static function get_agent_object($id){
        return json_decode(Agents::show($id));
    }

    //Gets Target object by ID
    public static function get_target_object($id){
        $target = Modern_NetBeez_API::fetchTargetDetail((int) $id);
        $legacy = json_decode(Nb_Targets::show($id));
        if (is_array($target)) {
            if ((empty($target['agent_ids']) || count($target['agent_ids']) === 0) && isset($legacy->{'agent_ids'})) {
                $target['agent_ids'] = $legacy->{'agent_ids'};
            }
            if ((!isset($target['description']) || trim((string) $target['description']) === '') && isset($legacy->{'description'})) {
                $target['description'] = $legacy->{'description'};
            }
            return json_decode(json_encode($target));
        }

        return $legacy;
    }


    /* - - - - Functions for Populating Agent Table Data Object - - - - */


    //Populates Agent IDs in the Agent table object
    public static function populate_agent_ids(){
        foreach(Process_Data::$agent_ids as $id){
            Process_Data::$agent_table_object[$id]['agent_id'] = $id;
        }
    }

    //Populates Agent names in the table object
    public static function populate_agent_names(){
        $agent_names = json_decode(Agents::names());

        foreach($agent_names as $id=>$name) {
            Process_Data::$agent_table_object[$id]['agent_name'] = $name;
        }
    }

    //Populates Agent availability
    public static function populate_agent_availabilities($from, $to, $window_size){
        $agent_avail_data = json_decode(Agents::availabilities($from, $to, $window_size));

        foreach($agent_avail_data as $id=>$agent_statistic) {
            Process_Data::$agent_table_object[$id]['availability'] = round($agent_statistic->{'uptime'}, 2);
        }
    }

    //Populates Agent status
    public static function populate_agent_statuses(){
        $agent_status_data = json_decode(Agents::statuses());

        foreach($agent_status_data as $id=>$agent_status) {
            if(!empty(Process_Data::$agent_table_object[$id])) {
                Process_Data::$agent_table_object[$id]['status'] = $agent_status;
            }
        }
    }

    //Populates Agent upload and download speeds for the time period defined.
    public static function populate_agent_bandwidths($from, $to) {

        foreach(Process_Data::$agent_ids as $id) {
            $sched_results = json_decode(Scheduled_Nb_Test_Results::index($from, $to, $id, 7, 1));
            $sched_results = $sched_results->{'nb_test_results'};

            $download_speed = 0;
            $upload_speed = 0;

            if(!empty($sched_results)){
                $all_values = array();
                foreach($sched_results as $sched_res) {
                    $all_values = array_merge($all_values, $sched_res->{'result_values'});
                }

                foreach($all_values as $result_value) {
                    if($result_value->{'key'} == 'down') {
                        $download_speed = round($result_value->{'value'}, 2);
                    }
                    if($result_value->{'key'} == 'up') {
                        $upload_speed = round($result_value->{'value'}, 2);
                    }
                }
            }

            Process_Data::$agent_table_object[$id]['download_speed'] = $download_speed;
            Process_Data::$agent_table_object[$id]['upload_speed'] = $upload_speed;
        }
    }

    //Populates Target alert counts in the Agent table object
    public static function populate_agent_alert_counts(){
        $summary = Process_Data::get_current_alerts_summary();

        foreach(Process_Data::$agent_ids as $id){
            Process_Data::$agent_table_object[$id]['open_alerts_count'] = isset($summary['by_agent'][$id])
                ? (int) $summary['by_agent'][$id]['count']
                : 0;
        }

    }



    /* - - - - Functions for Populating Target Table Data Object - - - - */


    //Populates Target IDs in the Target table object
    public static function populate_target_ids(){
        foreach(Process_Data::$target_ids as $id){
            Process_Data::$target_table_object[$id]['target_id'] = $id;
        }
    }


    //Populates Target names in the Target table object
    public static function populate_target_names(){
        $target_names = json_decode(Nb_Targets::names(Process_Data::$target_ids));

        foreach($target_names as $id=>$name) {
            Process_Data::$target_table_object[$id]['target_name'] = $name;
        }
    }


    //Populates Target response times in the Target table object
    public static function populate_target_response_times($from, $to, $window_size){

        foreach(Process_Data::$target_ids as $id){
            $test_statistics = json_decode(Nb_Test_Statistics::index($from, $to, $id, 3, $window_size, null));
            $test_statistics = $test_statistics->{"nbTestStatistics"};
            if(isset($test_statistics[0]->{'value'})) {
                Process_Data::$target_table_object[$id]['response_time'] = round($test_statistics[0]->{'value'}, 2);
            } else {
                Process_Data::$target_table_object[$id]['response_time'] = 0;
            }
        }
    }


    //Populates Target alert counts in the Target table object
    public static function populate_target_alert_counts_and_status(){
        $summary = Process_Data::get_current_alerts_summary();

        foreach(Process_Data::$target_ids as $id){
            $target_summary = isset($summary['by_target'][$id]) ? $summary['by_target'][$id] : array(
                'count' => 0,
                'warn_count' => 0,
                'fail_count' => 0,
            );

            Process_Data::$target_table_object[$id]['open_alerts_count'] = (int) $target_summary['count'];

            if((int) $target_summary['fail_count'] > 0){
                Process_Data::$target_table_object[$id]['status'] = 2;
            } else if ((int) $target_summary['warn_count'] > 0){
                Process_Data::$target_table_object[$id]['status'] = 1;
            }
        }
    }

    public static function get_current_alerts_summary(){
        if (is_array(Process_Data::$current_alerts_summary)) {
            return Process_Data::$current_alerts_summary;
        }

        $summary = array(
            'by_agent' => array(),
            'by_target' => array(),
        );

        $current_alerts = json_decode(Nb_Alerts::current_alerts());
        $alerts = isset($current_alerts->{'current_alerts'}) ? $current_alerts->{'current_alerts'} : array();

        if (is_array($alerts) || is_object($alerts)) {
            foreach($alerts as $alert) {
                if (!is_object($alert)) {
                    continue;
                }

                $severity = isset($alert->{'severity'}) ? (int) $alert->{'severity'} : 0;

                if (isset($alert->{'agent_id'}) && is_numeric($alert->{'agent_id'})) {
                    $agent_id = (int) $alert->{'agent_id'};
                    if (!isset($summary['by_agent'][$agent_id])) {
                        $summary['by_agent'][$agent_id] = array('count' => 0);
                    }
                    $summary['by_agent'][$agent_id]['count']++;
                }

                if (isset($alert->{'nb_target_id'}) && is_numeric($alert->{'nb_target_id'})) {
                    $target_id = (int) $alert->{'nb_target_id'};
                    if (!isset($summary['by_target'][$target_id])) {
                        $summary['by_target'][$target_id] = array(
                            'count' => 0,
                            'warn_count' => 0,
                            'fail_count' => 0,
                        );
                    }

                    $summary['by_target'][$target_id]['count']++;
                    if ($severity === 1) {
                        $summary['by_target'][$target_id]['fail_count']++;
                    } else if ($severity === 4) {
                        $summary['by_target'][$target_id]['warn_count']++;
                    }
                }
            }
        }

        Process_Data::$current_alerts_summary = $summary;
        return $summary;
    }



    /* - - - - Functions for Building Table Data Objects - - - - */


    //Populates data object for Agent/Location table
    public static function build_agent_table_data($time_window){

        $current_time = new DateTime();
        $to = $current_time->getTimestamp() * 1000;
        $from = $to - $time_window;

        if(sizeof(Process_Data::$agent_ids) == 0) {
            $json = json_decode(Agents::ids());
            Process_Data::$agent_ids = $json->{'ids'};
        }

        Process_Data::$agent_table_object = array_fill_keys(Process_Data::$agent_ids, array(
            "agent_id" => 0,
            "agent_name" => null,
            "status" => null,
            "availability" => null,
            "download_speed" => 0,
            "upload_speed" => 0,
            "open_alerts_count" => 0
        ));

        //Call the populate data functions one by one
        Process_Data::populate_agent_ids();
        Process_Data::populate_agent_names();
        Process_Data::populate_agent_availabilities($from, $to, $time_window/60000);
        Process_Data::populate_agent_statuses();
        Process_Data::populate_agent_bandwidths($from, $to);
        Process_Data::populate_agent_alert_counts();

        return json_encode(array_values(Process_Data::$agent_table_object), JSON_HEX_APOS);
    }


    //Populates data object for Target/Application table
    public static function build_target_table_data($time_window){

        $current_time = new DateTime();
        $to = $current_time->getTimestamp() * 1000;
        $from = $to - $time_window;

        if(sizeof(Process_Data::$target_ids) ==0) {
            $target_ids = json_decode(Nb_Targets::ids());
            $target_ids = $target_ids->{'targetIds'};

            Process_Data::$target_ids = $target_ids;
        }

        Process_Data::$target_table_object = array_fill_keys(Process_Data::$target_ids, array(
            "target_id" => 0,
            "target_name" => null,
            "status" => 0,
            "response_time" => 0,
            "open_alerts_count" => 0
        ));

        Process_Data::populate_target_ids();
        Process_Data::populate_target_names();
        Process_Data::populate_target_alert_counts_and_status();
        Process_Data::populate_target_response_times($from, $to, $time_window/60000);

        return json_encode(array_values(Process_Data::$target_table_object), JSON_HEX_APOS);
    }



    /* - - - - Functions for line/area/bar charts - - - - */

    public static function build_agent_graphs_data_object($time_window, $agent_id){

        $current_time = new DateTime();
        $to = $current_time->getTimestamp() * 1000;
        $from = $to - $time_window;

        $agent_data = json_decode(Scheduled_Nb_Test_Results::index($from, $to, $agent_id, 7, 0));
        $agent_data = $agent_data->{'nb_test_results'};

        $agent_data_object = array();

        for($i = 0; $i < count($agent_data); $i++){

            if(!empty($agent_data)){
                $sched_results = $agent_data[$i]->{'result_values'};

                $download_speed = round($sched_results[0]->{'value'}, 2);
                $latency = round($sched_results[1]->{'value'}, 2);
                $upload_speed = round($sched_results[2]->{'value'}, 2);
            } else {
                $download_speed = 0;
                $latency = 0;
                $upload_speed = 0;
            }

            $test_run = array(
                "ts" => $agent_data[$i]->{'ts'},
                "down" => $download_speed,
                "latency" => $latency,
                "up" => $upload_speed
            );

            array_push($agent_data_object, $test_run);
        }

        return json_encode($agent_data_object, JSON_HEX_APOS);
    }

    public static function build_agent_access_point_metrics_data_object($time_window, $agent_id){

        $current_time = new DateTime();
        $to = $current_time->getTimestamp() * 1000;
        $from = $to - $time_window;

        $metric_data = json_decode(Access_Point_Metrics::sample($from, $to, $agent_id));
        $metric_data = $metric_data->{'metrics'};

        $metric_data_object = array();

        for($i = 0; $i < count($metric_data); $i++){

            if(!empty($metric_data)){
                $signal_level = $metric_data[$i]->{'signal_level'};
                $link_quality = $metric_data[$i]->{'link_quality'} * 100;
                $bit_rate = $metric_data[$i]->{'bit_rate'};
            } else {
                $signal_level = 0;
                $link_quality = 0;
                $bit_rate = 0;
            }

            $data_point = array(
                "ts" => $metric_data[$i]->{'timestamp'},
                "signal_level" => $signal_level,
                "link_quality" => $link_quality,
                "bit_rate" => $bit_rate
            );

            array_push($metric_data_object, $data_point);
        }

        return json_encode($metric_data_object, JSON_HEX_APOS);
    }


    public static function build_target_line_graph_data_object($time_window, $target_id){

        $current_time = new DateTime();
        $to = $current_time->getTimestamp() * 1000;
        $from = $to - $time_window;

        //adjusts window size based on current time window
        if($time_window == day_time_window){
            $window_size = 60;
        } else if($time_window == week_time_window){
            $window_size = 240;
        } else {
            $window_size = 720;
        }

        $test_statistics = json_decode(Nb_Test_Statistics::index($from, $to, $target_id, 3, $window_size, null));
        $test_statistics = isset($test_statistics->{"nbTestStatistics"}) ? $test_statistics->{"nbTestStatistics"} : array();

        $response_time_array = array();

        for($i = 0; $i < count($test_statistics); $i++){
            if (!isset($test_statistics[$i]->{'timestamp'}) || !isset($test_statistics[$i]->{'value'})) {
                continue;
            }

            $response_time_ts = array(
                "ts" => $test_statistics[$i]->{'timestamp'},
                "response_time" => round($test_statistics[$i]->{'value'}, 2)
            );

            array_push($response_time_array, $response_time_ts);
        }

        return json_encode($response_time_array, JSON_HEX_APOS);
    }

    public static function build_target_bar_graph_data_object($time_window, $target_id){

        $current_time = new DateTime();
        $to = $current_time->getTimestamp() * 1000;
        $from = $to - $time_window;

        $target_object = Process_Data::get_target_object($target_id);
        $agent_ids = normalize_target_agent_ids(isset($target_object->{'agent_ids'}) ? $target_object->{'agent_ids'} : array());
        Process_Data::$agent_ids = $agent_ids;
        $agent_names = count($agent_ids) > 0 ? json_decode(Agents::names($agent_ids)) : (object) array();

        $http_test_data = json_decode(Nb_Test_Statistics::index($from, $to, $target_id, 3, $time_window/60000, "agent_id"));
        $http_test_data = isset($http_test_data->{"nbTestStatistics"}) ? $http_test_data->{"nbTestStatistics"} : array();

        $per_agent_http_data_array = array();

        for($i = 0; $i < count($http_test_data); $i++){
            $agent_id = isset($http_test_data[$i]->{"agent_id"}) ? $http_test_data[$i]->{"agent_id"} : null;
            $agent_name = ($agent_id !== null && is_object($agent_names) && isset($agent_names->{$agent_id})) ? $agent_names->{$agent_id} : ('Agent ' . $agent_id);

            $per_agent_entry = array(
                "agent_id" => $agent_id,
                "agent_name" => $agent_name,
                "response_time" => round($http_test_data[$i]->{"value"}, 2)
            );

            array_push($per_agent_http_data_array, $per_agent_entry);
        }

        return json_encode($per_agent_http_data_array, JSON_HEX_APOS);
    }



    /* - - - - Swim Lane Graph Data Functions - - - - */


    //Builds data object for agent-based alert timeline data
    public static function build_agent_swimlane_data($time_window, $agent_id, $target_ids_array){

        $current_time = new DateTime();
        $to = $current_time->getTimestamp() * 1000;
        $from = $to - $time_window;

        //adjusts interval based on current time window
        if($time_window == day_time_window){
            $interval = 15;
        } else if($time_window == week_time_window){
            $interval = 120;
        } else {
            $interval = 360;
        }

        $timestamp_array = array();
        $timestamp = $from;
        while($timestamp < $to){
            $timestamp = $timestamp + ($interval * 60000);
            array_push($timestamp_array, $timestamp);
        }

        Process_Data::$target_ids = $target_ids_array;

        $target_names = json_decode(Nb_Targets::names());

        $target_alert_count_array = array();

        foreach(Process_Data::$target_ids as $target_id){
            $target_alert_count = json_decode(Nb_Alerts::open_alerts_at_intervals($from, $to, $interval, array("agent_id" => (int) $agent_id, "nb_target_id" => (int) $target_id)));
            array_push($target_alert_count_array, $target_alert_count);
        }

        $agent_alert_data_object = array();

        for($i = 0; $i < count($timestamp_array); $i++){

            $per_target_counts = array();

            $j = 0;
            foreach(Process_Data::$target_ids as $target_id){
                $per_target_counts[$target_names->{$target_id}] = array(
                    "counts" => array(
                        "1" => $target_alert_count_array[$j][$i]->{'counts'}->{'1'},
                        "4" => $target_alert_count_array[$j][$i]->{'counts'}->{'4'}
                    )
                );

                $j++;
            }

            $count_interval = array(
                "ts" => $timestamp_array[$i],
                "per_item_counts" => $per_target_counts
            );

            array_push($agent_alert_data_object, $count_interval);
        }

        return json_encode($agent_alert_data_object, JSON_HEX_APOS);
    }


    //Builds data object for target-based alert timeline data
    public static function build_target_swimlane_data($time_window, $target_id, $agent_ids_array){
        $current_time = new DateTime();
        $to = $current_time->getTimestamp() * 1000;
        $from = $to - $time_window;

        //adjusts interval based on current time window
        if($time_window == day_time_window){
            $interval = 15;
        } else if($time_window == week_time_window){
            $interval = 120;
        } else {
            $interval = 360;
        }

        $timestamp_array = array();
        $timestamp = $from;
        while($timestamp < $to){
            $timestamp = $timestamp + ($interval * 60000);
            array_push($timestamp_array, $timestamp);
        }

        Process_Data::$agent_ids = normalize_target_agent_ids($agent_ids_array);
        if (count(Process_Data::$agent_ids) === 0) {
            return json_encode(array(), JSON_HEX_APOS);
        }

        $agent_names = json_decode(Agents::names(Process_Data::$agent_ids));

        $agent_alert_count_array = array();

        foreach(Process_Data::$agent_ids as $agent_id){
            $agent_alert_count = json_decode(Nb_Alerts::open_alerts_at_intervals($from, $to, $interval, array("agent_id" => (int) $agent_id, "nb_target_id" => (int) $target_id)));
            array_push($agent_alert_count_array, $agent_alert_count);
        }

        $target_alert_data_object = array();

        for($i = 0; $i < count($timestamp_array); $i++){

            $per_agent_counts = array();

            $j = 0;
            foreach(Process_Data::$agent_ids as $agent_id){
                $per_agent_counts[$agent_names->{$agent_id}] = array(
                    "counts" => array(
                        "1" => $agent_alert_count_array[$j][$i]->{'counts'}->{'1'},
                        "4" => $agent_alert_count_array[$j][$i]->{'counts'}->{'4'}
                    )
                );

                $j++;
            }

            $count_interval = array(
                "ts" => $timestamp_array[$i],
                "per_item_counts" => $per_agent_counts
            );

            array_push($target_alert_data_object, $count_interval);
        }

        return json_encode($target_alert_data_object, JSON_HEX_APOS);
    }

    public static function get_agent_table_rows($time_window){
        Process_Data::initialize_settings('admin/settings.json', false);
        $rows = json_decode(Process_Data::build_agent_table_data($time_window), true);
        return is_array($rows) ? $rows : array();
    }

    public static function get_target_table_rows($time_window){
        Process_Data::initialize_settings('admin/settings.json', false);
        $rows = json_decode(Process_Data::build_target_table_data($time_window), true);
        return is_array($rows) ? $rows : array();
    }

    public static function build_main_dashboard_snapshot($time_window){
        $agentRows = Process_Data::get_agent_table_rows($time_window);
        $targetRows = Process_Data::get_target_table_rows($time_window);
        $supplemental = Modern_NetBeez_API::buildHomepageSupplementalData((int) $time_window);
        $agentCategories = isset($supplemental['agent_categories']) && is_array($supplemental['agent_categories'])
            ? $supplemental['agent_categories']
            : array();

        $networkAgentRows = array_values(array_filter($agentRows, function($row) use ($agentCategories){
            $agentId = (int) ($row['agent_id'] ?? 0);
            return isset($agentCategories[$agentId]) && $agentCategories[$agentId] === 'network_agent';
        }));

        $operationalAgents = 0;
        foreach ($networkAgentRows as $row) {
            if (!empty($row['status'])) {
                $operationalAgents++;
            }
        }

        $activeTargets = 0;
        foreach ($targetRows as $row) {
            if ((int) ($row['status'] ?? 0) === 0) {
                $activeTargets++;
            }
        }

        return array(
            'agent_rows' => $networkAgentRows,
            'target_rows' => $targetRows,
            'isp_rows' => $supplemental['isp_rows'] ?? array(),
            'group_rows' => $supplemental['group_rows'] ?? array(),
            'remote_worker_summary' => $supplemental['remote_worker_summary'] ?? array(
                'total_quantity' => 0,
                'total_online' => 0,
                'total_offline' => 0,
                'total_with_active_incidents' => 0,
            ),
            'latest_sample_ts' => (int) ($supplemental['latest_sample_ts'] ?? 0),
            'errors' => $supplemental['errors'] ?? array(),
            'hero' => array(
                'location_count' => count($networkAgentRows),
                'location_operational_count' => $operationalAgents,
                'application_count' => count($targetRows),
                'application_healthy_count' => $activeTargets,
                'isp_count' => count($supplemental['isp_rows'] ?? array()),
                'group_count' => count($supplemental['group_rows'] ?? array()),
            ),
        );
    }

    public static function get_target_last_updated_timestamp($time_window, $target_id){
        $current_time = new DateTime();
        $to = $current_time->getTimestamp() * 1000;
        $from = $to - $time_window;

        if($time_window == day_time_window){
            $window_size = 60;
        } else if($time_window == week_time_window){
            $window_size = 240;
        } else {
            $window_size = 720;
        }

        $payload = json_decode(Nb_Test_Statistics::index($from, $to, $target_id, 3, $window_size, null));
        $rows = isset($payload->{"nbTestStatistics"}) ? $payload->{"nbTestStatistics"} : array();

        $latest = 0;
        if (is_array($rows) || is_object($rows)) {
            foreach($rows as $row) {
                if (isset($row->{'timestamp'}) && is_numeric($row->{'timestamp'})) {
                    $latest = max($latest, (int) $row->{'timestamp'});
                }
            }
        }

        return $latest;
    }

    public static function get_target_last_updated_label($time_window, $target_id){
        $latest = Process_Data::get_target_last_updated_timestamp($time_window, $target_id);
        if ($latest <= 0) {
            return 'No recent HTTP samples in the selected time window';
        }

        return gmdate('Y-m-d H:i:s', (int) floor($latest / 1000)) . ' UTC';
    }
}
