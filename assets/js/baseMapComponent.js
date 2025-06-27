import L from 'leaflet';
import MapLayers from './mapLayers';
import MapToolbar from './mapToolbar';

// Fix Leaflet default icons path
L.Icon.Default.prototype.options.imagePath = '/build/images/leaflet/';

/**
 * Base Map Component - Common functionality for map components
 * Provides shared methods for map initialization, toolbar management, and utilities
 */
export default class BaseMapComponent {
    constructor() {
        this.map = null;
        this.baseLayers = {};
        this.layerControl = null;
        this.toolbar = null;
    }

    /**
     * Get map coordinates from container attributes
     * @param {HTMLElement} container - Map container element
     * @param {Object} defaults - Default coordinates
     * @returns {Object} Map coordinates {lat, lng, zoom}
     */
    getMapCoordinatesFromContainer(container, defaults = {}) {
        const centerLat =
            parseFloat(container.getAttribute('data-map-center-lat')) ||
            defaults.lat ||
            51.505;

        const centerLng =
            parseFloat(container.getAttribute('data-map-center-lng')) ||
            defaults.lng ||
            -0.09;

        const zoom =
            parseInt(container.getAttribute('data-map-zoom')) ||
            defaults.zoom ||
            13;

        return { lat: centerLat, lng: centerLng, zoom };
    }

    /**
     * Initialize Leaflet map with layers
     * @param {string|HTMLElement} container - Container ID or element
     * @param {Object} coordinates - Map coordinates {lat, lng, zoom}
     * @param {Object} options - Map options
     * @returns {L.Map} Leaflet map instance
     */
    initializeLeafletMap(container, coordinates, options = {}) {
        const defaultOptions = {
            center: [coordinates.lat, coordinates.lng],
            zoom: coordinates.zoom,
            zoomControl: true,
        };

        const mapOptions = { ...defaultOptions, ...options };
        this.map = L.map(container, mapOptions);

        // Initialize map with layers
        const layersData = MapLayers.initializeMapWithLayers(this.map);
        this.baseLayers = layersData.baseLayers;
        this.layerControl = layersData.layerControl;

        return this.map;
    }

    /**
     * Initialize map toolbar
     * @param {Object} mapData - Map data for toolbar
     * @returns {MapToolbar} Toolbar instance
     */
    initializeToolbar(mapData) {
        if (!this.map) {
            throw new Error('Map must be initialized before toolbar');
        }

        this.toolbar = new MapToolbar(
            this.map,
            mapData,
            this.baseLayers,
            this.layerControl
        );

        return this.toolbar;
    }

    /**
     * Force map size invalidation after delay
     * @param {number} delay - Delay in milliseconds
     */
    invalidateMapSizeAfterDelay(delay = 100) {
        if (this.map) {
            setTimeout(() => {
                this.map.invalidateSize();
            }, delay);
        }
    }

    /**
     * Get value from form field with fallback
     * @param {string} fieldId - ID of the form field
     * @param {*} fallback - Fallback value if field is not found or value is invalid
     * @returns {*} The field value or fallback
     */
    getValueFromField(fieldId, fallback) {
        const field = document.getElementById(fieldId);
        if (!field || field.value === '') {
            return fallback;
        }

        const value = field.value;
        if (isNaN(value)) {
            return fallback;
        }

        return parseFloat(value);
    }

    /**
     * Set value of a form field
     * @param {string} fieldId - ID of the form field
     * @param {*} value - Value to set
     */
    setFieldValue(fieldId, value) {
        const field = document.getElementById(fieldId);
        if (field) {
            field.value = value;
        }
    }

    /**
     * Set text content of an element
     * @param {string} elementId - ID of the element
     * @param {string} text - Text to set
     */
    setElementText(elementId, text) {
        const element = document.getElementById(elementId);
        if (element) {
            element.textContent = text;
        }
    }

    /**
     * Destroy map component and cleanup
     */
    destroy() {
        if (this.toolbar) {
            this.toolbar.destroy();
            this.toolbar = null;
        }

        if (this.map) {
            this.map.remove();
            this.map = null;
        }

        this.baseLayers = {};
        this.layerControl = null;
    }

    /**
     * Get the Leaflet map instance
     * @returns {L.Map} Map instance
     */
    getLeafletMap() {
        return this.map;
    }
}
