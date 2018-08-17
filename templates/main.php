<?php
/**
 * The main page template
 */


include "templates/header.php"; ?>

<div class="main">

    <div class="panel-section" id="agent-table-container">
        <div class="section-header">
            <h4>Your Network Locations</h4>
            <div class="description"><p>Click on a Location's name to see more information.</p></div>
        </div>

        <table id="agent-sort-table" class="table exec-sort-table">
            <thead id="agent-sort-thead">
            <tr>
                <th style="width:140px;" class="sort-th">Name</th>
                <th class="sort-th">Status</th>
                <th class="sort-th">Availability</th>
                <th class="sort-th">Download (mbps)</th>
                <th class="sort-th">Upload (mbps)</th>
                <th class="sort-th"># Open Alerts</th>
            </tr>
            </thead>

            <tbody id="agent-sort-tbody">
            </tbody>
        </table>

    </div>

    <div class="panel-section">
        <div class="section-header">
            <h4>Your Applications</h4>
            <div class="description"><p>Click on an Applications's name to see more information.</p></div>
        </div>


        <table id="target-sort-table" class="table exec-sort-table">
            <thead id="target-sort-thead">
            <tr>
                <th style="width:140px;" class="sort-th">Name</th>
                <th class="sort-th">Status</th>
                <th class="sort-th">Response Time (HTTP) (s)</th>
                <th class="sort-th"># Open Alerts</th>
            </tr>
            </thead>

            <tbody id="target-sort-tbody">
            </tbody>
        </table>
    </div>

</div>

<?php include "templates/footer.php"; ?>
