/**
 * Component for handling geo objects on the map
 */
class MapGeoObjectManager {
    constructor(map) {
        this.map = map;
        this.leafletMap = map.getLeafletMap();
        this.geoObjectLayers = {};
        this.tempLayer = null;
        this.drawingMode = false;
        this.drawingType = null;
        this.drawingCallback = null;

        // Handlers for different object types
        this.pointClickHandler = this.handlePointClick.bind(this);
        this.circleFirstClickHandler = this.handleCircleFirstClick.bind(this);
        this.polygonClickHandler = this.handlePolygonClick.bind(this);
        this.lineClickHandler = this.handleLineClick.bind(this);

        // Bound handlers for double click events
        this.finishPolygonHandler = this.finishPolygon.bind(this);
        this.finishLineHandler = this.finishLine.bind(this);

        // Temporary data for drawing
        this.tempPoints = [];
        this.tempCircleCenter = null;

        // Edit mode data
        this.editMode = false;
        this.editPointMarkers = []; // Markers for editable points

        // Side filtering
        this.visibleSides = new Set(); // Empty set means all sides are visible
        this.sideFilterControl = null;
    }

    /**
     * Load all geo objects for a map
     */
    loadGeoObjects(mapId) {
        // Check if the argument is a number or string
        if (
            !mapId ||
            (typeof mapId !== 'number' && typeof mapId !== 'string')
        ) {
            return;
        }

        // Clear existing objects
        this.clearGeoObjects();

        // Fetch objects from API
        fetch(`/geo-object/by-map/${mapId}`)
            .then((response) => {
                return response.json();
            })
            .then((data) => {
                if (data.success) {
                    this.renderGeoObjects(data.objects);

                    // Create legend for sides
                    this.createSidesLegend(data.objects);

                    // Also update the objects list in the sidebar
                    if (
                        window.geoObjectForm &&
                        window.geoObjectForm.updateObjectsList
                    ) {
                        window.geoObjectForm.updateObjectsList(data.objects);
                    }
                }
            })
            .catch((error) => {
                // Silent error handling
            });
    }

    /**
     * Clear all geo objects from the map
     */
    clearGeoObjects() {
        // Check total layers on map before clearing
        const allLayers = [];
        this.leafletMap.eachLayer(function (layer) {
            allLayers.push(layer);
        });

        Object.values(this.geoObjectLayers).forEach((item, index) => {
            if (item.layer) {
                this.leafletMap.removeLayer(item.layer);
            }
        });

        this.geoObjectLayers = {};

        // FORCE REMOVE ALL GEO OBJECTS - brute force approach
        this.leafletMap.eachLayer((layer) => {
            // Remove everything except the tile layer
            if (
                layer instanceof L.Marker ||
                layer instanceof L.Polygon ||
                layer instanceof L.Circle ||
                layer instanceof L.Polyline
            ) {
                this.leafletMap.removeLayer(layer);
            }
        });

        // Check total layers on map after clearing
        const allLayersAfter = [];
        this.leafletMap.eachLayer(function (layer) {
            allLayersAfter.push(layer);
        });
    }

    /**
     * Render geo objects on the map
     */
    renderGeoObjects(objects) {
        if (!Array.isArray(objects)) {
            return;
        }

        objects.forEach((object, index) => {
            try {
                // Check and process JSON string
                const geoJson =
                    typeof object.geoJson === 'string'
                        ? JSON.parse(object.geoJson)
                        : object.geoJson;

                // Create a layer depending on the type
                let layer;
                const objectType = object.type.toLowerCase(); // Normalize to lowercase
                switch (objectType) {
                    case 'point':
                        layer = this.createPointLayer(geoJson, object);
                        break;
                    case 'polygon':
                        layer = this.createPolygonLayer(geoJson, object);
                        break;
                    case 'circle':
                        layer = this.createCircleLayer(geoJson, object);
                        break;
                    case 'line':
                    case 'linestring':
                        layer = this.createLineLayer(geoJson, object);
                        break;
                    default:
                        return;
                }

                if (layer) {
                    // Store the layer with object ID FIRST
                    this.geoObjectLayers[object.id] = {
                        layer: layer,
                        type: object.type,
                        data: object,
                    };

                    // Add a popup with object info
                    // For LayerGroups, bind popup to each layer
                    if (layer instanceof L.LayerGroup) {
                        const popupContent = this.createPopupContent(object);
                        layer.eachLayer((sublayer) => {
                            sublayer.bindPopup(popupContent);
                            // Remove any existing popupopen listeners to prevent duplicates
                            sublayer.off('popupopen');
                            sublayer.on('popupopen', () => {
                                // Use setTimeout to ensure popup DOM is ready
                                setTimeout(() => {
                                    this.attachPopupEventListeners(object);
                                }, 10);
                            });
                        });
                    } else {
                        layer.bindPopup(this.createPopupContent(object));
                        // Remove any existing popupopen listeners to prevent duplicates
                        layer.off('popupopen');
                        layer.on('popupopen', () => {
                            // Use setTimeout to ensure popup DOM is ready
                            setTimeout(() => {
                                this.attachPopupEventListeners(object);
                            }, 10);
                        });
                    }

                    // Add to map
                    layer.addTo(this.leafletMap);
                }
            } catch (error) {
                // Silent error handling
            }
        });
    }

    /**
     * Create a popup content for a geo object
     */
    createPopupContent(object) {
        let content = `<div class="geo-popup" data-object-id="${object.id}">`;

        // Add side information prominently at the top if available
        if (object.side && object.side.name) {
            content += `<div class="side-info mb-2">
                <span class="badge side-badge" style="
                    background-color: ${object.side.color || '#6c757d'}; 
                    color: white;
                    font-size: 0.9em;
                    padding: 0.4em 0.8em;
                    border-radius: 0.25rem;
                    font-weight: 600;
                    text-shadow: 0 1px 2px rgba(0,0,0,0.2);
                ">
                    ${object.side.name}
                </span>
            </div>`;
        }

        content += `<h5>${object.title || 'Unnamed object'}</h5>`;

        if (object.description) {
            content += `<p>${object.description}</p>`;
        }

        if (object.ttl > 0) {
            // Format TTL for display
            let ttlDisplay;
            if (object.ttl >= 3600) {
                const hours = Math.floor(object.ttl / 3600);
                const minutes = Math.floor((object.ttl % 3600) / 60);
                ttlDisplay =
                    hours + 'h' + (minutes > 0 ? ' ' + minutes + 'm' : '');
            } else if (object.ttl >= 60) {
                const minutes = Math.floor(object.ttl / 60);
                ttlDisplay = minutes + 'm';
            } else {
                ttlDisplay = object.ttl + 's';
            }

            content += `<div class="ttl-info">
                <small class="text-muted">
                    <i class="fas fa-clock"></i> TTL: ${ttlDisplay}
                </small>
            </div>`;
        }

        content += `
            <div class="popup-actions mt-2">
                <button class="btn btn-sm btn-primary popup-edit-btn" data-object-id="${object.id}">
                    <i class="fas fa-edit"></i> Edit
                </button>
                <button class="btn btn-sm btn-danger popup-delete-btn" data-object-id="${object.id}">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        </div>`;

        return content;
    }

