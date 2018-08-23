/**
 * Created by Allison on 10/11/2016.
 */

function TargetSortTable(dataObj) {

    var targets = JSON.parse(dataObj);
        
    var values = d3.keys(targets[0]).filter(function(key) {
        return key != "target_id";
    });

    var barOutputValues = d3.keys(targets[0]).filter(function(key) {
        return key != "target_name" && key != "target_id" && key != "status" && key != "open_alerts_count";
    });

    var alertValues = d3.keys(targets[0]).filter(function(key) {
        return key == "open_alerts_count";
    });

    var toggle = 1;
    d3.selectAll("thead#target-sort-thead th.sort-th").data(values).on("click", function(key) {
        toggle = toggle * -1;
        tr.sort(function(a, b) {
            return toggle * (a[key] < b[key]? -1 : 1);
        });
    });

    var x = d3.scale.linear()
        .domain([0, (1.25 * d3.max(targets, function(d) { return d.response_time; }) / 2) * 2])
        .range([0, 200]);

    var x2 = d3.scale.linear()
        .domain([0, (1.25 * d3.max(targets, function(d) { return d.open_alerts_count; }) / 2) * 2])
        .range([0, 100]);

    var responseTimeTip = d3.tip()
        .attr('class', 'd3-tip')
        .offset([-10, 0])
        .html(function(d) {
            return "Response Time: " + d + " s";
        });

    var alertTip = d3.tip()
        .attr('class', 'd3-tip')
        .offset([-10, 0])
        .html(function(d) {
            return "Current Alerts: " + d;
        });
        
    var tr = d3.select("tbody#target-sort-tbody").selectAll("tr")
        .data(targets)
        .enter().append("tr");

    tr.append("th")
        .html(function(d) {
            return '<a class="application-link" href="javascript:void(0)">' + d.target_name + '</a>';
        })
        .on("click", function(d){
            goToTarget(d.target_id, d.open_alerts_count, d.status);
        });

    tr.append("td")
        .attr("class", "status-cell")
        .html(function(d) {
            if (d.status === "0" || d.status === 0) {
                return "<i id='mode-icon' class='fa fa-circle success'></i> <span style='color: #2FB52C;'>No Alerts</span>";
            } else if (d.status === "1" || d.status === 1) {
                return "<i id='mode-icon' class='fa fa-circle warning'></i> <span style='color: #FF9000;'>Warnings</span>";
            } else if (d.status === "2" || d.status === 2) {
                return "<i id='mode-icon' class='fa fa-circle fail'></i> <span style='color: #EB2D08;'>Failures</span>";
            } else {
                return "Status unknown ("+ d.status + ")";
            }

        });

    tr.selectAll("td.cell")
        .data(function(d) { return barOutputValues.map(function(k) { return d[k] }); }).enter()
        .append("td")
            .attr("class", "cell")
            .append("svg")
                .attr("width", 260)
                .attr("height", 12)
                .call(responseTimeTip)
                .on('mouseover', responseTimeTip.show)
                .on('mouseout', responseTimeTip.hide)
                .append("g")
                    .attr("class", "bar-container")
                    .text(function(d) { return d; })
                    .append("rect")
                        .attr("class", "rect-bar response-time")
                        .attr("x", 80)
                        .attr("height", 12)
                        .attr("width", function(d) {
                            return x(d);
                        });

    tr.selectAll("g.bar-container")
        .append("text")
            .attr("y", 9)
            .attr("x", 0)
            .text(function(d) {
                console.log("value: "+ d);
                return d + " s";
            });

    tr.selectAll("td.alert-count-cell")
        .data(function(d) { return alertValues.map(function(k) { return d[k] }); }).enter()
        .append("td")
            .attr("class", "alert-count-cell")
            .append("svg")
                .attr("width", 160)
                .attr("height", 12)
                .call(alertTip)
                .on('mouseover', alertTip.show)
                .on('mouseout', alertTip.hide)
                .append("g")
                    .attr("class", "alert-bar-container")
                    .text(function(d) { return d; })
                    .append("rect")
                        .attr("class", "rect-bar alert-count")
                        .attr("x", 72)
                        .attr("height", 12)
                        .attr("width", function(d) {
                            return x2(d);
                        });

    tr.selectAll("g.alert-bar-container")
        .append("text")
            .attr("y", 9)
            .attr("x", 0)
            .text(function(d) {
                if (d === 1){
                    return d + " Alert";
                } else {
                    return d + " Alerts";
                }
            });
        

    function goToTarget(targetId, alertCount, status) {
        window.location.href = "?view=view_target&id=" + targetId +"&status=" + status + "&alert_count=" + alertCount;
    }
}