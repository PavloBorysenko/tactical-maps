/**
 * Map Geo Object Manager
 */
class MapGeoObjectManager {
    constructor(mapViewer) {
        this.mapViewer = mapViewer;
        this.leafletMap = mapViewer.getLeafletMap();
        this.drawingCallback = null;
        this.tempPoints = [];
        this.tempLayer = null;
    }

    /**
     * Enable drawing mode for creating geo objects
     */
    enableDrawingMode(type, callback) {
        this.drawingCallback = callback;
        this.tempPoints = [];
        this.tempLayer = null;

        this.leafletMap.on('click', this.handleClick.bind(this));
        this.leafletMap.on('dblclick', this.handleDoubleClick.bind(this));

        this.mapViewer.setDrawingCursor(type);
    }

    /**
     * Disable drawing mode
     */
    disableDrawingMode() {
        this.drawingCallback = null;
        this.tempPoints = [];
        this.tempLayer = null;

        this.leafletMap.off('click');
        this.leafletMap.off('dblclick');

        this.mapViewer.resetCursor();
    }

    /**
     * Handle click event
     */
    handleClick(e) {
        this.tempPoints.push(e.latlng);
        this.tempLayer = L.circle(e.latlng, {
            radius: 5,
            color: 'black',
            fillOpacity: 0.5,
        }).addTo(this.leafletMap);
    }

    /**
     * Handle double click event
     */
    handleDoubleClick(e) {
        this.finishLine(e);
    }

    /**
     * Finish line on double click
     */
    finishLine(e) {
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
     * Create a layer for a point geo object
     */
    createPointLayer(geoJson) {
        if (geoJson.type !== 'Point' || !geoJson.coordinates) {
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
        if (geoJson.type !== 'LineString' || !geoJson.coordinates) {
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
}

// Export the manager for use in map_viewer.js
export default MapGeoObjectManager;
