/**
 * CMDB impact graph renderer — org-chart-style tree layout (vanilla SVG + positioned nodes).
 */
(function (global) {
    'use strict';

    function buildChildrenMap(edges) {
        var childrenMap = {};
        edges.forEach(function (e) {
            var parent = parseInt(e.parent_ci_id, 10);
            var child = parseInt(e.child_ci_id, 10);
            if (!parent || !child) {
                return;
            }
            if (!childrenMap[parent]) {
                childrenMap[parent] = [];
            }
            childrenMap[parent].push(child);
        });
        return childrenMap;
    }

    function calculateWidth(node, childrenMap) {
        var kids = childrenMap[node.id] || [];
        if (!kids.length) {
            return 1;
        }
        return kids.reduce(function (acc, childId) {
            return acc + calculateWidth({ id: childId }, childrenMap);
        }, 0);
    }

    function layoutTree(rootId, nodes, edges, nodeWidth, nodeHeight, horizontalGap, verticalGap) {
        var nodeMap = {};
        nodes.forEach(function (n) {
            nodeMap[parseInt(n.id, 10)] = n;
        });
        var childrenMap = buildChildrenMap(edges);
        var positions = {};
        var roots = [rootId];

        function traverse(nodeId, depth, startX) {
            var widthUnits = calculateWidth({ id: nodeId }, childrenMap);
            var x = startX + (widthUnits * (nodeWidth + horizontalGap)) / 2 - nodeWidth / 2;
            var y = depth * (nodeHeight + verticalGap) + 40;
            positions[nodeId] = { x: x, y: y };

            var childIds = childrenMap[nodeId] || [];
            var childStart = startX;
            childIds.forEach(function (childId) {
                var childWidth = calculateWidth({ id: childId }, childrenMap);
                traverse(childId, depth + 1, childStart);
                childStart += childWidth * (nodeWidth + horizontalGap);
            });
        }

        var startX = 50;
        roots.forEach(function (root) {
            var width = calculateWidth({ id: root }, childrenMap) * (nodeWidth + horizontalGap);
            traverse(root, 0, startX);
            startX += width;
        });

        // Center parents over children (org-chart refine pass).
        Object.keys(childrenMap).forEach(function (parentId) {
            var kids = childrenMap[parentId];
            if (!kids.length || !positions[parentId]) {
                return;
            }
            var first = positions[kids[0]];
            var last = positions[kids[kids.length - 1]];
            if (first && last) {
                positions[parentId].x = (first.x + last.x) / 2;
            }
        });

        return positions;
    }

    function renderCmdbImpactGraph(options) {
        var graph = options.graph || {};
        var nodes = graph.nodes || [];
        var edges = graph.edges || [];
        var rootId = parseInt(graph.root_id || 0, 10);
        var viewport = options.viewportEl;
        var nodesHost = options.nodesEl;
        var svg = options.svgEl;
        if (!viewport || !nodesHost || !svg || !rootId || !nodes.length) {
            return;
        }

        var mini = !!options.mini;
        var nodeW = mini ? 140 : 200;
        var nodeH = mini ? 44 : 56;
        var gapX = mini ? 24 : 48;
        var gapY = mini ? 60 : 90;
        var viewUrl = options.viewUrl || 'view.php?id=';

        nodesHost.innerHTML = '';
        while (svg.firstChild) {
            svg.removeChild(svg.firstChild);
        }

        var positions = layoutTree(rootId, nodes, edges, nodeW, nodeH, gapX, gapY);

        nodes.forEach(function (n) {
            var id = parseInt(n.id, 10);
            var pos = positions[id];
            if (!pos) {
                return;
            }
            var el = document.createElement('a');
            el.href = viewUrl + id;
            el.className = 'org-node card';
            el.style.cssText = 'position:absolute;width:' + nodeW + 'px;padding:' + (mini ? '4px' : '8px') + ';text-align:center;left:' + pos.x + 'px;top:' + pos.y + 'px;';
            if (id === rootId) {
                el.style.border = '2px solid #0d6efd';
            }
            var icon = n.ci_type_icon || '';
            var name = n.name || '';
            var typeName = n.ci_type_name || '';
            el.innerHTML = '<div>' + icon + '</div><strong>' + name + '</strong>' +
                (mini ? '' : '<div style="font-size:12px;opacity:.8;">' + typeName + '</div>');
            nodesHost.appendChild(el);
        });

        edges.forEach(function (e) {
            var p = positions[parseInt(e.parent_ci_id, 10)];
            var c = positions[parseInt(e.child_ci_id, 10)];
            if (!p || !c) {
                return;
            }
            var x1 = p.x + nodeW / 2;
            var y1 = p.y + nodeH;
            var x2 = c.x + nodeW / 2;
            var y2 = c.y;
            var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            var mid = (y1 + y2) / 2;
            path.setAttribute('d', 'M' + x1 + ',' + y1 + ' C' + x1 + ',' + mid + ' ' + x2 + ',' + mid + ' ' + x2 + ',' + y2);
            path.setAttribute('stroke', '#6c757d');
            path.setAttribute('fill', 'none');
            path.setAttribute('stroke-width', mini ? '1.5' : '2');
            svg.appendChild(path);
        });

        if (!mini && options.enablePan) {
            var dragging = false;
            var sx = 0;
            var sy = 0;
            var sl = 0;
            var st = 0;
            viewport.addEventListener('mousedown', function (ev) {
                dragging = true;
                sx = ev.clientX;
                sy = ev.clientY;
                sl = viewport.scrollLeft;
                st = viewport.scrollTop;
            });
            window.addEventListener('mouseup', function () {
                dragging = false;
            });
            viewport.addEventListener('mousemove', function (ev) {
                if (!dragging) {
                    return;
                }
                viewport.scrollLeft = sl - (ev.clientX - sx);
                viewport.scrollTop = st - (ev.clientY - sy);
            });
        }
    }

    global.itmRenderCmdbImpactGraph = renderCmdbImpactGraph;
})(typeof window !== 'undefined' ? window : this);
