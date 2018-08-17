/**
 * Created by Allison on 10/12/2016.
 */


function AlertLaneGraph(container, width, jsonFile) {
    //var self = this;
    
    var margin = {top: 20, right: 80, bottom: 20, left: 80},
        height = 400 - margin.top - margin.bottom;
    
    width = width - margin.left - margin.right;
    
    var formatDate = d3.time.format("%b %e %I:%M %p");
    
    var yValues;
    var yBandHeight;
    var yLaneHeight;
    
    var x = d3.time.scale().range([0, width]);
    var xOrdinal = d3.scale.ordinal().rangeBands([0, width]);
    var y = d3.scale.ordinal();
    
    var svg;

    var data = JSON.parse(jsonFile);
    
    function sortFunction(a,b){
        var dateA = new Date(a.ts).getTime();
        var dateB = new Date(b.ts).getTime();

        return dateA > dateB ? 1 : -1;
    }

    data.sort(sortFunction);
    data.forEach(function(d) { d.ts = new Date(d.ts); });

    yValues = d3.keys(data[0]['per_item_counts']);

    yBandHeight = height / yValues.length;

    if (yBandHeight <= 20) {
        yLaneHeight = yBandHeight/2;
    } else {
        yLaneHeight = yBandHeight/3;
    }

    x.domain(d3.extent(data, function(d) { return d.ts; }));
    xOrdinal.domain(data.map(function(d) { return d.ts; }));
    y.domain(yValues).rangeRoundBands([0, height]);

    var xAxis = d3.svg.axis()
        .scale(x)
        .orient("bottom")
        .ticks(4);

    var yAxis = d3.svg.axis()
        .scale(y)
        .orient("left")
        .tickSize(0)
        .tickPadding(8);

    var tip = d3.tip()
        .attr('class', 'd3-tip')
        .offset([-10, 0])
        .html(function(d, value) {
            if (d['per_item_counts'][value]['counts']['4'] === null){
                d['per_item_counts'][value]['counts']['4'] = 0;
            }
            if (d['per_item_counts'][value]['counts']['1'] === null){
                d['per_item_counts'][value]['counts']['1'] = 0;
            }
            return  "<h6 class='tip-heading'>" + value + "</h6>" +
                    "<span class='tip-label'>"+formatDate(d.ts)+"</span><br />" +
                    "<span class='tip-label'>Warning: </span><strong><span style='color:white'>" + d['per_item_counts'][value]['counts']['4'] + " Alerts</span></strong><br />" +
                    "<span class='tip-label'>Failing: </span><strong><span style='color:white'>" + d['per_item_counts'][value]['counts']['1'] + " Alerts</span></strong>";
        });

    svg = d3.select(container[0]).append("svg")
        .attr("width", width + margin.left + margin.right)
        .attr("height", height + margin.top + margin.bottom)
        .attr("class", "nb-graph wifi-band-graph")
        .append("g")
        .attr("transform", "translate(" + margin.left + "," + margin.top + ")");

    svg.append("defs").append("clipPath")
        .attr("id", "clip-path")
        .append("rect")
        .attr("width", width)
        .attr("height", height);

    var dataContainer = svg.append("g")
        .attr("clip-path", "url(#clip-path)");

    svg.append("g")
        .attr("class", "x axis")
        .attr("transform", "translate(0," + height + ")")
        .call(xAxis);

    svg.append("g")
        .attr("class", "y axis")
        .call(yAxis);

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
        .attr("class", "visual-line secondary")
        .attr("x1", 0)
        .attr("y1", 0)
        .attr("x2", width)
        .attr("y2", 0);

    svg.append("line")
        .attr("class", "visual-line")
        .attr("x1", width)
        .attr("y1", 0)
        .attr("x2", width)
        .attr("y2", height);

    $.each(yValues, function (i, value) {

        dataContainer.call(tip);

        dataContainer.selectAll("band-rect")
            .data(data)
            .enter().append("rect")
            .attr("class", function(d){
                if (d['per_item_counts'][value]['counts']['4'] > 0 && (d['per_item_counts'][value]['counts']['1'] === 0 || d['per_item_counts'][value]['counts']['1'] === null)){
                    return "band-rect warning";
                } else if (d['per_item_counts'][value]['counts']['1'] > 0){
                    return "band-rect fail";
                } else if ((d['per_item_counts'][value]['counts']['4'] === 0 || d['per_item_counts'][value]['counts']['4'] === null) && (d['per_item_counts'][value]['counts']['1'] === 0 || d['per_item_counts'][value]['counts']['1'] === null)){
                    return "band-rect success";
                } else {
                    return "band-rect unknown";
                }
            })
            .attr("width", xOrdinal.rangeBand())
            .attr("height", yLaneHeight)
            .attr("x", function(d) {
                return xOrdinal(d.ts);
            })
            .attr("y", ((yBandHeight * i) + yLaneHeight))
            .on('mouseover', function(d){
                tip.show(d, value);
            })
            .on('mouseout', function(d){
                tip.hide(d, value);
            });
    });
}