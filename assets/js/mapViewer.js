import MapGeoObjectManager from './mapGeoObjects';
import BaseMapComponent from './baseMapComponent';

/**
 * Tactical Map Viewer component
 * Extends BaseMapComponent for viewing and interacting with tactical maps
 */
class TacticalMapViewer extends BaseMapComponent {
    constructor(options = {}) {
        super();

        this.options = Object.assign(
            {
                mapContainerId: 'map-container',
                initialZoom: 13,
                maxZoom: 18,
            },
            options
        );

        this.container = document.getElementById(this.options.mapContainerId);
        if (!this.container) {
            console.warn(
                `Map container with ID "${this.options.mapContainerId}" not found`
            );
            return;
        }

        this.geoObjectManager = null;
        this.init();
    }

    /**
     * Initialize the map viewer
     */
    init() {
        try {
            // Get map coordinates from container
            const coordinates = this.getMapCoordinatesFromContainer(
                this.container,
                { zoom: this.options.initialZoom }
            );

            // Initialize map
            this.initializeLeafletMap(this.container, coordinates);

            // Initialize toolbar
            const mapData = {
                centerLat: coordinates.lat,
                centerLng: coordinates.lng,
                zoom: coordinates.zoom,
            };
            this.initializeToolbar(mapData);

            // Setup map-specific functionality
            this.setupMapViewer();

            // Initialize geo-objects manager
            this.initializeGeoObjectManager();

            // Force map size update
            this.invalidateMapSizeAfterDelay();
        } catch (error) {
            console.error('Error initializing map viewer:', error);
        }
    }

    /**
     * Setup map viewer specific functionality
     */
    setupMapViewer() {
        // Make the map object available globally
        window.tacticalMap = this;

        // Generate a user event to notify other scripts
        const mapReadyEvent = new CustomEvent('tactical-map-ready', {
            detail: { map: this },
        });
        document.dispatchEvent(mapReadyEvent);

        // Add event listener for geo objects refresh
        document.addEventListener('geo-objects-refresh', (event) => {
            this.handleGeoObjectsRefresh(event);
        });
    }

    /**
     * Initialize geo-objects manager
     */
    initializeGeoObjectManager() {
        try {
            this.geoObjectManager = new MapGeoObjectManager(this);

            // Load objects after manager is created
            const mapId = this.container.getAttribute('data-map-id');
            if (mapId) {
                this.loadGeoObjects(mapId);
            }
        } catch (error) {
            console.warn('Could not initialize geo object manager:', error);
        }
    }

    /**
     * Handle geo objects refresh event
     * @param {CustomEvent} event - Refresh event
     */
    handleGeoObjectsRefresh(event) {
        if (event.detail?.mapId && this.geoObjectManager) {
            this.geoObjectManager.clearGeoObjects();
            this.geoObjectManager.loadGeoObjects(event.detail.mapId);
        }
    }

    /**
     * Load geo objects for a specific map
     * @param {string} mapId - Map ID
     */
    loadGeoObjects(mapId) {
        if (!this.geoObjectManager?.loadGeoObjects) {
            return;
        }
        this.geoObjectManager.loadGeoObjects(mapId);
    }

    /**
     * Enable drawing mode for creating geo objects
     * @param {string} type - Drawing type
     * @param {Function} callback - Callback function
     */
    enableDrawingMode(type, callback) {
        if (!this.geoObjectManager?.enableDrawingMode) {
            return;
        }
        this.geoObjectManager.enableDrawingMode(type, callback);
    }

    /**
     * Disable drawing mode
     */
    disableDrawingMode() {
        if (!this.geoObjectManager?.disableDrawingMode) {
            return;
        }
        this.geoObjectManager.disableDrawingMode();
    }

    /**
     * Clear temporary objects from the map
     */
    clearTempObjects() {
        if (!this.geoObjectManager?.clearTempObjects) {
            return;
        }
        this.geoObjectManager.clearTempObjects();
    }

    /**
     * Focus on a specific geo object
     * @param {string} objectId - Object ID
     */
    focusOnObject(objectId) {
        if (this.geoObjectManager?.focusOnObject) {
            this.geoObjectManager.focusOnObject(objectId);
        }
    }

    /**
     * Show a specific GeoJSON object on the map (for editing)
     * @param {Object} geoJson - GeoJSON object
     * @param {string} type - Object type
     */
    showGeoJsonObject(geoJson, type) {
        if (this.geoObjectManager?.showGeoJsonObject) {
            this.geoObjectManager.showGeoJsonObject(geoJson, type);
        }
    }

    /**
     * Set cursor style for drawing mode
     * @param {string} type - Drawing type
     */
    setDrawingCursor(type) {
        if (!this.container) return;

        const cursorMap = {
            point: 'crosshair',
            polygon: 'pointer',
            line: 'pointer',
            circle: 'cell',
        };

        this.container.style.cursor = cursorMap[type] || 'default';
    }

    /**
     * Reset cursor to default
     */
    resetCursor() {
        if (this.container) {
            this.container.style.cursor = 'default';
        }
    }

    /**
     * Destroy map viewer and cleanup
     */
    destroy() {
        if (this.geoObjectManager) {
            this.geoObjectManager = null;
        }

        // Remove global reference
        if (window.tacticalMap === this) {
            delete window.tacticalMap;
        }

        super.destroy();
    }
}

// Initialize the map when the DOM is loaded
document.addEventListener('DOMContentLoaded', function () {
    // Create an instance of TacticalMapViewer
    const mapViewer = new TacticalMapViewer();
});

export default TacticalMapViewer;
