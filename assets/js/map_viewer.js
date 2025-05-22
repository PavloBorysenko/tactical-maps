import MapGeoObjectManager from './map_geo_objects';

/**
 * Tactical Map Viewer component
 */
class TacticalMapViewer {
    constructor(options = {}) {
        this.options = Object.assign(
            {
                mapContainerId: 'map-container',
                tileUrl: '/tiles/{z}/{x}/{y}.png',
                attribution: 'Tactical Map',
                initialZoom: 13,
                maxZoom: 18,
            },
            options
        );

        this.container = document.getElementById(this.options.mapContainerId);
        if (!this.container) {
            console.error(
                `Map container with ID "${this.options.mapContainerId}" not found.`
            );
            return;
        }

        this.initMap();
        this.geoObjectManager = new MapGeoObjectManager(this);

        // Expose this instance globally for interaction with form scripts
        window.tacticalMap = this;
    }

    /**
     * Initialize the Leaflet map
     */
    initMap() {
        // Get map settings from container data attributes
        const mapId = this.container.getAttribute('data-map-id');
        let centerLat =
            parseFloat(this.container.getAttribute('data-map-center-lat')) || 0;
        let centerLng =
            parseFloat(this.container.getAttribute('data-map-center-lng')) || 0;
        let zoom =
            parseInt(this.container.getAttribute('data-map-zoom')) ||
            this.options.initialZoom;

        // Create Leaflet map
        this.map = L.map(this.container, {
            center: [centerLat, centerLng],
            zoom: zoom,
            zoomControl: true,
        });

        // Add tile layer
        L.tileLayer(this.options.tileUrl, {
            attribution: this.options.attribution,
            maxZoom: this.options.maxZoom,
        }).addTo(this.map);

        // Load geo objects if map ID is available
        if (mapId) {
            this.loadGeoObjects(mapId);
        }
    }

    /**
     * Get the Leaflet map instance
     */
    getLeafletMap() {
        return this.map;
    }

    /**
     * Load geo objects for a specific map
     */
    loadGeoObjects(mapId) {
        if (this.geoObjectManager) {
            this.geoObjectManager.loadGeoObjects(mapId);
        }
    }

    /**
     * Enable drawing mode for creating geo objects
     */
    enableDrawingMode(type, callback) {
        if (this.geoObjectManager) {
            this.geoObjectManager.enableDrawingMode(type, callback);
        }
    }

    /**
     * Disable drawing mode
     */
    disableDrawingMode() {
        if (this.geoObjectManager) {
            this.geoObjectManager.disableDrawingMode();
        }
    }

    /**
     * Clear temporary objects from the map
     */
    clearTempObjects() {
        if (this.geoObjectManager) {
            this.geoObjectManager.clearTempObjects();
        }
    }

    /**
     * Focus on a specific geo object
     */
    focusOnObject(objectId) {
        if (this.geoObjectManager) {
            this.geoObjectManager.focusOnObject(objectId);
        }
    }

    /**
     * Show a specific GeoJSON object on the map (for editing)
     */
    showGeoJsonObject(geoJson, type) {
        if (this.geoObjectManager) {
            this.geoObjectManager.showGeoJsonObject(geoJson, type);
        }
    }

    /**
     * Set cursor style for drawing mode
     */
    setDrawingCursor(type) {
        if (!this.container) return;

        switch (type) {
            case 'point':
                this.container.style.cursor = 'crosshair';
                break;
            case 'polygon':
            case 'line':
                this.container.style.cursor = 'pointer';
                break;
            case 'circle':
                this.container.style.cursor = 'cell';
                break;
            default:
                this.container.style.cursor = 'default';
        }
    }

    /**
     * Reset cursor to default
     */
    resetCursor() {
        if (this.container) {
            this.container.style.cursor = 'default';
        }
    }
}

// Initialize map when DOM is loaded
document.addEventListener('DOMContentLoaded', function () {
    new TacticalMapViewer();
});

export default TacticalMapViewer;