    /**
     * Create a layer for a point geo object
     */
    createPointLayer(geoJson, objectData = null) {
        if (!geoJson || geoJson.type !== 'Point' || !geoJson.coordinates) {
            return null;
        }

        const latlng = L.latLng(geoJson.coordinates[1], geoJson.coordinates[0]);

        // Use custom icon if available
        if (objectData && objectData.iconUrl) {
            const customIcon = L.icon({
                iconUrl: objectData.iconUrl,
                iconSize: [32, 32],
                iconAnchor: [16, 32],
                popupAnchor: [0, -32],
            });

            return L.marker(latlng, { icon: customIcon });
        }

        // Create colored marker based on side
        if (objectData && objectData.side && objectData.side.color) {
            const sideColor = objectData.side.color;
            const coloredIcon = L.divIcon({
                className: 'colored-marker',
                html: `<div style="
                    background-color: ${sideColor};
                    width: 20px;
                    height: 20px;
                    border-radius: 50%;
                    border: 2px solid white;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.3);
                "></div>`,
                iconSize: [20, 20],
                iconAnchor: [10, 10],
                popupAnchor: [0, -10],
            });

            return L.marker(latlng, { icon: coloredIcon });
        }

        // Use default marker
        return L.marker(latlng);
    }

    /**
     * Create a layer for a polygon geo object
     */
    createPolygonLayer(geoJson, objectData = null) {
        if (
            !geoJson ||
            geoJson.type !== 'Polygon' ||
            !geoJson.coordinates ||
            !geoJson.coordinates[0]
        ) {
            return null;
        }

        const points = geoJson.coordinates[0].map((coord) =>
            L.latLng(coord[1], coord[0])
        );

        // Use side color if available
        const sideColor =
            objectData && objectData.side && objectData.side.color
                ? objectData.side.color
                : 'blue';

        const polygon = L.polygon(points, {
            color: sideColor,
            weight: 2,
            fillOpacity: 0.3,
            fillColor: sideColor,
        });

        // Add custom icon or colored marker if available
        if (objectData && objectData.iconUrl) {
            // Calculate polygon center
            const bounds = polygon.getBounds();
            const center = bounds.getCenter();

            const customIcon = L.icon({
                iconUrl: objectData.iconUrl,
                iconSize: [24, 24],
                iconAnchor: [12, 12],
                popupAnchor: [0, -12],
            });

            const iconMarker = L.marker(center, { icon: customIcon });

            // Create a layer group with polygon and icon
            return L.layerGroup([polygon, iconMarker]);
        } else if (objectData && objectData.side && objectData.side.color) {
            // Add colored marker at center
            const bounds = polygon.getBounds();
            const center = bounds.getCenter();

            const coloredIcon = L.divIcon({
                className: 'colored-marker',
                html: `<div style="
                    background-color: ${sideColor};
                    width: 16px;
                    height: 16px;
                    border-radius: 50%;
                    border: 2px solid white;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.3);
                "></div>`,
                iconSize: [16, 16],
                iconAnchor: [8, 8],
                popupAnchor: [0, -8],
            });

            const centerMarker = L.marker(center, { icon: coloredIcon });

            // Create a layer group with polygon and center marker
            return L.layerGroup([polygon, centerMarker]);
        }

        return polygon;
    }

    /**
     * Create a layer for a circle geo object
     */
    createCircleLayer(geoJson, objectData = null) {
        if (
            !geoJson ||
            geoJson.type !== 'Circle' ||
            !geoJson.coordinates ||
            !geoJson.radius
        ) {
            return null;
        }

        const center = L.latLng(geoJson.coordinates[1], geoJson.coordinates[0]);

        // Use side color if available
        const sideColor =
            objectData && objectData.side && objectData.side.color
                ? objectData.side.color
                : 'red';

        const circle = L.circle(center, {
            radius: geoJson.radius,
            color: sideColor,
            weight: 2,
            fillOpacity: 0.2,
            fillColor: sideColor,
        });

        // Add custom icon or colored marker if available
        if (objectData && objectData.iconUrl) {
            const customIcon = L.icon({
                iconUrl: objectData.iconUrl,
                iconSize: [24, 24],
                iconAnchor: [12, 12],
                popupAnchor: [0, -12],
            });

            const iconMarker = L.marker(center, { icon: customIcon });

            // Create a layer group with circle and icon
            return L.layerGroup([circle, iconMarker]);
        } else if (objectData && objectData.side && objectData.side.color) {
            // Add colored marker at center
            const coloredIcon = L.divIcon({
                className: 'colored-marker',
                html: `<div style="
                    background-color: ${sideColor};
                    width: 16px;
                    height: 16px;
                    border-radius: 50%;
                    border: 2px solid white;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.3);
                "></div>`,
                iconSize: [16, 16],
                iconAnchor: [8, 8],
                popupAnchor: [0, -8],
            });

            const centerMarker = L.marker(center, { icon: coloredIcon });

            // Create a layer group with circle and center marker
            return L.layerGroup([circle, centerMarker]);
        }

        return circle;
    }

