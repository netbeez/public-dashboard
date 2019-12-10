<?php

/**
 * Agent and Target Selection Module
 */

class Selection_Module {

    function __construct($type) {
        $this->type = $type;

        $this->title = '';

        $this->component_array = array();
        $this->component_status_array = array();
        $this->settings_data = file_get_contents('settings.json');

        if($type == "agent"){
            $this->title = "Agent";
            $this->component_array = json_decode(Agents::index())->{'agents'};
            $this->component_status_array = json_decode(Agents::statuses());
        } else if($type == "target"){
            $this->title = "Target";
            $this->component_array = $components_array = json_decode(Nb_Targets::index())->{'targets'};
        }

        $this->formatted_component_list = '';
        $this->formatted_selected_component_list = '';

        $this->build_component_lists();
    }

    private function build_component_lists(){

        foreach($this->component_array as $component){

            if($this->type == "agent"){

                if(in_array($component->{'id'}, json_decode($this->settings_data)->{'selected_agents'})){
                    $selected = 'selected';
                } else {
                    $selected = '';
                }

                if(in_array($component->{'id'}, json_decode($this->settings_data)->{'agents_under_maintenance'})){
                    $maintenance_status = 'maintenance-status-active';
                } else {
                    $maintenance_status = '';
                }

                if($this->component_status_array->{$component->{'id'}}){
                    $status = 'success';
                } else {
                    $status = 'fail';
                }

                $agent_type_display = $this->get_agent_type_icon($component->{'agent_class'});

                $list_item =   '<li class="selection-list-item id-' . $component->{'id'} . ' ' . $selected . '" data-value="' . $component->{'id'} . '">
                        <i id="mode-icon" class="fa fa-circle ' . $status . '"></i>'  . $agent_type_display . ' 
                        <span class="js-agent-name">'.$component->{'name'} .'</span>
                    </li>';

                $selected_list_item = '<li class="selected-item ' . $maintenance_status . ' ' . $selected . '" id="selected-'. $this->type .'-' . $component->{'id'} . '" data-value="' . $component->{'id'} . '">
                        <i id="mode-icon" class="fa fa-circle ' . $status . '"></i>' . $agent_type_display . '
                        <span id="component-name">' . $component->{'name'} . '</span>
                        <span class="maintenance-status-toggle pull-right"><i class="fa fa-wrench"></i></span>
                        <span class="fa fa-times pull-right deselect-option"></span>
                    </li>';


                $this->formatted_component_list= $this->formatted_component_list . $list_item;
                $this->formatted_selected_component_list = $this->formatted_selected_component_list . $selected_list_item;

            } else if($this->type == "target"){

                if(in_array($component->{'id'}, json_decode($this->settings_data)->{'selected_targets'})){
                    $selected = 'selected';
                } else {
                    $selected = '';
                }

                if(in_array($component->{'id'}, json_decode($this->settings_data)->{'targets_under_maintenance'})){
                    $maintenance_status = 'maintenance-status-active';
                } else {
                    $maintenance_status = '';
                }

                $list_item =   '<li class="selection-list-item id-' . $component->{'id'} . ' ' . $selected . '" data-value="' . $component->{'id'} . '">
                        <span class="js-agent-name">'.$component->{'name'} .'</span>
                    </li>';

                $selected_list_item = '<li class="selected-item ' . $maintenance_status . ' ' . $selected . '" id="selected-'. $this->type .'-' . $component->{'id'} . '" data-value="' . $component->{'id'} . '">
                        <span id="component-name">' . $component->{'name'} . '</span>
                        <span class="maintenance-status-toggle pull-right"><i class="fa fa-wrench"></i></span>
                        <span class="fa fa-times pull-right deselect-option"></span>
                    </li>';


                $this->formatted_component_list= $this->formatted_component_list . $list_item;
                $this->formatted_selected_component_list = $this->formatted_selected_component_list . $selected_list_item;
            }
        }
    }

    public function display_component_list(){
        echo $this->formatted_component_list;
    }

    public function display_selected_component_list(){
        echo $this->formatted_selected_component_list;
    }

    function get_agent_type_icon($agent_type){

        $agent_type_display = '';
        if($agent_type == "faste"){
            $agent_type_display = '<span id="agent-icon" class="wired-agent-icon"><i class="fa fa-plug"></i></span>';
        } else if($agent_type == "gige"){
            $agent_type_display = '<span id="agent-icon" class="wired-g-agent-icon"><i class="fa">G</i></span>';
        } else if($agent_type == "wireless"){
            $agent_type_display = '<span id="agent-icon" class="wireless-agent-icon"><i class="fa fa-wifi"></i></span>';
        } else if($agent_type == "virtual"){
            $agent_type_display = '<span id="agent-icon" class="virtual-agent-icon"><i class="fa fa-cube"></i></span>';
        } else if($agent_type == "external"){
            $agent_type_display = '<span id="agent-icon" class="external-agent-icon"><i class="fa fa-globe"></i></span>';
        } else if($agent_type == "cumulus"){
            $agent_type_display = '';
        } else if($agent_type == "software"){
            $agent_type_display = '<span id="agent-icon" class="software-agent-icon"><i class="fa fa-terminal"></i></span>';
        }

        return $agent_type_display;
    }

    function get_selected_components_count(){
        $count = 0;

        if($this->type == "agent"){
            $count = count(json_decode($this->settings_data)->{'selected_agents'});
        } else if ($this->type == "target"){
            $count = count(json_decode($this->settings_data)->{'selected_targets'});
        }

        return $count;
    }

    function display_module(){
        ?>
                    <div class="selection-module">
                        <div class="selection-widget-container three-col-x2">
                            <div class="selection-widget items no-groups">
                                <div id="<?php echo $this->type ?>s-selection-widget">
                                    <div class="selection-module-header">
                                        <span class="title">Add <?php echo $this->title ?>s</span>
                                        <input class="<?php echo $this->type ?>s-filter" type="text" placeholder="Search by <?php echo $this->title ?> name">
                                        <button id="<?php echo $this->type ?>-search" class="search-button" type="button"><i class="fa fa-search" aria-hidden="true"></i></button>
                                    </div>
                                    <div class="selection-list-container tall">
                                        <ul class="<?php echo $this->type ?>-list selection-list">
                                            <?php $this->display_component_list(); ?>
                                        </ul>
                                    </div>
                                    <div class="selection-module-footer">
                                        <button class="button button-small" id="select-all-<?php echo $this->type ?>s" type="button">Select All</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="<?php echo $this->type ?>s-selected-list" class="selection-widget-container selected-items-list-container three-col" style="background-color: rgb(208, 255, 139);">
                            <div class="selection-widget">
                                <div id="selected-<?php echo $this->type ?>s-list-widget">
                                    <div class="selection-module-header">
                                        <span id="selected-components-list-title" class="title label label-success"><span id="num-of-<?php echo $this->type ?>s-selected"><?php echo $this->get_selected_components_count(); ?></span> <?php echo $this->title ?>(s) displayed</span>
                                        <input class="<?php echo $this->type ?>s-filter" type="text" placeholder="Search by  name">
                                    </div>
                                    <div id="select-<?php echo $this->type ?>s" class="selection-list-container">
                                        <ul id="selected-components-container" class="selection-list selected-items-list selected-<?php echo $this->type ?>s-list">
                                            <?php $this->display_selected_component_list(); ?>
                                        </ul>
                                    </div>
                                    <div class="selection-module-footer">
                                        <button class="button button-small" id="deselect-all-<?php echo $this->type ?>s" type="button">Clear Selection</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div><!-- .selection-module -->
        <?php
    }
}