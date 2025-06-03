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

        // FORCE REMOVE ALL GEO OBJECTS - брute force approach
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
                        layer = this.createPointLayer(geoJson);
                        break;
                    case 'polygon':
                        layer = this.createPolygonLayer(geoJson);
                        break;
                    case 'circle':
                        layer = this.createCircleLayer(geoJson);
                        break;
                    case 'line':
                    case 'linestring':
                        layer = this.createLineLayer(geoJson);
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
                    layer.bindPopup(this.createPopupContent(object));

                    // Add event listener for popup open to attach button handlers
                    layer.on('popupopen', () => {
                        this.attachPopupEventListeners(object);
                    });

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
        let content = `<div class="geo-popup">
            <h5>${object.title || 'Unnamed object'}</h5>`;

        if (object.description) {
            content += `<p>${object.description}</p>`;
        }

        if (object.ttl > 0) {
            // Format TTL for display
            let ttlDisplay;
            if (object.ttl >= 3600) {
                const hours = Math.floor(object.ttl / 3600);
                const minutes = Math.floor((object.ttl % 3600) / 60);
                ttlDisplay = `${hours} hour${hours !== 1 ? 's' : ''}`;
                if (minutes > 0) {
                    ttlDisplay += ` ${minutes} min`;
                }
            } else if (object.ttl >= 60) {
                ttlDisplay = `${Math.floor(object.ttl / 60)} min`;
            } else {
                ttlDisplay = `${object.ttl} sec`;
            }

            content += `<small class="text-muted">Expires in: ${ttlDisplay}</small>`;
        } else {
            content += `<small class="text-muted">No expiration</small>`;
        }

        content += `<div class="mt-2">
            <button class="btn btn-sm btn-outline-primary edit-from-popup" data-id="${object.id}">
                <i class="fas fa-edit"></i> Edit
            </button>
            <button class="btn btn-sm btn-outline-danger delete-from-popup" data-id="${object.id}">
                <i class="fas fa-trash"></i> Delete
            </button>
        </div>`;

        content += '</div>';

        // For simplicity, return the HTML string instead of a function
        return content;
    }

    /**
     * Create a layer for a point geo object
     */
    createPointLayer(geoJson) {
        if (!geoJson || geoJson.type !== 'Point' || !geoJson.coordinates) {
            return null;
        }

        const latlng = L.latLng(geoJson.coordinates[1], geoJson.coordinates[0]);
        return L.marker(latlng);
    }

    /**
     * Create a layer for a polygon geo object
     */
    createPolygonLayer(geoJson) {
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
        return L.polygon(points, {
            color: 'blue',
            weight: 2,
            fillOpacity: 0.3,
        });
    }

    /**
     * Create a layer for a circle geo object
     */
    createCircleLayer(geoJson) {
        if (
            !geoJson ||
            geoJson.type !== 'Circle' ||
            !geoJson.coordinates ||
            !geoJson.radius
        ) {
            return null;
        }

        const center = L.latLng(geoJson.coordinates[1], geoJson.coordinates[0]);
        return L.circle(center, {
            radius: geoJson.radius,
            color: 'red',
            weight: 2,
            fillOpacity: 0.2,
        });
    }

    /**
     * Create a layer for a line geo object
     */
    createLineLayer(geoJson) {
        if (!geoJson || geoJson.type !== 'LineString' || !geoJson.coordinates) {
            return null;
        }

        const points = geoJson.coordinates.map((coord) =>
            L.latLng(coord[1], coord[0])
        );
        return L.polyline(points, {
            color: 'green',
            weight: 3,
        });
    }

    /**
     * Display a specific GeoJSON object on the map (for editing)
     */
    showGeoJsonObject(geoJson, type) {
        this.clearTempObjects();

        if (!geoJson || !type) {
            return;
        }

        let layer;
        const normalizedType = type.toLowerCase(); // Normalize to lowercase
        switch (normalizedType) {
            case 'point':
                layer = this.createPointLayer(geoJson);
                break;
            case 'polygon':
                layer = this.createPolygonLayer(geoJson);
                break;
            case 'circle':
                layer = this.createCircleLayer(geoJson);
                break;
            case 'line':
            case 'linestring':
                layer = this.createLineLayer(geoJson);
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
    }

    /**
     * Enable drawing mode for a specific type
     */
    enableDrawingMode(type, callback) {
        console.log('Enabling drawing mode for type:', type);

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
                console.log('Adding point click handler');
                this.leafletMap.on('click', this.pointClickHandler);
                break;
            case 'circle':
                console.log('Adding circle click handler');
                this.leafletMap.on('click', this.circleFirstClickHandler);
                break;
            case 'polygon':
                console.log('Adding polygon click handlers');
                this.leafletMap.on('click', this.polygonClickHandler);
                // Keep double click for users who want to use it, but it's not required
                // Keep double click for users who want to use it, but it's not required
                this.leafletMap.on('dblclick', this.finishPolygonHandler);
                break;
            case 'line':
            case 'linestring':
                console.log('Adding line click handlers');
                this.leafletMap.on('click', this.lineClickHandler);
                // Keep double click for users who want to use it, but it's not required
                // Keep double click for users who want to use it, but it's not required
                this.leafletMap.on('dblclick', this.finishLineHandler);
                break;
        }

        console.log('Drawing mode enabled, current drawing state:', {
            drawingMode: this.drawingMode,
            drawingType: this.drawingType,
            hasCallback: !!this.drawingCallback,
        });
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

        // Reset state
        this.drawingMode = false;
        this.drawingType = null;
        this.tempPoints = [];
        this.tempCircleCenter = null;

        // Hide point counter if it exists
        this.hidePointCounter();

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
        console.log('Point click detected at:', e.latlng);

        // Clear any previous temp objects
        this.clearTempObjects();

        // Create a marker at the clicked location
        const point = e.latlng;
        this.tempLayer = L.marker(point).addTo(this.leafletMap);

        // Create GeoJSON
        const geoJson = {
            type: 'Point',
            coordinates: [point.lng, point.lat],
        };

        console.log('Point GeoJSON created:', geoJson);

        // Call the callback with the GeoJSON
        if (this.drawingCallback) {
            this.drawingCallback(geoJson);
        }

        // Automatically exit drawing mode
        this.disableDrawingMode();
    }

    /**
     * Handle first click for circle drawing
     */
    handleCircleFirstClick(e) {
        // Store center point
        this.tempCircleCenter = e.latlng;

        // Create a temporary marker at the center
        this.clearTempObjects();
        this.tempLayer = L.marker(this.tempCircleCenter).addTo(this.leafletMap);

        // Change handler for second click
        this.leafletMap.off('click', this.circleFirstClickHandler);
        this.circleSecondClickHandler = this.handleCircleSecondClick.bind(this);
        this.leafletMap.on('click', this.circleSecondClickHandler);
    }

    /**
     * Handle second click for circle drawing (radius)
     */
    handleCircleSecondClick(e) {
        if (!this.tempCircleCenter) return;

        // Calculate radius in meters
        const radius = this.tempCircleCenter.distanceTo(e.latlng);

        // Remove temporary marker
        this.clearTempObjects();

        // Create circle
        this.tempLayer = L.circle(this.tempCircleCenter, {
            radius: radius,
        }).addTo(this.leafletMap);

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

        // Reset event handlers and exit drawing mode
        this.leafletMap.off('click', this.circleSecondClickHandler);
        this.disableDrawingMode();
    }

    /**
     * Handle clicks for polygon drawing
     */
    handlePolygonClick(e) {
        console.log(
            'Polygon click detected, point count:',
            this.tempPoints.length
        );

        // Add point to the temp points array
        this.tempPoints.push(e.latlng);

        // Clear previous temp layer
        this.clearTempObjects();

        // Create a temporary polyline to show progress
        if (this.tempPoints.length > 1) {
            this.tempLayer = L.polyline(this.tempPoints, {
                color: 'red',
                weight: 3,
                dashArray: '5, 5', // Dashed line to show it's temporary
                dashArray: '5, 5', // Dashed line to show it's temporary
            }).addTo(this.leafletMap);
        } else {
            // Just a marker for the first point
            this.tempLayer = L.marker(e.latlng).addTo(this.leafletMap);
        }

        // Update point counter display
        this.updatePointCounter();
    }

    /**
     * Finish polygon manually (called from form button)
     * Finish polygon manually (called from form button)
     */
    finishPolygonFromButton() {
    finishPolygonFromButton() {
        // Need at least 3 points for a polygon
        if (this.tempPoints.length < 3) {
            alert('Please add at least 3 points to create a polygon.');
            return false;
            return false;
        }

        // Create polygon
        this.clearTempObjects();
        this.tempLayer = L.polygon(this.tempPoints, {
            color: 'blue',
            weight: 2,
            fillOpacity: 0.3,
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
        return true;
    }

    /**
     * Handle clicks for line drawing
     */
    handleLineClick(e) {
        // Similar to polygon clicks
        this.tempPoints.push(e.latlng);

        this.clearTempObjects();

        if (this.tempPoints.length > 1) {
            this.tempLayer = L.polyline(this.tempPoints, {
                color: 'green',
                weight: 3,
                dashArray: '5, 5', // Dashed line to show it's temporary
                dashArray: '5, 5', // Dashed line to show it's temporary
            }).addTo(this.leafletMap);
        } else {
            this.tempLayer = L.marker(e.latlng).addTo(this.leafletMap);
        }

        // Update point counter display
        this.updatePointCounter();

        // Update point counter display
        this.updatePointCounter();
    }

    /**
     * Finish line manually (called from form button)
     * Finish line manually (called from form button)
     */
    finishLineFromButton() {
    finishLineFromButton() {
        // Need at least 2 points for a line
        if (this.tempPoints.length < 2) {
            alert('Please add at least 2 points to create a line.');
            return false;
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
            counter.innerHTML = `
                <div><strong>Polygon:</strong> ${pointCount} point${
                pointCount !== 1 ? 's' : ''
            }</div>
                <div style="font-size: 12px; color: ${
                    pointCount >= minPoints ? '#90EE90' : '#FFD700'
                }">${status}</div>
            `;
        } else if (drawingType === 'line' || drawingType === 'linestring') {
            const minPoints = 2;
            const status =
                pointCount >= minPoints
                    ? 'Ready to create!'
                    : `Need ${minPoints - pointCount} more point${
                          minPoints - pointCount > 1 ? 's' : ''
                      }`;
            counter.innerHTML = `
                <div><strong>Line:</strong> ${pointCount} point${
                pointCount !== 1 ? 's' : ''
            }</div>
                <div style="font-size: 12px; color: ${
                    pointCount >= minPoints ? '#90EE90' : '#FFD700'
                }">${status}</div>
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
            counter.innerHTML = `
                <div><strong>Polygon:</strong> ${pointCount} point${
                pointCount !== 1 ? 's' : ''
            }</div>
                <div style="font-size: 12px; color: ${
                    pointCount >= minPoints ? '#90EE90' : '#FFD700'
                }">${status}</div>
            `;
        } else if (drawingType === 'line' || drawingType === 'linestring') {
            const minPoints = 2;
            const status =
                pointCount >= minPoints
                    ? 'Ready to create!'
                    : `Need ${minPoints - pointCount} more point${
                          minPoints - pointCount > 1 ? 's' : ''
                      }`;
            counter.innerHTML = `
                <div><strong>Line:</strong> ${pointCount} point${
                pointCount !== 1 ? 's' : ''
            }</div>
                <div style="font-size: 12px; color: ${
                    pointCount >= minPoints ? '#90EE90' : '#FFD700'
                }">${status}</div>
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
     * Attach popup event listeners to a geo object
     */
    attachPopupEventListeners(object) {
        // Check if the object exists in geoObjectLayers
        if (!this.geoObjectLayers[object.id]) {
            return;
        }

        const layerInfo = this.geoObjectLayers[object.id];
        if (!layerInfo || !layerInfo.layer) {
            return;
        }

        const layer = layerInfo.layer;

        const editButton = layer
            .getPopup()
            .getElement()
            .querySelector('.edit-from-popup');
        const deleteButton = layer
            .getPopup()
            .getElement()
            .querySelector('.delete-from-popup');

        if (editButton) {
            editButton.addEventListener('click', () => {
                this.editGeoObject(object);
            });
        }

        if (deleteButton) {
            deleteButton.addEventListener('click', () => {
                this.deleteGeoObject(object);
            });
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
}

// Very important - export the class as default
export default MapGeoObjectManager;