    /**
     * Create a layer for a line geo object
     */
    createLineLayer(geoJson, objectData = null) {
        if (!geoJson || geoJson.type !== 'LineString' || !geoJson.coordinates) {
            return null;
        }

        const points = geoJson.coordinates.map((coord) =>
            L.latLng(coord[1], coord[0])
        );

        // Use side color if available
        const sideColor =
            objectData && objectData.side && objectData.side.color
                ? objectData.side.color
                : 'green';

        const line = L.polyline(points, {
            color: sideColor,
            weight: 3,
        });

        // Add custom icon or colored marker if available
        if (objectData && objectData.iconUrl) {
            // Calculate middle point of the line
            const middleIndex = Math.floor(points.length / 2);
            const center = points[middleIndex];

            const customIcon = L.icon({
                iconUrl: objectData.iconUrl,
                iconSize: [24, 24],
                iconAnchor: [12, 12],
                popupAnchor: [0, -12],
            });

            const iconMarker = L.marker(center, { icon: customIcon });

            // Create a layer group with line and icon
            return L.layerGroup([line, iconMarker]);
        } else if (objectData && objectData.side && objectData.side.color) {
            // Add colored marker at middle
            const middleIndex = Math.floor(points.length / 2);
            const center = points[middleIndex];

            const coloredIcon = L.divIcon({
                className: 'colored-marker',
                html: `<div style="
                    background-color: ${sideColor};
                    width: 16px;
                    height: 16px;
                    border-radius: 50%;
                    border: 2px solid white;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.3);
                "></div>`,
                iconSize: [16, 16],
                iconAnchor: [8, 8],
                popupAnchor: [0, -8],
            });

            const centerMarker = L.marker(center, { icon: coloredIcon });

            // Create a layer group with line and center marker
            return L.layerGroup([line, centerMarker]);
        }

        return line;
    }

    /**
     * Display a specific GeoJSON object on the map (for editing)
     */
    showGeoJsonObject(geoJson, type, objectData = null) {
        this.clearTempObjects();

        if (!geoJson || !type) {
            return;
        }

        let layer;
        const normalizedType = type.toLowerCase(); // Normalize to lowercase

        // During editing, don't use icons to avoid LayerGroup complexity
        // Use simple geometry for better editing experience
        switch (normalizedType) {
            case 'point':
                // For points, still show with icon if available
                layer = this.createPointLayer(geoJson, objectData);
                break;
            case 'polygon':
                // Use simple polygon without icon for editing
                layer = this.createPolygonLayer(geoJson, null);
                break;
            case 'circle':
                // Use simple circle without icon for editing
                layer = this.createCircleLayer(geoJson, null);
                break;
            case 'line':
            case 'linestring':
                // Use simple line without icon for editing
                layer = this.createLineLayer(geoJson, null);
                break;
            default:
                return;
        }

        if (layer) {
            this.tempLayer = layer;
            this.tempLayer.addTo(this.leafletMap);

            // Fit bounds for non-point objects
            if (normalizedType !== 'point' && layer.getBounds) {
                this.leafletMap.fitBounds(layer.getBounds());
            } else if (normalizedType === 'point' && layer.getLatLng) {
                this.leafletMap.setView(layer.getLatLng(), 15);
            }
        }
    }

    /**
     * Clear temporary objects used during drawing
     */
    clearTempObjects() {
        if (this.tempLayer) {
            this.leafletMap.removeLayer(this.tempLayer);
            this.tempLayer = null;
        }

        // Don't clear edit point markers in edit mode - they should persist
        // Edit markers are cleared separately via clearEditPointMarkers()
    }

    /**
     * Enable drawing mode for a specific type
     */
    enableDrawingMode(type, callback) {
        // Disable current drawing mode if active
        this.disableDrawingMode();

        this.drawingMode = true;
        this.drawingType = type;
        this.drawingCallback = callback;
        this.tempPoints = [];
        this.tempCircleCenter = null;

        // Show instruction cursor
        if (this.map && typeof this.map.setDrawingCursor === 'function') {
            this.map.setDrawingCursor(type);
        } else {
            // Backup variant if the method is not defined in map
            this.setDrawingCursor(type);
        }

        // Add event handlers depending on the type
        const normalizedType = type.toLowerCase(); // Normalize to lowercase

        switch (normalizedType) {
            case 'point':
                this.leafletMap.on('click', this.pointClickHandler);
                break;
            case 'circle':
                this.leafletMap.on('click', this.circleFirstClickHandler);
                break;
            case 'polygon':
                this.leafletMap.on('click', this.polygonClickHandler);
                // Keep double click for users who want to use it, but it's not required
                this.leafletMap.on('dblclick', this.finishPolygonHandler);
                break;
            case 'line':
            case 'linestring':
                this.leafletMap.on('click', this.lineClickHandler);
                // Keep double click for users who want to use it, but it's not required
                this.leafletMap.on('dblclick', this.finishLineHandler);
                break;
        }
    }

    /**
     * Disable drawing mode
     */
    disableDrawingMode() {
        if (!this.drawingMode) return;

        // Remove event handlers
        this.leafletMap.off('click', this.pointClickHandler);
        this.leafletMap.off('click', this.circleFirstClickHandler);
        this.leafletMap.off('click', this.circleSecondClickHandler);
        this.leafletMap.off('click', this.polygonClickHandler);
        this.leafletMap.off('click', this.lineClickHandler);
        this.leafletMap.off('dblclick', this.finishPolygonHandler);
        this.leafletMap.off('dblclick', this.finishLineHandler);

        // Reset cursor
        if (this.map && typeof this.map.resetCursor === 'function') {
            this.map.resetCursor();
        } else {
            // Backup variant if the method is not defined in map
            this.resetCursor();
        }

        // Clear temp layer
        this.clearTempObjects();

        // Clear edit mode data and markers
        this.clearEditPointMarkers();
        this.editMode = false;

        // Reset state
        this.drawingMode = false;
        this.drawingType = null;
        this.drawingCallback = null;
        this.tempPoints = [];
        this.tempCircleCenter = null; // Reset circle center

        // Hide point counter if it exists
        this.hidePointCounter();
    }

    /**
     * Set cursor style for drawing mode
     */
    setDrawingCursor(type) {
        if (!this.leafletMap || !this.leafletMap.getContainer()) return;

        const container = this.leafletMap.getContainer();
        const normalizedType = type.toLowerCase(); // Normalize to lowercase

        switch (normalizedType) {
            case 'point':
                container.style.cursor = 'crosshair';
                break;
            case 'polygon':
            case 'line':
            case 'linestring':
                container.style.cursor = 'pointer';
                break;
            case 'circle':
                container.style.cursor = 'cell';
                break;
            default:
                container.style.cursor = 'default';
        }
    }

    /**
     * Reset cursor to default
     */
    resetCursor() {
        if (!this.leafletMap || !this.leafletMap.getContainer()) return;
        this.leafletMap.getContainer().style.cursor = 'default';
    }

