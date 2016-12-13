<?php

require("config.php");
require("includes/functions.php");
require("includes/api_access.php");
require("includes/associate_api_data.php");

$view = isset( $_GET['view'] ) ? $_GET['view'] : "";

const day_time_window = 86400000;      //one day in milliseconds
const week_time_window = 604800000;    //one week in milliseconds
const month_time_window = 2592000000;  //one month in milliseconds

$selected_time_window = day_time_window;

if (!isset($_COOKIE["time_window"])){
    setcookie("time_window", $selected_time_window, (time() + 60), "/");
}

update_time_window_interval();



switch ( $view ) {
    case 'view_agent':
        show_agent();
        break;
    case 'view_target':
        show_target();
        break;
    default:
        show_main_page();
}


//Display Main Page
function show_main_page(){
    global $view;
    global $selected_time_window;
    global $name;
    global $description;
    $name = COMPANY_NAME;
    $description = SITE_DESCRIPTION;
    include "templates/main.php";
}


//Display Agent/Location Page
function show_agent(){
    global $view;
    global $selected_time_window;
    global $data_obj;
    global $name;
    global $description;
    $data_obj = query_current_page_object();
    $name = $data_obj->{'name'};
    $description = $data_obj->{'description'};
    include "templates/agent_template.php";
}


//Display Target/Application Page
function show_target(){
    global $view;
    global $selected_time_window;
    global $data_obj;
    global $name;
    global $description;
    $data_obj = query_current_page_object();
    $name = $data_obj->{'name'};
    $description = $data_obj->{'description'};
    include "templates/target_template.php";
}