/**
 * Component for handling geo objects on the map
 */
console.log('map_geo_objects.js загружен');

class MapGeoObjectManager {
    constructor(map) {
        this.map = map;
        this.leafletMap = map.getLeafletMap();
        this.geoObjectLayers = {};
        this.tempLayer = null;
        this.drawingMode = false;
        this.drawingType = null;
        this.drawingCallback = null;

        // Обработчики событий для различных типов объектов
        this.pointClickHandler = this.handlePointClick.bind(this);
        this.circleFirstClickHandler = this.handleCircleFirstClick.bind(this);
        this.polygonClickHandler = this.handlePolygonClick.bind(this);
        this.lineClickHandler = this.handleLineClick.bind(this);

        // Временные данные для рисования
        this.tempPoints = [];
        this.tempCircleCenter = null;

        console.log('MapGeoObjectManager constructor called', this);
        console.log('Leaflet map reference:', this.leafletMap);
    }

    /**
     * Load all geo objects for a map
     */
    loadGeoObjects(mapId) {
        console.log('Loading geo objects for map ID:', mapId);

        // Проверяем, что аргумент является числом или строкой
        if (
            !mapId ||
            (typeof mapId !== 'number' && typeof mapId !== 'string')
        ) {
            console.error('Invalid mapId provided to loadGeoObjects:', mapId);
            return;
        }

        // Clear existing objects
        this.clearGeoObjects();

        // Fetch objects from API
        fetch(`/geo-object/by-map/${mapId}`)
            .then((response) => {
                console.log('Received response from API:', response);
                return response.json();
            })
            .then((data) => {
                console.log('Parsed response data:', data);
                if (data.success) {
                    this.renderGeoObjects(data.objects);
                } else {
                    console.error('Failed to load geo objects:', data.message);
                }
            })
            .catch((error) => {
                console.error('Error loading geo objects:', error);
            });
    }

    /**
     * Clear all geo objects from the map
     */
    clearGeoObjects() {
        console.log('Clearing geo objects');

        Object.values(this.geoObjectLayers).forEach((item) => {
            if (item.layer) {
                this.leafletMap.removeLayer(item.layer);
            }
        });

        this.geoObjectLayers = {};
    }