    /**
     * Handle point drawing click
     */
    handlePointClick(e) {
        // In edit mode for points, replace the existing point
        if (
            this.editMode &&
            this.drawingType &&
            this.drawingType.toLowerCase() === 'point'
        ) {
            // Clear previous edit markers
            this.clearEditPointMarkers();

            // Update the point position
            const point = e.latlng;
            this.tempPoints = [point];

            // Create new editable marker
            this.createEditPointMarker(point, 0, 'point');

            // Update geometry callback
            this.updatePointGeometryCallback(point);

            return;
        }

        // Clear any previous temp objects
        this.clearTempObjects();

        // Create a marker at the clicked location with temporary styling
        const point = e.latlng;
        this.tempLayer = L.marker(point, {
            title: 'Click "Create" button to place this point',
            opacity: 0.7,
            icon: L.icon({
                iconUrl:
                    'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-orange.png',
                shadowUrl:
                    'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41],
            }),
        }).addTo(this.leafletMap);

        // Create GeoJSON
        const geoJson = {
            type: 'Point',
            coordinates: [point.lng, point.lat],
        };

        // Call the callback with the GeoJSON
        if (this.drawingCallback) {
            this.drawingCallback(geoJson);
        }

        // Don't automatically exit drawing mode for points - let the user see the marker
        // Drawing mode will be disabled when form is submitted or cancelled
    }

    /**
     * Handle first click for circle drawing
     */
    handleCircleFirstClick(e) {
        // In edit mode for circles, allow repositioning center
        if (
            this.editMode &&
            this.drawingType &&
            this.drawingType.toLowerCase() === 'circle'
        ) {
            // Clear previous edit markers
            this.clearEditPointMarkers();

            // Set new center but keep existing radius if available
            const existingRadius =
                this.tempLayer && this.tempLayer.getRadius
                    ? this.tempLayer.getRadius()
                    : 100; // Default 100m radius

            this.tempCircleCenter = e.latlng;
            this.tempPoints = [e.latlng];

            // Create new editable circle marker
            this.createEditCircleMarker(e.latlng, existingRadius);

            // Update visual and callback
            this.updateEditCircleVisual(existingRadius);
            this.updateCircleGeometryCallback(e.latlng, existingRadius);

            return;
        }

        // Store center point
        this.tempCircleCenter = e.latlng;
        this.tempPoints = [e.latlng];

        // Create editable center marker instead of temporary marker
        this.clearTempObjects();
        this.clearEditPointMarkers(); // Clear any previous edit markers

        // Create center marker that can be dragged
        const centerMarker = L.marker(this.tempCircleCenter, {
            draggable: true,
            icon: L.icon({
                iconUrl:
                    'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-yellow.png',
                shadowUrl:
                    'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41],
            }),
            title: 'Circle center - click anywhere to set radius',
        }).addTo(this.leafletMap);

        // Handle center dragging
        centerMarker.on('dragend', (e) => {
            const newCenter = e.target.getLatLng();
            this.tempCircleCenter = newCenter;
            this.tempPoints[0] = newCenter;

            // If we already have a radius marker, update circle but keep radius marker position
            if (this.editPointMarkers.length > 1) {
                const radiusMarker = this.editPointMarkers[1];
                const currentRadius = newCenter.distanceTo(
                    radiusMarker.getLatLng()
                );

                // Update visual and callback
                this.updateEditCircleVisual(currentRadius);
                this.updateCircleGeometryCallback(newCenter, currentRadius);

                // Update counter
                this.updatePointCounter();
            }
        });

        this.editPointMarkers.push(centerMarker);

        // Change handler for second click
        this.leafletMap.off('click', this.circleFirstClickHandler);
        this.circleSecondClickHandler = this.handleCircleSecondClick.bind(this);
        this.leafletMap.on('click', this.circleSecondClickHandler);

        // Update counter to show circle creation mode
        this.updatePointCounter();
    }

    /**
     * Handle second click for circle drawing (radius)
     */
    handleCircleSecondClick(e) {
        if (!this.tempCircleCenter) return;

        // Calculate radius in meters
        const radius = this.tempCircleCenter.distanceTo(e.latlng);

        // Remove previous temporary circle (if any)
        this.clearTempObjects();

        // If we already have a radius marker, remove it
        if (this.editPointMarkers.length > 1) {
            this.leafletMap.removeLayer(this.editPointMarkers[1]);
            this.editPointMarkers.splice(1, 1);
        }

        // Create radius control marker
        const radiusMarker = L.marker(e.latlng, {
            draggable: true,
            icon: L.icon({
                iconUrl:
                    'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-violet.png',
                shadowUrl:
                    'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                iconSize: [20, 32],
                iconAnchor: [10, 32],
                popupAnchor: [1, -28],
                shadowSize: [32, 32],
            }),
            title: 'Drag to adjust circle radius',
        }).addTo(this.leafletMap);

        // Handle radius dragging
        radiusMarker.on('dragend', (e) => {
            const newRadius = this.tempCircleCenter.distanceTo(
                e.target.getLatLng()
            );

            // Update visual and callback
            this.updateEditCircleVisual(newRadius);
            this.updateCircleGeometryCallback(this.tempCircleCenter, newRadius);

            // Update counter
            this.updatePointCounter();
        });

        this.editPointMarkers.push(radiusMarker);

        // Create circle with temporary styling
        this.tempLayer = L.circle(this.tempCircleCenter, {
            radius: radius,
            color: 'red',
            weight: 2,
            fillColor: 'red',
            fillOpacity: 0.2,
            dashArray: '5, 5', // Dashed border to show it's temporary
            opacity: 0.7,
        }).addTo(this.leafletMap);

        // Add tooltip with radius information
        const radiusText =
            radius < 1000
                ? `${Math.round(radius)} meters`
                : `${(radius / 1000).toFixed(2)} km`;

        this.tempLayer
            .bindTooltip(
                `Radius: ${radiusText}<br>Drag markers to adjust or "Create" to place this circle`,
                {
                    permanent: false,
                    direction: 'top',
                }
            )
            .openTooltip();

        // Create GeoJSON
        const geoJson = {
            type: 'Circle',
            coordinates: [this.tempCircleCenter.lng, this.tempCircleCenter.lat],
            radius: radius,
        };

        // Call the callback with the GeoJSON
        if (this.drawingCallback) {
            this.drawingCallback(geoJson);
        }

        // Keep the click handler active so user can continue adjusting radius
        // Don't remove the click handler - let user click again to adjust radius
        // Drawing mode will be disabled when form is submitted or cancelled

        // Update counter to show circle creation with markers
        this.updatePointCounter();
    }

    /**
     * Handle clicks for polygon drawing
     */
    handlePolygonClick(e) {
        // Add point to the temp points array
        this.tempPoints.push(e.latlng);

        // Always create editable marker for better UX (both creation and edit mode)
        const newIndex = this.tempPoints.length - 1;
        this.createEditPointMarker(e.latlng, newIndex);

        // Update visual representation
        this.updateEditPolygonVisual();

        // Update geometry callback
        this.updateGeometryCallback();

        // Update point counter display
        this.updatePointCounter();
    }

