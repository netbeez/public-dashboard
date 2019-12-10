<?php
/**
 * Form Handler for Admin Settings
 */

$settings_data = file_get_contents('settings.json');
$settings_data_decoded = json_decode($settings_data);

if(isset($_POST['selected_agents'])){
    $selected_agents = $_POST['selected_agents'];
    $selected_agents = array_map('intval', $selected_agents);
} else {
    $selected_agents = $settings_data_decoded->{'selected_agents'};
}

if(isset($_POST['agents_under_maintenance'])){
    $agents_under_maintenance = $_POST['agents_under_maintenance'];
    $agents_under_maintenance = array_map('intval', $agents_under_maintenance);
} else {
    $agents_under_maintenance = array();
}

if(isset($_POST['selected_targets'])){
    $selected_targets = $_POST['selected_targets'];
    $selected_targets = array_map('intval', $selected_targets);
} else {
    $selected_targets = $settings_data_decoded->{'selected_targets'};
}

if(isset($_POST['targets_under_maintenance'])){
    $targets_under_maintenance = $_POST['targets_under_maintenance'];
    $targets_under_maintenance = array_map('intval', $targets_under_maintenance);
} else {
    $targets_under_maintenance = array();
}

if(isset($_POST['refresh_interval'])){
    if($_POST['refresh_interval'] == "" && isset($settings_data_decoded->{'refresh_interval'})){
        $refresh_interval = $settings_data_decoded->{'refresh_interval'};
    } else {
       $refresh_interval = intval($_POST['refresh_interval']);
    }
} else {
    $refresh_interval = 2;
}


$settings_data_decoded->{'selected_agents'} = $selected_agents;
$settings_data_decoded->{'agents_under_maintenance'} = $agents_under_maintenance;

$settings_data_decoded->{'selected_targets'} = $selected_targets;
$settings_data_decoded->{'targets_under_maintenance'} = $targets_under_maintenance;

$settings_data_decoded->{'refresh_interval'} = $refresh_interval;

echo json_encode($settings_data_decoded);

file_put_contents('settings.json', json_encode($settings_data_decoded));
