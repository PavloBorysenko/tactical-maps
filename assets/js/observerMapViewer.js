import L from 'leaflet';
import BaseMapComponent from './baseMapComponent';

/**
 * Simple Map Viewer for Observers
 * Only displays active geo objects without filters or edit functionality
 */
class ObserverMapViewer extends BaseMapComponent {
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

        this.geoObjectLayers = {};
        this.init();
    }

    /**
     * Initialize the observer map viewer
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

            // Initialize toolbar (basic tools only)
            const mapData = {
                centerLat: coordinates.lat,
                centerLng: coordinates.lng,
                zoom: coordinates.zoom,
            };
            this.initializeToolbar(mapData);

            // Make the map object available globally
            window.tacticalMap = this;

            // Generate a user event to notify other scripts
            const mapReadyEvent = new CustomEvent('tactical-map-ready', {
                detail: { map: this },
            });
            document.dispatchEvent(mapReadyEvent);

            // Force map size update
            this.invalidateMapSizeAfterDelay();

            console.log('Observer Map Viewer initialized');
        } catch (error) {
            console.error('Error initializing observer map viewer:', error);
        }
    }

    /**
     * Load and display geo objects for observer
     * @param {Array} geoObjects - Array of geo objects to display
     */
    loadGeoObjects(geoObjects) {
        if (!Array.isArray(geoObjects)) {
            console.warn('Invalid geo objects data provided');
            return;
        }

        // Clear existing objects first
        this.clearGeoObjects();

        // Display each object
        geoObjects.forEach((object) => {
            this.displayGeoObject(object);
        });

        console.log(`Loaded ${geoObjects.length} geo objects for observer`);
    }

    /**
     * Universal method to bind popup to any layer type (including LayerGroup)
     * Following DRY principle - same popup behavior for all object types
     * @param {L.Layer} layer - Leaflet layer (can be single layer or LayerGroup)
     * @param {Object} objectData - Object data for popup content
     */
    bindPopupToLayer(layer, objectData) {
        const popupContent = this.createPopupContent(objectData);

        if (layer instanceof L.LayerGroup) {
            // For LayerGroup (geometry + icon), bind popup to ALL child layers
            layer.eachLayer((childLayer) => {
                childLayer.bindPopup(popupContent);
            });
        } else {
            // For single layer (like Point), bind popup directly
            layer.bindPopup(popupContent);
        }
    }

    /**
     * Display a single geo object on the map
     * @param {Object} object - Geo object data
     */
    displayGeoObject(object) {
        try {
            if (!object || !object.geoJson || !object.type) {
                console.warn('Invalid object data:', object);
                return;
            }

            // Parse geoJson if it's a string
            const geoJson =
                typeof object.geoJson === 'string'
                    ? JSON.parse(object.geoJson)
                    : object.geoJson;

            let layer = null;
            const objectType = object.type.toLowerCase();

            // Create layer based on type - simplified approach
            switch (objectType) {
                case 'point':
                    layer = this.createPointLayer(geoJson, object);
                    break;
                case 'polygon':
                    const polygonResult = this.createPolygonLayer(
                        geoJson,
                        object,
                        true
                    );
                    layer = polygonResult.layer;
                    break;
                case 'circle':
                    const circleResult = this.createCircleLayer(
                        geoJson,
                        object,
                        true
                    );
                    layer = circleResult.layer;
                    break;
                case 'line':
                case 'linestring':
                    const lineResult = this.createLineLayer(
                        geoJson,
                        object,
                        true
                    );
                    layer = lineResult.layer;
                    break;
                default:
                    console.warn(`Unknown geometry type: ${objectType}`);
                    return;
            }

            if (layer) {
                // Use universal popup binding method (DRY principle)
                this.bindPopupToLayer(layer, object);

                // Add to map
                layer.addTo(this.map);

                // Store reference (simplified - no need for mainLayer anymore)
                this.geoObjectLayers[object.id] = {
                    layer: layer,
                    type: object.type,
                    data: object,
                };
            } else {
                console.error('Failed to create layer for object:', object);
            }
        } catch (error) {
            console.error(
                'Error displaying geo object:',
                error,
                'object:',
                object
            );
        }
    }

    /**
     * Create popup content for observer (read-only)
     * Uses base method to eliminate code duplication (DRY principle)
     */
    createPopupContent(object) {
        // Use base method with observer-specific options
        const content = this.createBasePopupContent(object, {
            cssClass: 'geo-popup-observer',
            showCreatedAt: true, // Show creation time for observers
            showActions: false, // No edit/delete buttons for observers
            showVisibility: false, // No visibility info for observers
        });

        return content;
    }

    /**
     * Create point layer for observer display
     */
    createPointLayer(geoJson, objectData) {
        if (!geoJson || !geoJson.coordinates) {
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

        // Use colored marker based on side
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

        // Default marker
        return L.marker(latlng);
    }

    /**
     * Create polygon layer for observer display
     */
    createPolygonLayer(geoJson, objectData, returnBothLayers = false) {
        if (!geoJson || !geoJson.coordinates || !geoJson.coordinates[0]) {
            return { layer: null };
        }

        const points = geoJson.coordinates[0].map((coord) =>
            L.latLng(coord[1], coord[0])
        );

        const sideColor =
            objectData && objectData.side && objectData.side.color
                ? objectData.side.color
                : '#007bff';

        const polygon = L.polygon(points, {
            color: sideColor,
            weight: 2,
            fillOpacity: 0.3,
            fillColor: sideColor,
        });

        // Add icon at center if available
        if (objectData && objectData.iconUrl) {
            const bounds = polygon.getBounds();
            const center = bounds.getCenter();
            const customIcon = L.icon({
                iconUrl: objectData.iconUrl,
                iconSize: [24, 24],
                iconAnchor: [12, 12],
                popupAnchor: [0, -12],
            });
            const iconMarker = L.marker(center, { icon: customIcon });
            const layerGroup = L.layerGroup([polygon, iconMarker]);

            // Return LayerGroup - popup will be bound to all children by bindPopupToLayer
            return { layer: layerGroup };
        }

        return { layer: polygon };
    }

    /**
     * Create circle layer for observer display
     */
    createCircleLayer(geoJson, objectData, returnBothLayers = false) {
        if (!geoJson || !geoJson.coordinates || !geoJson.radius) {
            return { layer: null };
        }

        const center = L.latLng(geoJson.coordinates[1], geoJson.coordinates[0]);
        const sideColor =
            objectData && objectData.side && objectData.side.color
                ? objectData.side.color
                : '#dc3545';

        const circle = L.circle(center, {
            radius: geoJson.radius,
            color: sideColor,
            weight: 2,
            fillOpacity: 0.2,
            fillColor: sideColor,
        });

        // Add icon at center if available
        if (objectData && objectData.iconUrl) {
            const customIcon = L.icon({
                iconUrl: objectData.iconUrl,
                iconSize: [24, 24],
                iconAnchor: [12, 12],
                popupAnchor: [0, -12],
            });
            const iconMarker = L.marker(center, { icon: customIcon });
            const layerGroup = L.layerGroup([circle, iconMarker]);

            // Return LayerGroup - popup will be bound to all children by bindPopupToLayer
            return { layer: layerGroup };
        }

        return { layer: circle };
    }

    /**
     * Create line layer for observer display
     */
    createLineLayer(geoJson, objectData, returnBothLayers = false) {
        if (!geoJson || !geoJson.coordinates) {
            console.warn('Invalid line geometry:', geoJson);
            return { layer: null };
        }

        const points = geoJson.coordinates.map((coord) =>
            L.latLng(coord[1], coord[0])
        );

        const sideColor =
            objectData && objectData.side && objectData.side.color
                ? objectData.side.color
                : '#28a745';

        const line = L.polyline(points, {
            color: sideColor,
            weight: 3,
        });

        // Add icon at middle point if available
        if (objectData && objectData.iconUrl) {
            const middleIndex = Math.floor(points.length / 2);
            const center = points[middleIndex];
            const customIcon = L.icon({
                iconUrl: objectData.iconUrl,
                iconSize: [24, 24],
                iconAnchor: [12, 12],
                popupAnchor: [0, -12],
            });
            const iconMarker = L.marker(center, { icon: customIcon });
            const layerGroup = L.layerGroup([line, iconMarker]);

            // Return LayerGroup - popup will be bound to all children by bindPopupToLayer
            return { layer: layerGroup };
        }

        return { layer: line };
    }

    /**
     * Clear all geo objects from the map
     */
    clearGeoObjects() {
        Object.values(this.geoObjectLayers).forEach((item) => {
            if (item.layer) {
                this.map.removeLayer(item.layer);
            }
        });
        this.geoObjectLayers = {};
    }

    /**
     * Get the Leaflet map instance
     */
    getLeafletMap() {
        return this.map;
    }
}

// Initialize the observer map viewer when the DOM is loaded
document.addEventListener('DOMContentLoaded', function () {
    // Create an instance of ObserverMapViewer only if map container exists
    const mapContainer = document.getElementById('map-container');
    if (mapContainer) {
        const observerMapViewer = new ObserverMapViewer();
    }
});

// Make available globally
window.ObserverMapViewer = ObserverMapViewer;

export default ObserverMapViewer;