    /**
     * Finish polygon manually (called from form button)
     */
    finishPolygonFromButton() {
        // Need at least 3 points for a polygon
        if (this.tempPoints.length < 3) {
            alert('Please add at least 3 points to create a polygon.');
            return false;
        }

        // Create polygon
        this.clearTempObjects();
        this.tempLayer = L.polygon(this.tempPoints, {
            color: 'blue',
            weight: 2,
            fillOpacity: 0.3,
        }).addTo(this.leafletMap);

        // Create GeoJSON
        const coordinates = [
            this.tempPoints.map((point) => [point.lng, point.lat]),
        ];
        // Close the polygon by adding the first point at the end
        coordinates[0].push([this.tempPoints[0].lng, this.tempPoints[0].lat]);

        const geoJson = {
            type: 'Polygon',
            coordinates: coordinates,
        };

        // Call the callback with the GeoJSON
        if (this.drawingCallback) {
            this.drawingCallback(geoJson);
        }

        return true;
    }

    /**
     * Handle clicks for line drawing
     */
    handleLineClick(e) {
        // Similar to polygon clicks
        this.tempPoints.push(e.latlng);

        // Always create editable marker for better UX (both creation and edit mode)
        const newIndex = this.tempPoints.length - 1;
        this.createEditPointMarker(e.latlng, newIndex);

        // Update visual representation
        this.updateEditLineVisual();

        // Update geometry callback
        this.updateGeometryCallback();

        // Update point counter display
        this.updatePointCounter();
    }

    /**
     * Finish line manually (called from form button)
     */
    finishLineFromButton() {
        // Need at least 2 points for a line
        if (this.tempPoints.length < 2) {
            alert('Please add at least 2 points to create a line.');
            return false;
        }

        this.clearTempObjects();
        this.tempLayer = L.polyline(this.tempPoints, {
            color: 'green',
            weight: 3,
        }).addTo(this.leafletMap);

        // Create GeoJSON
        const coordinates = this.tempPoints.map((point) => [
            point.lng,
            point.lat,
        ]);

        const geoJson = {
            type: 'LineString',
            coordinates: coordinates,
        };

        // Call the callback with the GeoJSON
        if (this.drawingCallback) {
            this.drawingCallback(geoJson);
        }

        return true;
    }

    /**
     * Update point counter display
     */
    updatePointCounter() {
        const mapContainer = this.leafletMap.getContainer();
        let counter = mapContainer.querySelector('.point-counter');

        if (!counter) {
            counter = document.createElement('div');
            counter.className = 'point-counter';
            counter.style.cssText = `
                position: absolute;
                top: 10px;
                right: 10px;
                background: rgba(0,0,0,0.8);
                color: white;
                padding: 8px 12px;
                border-radius: 4px;
                font-size: 14px;
                z-index: 1000;
                font-family: Arial, sans-serif;
            `;
            mapContainer.appendChild(counter);
        }

        const pointCount = this.tempPoints.length;
        const drawingType = this.drawingType
            ? this.drawingType.toLowerCase()
            : '';

        if (drawingType === 'polygon') {
            const minPoints = 3;
            const status =
                pointCount >= minPoints
                    ? 'Ready to create!'
                    : `Need ${minPoints - pointCount} more point${
                          minPoints - pointCount > 1 ? 's' : ''
                      }`;

            const modeText = this.editMode ? 'EDIT MODE' : 'CREATE MODE';

            counter.innerHTML = `
                <div><strong>Polygon:</strong> ${pointCount} point${
                pointCount !== 1 ? 's' : ''
            } <span style="color: #FFD700">[${modeText}]</span></div>
                <div style="font-size: 12px; color: ${
                    pointCount >= minPoints ? '#90EE90' : '#FFD700'
                }">${status}</div>
                <div style="font-size: 11px; color: #FFD700">Right-click points to delete • Drag to move</div>
            `;
        } else if (drawingType === 'line' || drawingType === 'linestring') {
            const minPoints = 2;
            const status =
                pointCount >= minPoints
                    ? 'Ready to create!'
                    : `Need ${minPoints - pointCount} more point${
                          minPoints - pointCount > 1 ? 's' : ''
                      }`;

            const modeText = this.editMode ? 'EDIT MODE' : 'CREATE MODE';

            counter.innerHTML = `
                <div><strong>Line:</strong> ${pointCount} point${
                pointCount !== 1 ? 's' : ''
            } <span style="color: #FFD700">[${modeText}]</span></div>
                <div style="font-size: 12px; color: ${
                    pointCount >= minPoints ? '#90EE90' : '#FFD700'
                }">${status}</div>
                <div style="font-size: 11px; color: #FFD700">Right-click points to delete • Drag to move</div>
            `;
        } else if (drawingType === 'point' && this.editMode) {
            counter.innerHTML = `
                <div><strong>Point:</strong> <span style="color: #FFD700">[EDIT MODE]</span></div>
                <div style="font-size: 12px; color: #90EE90">Drag orange marker to move</div>
            `;
        } else if (drawingType === 'circle' && this.editMode) {
            counter.innerHTML = `
                <div><strong>Circle:</strong> <span style="color: #FFD700">[EDIT MODE]</span></div>
                <div style="font-size: 12px; color: #90EE90">Drag yellow center or purple radius</div>
            `;
        } else if (drawingType === 'circle') {
            // Circle creation mode
            const hasCenter = this.tempCircleCenter !== null;
            const hasRadius = this.editPointMarkers.length > 1;

            let status = '';
            if (!hasCenter) {
                status = 'Click to set center';
            } else if (!hasRadius) {
                status = 'Click to set radius';
            } else {
                status = 'Ready to create!';
            }

            counter.innerHTML = `
                <div><strong>Circle:</strong> <span style="color: #FFD700">[CREATE MODE]</span></div>
                <div style="font-size: 12px; color: ${
                    hasCenter && hasRadius ? '#90EE90' : '#FFD700'
                }">${status}</div>
                ${
                    hasCenter
                        ? '<div style="font-size: 11px; color: #FFD700">Drag markers to adjust • Click map to reposition</div>'
                        : ''
                }
            `;
        }
    }

    /**
     * Hide point counter
     */
    hidePointCounter() {
        const mapContainer = this.leafletMap.getContainer();
        const counter = mapContainer.querySelector('.point-counter');
        if (counter) {
            counter.remove();
        }
    }

