
function BarGraph(location, width, dataObj, graphData) {

	var margin = {top: 20, right: 80, bottom: 80, left: 80},
		height = 260 - margin.top - margin.bottom;
    
    width = width - margin.left - margin.right;    
	
	var x = d3.scale.ordinal().rangeRoundBands([0, width], 0.5);	
	var y = d3.scale.linear().range([height, 0]);
    
    var numberOfTicks = 6;
	
	var xAxis = d3.svg.axis()
		.scale(x)
		.orient("bottom");
	
	var yAxis = d3.svg.axis()
		.scale(y)
        .ticks(numberOfTicks)
		.orient("left");
    
    var yAxisRight = d3.svg.axis()
		.scale(y)
        .ticks(numberOfTicks)
        .tickSize(width, 0)
        .tickFormat("")
		.orient("left");
    
    var tip = d3.tip()
        .attr('class', 'd3-tip')
        .offset([-10, 0])
        .html(function(d) {
            var tooltipTxt = "<h6>" + d.agent_name + "</h6>";
            return  tooltipTxt +
                "<span class='tip-label'>" + graphData.label + ":</span> <strong><span style='color:white'>" + d[graphData.variable_accessor] + " " + graphData.unit + "</span></strong>";
        });
		
	var svg = d3.select(location[0]).append("svg")
        .attr("class", "nb-graph sched-graph sched-bar-graph")
		.attr("width", width + margin.left + margin.right)
		.attr("height", height + margin.top + margin.bottom)
	  	.append("g")
		    .attr("transform", "translate(" + margin.left + "," + margin.top + ")");
		
	svg.call(tip);

	var data = JSON.parse(dataObj);
		
	x.domain(data.map(function(d) {
		if(d.agent_name.length < 16){
			d.agent_name_label = d.agent_name;
			return d.agent_name_label;
		} else {
			d.agent_name_label = d.agent_name.substring(0, 13) + "...";
			return d.agent_name_label;
		}
	}));
	y.domain([0, (1.25 * d3.max(data, function(d) { return d[graphData.variable_accessor]; }) / 2) * 2]);

	svg.append("g")
		.attr("class", "y axis grid-line")
		.call(yAxisRight)
		.attr("transform", "translate(" + width + ", 0)");

	svg.append("g")
		.attr("class", "y axis " + graphData.class + "-axis")
		.call(yAxis)
		.append("text")
			.attr("transform", "rotate(-90)")
			.attr("y", 0)
			.attr("dy", "-60")
			.attr("class", "axis-label")
			.text(function(){
				return graphData.label + " (" + graphData.unit + ")";
			});

	svg.append("g")
		.attr("class", "x axis")
		.attr("transform", "translate(0," + height + ")")
		.call(xAxis)
		.selectAll("text")
			.style("text-anchor", "end")
			.attr("dx", "-.8em")
			.attr("dy", ".15em")
			.attr("transform", "rotate(-45)" );

	var bar = svg.selectAll(".bar")
		.data(data)
		.enter().append("g")
		.attr("class", "bar")
		.attr("x", function(d) { return x(d.agent_name); });

	bar.append("rect")
		.attr("class", graphData.class + "-bar")
		.attr("x", function(d) { return x(d.agent_name_label); })
		.attr("width", x.rangeBand())
		.attr("y", function(d) { return y(d[graphData.variable_accessor]); })
		.attr("height", function(d) { return height - y(d[graphData.variable_accessor]); })
		.on('mouseover', tip.show)
		.on('mouseout', tip.hide);

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
		.attr("class", "visual-line secondary")
		.attr("x1", width)
		.attr("y1", 0)
		.attr("x2", width)
		.attr("y2", height);
}