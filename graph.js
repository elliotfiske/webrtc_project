
function myGraph(el) {

    // Add and remove elements on the graph object
    this.addNode = function (id) {
        nodes.push({"id":id});
        update();
        update_click_handlers();
    }

    this.removeNode = function (id) {
        var i = 0;
        var n = findNode(id);
        while (i < links.length) {
            if ((links[i]['source'] === n)||(links[i]['target'] == n)) links.splice(i,1);
            else i++;
        }
        var index = findNodeIndex(id);
        if(index !== undefined) {
            nodes.splice(index, 1);
            update();
        }
    }

    this.addLink = function (sourceId, targetId) {
        var sourceNode = findNode(sourceId);
        var targetNode = findNode(targetId);

        if((sourceNode !== undefined) && (targetNode !== undefined)) {
            links.push({"source": sourceNode, "target": targetNode});
            update();
        }
    }

    var findNode = function (id) {
        for (var i=0; i < nodes.length; i++) {
            if (nodes[i].id === id)
                return nodes[i]
        };
    }

    var findNodeIndex = function (id) {
        for (var i=0; i < nodes.length; i++) {
            if (nodes[i].id === id)
                return i
        };
    }

    // set up the D3 visualisation in the specified element
    var w = $(el).innerWidth(),
        h = $(el).innerHeight();

    var vis = this.vis = d3.select(el).append("svg:svg")
        .attr("width", w)
        .attr("height", h);

    var force = d3.layout.force()
        .gravity(.05)
        .distance(100)
        .charge(-100)
        .size([w, h]);

    var nodes = force.nodes(),
        links = force.links();

    var update = function () {

        var link = vis.selectAll("line.link")
            .data(links, function(d) { return d.source.id + "-" + d.target.id; });

        link.enter().insert("line")
            .attr("class", "link");

        link.exit().remove();

        var node = vis.selectAll("g.node")
            .data(nodes, function(d) { return d.id;});

        var nodeEnter = node.enter().append("g")
            .attr("class", "node")
            .call(force.drag);

        nodeEnter.append("image")
            .attr("class", "circle")
            .attr("xlink:href", "https://github.com/favicon.ico")
            .attr("x", "-16px")
            .attr("y", "-16px")
            .attr("width", "32px")
            .attr("height", "32px");

        nodeEnter.append("text")
            .attr("class", "nodetext")
            .attr("dx", 18)
            .attr("dy", ".75em")
            .text(function(d) {return d.id});

        nodeEnter.append("text")
            .attr("class", "handletext")
            .attr("id", function(d) {return d.id + "-handle"})
            .attr("dx", 18)
            .attr("dy", "-.1em")
            .text(function(d) {return "<Handle not set>"});

        node.exit().remove();

        force.on("tick", function() {
          link.attr("x1", function(d) { return d.source.x; })
              .attr("y1", function(d) { return d.source.y; })
              .attr("x2", function(d) { return d.target.x; })
              .attr("y2", function(d) { return d.target.y; });

          node.attr("transform", function(d) { return "translate(" + d.x + "," + d.y + ")"; });
        });

        // Restart the force layout.
        force.start();
    }

    // Make it all go
    update();
}

graph = new myGraph("#graph");

// You can click nodes to have stuff happen!
$(document).ready(function() {
    update_click_handlers();
});

var GRAPH_CLICK_DELAY = 300, graph_node_clicks = 0, graph_node_timer = null;
function update_click_handlers() {
    // $(".node").on("click", function(e){
    //     graph_node_clicks++;  //count graph_node_clicks
    //
    //     if(graph_node_clicks === 1) {
    //         var that = $(this);
    //
    //         graph_node_timer = setTimeout(function() {
    //             graph_node_clicks = 0;             //after action performed, reset counter
    //             ask_for_sound(that.find("text").html(), false);
    //         }, GRAPH_CLICK_DELAY);
    //
    //     } else {
    //         clearTimeout(graph_node_timer);    //prevent single-click action
    //         graph_node_clicks = 0;             //after action performed, reset counter
    //         ask_for_sound($(this).find("text").html(), true);
    //     }
    //
    // })
    // .on("dblclick", function(e){
    //     e.preventDefault();  //cancel system double-click event
    // });
    $(".node").on("click", function(e) {
        ask_for_sound($(this).find("text").html(), true);
    }
}

function update_handle(other_id, new_handle) {
    $("#" + other_id + "-handle").html(new_handle);
}
