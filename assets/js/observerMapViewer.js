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
     * Display a single geo object on the map
     * @param {Object} object - Geo object data
     */
    displayGeoObject(object) {
        try {
            if (!object || !object.geoJson || !object.type) {
                return;
            }

            // Parse geoJson if it's a string
            const geoJson =
                typeof object.geoJson === 'string'
                    ? JSON.parse(object.geoJson)
                    : object.geoJson;

            let layer = null;
            const objectType = object.type.toLowerCase();

            // Create layer based on type
            switch (objectType) {
                case 'point':
                    layer = this.createPointLayer(geoJson, object);
                    break;
                case 'polygon':
                    layer = this.createPolygonLayer(geoJson, object);
                    break;
                case 'circle':
                    layer = this.createCircleLayer(geoJson, object);
                    break;
                case 'line':
                case 'linestring':
                    layer = this.createLineLayer(geoJson, object);
                    break;
                default:
                    console.warn(`Unknown geometry type: ${objectType}`);
                    return;
            }

            if (layer) {
                // For LayerGroup (line with icon), popup is already bound in createLineLayer
                // For other layer types, bind popup here
                if (!(layer instanceof L.LayerGroup)) {
                    layer.bindPopup(this.createPopupContent(object));
                }

                // Add to map
                layer.addTo(this.map);

                // Store reference
                this.geoObjectLayers[object.id] = {
                    layer: layer,
                    type: object.type,
                    data: object,
                };
            }
        } catch (error) {
            console.error('Error displaying geo object:', error);
        }
    }

    /**
     * Create popup content for observer (read-only)
     */
    createPopupContent(object) {
        console.log('Creating popup for object:', object);

        let content = `<div class="geo-popup-observer" data-object-id="${object.id}">`;

        // Add side information if available
        if (object.side && object.side.name) {
            content += `<div class="side-info mb-2">
                <span class="badge" style="
                    background-color: ${object.side.color || '#6c757d'}; 
                    color: white;
                    font-size: 0.9em;
                    padding: 0.4em 0.8em;
                    border-radius: 0.25rem;
                    font-weight: 600;
                ">
                    ${object.side.name}
                </span>
            </div>`;
        }

        content += `<h5>${
            object.title || object.name || 'Unnamed object'
        }</h5>`;

        if (object.description) {
            content += `<p>${object.description}</p>`;
        }

        // Show TTL info if available
        if (object.ttl > 0) {
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

            content += `<div class="ttl-info">
                <small class="text-muted">
                    <i class="fas fa-clock"></i> TTL: ${ttlDisplay}
                </small>
            </div>`;
        } else if (object.ttl === 0 || object.ttl === null) {
            content += `<div class="ttl-info">
                <small class="text-muted">
                    <i class="fas fa-infinity"></i> No expiration
                </small>
            </div>`;
        }

        // Show creation time
        if (object.createdAt) {
            content += `<div class="creation-info mt-2">
                <small class="text-muted">
                    <i class="fas fa-calendar"></i> Created: ${object.createdAt}
                </small>
            </div>`;
        }

        content += `</div>`;
        console.log('Generated popup content:', content);
        return content;
    }

    /**
     * Create point layer for observer display
     */
    createPointLayer(geoJson, objectData) {
        if (!geoJson || geoJson.type !== 'Point' || !geoJson.coordinates) {
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
    createPolygonLayer(geoJson, objectData) {
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

            // Bind popup to the layer group
            layerGroup.bindPopup(this.createPopupContent(objectData));

            return layerGroup;
        }

        return polygon;
    }

    /**
     * Create circle layer for observer display
     */
    createCircleLayer(geoJson, objectData) {
        if (
            !geoJson ||
            geoJson.type !== 'Circle' ||
            !geoJson.coordinates ||
            !geoJson.radius
        ) {
            return null;
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

            // Bind popup to the layer group
            layerGroup.bindPopup(this.createPopupContent(objectData));

            return layerGroup;
        }

        return circle;
    }

    /**
     * Create line layer for observer display
     */
    createLineLayer(geoJson, objectData) {
        console.log(
            'Creating line layer with geoJson:',
            geoJson,
            'objectData:',
            objectData
        );

        if (
            !geoJson ||
            (geoJson.type !== 'LineString' && geoJson.type !== 'Line') ||
            !geoJson.coordinates
        ) {
            console.warn('Invalid line geometry:', geoJson);
            return null;
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

            // Bind popup to the layer group
            layerGroup.bindPopup(this.createPopupContent(objectData));

            return layerGroup;
        }

        return line;
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
