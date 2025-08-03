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
     * Create base popup content for geo objects (DRY principle)
     * This method contains shared popup logic, can be extended by child classes
     * @param {Object} object - Geo object data
     * @param {Object} options - Popup options
     * @returns {string} HTML content for popup
     */
    createBasePopupContent(object, options = {}) {
        const {
            cssClass = 'geo-popup',
            showCreatedAt = false,
            showActions = false,
            showVisibility = false,
        } = options;

        let content = `<div class="${cssClass}" data-object-id="${object.id}">`;

        // Add side information if available (common for all popups)
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

        // Add title (common for all popups)
        content += `<h5>${
            object.title || object.name || 'Unnamed object'
        }</h5>`;

        // Add description if available (common for all popups)
        if (object.description) {
            content += `<p>${object.description}</p>`;
        }

        // Add visibility info (admin only)
        if (showVisibility && object.isExpired !== undefined) {
            const visibilityIcon = object.isExpired
                ? '<i class="fas fa-eye-slash text-danger"></i>'
                : '<i class="fas fa-eye text-success"></i>';
            const visibilityText = object.isExpired
                ? 'Expired (not visible)'
                : 'Visible';
            const visibilityClass = object.isExpired
                ? 'text-danger'
                : 'text-success';

            content += `<div class="visibility-info mb-2">
                <small class="${visibilityClass}">
                    ${visibilityIcon} ${visibilityText}
                </small>
            </div>`;
        }

        // Add TTL information (common logic with different display)
        content += this.formatTTLInfo(object);

        // Add creation time (observer only)
        if (showCreatedAt && object.createdAt) {
            content += `<div class="creation-info mt-2">
                <small class="text-muted">
                    <i class="fas fa-calendar"></i> Created: ${object.createdAt}
                </small>
            </div>`;
        }

        // Add action buttons (admin only)
        if (showActions) {
            content += `
                <div class="popup-actions mt-2">
                    <button class="btn btn-sm btn-primary popup-edit-btn" data-object-id="${object.id}">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button class="btn btn-sm btn-danger popup-delete-btn" data-object-id="${object.id}">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>`;
        }

        content += `</div>`;
        return content;
    }

    /**
     * Format TTL information for popup (DRY principle)
     * @param {Object} object - Geo object data
     * @returns {string} HTML for TTL info
     */
    formatTTLInfo(object) {
        if (object.ttl > 0) {
            // Format TTL for display (common logic)
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

            return `<div class="ttl-info">
                <small class="text-muted">
                    <i class="fas fa-clock"></i> TTL: ${ttlDisplay}
                </small>
            </div>`;
        } else if (object.ttl === 0 || object.ttl === null) {
            return `<div class="ttl-info">
                <small class="text-muted">
                    <i class="fas fa-infinity"></i> No expiration
                </small>
            </div>`;
        }
        return '';
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
