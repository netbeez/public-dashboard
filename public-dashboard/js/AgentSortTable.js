/**
 * Created by Allison on 10/19/2016.
 */

function AgentSortTable(dataObj) {

    var agents = JSON.parse(dataObj);
        
    var values = d3.keys(agents[0]).filter(function(key) {
        return key != "agent_id";
    });

    var downloadKey = d3.keys(agents[0]).filter(function(key) {
        return key == "download_speed";
    });

    var uploadKey = d3.keys(agents[0]).filter(function(key) {
        return key == "upload_speed";
    });

    var alertKey = d3.keys(agents[0]).filter(function(key) {
        return key == "open_alerts_count";
    });

    var toggle = 1;
    d3.selectAll("thead#agent-sort-thead th.sort-th").data(values).on("click", function(key) {
        toggle = toggle * -1;
        tr.sort(function(a, b) {
            return toggle * (a[key] - b[key]);
        });
    });

    var x = d3.scale.linear()
        .domain([0, (1.25 * d3.max(agents, function(d) { return Math.max(d.download_speed, d.upload_speed); }) / 2) * 2])
        .range([0, 100]);

    var xAlerts = d3.scale.linear()
        .domain([0, (1.25 * d3.max(agents, function(d) { return d.open_alerts_count; }) / 2) * 2])
        .range([0, 100]);

    var downloadTip = d3.tip()
        .attr('class', 'd3-tip')
        .offset([-10, 0])
        .html(function(d) {
            return "Download Speed: " + d + " ms";
        });

    var uploadTip = d3.tip()
        .attr('class', 'd3-tip')
        .offset([-10, 0])
        .html(function(d) {
            return "Upload Speed: " + d + " ms";
        });

    var alertTip = d3.tip()
        .attr('class', 'd3-tip')
        .offset([-10, 0])
        .html(function(d) {
            return "Current Alerts: " + d;
        });
        
    var tr = d3.select("tbody#agent-sort-tbody").selectAll("tr")
        .data(agents)
        .enter().append("tr");

    tr.append("th")
        .html(function(d) {
            return "<a class='location-link' href='javascript:void(0)'>" + d.agent_name + "</a>";
        })
        .on("click", function(d){
            goToAgent(d.agent_id, d.open_alerts_count, d.status);
        });

    tr.append("td")
        .attr("class", "status-cell")
        .html(function(d) {
            if (d.status === true) {
                return "<i id='mode-icon' class='fa fa-circle success'></i> <span style='color: #2FB52C;'>Operational</span>";
            } else if (d.status === false) {
                return "<i id='mode-icon' class='fa fa-circle fail'></i> <span style='color: #EB2D08;'>Unreachable</span>";
            } else {
                return "Status unknown ("+ d.status + ")";
            }

        });

    tr.append("td")
        .attr("class", function(d) {
            if (d.availability < 1) {
                return "avail-cell warning";
            } else {
                return "avail-cell";
            }
        })
        .html(function(d) {
            return (d.availability) + "%";
        });


    //download speed column
    tr.selectAll("td.download-cell")
        .data(function(d) { return downloadKey.map(function(k) { return d[k] }); }).enter()
        .append("td")
            .attr("class", "download-cell")
            .append("svg")
                .attr("width", 180)
                .attr("height", 12)
                .call(downloadTip)
                .on('mouseover', downloadTip.show)
                .on('mouseout', downloadTip.hide)
                .append("g")
                    .attr("class", "download-bar-container")
                    .text(function(d) { return d; })
                    .append("rect")
                        .attr("class", "rect-bar download")
                        .attr("x", 80)
                        .attr("height", 12)
                        .attr("width", function(d) {
                            return x(d);
                        });

    tr.selectAll("g.download-bar-container")
        .append("text")
            .attr("y", 9)
            .attr("x", 0)
            .text(function(d) {
                return d + " Mbps";
            });


    //upload speed column
    tr.selectAll("td.upload-cell")
        .data(function(d) { return uploadKey.map(function(k) { return d[k] }); }).enter()
        .append("td")
            .attr("class", "upload-cell")
            .append("svg")
                .attr("width", 180)
                .attr("height", 12)
                .call(uploadTip)
                .on('mouseover', uploadTip.show)
                .on('mouseout', uploadTip.hide)
                .append("g")
                    .attr("class", "upload-bar-container")
                    .text(function(d) { return d; })
                    .append("rect")
                        .attr("class", "rect-bar upload")
                        .attr("x", 80)
                        .attr("height", 12)
                        .attr("width", function(d) {
                            return x(d);
                        });

    tr.selectAll("g.upload-bar-container")
        .append("text")
            .attr("y", 9)
            .attr("x", 0)
            .text(function(d) {
                return d + " Mbps";
            });


    //alert count column
    tr.selectAll("td.alert-count-cell")
        .data(function(d) { return alertKey.map(function(k) { return d[k] }); }).enter()
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
                            return xAlerts(d);
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

    
    function goToAgent(agentId, alertCount, status) {
        window.location.href = "?view=view_agent&id=" + agentId +"&status=" + status + "&alert_count=" + alertCount;
    }
}