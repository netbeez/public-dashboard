<?php
/**
 * This class of functions handles the processing of the API data and building data objects for the graphs
 */

class Process_Data {

    //Data object variables for populating the Agent table
    public static $agent_table_object = null;
    public static $agent_ids = null;

    //Data object variables for populating the Target table
    public static $target_table_object = null;
    public static $target_ids = null;


    /* - - - - Functions for Accessing Data by ID - - - - */

    //Gets Agent object by ID
    public static function get_agent_object($id){
        return json_decode(Agents::show($id));
    }

    //Gets Target object by ID
    public static function get_target_object($id){
        return json_decode(Nb_Targets::show($id));
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
            Process_Data::$agent_table_object[$id]['status'] = $agent_status;
        }
    }

    //Populates Agent upload and download speeds for the time period defined.
    public static function populate_agent_bandwidths($from, $to) {

        foreach(Process_Data::$agent_ids as $id) {
            $sched_results = json_decode(Scheduled_Nb_Test_Results::index($from, $to, $id, 7, 1));
            $sched_results = $sched_results->{'nb_test_results'};

            if(!empty($sched_results)){
                $sched_results = $sched_results[0]->{'result_values'};

                $download_speed = round($sched_results[0]->{'value'}, 2);
                $upload_speed = round($sched_results[2]->{'value'}, 2);
            } else {
                $download_speed = 0;
                $upload_speed = 0;
            }

            Process_Data::$agent_table_object[$id]['download_speed'] = $download_speed;
            Process_Data::$agent_table_object[$id]['upload_speed'] = $upload_speed;
        }
    }

    //Populates Target alert counts in the Agent table object
    public static function populate_agent_alert_counts(){

        foreach(Process_Data::$agent_ids as $id){
            $current_alerts = json_decode(Nb_Alerts::current_alerts(array("agent_id" => $id)));
            Process_Data::$agent_table_object[$id]['open_alerts_count'] = count($current_alerts->{'current_alerts'});
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
        $target_names = json_decode(Nb_Targets::names());

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

        foreach(Process_Data::$target_ids as $id){
            $current_alerts = json_decode(Nb_Alerts::current_alerts(array("nb_target_id" => $id)));
            Process_Data::$target_table_object[$id]['open_alerts_count'] = count($current_alerts->{'current_alerts'});

            if(count($current_alerts->{'current_alerts'}) > 0){
                $warn_count = 0;
                $fail_count = 0;

                for($i = 0; $i < count($current_alerts->{'current_alerts'}); $i++){
                    if($current_alerts->{'current_alerts'}[$i]->{'severity'} == 4){
                        $warn_count++;
                    }
                    if($current_alerts->{'current_alerts'}[$i]->{'severity'} == 1){
                        $fail_count++;
                    }
                }

                if($fail_count > 0){
                    Process_Data::$target_table_object[$id]['status'] = 2;
                } else if ($fail_count == 0 && $warn_count > 0){
                    Process_Data::$target_table_object[$id]['status'] = 1;
                }
            }
        }
    }



    /* - - - - Functions for Building Table Data Objects - - - - */


    //Populates data object for Agent/Location table
    public static function build_agent_table_data($time_window){

        $current_time = new DateTime();
        $to = $current_time->getTimestamp() * 1000;
        $from = $to - $time_window;

        $json = json_decode(Agents::ids());
        Process_Data::$agent_ids = $json->{'ids'};

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

        return json_encode(array_values(Process_Data::$agent_table_object));
    }


    //Populates data object for Target/Application table
    public static function build_target_table_data($time_window){

        $current_time = new DateTime();
        $to = $current_time->getTimestamp() * 1000;
        $from = $to - $time_window;

        $target_ids = json_decode(Nb_Targets::ids());
        $target_ids = $target_ids->{'targetIds'};

        Process_Data::$target_ids = $target_ids;

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

        return json_encode(array_values(Process_Data::$target_table_object));
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

        return json_encode($agent_data_object);
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
        $test_statistics = $test_statistics->{"nbTestStatistics"};

        $response_time_array = array();

        for($i = 0; $i < count($test_statistics); $i++){

            $response_time_ts = array(
                "ts" => $test_statistics[$i]->{'timestamp'},
                "response_time" => round($test_statistics[$i]->{'value'}, 2)
            );

            array_push($response_time_array, $response_time_ts);
        }

        return json_encode($response_time_array);
    }

    public static function build_target_bar_graph_data_object($time_window, $target_id){

        $current_time = new DateTime();
        $to = $current_time->getTimestamp() * 1000;
        $from = $to - $time_window;

        $agent_names = json_decode(Agents::names());

        $http_test_data = json_decode(Nb_Test_Statistics::index($from, $to, $target_id, 3, $time_window/60000, "agent_id"));
        $http_test_data = $http_test_data->{"nbTestStatistics"};

        $per_agent_http_data_array = array();

        for($i = 0; $i < count($http_test_data); $i++){

            $per_agent_entry = array(
                "agent_id" => $http_test_data[$i]->{"agent_id"},
                "agent_name" => $agent_names->{$http_test_data[$i]->{"agent_id"}},
                "response_time" => round($http_test_data[$i]->{"value"}, 2)
            );

            array_push($per_agent_http_data_array, $per_agent_entry);
        }

        return json_encode($per_agent_http_data_array);
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

        return json_encode($agent_alert_data_object);
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

        Process_Data::$agent_ids = $agent_ids_array;

        $agent_names = json_decode(Agents::names());

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

        return json_encode($target_alert_data_object);
    }
}