    /**
     * Get current drawing status
     */
    getDrawingStatus() {
        if (!this.drawingMode) {
            return { isDrawing: false };
        }

        const drawingType = this.drawingType
            ? this.drawingType.toLowerCase()
            : '';
        const pointCount = this.tempPoints.length;

        if (drawingType === 'polygon') {
            return {
                isDrawing: true,
                type: 'polygon',
                pointCount: pointCount,
                minPoints: 3,
                canFinish: pointCount >= 3,
            };
        } else if (drawingType === 'line' || drawingType === 'linestring') {
            return {
                isDrawing: true,
                type: 'line',
                pointCount: pointCount,
                minPoints: 2,
                canFinish: pointCount >= 2,
            };
        }

        return { isDrawing: true, canFinish: true };
    }

    /**
     * Finish polygon on double click (kept for compatibility, but now works as manual finish)
     */
    finishPolygon(e) {
        // Check if this is called from double click event
        if (e && e.originalEvent) {
            // Prevent default behavior and stop propagation only if it's a real event
            e.originalEvent.preventDefault();
            e.originalEvent.stopPropagation();
        }

        return this.finishPolygonFromButton();
    }

    /**
     * Finish line on double click (kept for compatibility, but now works as manual finish)
     */
    finishLine(e) {
        // Check if this is called from double click event
        if (e && e.originalEvent) {
            // Prevent default behavior and stop propagation only if it's a real event
            e.originalEvent.preventDefault();
            e.originalEvent.stopPropagation();
        }

        return this.finishLineFromButton();
    }

