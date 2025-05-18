// Import Leaflet
import L from 'leaflet';

L.Icon.Default.prototype.options.imagePath = '/build/images/leaflet/';

/**
 * Map Viewer - Handles Leaflet map for viewing maps and geo objects
 */
export default class MapViewer {
    /**
     * Initialize the map viewer
     * @param {string} containerId - ID of the map container element
     * @param {Object} options - Configuration options
     */
    constructor(containerId, options = {}) {
        this.containerId = containerId;
        this.options = Object.assign(
            {
                centerLat: 51.505,
                centerLng: -0.09,
                zoomLevel: 13,
                tileLayerUrl:
                    'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                maxZoom: 19,
                showCenterMarker: true,
                geoObjects: [],
                title: 'Map Center',
            },
            options
        );

        this.map = null;

        // Initialize map when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.init());
        } else {
            this.init();
        }
    }

    /**
     * Initialize the map
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

        // Create map with initial values
        this.map = L.map(this.containerId).setView(
            [this.options.centerLat, this.options.centerLng],
            this.options.zoomLevel
        );

        // Add tile layer
        L.tileLayer(this.options.tileLayerUrl, {
            maxZoom: this.options.maxZoom,
            attribution:
                '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).addTo(this.map);

        // Add center marker if required
        if (this.options.showCenterMarker) {
            L.marker([this.options.centerLat, this.options.centerLng])
                .addTo(this.map)
                .bindPopup(this.options.title)
                .openPopup();
        }

        // Add geo objects
        this.addGeoObjects();

        // Force a map resize after a short delay
        setTimeout(() => {
            this.map.invalidateSize();
        }, 100);
    }

    /**
     * Add geo objects to the map
     */
    addGeoObjects() {
        if (!this.options.geoObjects || !this.options.geoObjects.length) {
            return;
        }

        this.options.geoObjects.forEach((obj) => {
            // Handle different geo object types
            switch (obj.type) {
                case 'point':
                    this.addPoint(obj);
                    break;
                case 'polygon':
                    this.addPolygon(obj);
                    break;
                case 'polyline':
                    this.addPolyline(obj);
                    break;
                // Add more types as needed
            }
        });
    }

    /**
     * Add a point to the map
     * @param {Object} point - Point object with coordinates and properties
     */
    addPoint(point) {
        if (!point.lat || !point.lng) return;

        L.marker([point.lat, point.lng])
            .addTo(this.map)
            .bindPopup(point.name || 'Point');
    }

    /**
     * Add a polygon to the map
     * @param {Object} polygon - Polygon object with coordinates and properties
     */
    addPolygon(polygon) {
        if (!polygon.coordinates || !polygon.coordinates.length) return;

        L.polygon(polygon.coordinates, {
            color: polygon.color || 'blue',
            fillColor: polygon.fillColor || '#3388ff',
            fillOpacity: polygon.fillOpacity || 0.2,
        })
            .addTo(this.map)
            .bindPopup(polygon.name || 'Polygon');
    }

    /**
     * Add a polyline to the map
     * @param {Object} polyline - Polyline object with coordinates and properties
     */
    addPolyline(polyline) {
        if (!polyline.coordinates || !polyline.coordinates.length) return;

        L.polyline(polyline.coordinates, {
            color: polyline.color || 'red',
            weight: polyline.weight || 3,
        })
            .addTo(this.map)
            .bindPopup(polyline.name || 'Polyline');
    }
}

// Initialize map viewer when document is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Check if map display exists
    const mapDisplay = document.getElementById('map-display');
    if (!mapDisplay) return;

    // Get map data from container attributes
    const centerLat = parseFloat(mapDisplay.dataset.lat || 51.505);
    const centerLng = parseFloat(mapDisplay.dataset.lng || -0.09);
    const zoomLevel = parseInt(mapDisplay.dataset.zoom || 13);
    const title = mapDisplay.dataset.title || 'Map Center';

    // Parse geo objects from data attribute if present
    const geoObjectsJson = mapDisplay.dataset.geoObjects;
    const geoObjects = geoObjectsJson ? JSON.parse(geoObjectsJson) : [];

    // Create map viewer
    new MapViewer('map-display', {
        centerLat,
        centerLng,
        zoomLevel,
        geoObjects,
        title,
    });
});
