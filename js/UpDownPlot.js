/**
 * Created by Allison on 10/12/2016.
 */



function UpDownAreaGraph(location, width, jsonFile, timeWindow) {
    
    console.log(timeWindow);
    var margin = {top: 20, right: 80, bottom: 20, left: 80},
        height = 200 - margin.top - margin.bottom;
    
    width = width - margin.left - margin.right;
    
    var formatDate = d3.time.format("%m/%d/%Y %H:%M");
    
    var x = d3.time.scale().range([0, width]);
    var y = d3.scale.linear().range([height, 0]);
    var y2 = d3.scale.linear().range([height, 0]);
    
    var numberOfTicks = 6;
    
    var xAxis = d3.svg.axis()
        .scale(x)
        .orient("bottom");
    
    var yAxis = d3.svg.axis()
        .scale(y)
        .ticks(numberOfTicks)
        .orient("left");
    
    var yAxis2 = d3.svg.axis()
        .scale(y2)
        .ticks(numberOfTicks)
        .orient("right");
    
    var yAxisRight = d3.svg.axis()
        .scale(y)
        .ticks(numberOfTicks)
        .tickSize(width, 0)
        .tickFormat("")
        .orient("left");
    
    //down line
    var line = d3.svg.line()
        .x(function(d) { return x(d.ts); })
        .y(function(d) { return y(d.down); });
    
    //up line
    var line2 = d3.svg.line()
        .x(function(d) { return x(d.ts); })
        .y(function(d) { return y(d.up); });
    
    var tip = d3.tip()
        .attr('class', 'd3-tip')
        .offset([-10, 0])
        .html(function(d) {
            return  "<h6>" + formatDate(new Date(d.ts)) + "</h6>" +
                "<span class='tip-label'>Download:</span> <strong><span style='color:white'>" + d.down + " Mbps</span></strong><br />" +
                "<span class='tip-label'>Upload:</span> <strong><span style='color:white'>" + d.up + " Mbps</span></strong>";
        });
    
    var svg = d3.select(location[0]).append("svg")
        .attr("class", "nb-graph sched-graph sched-historical-graph speedtest-historical-up-down")
        .attr("width", width + margin.left + margin.right)
        .attr("height", height + margin.top + margin.bottom)
        .append("g")
        .attr("transform", "translate(" + margin.left + "," + margin.top + ")");
    
    svg.call(tip);

    var data = JSON.parse(jsonFile);
        
        x.domain(d3.extent(data, function(d) { return d.ts; }));
        var upperBound = Math.ceil((1.25 * d3.max(data, function(d) { return Math.max(d.down, d.up); }))/2) * 2;
        y.domain([0, upperBound]);
        y2.domain([0, upperBound]);
        
        svg.append("g")
            .attr("class", "y axis grid-line")
            .call(yAxisRight)
            .attr("transform", "translate(" + width + ", 0)");
        
        svg.append("g")
            .attr("class", "x axis")
            .attr("transform", "translate(0," + height + ")")
            .call(xAxis);
        
        //down axis
        svg.append("g")
            .attr("class", "y axis downl-axis")
            .call(yAxis)
            .append("text")
            .attr("transform", "rotate(-90)")
            .attr("y", 0)
            .attr("dy", "-60")
            .attr("class", "axis-label")
            .text("Download (Mbps)");
        
        //up axis
        svg.append("g")
            .attr("class", "y axis upl-axis")
            .call(yAxis2)
            .attr("transform", "translate(" + width +", 0)")
            .append("text")
            .attr("transform", "rotate(90)")
            .attr("y", 0)
            .attr("dy", "-60")
            .attr("class", "axis-label right-axis")
            .text("Upload (Mbps)");
        
        //down line
        svg.append("path")
            .datum(data)
            .attr("class", "line downl-line")
            .attr("d", line);
        
        //up line
        svg.append("path")
            .datum(data)
            .attr("class", "line upl-line")
            .attr("d", line2);
        
        svg.append("line")
            .attr("class", "visual-line")
            .attr("x1", 0)
            .attr("y1", height)
            .attr("x2", width)
            .attr("y2", height);
        
        svg.append("line")
            .attr("class", "visual-line")
            .attr("x1", 0)
            .attr("y1", 0)
            .attr("x2", 0)
            .attr("y2", height);
        
        svg.append("line")
            .attr("class", "visual-line")
            .attr("x1", width)
            .attr("y1", 0)
            .attr("x2", width)
            .attr("y2", height);
        
        svg.append("line")
            .attr("class", "visual-line secondary")
            .attr("x1", 0)
            .attr("y1", 0)
            .attr("x2", width)
            .attr("y2", 0);

    if(timeWindow < 604800000) {

        //down circle
        svg.selectAll("dot")
            .data(data)
            .enter().append("circle")
            .attr("r", 3.5)
            .attr("cx", function (d) {
                return x(d.ts);
            })
            .attr("cy", function (d) {
                return y(d.down);
            })
            .attr("class", "circle downl-circle")
            .on('mouseover', tip.show)
            .on('mouseout', tip.hide);

        //up circle
        svg.selectAll("dot")
            .data(data)
            .enter().append("circle")
            .attr("r", 3.5)
            .attr("cx", function (d) {
                return x(d.ts);
            })
            .attr("cy", function (d) {
                return y(d.up);
            })
            .attr("class", "circle upl-circle")
            .on('mouseover', tip.show)
            .on('mouseout', tip.hide);
    }
}