    /**
     * Attach popup event listeners to a geo object
     */
    attachPopupEventListeners(object) {
        // Check if the object exists in geoObjectLayers
        if (!this.geoObjectLayers[object.id]) {
            console.warn('Object not found in geoObjectLayers:', object.id);
            return;
        }

        const layerInfo = this.geoObjectLayers[object.id];
        if (!layerInfo || !layerInfo.layer) {
            console.warn('Layer info not found for object:', object.id);
            return;
        }

        const layer = layerInfo.layer;
        let popupElement = null;

        // Handle LayerGroups differently
        if (layer instanceof L.LayerGroup) {
            // Find the first layer with a popup
            layer.eachLayer((sublayer) => {
                if (sublayer.getPopup && sublayer.getPopup()) {
                    popupElement = sublayer.getPopup().getElement();
                    return false; // Break early
                }
            });
        } else {
            // Regular layer
            if (layer.getPopup && layer.getPopup()) {
                popupElement = layer.getPopup().getElement();
            }
        }

        if (!popupElement) {
            console.warn('Popup element not found for object:', object.id);
            return;
        }

        // Use event delegation and check if listeners are already attached
        const popupEditButton = popupElement.querySelector('.popup-edit-btn');
        const popupDeleteButton =
            popupElement.querySelector('.popup-delete-btn');

        if (
            popupEditButton &&
            !popupEditButton.hasAttribute('data-listener-attached')
        ) {
            popupEditButton.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                console.log('Edit button clicked for object:', object.id);
                this.editGeoObject(object);
            });
            popupEditButton.setAttribute('data-listener-attached', 'true');
            console.log('Edit button listener attached for object:', object.id);
        }

        if (
            popupDeleteButton &&
            !popupDeleteButton.hasAttribute('data-listener-attached')
        ) {
            popupDeleteButton.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                console.log('Delete button clicked for object:', object.id);
                this.deleteGeoObject(object);
            });
            popupDeleteButton.setAttribute('data-listener-attached', 'true');
            console.log(
                'Delete button listener attached for object:',
                object.id
            );
        }
    }

    /**
     * Edit a geo object
     */
    editGeoObject(object) {
        // Close the popup
        this.leafletMap.closePopup();

        // Call the global form function to set edit mode
        if (window.geoObjectForm && window.geoObjectForm.setEditMode) {
            window.geoObjectForm.setEditMode(object.id);
        }
    }

    /**
     * Delete a geo object
     */
    deleteGeoObject(object) {
        // Close the popup
        this.leafletMap.closePopup();

        // Confirm deletion
        if (confirm(`Are you sure you want to delete "${object.title}"?`)) {
            // Send delete request
            fetch(`/geo-object/${object.id}/delete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
            })
                .then((response) => response.json())
                .then((data) => {
                    if (data.success) {
                        // Get mapId for refresh
                        const mapContainer =
                            document.getElementById('map-container');
                        const mapId = mapContainer
                            ? mapContainer.getAttribute('data-map-id')
                            : null;

                        if (mapId) {
                            // Clear and reload using current instance
                            this.clearGeoObjects();
                            this.loadGeoObjects(mapId);

                            // Also try to refresh via global reference if available
                            if (
                                window.geoObjectForm &&
                                window.geoObjectForm.refreshObjects
                            ) {
                                window.geoObjectForm.refreshObjects();
                            }
                        }

                        alert('Object deleted successfully');
                    } else {
                        alert(
                            'Error deleting object: ' +
                                (data.message || 'Unknown error')
                        );
                    }
                })
                .catch((error) => {
                    alert('Error deleting object. Please try again.');
                });
        }
    }

    /**
     * Load existing geometry for editing mode
     */
    loadExistingGeometryForEdit(geoJson, type) {
        if (!geoJson || !type) return;

        this.editMode = true;
        this.clearEditPointMarkers();

        const normalizedType = type.toLowerCase();

        if (
            normalizedType === 'polygon' &&
            geoJson.type === 'Polygon' &&
            geoJson.coordinates &&
            geoJson.coordinates[0]
        ) {
            // Load polygon points for editing
            const coordinates = geoJson.coordinates[0];
            this.tempPoints = [];

            // Convert coordinates to LatLng objects (skip last duplicate point)
            for (let i = 0; i < coordinates.length - 1; i++) {
                const coord = coordinates[i];
                const latlng = L.latLng(coord[1], coord[0]);
                this.tempPoints.push(latlng);

                // Create editable point marker
                this.createEditPointMarker(latlng, i);
            }

            // Update visual representation
            this.updateEditPolygonVisual();
        } else if (
            normalizedType === 'line' &&
            geoJson.type === 'LineString' &&
            geoJson.coordinates
        ) {
            // Load line points for editing
            this.tempPoints = [];

            for (let i = 0; i < geoJson.coordinates.length; i++) {
                const coord = geoJson.coordinates[i];
                const latlng = L.latLng(coord[1], coord[0]);
                this.tempPoints.push(latlng);

                // Create editable point marker
                this.createEditPointMarker(latlng, i);
            }

            // Update visual representation
            this.updateEditLineVisual();
        } else if (
            normalizedType === 'point' &&
            geoJson.type === 'Point' &&
            geoJson.coordinates
        ) {
            // Load point for editing
            const coord = geoJson.coordinates;
            const latlng = L.latLng(coord[1], coord[0]);
            this.tempPoints = [latlng];

            // Create editable point marker for the point
            this.createEditPointMarker(latlng, 0, 'point');
        } else if (
            normalizedType === 'circle' &&
            geoJson.type === 'Circle' &&
            geoJson.coordinates &&
            geoJson.radius
        ) {
            // Load circle for editing
            const coord = geoJson.coordinates;
            const center = L.latLng(coord[1], coord[0]);
            this.tempCircleCenter = center;
            this.tempPoints = [center];

            // Create editable center marker
            this.createEditCircleMarker(center, geoJson.radius);

            // Update visual representation
            this.updateEditCircleVisual(geoJson.radius);
        }
    }

    /**
     * Create an editable point marker
     */
    createEditPointMarker(latlng, index, objectType = null) {
        const isPoint = objectType === 'point';

        const marker = L.marker(latlng, {
            draggable: true,
            icon: L.icon({
                iconUrl: isPoint
                    ? 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-orange.png'
                    : 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
                shadowUrl:
                    'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                iconSize: isPoint ? [25, 41] : [20, 32],
                iconAnchor: isPoint ? [12, 41] : [10, 32],
                popupAnchor: [1, isPoint ? -34 : -28],
                shadowSize: isPoint ? [41, 41] : [32, 32],
            }),
            title: isPoint
                ? 'Drag to move point location'
                : `Point ${index + 1} - Drag to move, Right-click to delete`,
        }).addTo(this.leafletMap);

        // Handle dragging
        marker.on('dragend', (e) => {
            const newPos = e.target.getLatLng();
            this.tempPoints[index] = newPos;

            if (isPoint) {
                // For point objects, immediately update geometry
                this.updatePointGeometryCallback(newPos);
            } else {
                // For polygon/line objects
                this.updateEditVisual();
                this.updateGeometryCallback();
            }
        });

        // Handle right-click for deletion (not for single points)
        if (!isPoint) {
            marker.on('contextmenu', (e) => {
                e.originalEvent.preventDefault();
                this.deleteEditPoint(index);
            });
        }

        this.editPointMarkers.push(marker);
        return marker;
    }

    /**
     * Create an editable circle marker (center + radius control)
     */
    createEditCircleMarker(center, radius) {
        // Create center marker
        const centerMarker = L.marker(center, {
            draggable: true,
            icon: L.icon({
                iconUrl:
                    'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-yellow.png',
                shadowUrl:
                    'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41],
            }),
            title: 'Drag to move circle center',
        }).addTo(this.leafletMap);

        // Create radius control point
        const radiusLatLng = L.latLng(center.lat, center.lng + radius / 111000); // Approximate longitude offset
        const radiusMarker = L.marker(radiusLatLng, {
            draggable: true,
            icon: L.icon({
                iconUrl:
                    'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-violet.png',
                shadowUrl:
                    'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                iconSize: [20, 32],
                iconAnchor: [10, 32],
                popupAnchor: [1, -28],
                shadowSize: [32, 32],
            }),
            title: 'Drag to adjust circle radius',
        }).addTo(this.leafletMap);

        // Handle center dragging
        centerMarker.on('dragend', (e) => {
            const newCenter = e.target.getLatLng();
            this.tempCircleCenter = newCenter;
            this.tempPoints[0] = newCenter;

            // If we already have a radius marker, update circle but keep radius marker position
            if (this.editPointMarkers.length > 1) {
                const radiusMarker = this.editPointMarkers[1];
                const currentRadius = newCenter.distanceTo(
                    radiusMarker.getLatLng()
                );

                // Update visual and callback
                this.updateEditCircleVisual(currentRadius);
                this.updateCircleGeometryCallback(newCenter, currentRadius);

                // Update counter
                this.updatePointCounter();
            }
        });

        // Handle radius dragging
        radiusMarker.on('dragend', (e) => {
            const newRadius = this.tempCircleCenter.distanceTo(
                e.target.getLatLng()
            );

            // Update visual and callback
            this.updateEditCircleVisual(newRadius);
            this.updateCircleGeometryCallback(this.tempCircleCenter, newRadius);
        });

        this.editPointMarkers.push(centerMarker);
        this.editPointMarkers.push(radiusMarker);

        return { centerMarker, radiusMarker };
    }

    /**
     * Update visual representation for editing circle
     */
    updateEditCircleVisual(radius) {
        this.clearTempObjects();

        if (this.tempCircleCenter && radius) {
            this.tempLayer = L.circle(this.tempCircleCenter, {
                radius: radius,
                color: 'red',
                weight: 2,
                fillColor: 'red',
                fillOpacity: 0.2,
                dashArray: '5, 5', // Dashed border to show it's being edited
                opacity: 0.7,
            }).addTo(this.leafletMap);
        }
    }

    /**
     * Update geometry callback for point objects
     */
    updatePointGeometryCallback(latlng) {
        if (!this.drawingCallback) return;

        const geoJson = {
            type: 'Point',
            coordinates: [latlng.lng, latlng.lat],
        };

        this.drawingCallback(geoJson);

        // Update point counter display for edit mode
        if (this.editMode) {
            this.updatePointCounter();
        }
    }

    /**
     * Update geometry callback for circle objects
     */
    updateCircleGeometryCallback(center, radius) {
        if (!this.drawingCallback) return;

        const geoJson = {
            type: 'Circle',
            coordinates: [center.lng, center.lat],
            radius: radius,
        };

        this.drawingCallback(geoJson);

        // Update point counter display for edit mode
        if (this.editMode) {
            this.updatePointCounter();
        }
    }

    /**
     * Delete a point from editing
     */
    deleteEditPoint(index) {
        const drawingType = this.drawingType
            ? this.drawingType.toLowerCase()
            : '';
        const minPoints = drawingType === 'polygon' ? 3 : 2;

        if (this.tempPoints.length <= minPoints) {
            alert(
                `Cannot delete point. Minimum ${minPoints} points required for ${drawingType}.`
            );
            return;
        }

        // Remove the point and marker
        this.tempPoints.splice(index, 1);

        // Remove and recreate all markers to update indices
        this.recreateEditPointMarkers();

        // Update visual representation
        this.updateEditVisual();
        this.updateGeometryCallback();
    }

    /**
     * Recreate all edit point markers with correct indices
     */
    recreateEditPointMarkers() {
        // Clear existing markers
        this.clearEditPointMarkers();

        // Recreate markers with updated indices
        this.tempPoints.forEach((latlng, index) => {
            this.createEditPointMarker(latlng, index);
        });
    }

    /**
     * Clear edit point markers
     */
    clearEditPointMarkers() {
        this.editPointMarkers.forEach((marker) => {
            this.leafletMap.removeLayer(marker);
        });
        this.editPointMarkers = [];
    }

    /**
     * Update visual representation for editing polygon
     */
    updateEditPolygonVisual() {
        this.clearTempObjects();

        if (this.tempPoints.length >= 2) {
            this.tempLayer = L.polygon(this.tempPoints, {
                color: 'blue',
                weight: 2,
                fillOpacity: 0.3,
                dashArray: '5, 5', // Dashed to show it's being edited
            }).addTo(this.leafletMap);
        }
    }

    /**
     * Update visual representation for editing line
     */
    updateEditLineVisual() {
        this.clearTempObjects();

        if (this.tempPoints.length >= 2) {
            this.tempLayer = L.polyline(this.tempPoints, {
                color: 'green',
                weight: 3,
                dashArray: '5, 5', // Dashed to show it's being edited
            }).addTo(this.leafletMap);
        }
    }

    /**
     * Update visual representation based on current drawing type
     */
    updateEditVisual() {
        const drawingType = this.drawingType
            ? this.drawingType.toLowerCase()
            : '';

        if (drawingType === 'polygon') {
            this.updateEditPolygonVisual();
        } else if (drawingType === 'line' || drawingType === 'linestring') {
            this.updateEditLineVisual();
        }
    }

    /**
     * Update geometry callback for edit mode
     */
    updateGeometryCallback() {
        if (!this.drawingCallback) return;

        const drawingType = this.drawingType
            ? this.drawingType.toLowerCase()
            : '';

        if (drawingType === 'polygon' && this.tempPoints.length >= 3) {
            // Create GeoJSON for polygon
            const coordinates = [
                this.tempPoints.map((point) => [point.lng, point.lat]),
            ];
            // Close the polygon
            coordinates[0].push([
                this.tempPoints[0].lng,
                this.tempPoints[0].lat,
            ]);

            const geoJson = {
                type: 'Polygon',
                coordinates: coordinates,
            };

            this.drawingCallback(geoJson);
        } else if (
            (drawingType === 'line' || drawingType === 'linestring') &&
            this.tempPoints.length >= 2
        ) {
            // Create GeoJSON for line
            const coordinates = this.tempPoints.map((point) => [
                point.lng,
                point.lat,
            ]);

            const geoJson = {
                type: 'LineString',
                coordinates: coordinates,
            };

            this.drawingCallback(geoJson);
        }
    }

    /**
     * Create a legend showing all sides present on the map
     */
    createSidesLegend(objects) {
        // Remove existing legend
        this.removeSidesLegend();

        // Collect unique sides from objects
        const sides = new Map();
        objects.forEach((object) => {
            if (object.side && object.side.id) {
                sides.set(object.side.id, object.side);
            }
        });

        // Only create legend if there are sides to display
        if (sides.size === 0) {
            return;
        }

        // Create legend control with filtering functionality
        const legendControl = L.control({ position: 'topright' });

        legendControl.onAdd = () => {
            const div = L.DomUtil.create('div', 'sides-legend');
            div.innerHTML = '<h4>Sides <small>(click to filter)</small></h4>';

            sides.forEach((side) => {
                const sideItem = L.DomUtil.create(
                    'div',
                    'legend-item legend-item-clickable',
                    div
                );
                sideItem.setAttribute('data-side-id', side.id);
                sideItem.innerHTML = `
                    <div class="legend-color" style="
                        background-color: ${side.color || '#666'};
                        width: 16px;
                        height: 16px;
                        border-radius: 50%;
                        display: inline-block;
                        margin-right: 8px;
                        border: 2px solid white;
                        box-shadow: 0 1px 3px rgba(0,0,0,0.3);
                    "></div>
                    <span>${side.name}</span>
                `;

                // Add click handler for filtering
                L.DomEvent.on(sideItem, 'click', (e) => {
                    L.DomEvent.stopPropagation(e);
                    this.toggleSideVisibility(side.id);
                    this.updateLegendItemAppearance(sideItem, side.id);
                });

                // Prevent map interaction when clicking on legend
                L.DomEvent.disableClickPropagation(sideItem);
            });

            return div;
        };

        this.legendControl = legendControl;
        legendControl.addTo(this.leafletMap);
    }

    /**
     * Toggle visibility of objects for a specific side
     */
    toggleSideVisibility(sideId) {
        if (this.visibleSides.has(sideId)) {
            this.visibleSides.delete(sideId);
        } else {
            this.visibleSides.add(sideId);
        }

        // Update visibility of all objects
        this.updateObjectsVisibility();
    }

    /**
     * Update visibility of all objects based on side filters
     */
    updateObjectsVisibility() {
        Object.values(this.geoObjectLayers).forEach((item) => {
            const object = item.data;
            const layer = item.layer;

            if (!layer) return;

            // If no sides are filtered (empty set), show all objects
            // If sides are filtered, only show objects of visible sides or objects without sides
            const shouldShow =
                this.visibleSides.size === 0 ||
                !object.side ||
                this.visibleSides.has(object.side.id);

            if (shouldShow) {
                if (!this.leafletMap.hasLayer(layer)) {
                    layer.addTo(this.leafletMap);
                }
            } else {
                if (this.leafletMap.hasLayer(layer)) {
                    this.leafletMap.removeLayer(layer);
                }
            }
        });
    }

    /**
     * Update the appearance of legend item based on visibility state
     */
    updateLegendItemAppearance(item, sideId) {
        const isVisible =
            this.visibleSides.size === 0 || this.visibleSides.has(sideId);

        if (isVisible) {
            item.style.opacity = '1';
            item.classList.remove('legend-item-hidden');
        } else {
            item.style.opacity = '0.5';
            item.classList.add('legend-item-hidden');
        }
    }

    /**
     * Remove sides legend from the map
     */
    removeSidesLegend() {
        if (this.legendControl) {
            this.leafletMap.removeControl(this.legendControl);
            this.legendControl = null;
        }
    }
}

// Very important - export the class as default
export default MapGeoObjectManager;
