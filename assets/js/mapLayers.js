import L from 'leaflet';

/**
 * Map Layers Manager
 * Centralized management of map base layers
 */
export default class MapLayers {
    /**
     * Get all available base layers
     * @returns {Object} Object with layer names as keys and Leaflet layers as values
     */
    static getBaseLayers() {
        return {
            // OpenStreetMap
            'Street Map': L.tileLayer(
                'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                {
                    attribution:
                        '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                    maxZoom: 19,
                }
            ),

            // Satellite layer (Esri World Imagery)
            Satellite: L.tileLayer(
                'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
                {
                    attribution:
                        'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
                    maxZoom: 18,
                }
            ),

            // Hybrid layer (Satellite with labels)
            Hybrid: L.tileLayer(
                'https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}',
                {
                    attribution: '&copy; Google',
                    maxZoom: 20,
                }
            ),

            // Topographic layer
            Topographic: L.tileLayer(
                'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
                {
                    attribution:
                        'Map data: &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, <a href="http://viewfinderpanoramas.org">SRTM</a> | Map style: &copy; <a href="https://opentopomap.org">OpenTopoMap</a>',
                    maxZoom: 17,
                }
            ),

            // CartoDB Positron (Light theme)
            'Light Theme': L.tileLayer(
                'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
                {
                    attribution:
                        '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
                    maxZoom: 19,
                }
            ),

            // CartoDB Dark Matter (Dark theme)
            'Dark Theme': L.tileLayer(
                'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
                {
                    attribution:
                        '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
                    maxZoom: 19,
                }
            ),
        };
    }

    /**
     * Get default layer name
     * @returns {string} Default layer name
     */
    static getDefaultLayerName() {
        return 'Street Map';
    }

    /**
     * Add layer control to map
     * @param {L.Map} map - Leaflet map instance
     * @param {Object} baseLayers - Base layers object
     * @param {Object} options - Control options
     * @returns {L.Control.Layers} Layer control instance
     */
    static addLayerControl(map, baseLayers, options = {}) {
        const defaultOptions = {
            position: 'topright',
            collapsed: true,
        };

        const controlOptions = Object.assign(defaultOptions, options);

        const layerControl = L.control.layers(baseLayers, null, controlOptions);
        layerControl.addTo(map);

        setTimeout(() => {
            const controlElement = layerControl.getContainer();
            if (
                controlElement &&
                !controlElement.classList.contains(
                    'leaflet-control-layers-expanded'
                )
            ) {
                const toggleButton = controlElement.querySelector(
                    '.leaflet-control-layers-toggle'
                );
                if (toggleButton) {
                    toggleButton.click();
                }
            }
        }, 100);

        return layerControl;
    }

    /**
     * Initialize map with base layers and layer control
     * @param {L.Map} map - Leaflet map instance
     * @param {Object} options - Options for layer control
     * @returns {Object} Object containing baseLayers and layerControl
     */
    static initializeMapWithLayers(map, options = {}) {
        const baseLayers = this.getBaseLayers();
        const defaultLayerName = this.getDefaultLayerName();

        // Add default layer to map
        baseLayers[defaultLayerName].addTo(map);

        // Add layer control
        const layerControl = this.addLayerControl(map, baseLayers, options);

        return {
            baseLayers,
            layerControl,
            defaultLayer: baseLayers[defaultLayerName],
        };
    }
}
