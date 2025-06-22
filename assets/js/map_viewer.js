import L from 'leaflet';
import MapGeoObjectManager from './map_geo_objects';
import MapLayers from './mapLayers';
import MapToolbar from './mapToolbar';

// Fix Leaflet default icons path
L.Icon.Default.prototype.options.imagePath = '/build/images/leaflet/';

/**
 * Tactical Map Viewer component
 */
class TacticalMapViewer {
    constructor(options = {}) {
        this.options = Object.assign(
            {
                mapContainerId: 'map-container',
                tileUrl: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                attribution:
                    '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                initialZoom: 13,
                maxZoom: 18,
            },
            options
        );

        this.container = document.getElementById(this.options.mapContainerId);
        if (!this.container) {
            return;
        }

        // Initialize layers
        this.baseLayers = {};
        this.layerControl = null;
        this.toolbar = null;

        // Important! First initialize the map
        this.initMap();

        // Then create the geo-objects manager
        try {
            this.geoObjectManager = new MapGeoObjectManager(this);

            // Load objects ONLY AFTER the manager is created
            const mapId = this.container.getAttribute('data-map-id');
            if (mapId) {
                this.loadGeoObjects(mapId);
            }
        } catch (error) {
            // Silent error handling
        }
    }

    /**
     * Initialize the Leaflet map
     */
    initMap() {
        try {
            // Get map settings from container attributes
            const mapId = this.container.getAttribute('data-map-id');
            let centerLat =
                parseFloat(
                    this.container.getAttribute('data-map-center-lat')
                ) || 51.505;
            let centerLng =
                parseFloat(
                    this.container.getAttribute('data-map-center-lng')
                ) || -0.09;
            let zoom =
                parseInt(this.container.getAttribute('data-map-zoom')) ||
                this.options.initialZoom;

            // Create Leaflet map
            this.map = L.map(this.container, {
                center: [centerLat, centerLng],
                zoom: zoom,
                zoomControl: true,
            });

            // Initialize map with layers using the centralized module
            const layersData = MapLayers.initializeMapWithLayers(this.map);
            this.baseLayers = layersData.baseLayers;
            this.layerControl = layersData.layerControl;

            // Initialize toolbar
            this.initToolbar(centerLat, centerLng, zoom);

            // Forcefully update the map size after initialization
            setTimeout(() => {
                this.map.invalidateSize();
            }, 100);

            // Make the map object available globally
            window.tacticalMap = this;

            // Generate a user event to notify other scripts
            const mapReadyEvent = new CustomEvent('tactical-map-ready', {
                detail: { map: this },
            });
            document.dispatchEvent(mapReadyEvent);

            // Add event listener for geo objects refresh
            document.addEventListener('geo-objects-refresh', (event) => {
                if (
                    event.detail &&
                    event.detail.mapId &&
                    this.geoObjectManager
                ) {
                    this.geoObjectManager.clearGeoObjects();
                    this.geoObjectManager.loadGeoObjects(event.detail.mapId);
                }
            });
        } catch (error) {
            // Silent error handling
        }
    }

    /**
     * Initialize map toolbar
     */
    initToolbar(centerLat, centerLng, zoom) {
        const mapData = {
            centerLat: centerLat,
            centerLng: centerLng,
            zoom: zoom,
        };

        this.toolbar = new MapToolbar(this.map, mapData);
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
        if (!this.geoObjectManager) {
            return;
        }

        if (typeof this.geoObjectManager.loadGeoObjects !== 'function') {
            return;
        }

        this.geoObjectManager.loadGeoObjects(mapId);
    }

    /**
     * Enable drawing mode for creating geo objects
     */
    enableDrawingMode(type, callback) {
        if (!this.geoObjectManager) {
            return;
        }

        if (typeof this.geoObjectManager.enableDrawingMode !== 'function') {
            return;
        }

        this.geoObjectManager.enableDrawingMode(type, callback);
    }

    /**
     * Disable drawing mode
     */
    disableDrawingMode() {
        if (!this.geoObjectManager) {
            return;
        }

        if (typeof this.geoObjectManager.disableDrawingMode !== 'function') {
            return;
        }

        this.geoObjectManager.disableDrawingMode();
    }

    /**
     * Clear temporary objects from the map
     */
    clearTempObjects() {
        if (!this.geoObjectManager) {
            return;
        }

        if (typeof this.geoObjectManager.clearTempObjects !== 'function') {
            return;
        }

        this.geoObjectManager.clearTempObjects();
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

// Initialize the map when the DOM is loaded
document.addEventListener('DOMContentLoaded', function () {
    // Create an instance of TacticalMapViewer
    const mapViewer = new TacticalMapViewer();

    // Objects are already loaded in the constructor, no need to load them again
});

export default TacticalMapViewer;
