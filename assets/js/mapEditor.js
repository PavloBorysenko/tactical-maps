// Import Leaflet
import L from 'leaflet';
import BaseMapComponent from './baseMapComponent';

/**
 * Map Editor - Handles Leaflet map for creating and editing maps
 * Extends BaseMapComponent for map editing functionality
 */
export default class MapEditor extends BaseMapComponent {
    /**
     * Initialize the map editor
     * @param {string} containerId - ID of the map container element
     * @param {Object} options - Configuration options
     */
    constructor(containerId, options = {}) {
        super();

        this.containerId = containerId;
        this.options = Object.assign(
            {
                initialLat: 51.505,
                initialLng: -0.09,
                initialZoom: 13,
                latFieldId: 'map_centerLat',
                lngFieldId: 'map_centerLng',
                zoomFieldId: 'map_zoomLevel',
                displayLatId: 'display-lat',
                displayLngId: 'display-lng',
                displayZoomId: 'display-zoom',
                tileLayerUrl:
                    'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', // OSM by default
                maxZoom: 19,
            },
            options
        );

        this.map = null;
        this.marker = null;
        this.baseLayers = {};
        this.layerControl = null;
        this.toolbar = null;

        // Initialize map when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.init());
        } else {
            this.init();
        }
    }

    /**
     * Initialize the map editor
     */
    init() {
        // Get container element
        const container = document.getElementById(this.containerId);
        if (!container) {
            console.error(
                `Map container with ID "${this.containerId}" not found.`
            );
            return;
        }

        try {
            // Get initial coordinates from form fields
            const coordinates = this.getInitialCoordinates();

            // Initialize map
            this.initializeLeafletMap(container, coordinates);

            // Initialize toolbar
            const mapData = {
                centerLat: coordinates.lat,
                centerLng: coordinates.lng,
                zoom: coordinates.zoom,
            };
            this.initializeToolbar(mapData);

            // Setup editor-specific functionality
            this.setupMapEditor(coordinates);

            // Force map size update
            this.invalidateMapSizeAfterDelay();
        } catch (error) {
            console.error('Error initializing map editor:', error);
        }
    }

    /**
     * Get initial coordinates from form fields
     * @returns {Object} Coordinates {lat, lng, zoom}
     */
    getInitialCoordinates() {
        return {
            lat: this.getValueFromField(
                this.options.latFieldId,
                this.options.initialLat
            ),
            lng: this.getValueFromField(
                this.options.lngFieldId,
                this.options.initialLng
            ),
            zoom: this.getValueFromField(
                this.options.zoomFieldId,
                this.options.initialZoom
            ),
        };
    }

    /**
     * Setup map editor specific functionality
     * @param {Object} coordinates - Initial coordinates
     */
    setupMapEditor(coordinates) {
        // Add draggable marker at center
        this.addCenterMarker(coordinates);

        // Set up event listeners
        this.setupEventListeners();

        // Update form fields with initial values
        this.updateFormFields(
            coordinates.lat,
            coordinates.lng,
            coordinates.zoom
        );
    }

    /**
     * Add draggable center marker
     * @param {Object} coordinates - Marker coordinates
     */
    addCenterMarker(coordinates) {
        this.marker = L.marker([coordinates.lat, coordinates.lng], {
            draggable: true,
            icon: this.options.centerIcon,
        }).addTo(this.map);
    }

    /**
     * Set up event listeners for map and marker
     */
    setupEventListeners() {
        // Update form when marker is dragged
        this.marker.on('dragend', () => {
            const position = this.marker.getLatLng();
            this.map.setView(position);
            this.updateFormFields(
                position.lat,
                position.lng,
                this.map.getZoom()
            );
        });

        // Update form when map is moved
        this.map.on('moveend', () => {
            const center = this.map.getCenter();
            this.marker.setLatLng(center);
            this.updateFormFields(center.lat, center.lng, this.map.getZoom());
        });

        // Update form when zoom changes
        this.map.on('zoomend', () => {
            const center = this.map.getCenter();
            this.updateFormFields(center.lat, center.lng, this.map.getZoom());
        });
    }

    /**
     * Get value from form field with fallback
     * @param {string} fieldId - ID of the form field
     * @param {*} fallback - Fallback value if field is not found or value is invalid
     * @returns {*} - The field value or fallback
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
     * Update form fields with map values
     * @param {number} lat - Latitude
     * @param {number} lng - Longitude
     * @param {number} zoom - Zoom level
     */
    updateFormFields(lat, lng, zoom) {
        // Update hidden form fields
        this.setFieldValue(this.options.latFieldId, lat.toFixed(6));
        this.setFieldValue(this.options.lngFieldId, lng.toFixed(6));
        this.setFieldValue(this.options.zoomFieldId, zoom);

        // Update display values
        this.setElementText(this.options.displayLatId, lat.toFixed(6));
        this.setElementText(this.options.displayLngId, lng.toFixed(6));
        this.setElementText(this.options.displayZoomId, zoom);
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
     * Destroy map editor and cleanup
     */
    destroy() {
        if (this.marker) {
            this.map.removeLayer(this.marker);
            this.marker = null;
        }

        super.destroy();
    }
}

// Initialize map editor
document.addEventListener('DOMContentLoaded', () => {
    // Check if map container exists
    const mapContainer = document.getElementById('map-container');
    if (!mapContainer) return;

    // Get map data from container attributes
    const initialLat = parseFloat(mapContainer.dataset.lat || 51.505);
    const initialLng = parseFloat(mapContainer.dataset.lng || -0.09);
    const initialZoom = parseInt(mapContainer.dataset.zoom || 13);

    const centerIcon = L.icon({
        iconUrl: '/build/images/frame.webp',
        iconSize: [32, 32],
        iconAnchor: [16, 16],
        popupAnchor: [0, -16],
    });

    // Create map editor
    new MapEditor('map-container', {
        initialLat,
        initialLng,
        initialZoom,
        centerIcon,
    });
});
