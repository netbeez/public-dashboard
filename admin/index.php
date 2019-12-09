<?php
/**
 * Admin Main Index
 */

require("../config.php");
require("../includes/associate_api_data.php");
require("../includes/api_access.php");
require("../includes/functions.php");
require("modules/selection-module.php");

include('templates/header.php');

Process_Data::initialize_settings('settings.json',true);

$agent_selection_module = new Selection_Module("agent");
$target_selection_module = new Selection_Module("target");
?>

    <header id="admin-header" class="dashboard-header">
        <div class="header-logo"></div>
        <div class="header-title"><h3><?php echo get_page_title(true); ?></h3></div>
    </header>

    <div id="admin-main">

        <form id="admin-settings-form" class="form" action="settings-form-handler.php" method="POST">

            <div class="form-row form-section">
                <div class="form-section-header">
                    <h6>Select Agents to be displayed on this Dashboard</h6>
                </div>

                <div class="form-section-body selection-module-container">
                    <?php $agent_selection_module->display_module(); ?>
                </div>
            </div>

            <div class="form-row form-section">
                <div class="form-section-header">
                    <h6>Select Target to be displayed on this Dashboard</h6>

                </div>

                <div class="form-section-body selection-module-container">
                    <?php $target_selection_module->display_module(); ?>
                </div>
            </div>

            <div class="form-row form-section">
                <div class="form-section-header">
                    <h6>Set the refresh interval</h6>
                </div>

                <div class="form-section-body">
                    <div class="form-item full">
                        <label style="width: 180px;">Refresh Interval (minutes):</label>
                        <input id="refresh-interval" class="input input-number" type="number" name="refresh-interval" placeholder="<?php echo get_refresh_interval(true, false); ?>" >
                    </div>
                </div>
            </div>

            <div class="form-row form-footer">
                <button id="cancel-settings" class="button" type="button">Cancel</button>
                <button id="save-settings" class="button button-primary" type="submit">Save <i id="save-button-icon" class="fa fa-square-o"></i></button>
            </div>

        </form>

    </div>

<?php include('templates/footer.php'); ?>