    /**
     * Render geo objects on the map
     */
    renderGeoObjects(objects) {
        console.log('Rendering geo objects:', objects);

        if (!Array.isArray(objects)) {
            console.error('Expected array of objects, got:', objects);
            return;
        }

        objects.forEach((object) => {
            try {
                // Проверка и обработка JSON-строки
                const geoJson =
                    typeof object.geoJson === 'string'
                        ? JSON.parse(object.geoJson)
                        : object.geoJson;

                // Создаем слой в зависимости от типа
                let layer;
                switch (object.type) {
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
                        layer = this.createLineLayer(geoJson);
                        break;
                    default:
                        console.warn(`Unknown geo object type: ${object.type}`);
                        return;
                }

                if (layer) {
                    // Add a popup with object info
                    layer.bindPopup(this.createPopupContent(object));

                    // Store the layer with object ID
                    this.geoObjectLayers[object.id] = {
                        layer: layer,
                        type: object.type,
                        data: object,
                    };

                    // Add to map
                    layer.addTo(this.leafletMap);
                }
            } catch (error) {
                console.error(
                    `Error rendering geo object ${object.id}:`,
                    error
                );
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

        // Для простоты возвращаем HTML-строку вместо функции
        return content;
    }

    /**
     * Create a layer for a point geo object
     */
    createPointLayer(geoJson) {
        if (!geoJson || geoJson.type !== 'Point' || !geoJson.coordinates) {
            console.error('Invalid GeoJSON for point:', geoJson);
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
            console.error('Invalid GeoJSON for polygon:', geoJson);
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
            console.error('Invalid GeoJSON for circle:', geoJson);
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
            console.error('Invalid GeoJSON for line:', geoJson);
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
            console.error('Missing geoJson or type in showGeoJsonObject');
            return;
        }

        let layer;
        switch (type) {
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
                layer = this.createLineLayer(geoJson);
                break;
            default:
                console.warn(`Unknown geo object type for display: ${type}`);
                return;
        }

        if (layer) {
            this.tempLayer = layer;
            this.tempLayer.addTo(this.leafletMap);

            // Fit bounds for non-point objects
            if (type !== 'point' && layer.getBounds) {
                this.leafletMap.fitBounds(layer.getBounds());
            } else if (type === 'point' && layer.getLatLng) {
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
            // Резервный вариант, если метод не определен в map
            this.setDrawingCursor(type);
        }

        // Attach appropriate event handlers based on type
        switch (type) {
            case 'point':
                this.leafletMap.on('click', this.pointClickHandler);
                break;
            case 'circle':
                this.leafletMap.on('click', this.circleFirstClickHandler);
                break;
            case 'polygon':
                this.leafletMap.on('click', this.polygonClickHandler);
                this.leafletMap.on('dblclick', this.finishPolygon.bind(this));
                break;
            case 'line':
                this.leafletMap.on('click', this.lineClickHandler);
                this.leafletMap.on('dblclick', this.finishLine.bind(this));
                break;
        }

        console.log('Drawing mode enabled for:', type);
    }

    /**
     * Disable drawing mode
     */
    disableDrawingMode() {
        if (!this.drawingMode) return;

        console.log('Disabling drawing mode');

        // Remove event handlers
        this.leafletMap.off('click', this.pointClickHandler);
        this.leafletMap.off('click', this.circleFirstClickHandler);
        this.leafletMap.off('click', this.circleSecondClickHandler);
        this.leafletMap.off('click', this.polygonClickHandler);
        this.leafletMap.off('click', this.lineClickHandler);
        this.leafletMap.off('dblclick', this.finishPolygon.bind(this));
        this.leafletMap.off('dblclick', this.finishLine.bind(this));

        // Reset cursor
        if (this.map && typeof this.map.resetCursor === 'function') {
            this.map.resetCursor();
        } else {
            // Резервный вариант, если метод не определен в map
            this.resetCursor();
        }

        // Clear temp layer
        this.clearTempObjects();

        // Reset state
        this.drawingMode = false;
        this.drawingType = null;
        this.tempPoints = [];
        this.tempCircleCenter = null;

        console.log('Drawing mode disabled');
    }

    /**
     * Set cursor style for drawing mode
     */
    setDrawingCursor(type) {
        if (!this.leafletMap || !this.leafletMap.getContainer()) return;

        const container = this.leafletMap.getContainer();

        switch (type) {
            case 'point':
                container.style.cursor = 'crosshair';
                break;
            case 'polygon':
            case 'line':
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
        console.log('Point click at:', e.latlng);

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
        console.log('Circle first click at:', e.latlng);

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
        console.log('Circle second click at:', e.latlng);

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
        console.log('Polygon click at:', e.latlng);

        // Add point to the temp points array
        this.tempPoints.push(e.latlng);

        // Clear previous temp layer
        this.clearTempObjects();

        // Create a temporary polyline to show progress
        if (this.tempPoints.length > 1) {
            this.tempLayer = L.polyline(this.tempPoints, {
                color: 'red',
                weight: 3,
            }).addTo(this.leafletMap);
        } else {
            // Just a marker for the first point
            this.tempLayer = L.marker(e.latlng).addTo(this.leafletMap);
        }
    }

    /**
     * Finish polygon on double click
     */
    finishPolygon(e) {
        console.log('Finish polygon');

        // Need at least 3 points for a polygon
        if (this.tempPoints.length < 3) {
            alert('Please add at least 3 points to create a polygon.');
            return;
        }

        // Create polygon
        this.clearTempObjects();
        this.tempLayer = L.polygon(this.tempPoints, {
            color: 'blue',
            weight: 2,
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

        // Exit drawing mode
        this.disableDrawingMode();
    }

    /**
     * Handle clicks for line drawing
     */
    handleLineClick(e) {
        console.log('Line click at:', e.latlng);

        // Similar to polygon clicks
        this.tempPoints.push(e.latlng);

        this.clearTempObjects();

        if (this.tempPoints.length > 1) {
            this.tempLayer = L.polyline(this.tempPoints, {
                color: 'green',
                weight: 3,
            }).addTo(this.leafletMap);
        } else {
            this.tempLayer = L.marker(e.latlng).addTo(this.leafletMap);
        }
    }

    /**
     * Finish line on double click
     */
    finishLine(e) {
        console.log('Finish line');

        // Need at least 2 points for a line
        if (this.tempPoints.length < 2) {
            alert('Please add at least 2 points to create a line.');
            return;
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

        // Exit drawing mode
        this.disableDrawingMode();
    }
}

// Очень важно - экспортируем класс как default
export default MapGeoObjectManager;
