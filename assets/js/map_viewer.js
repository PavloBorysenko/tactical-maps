import L from 'leaflet';
import MapGeoObjectManager from './map_geo_objects';

console.log('map_viewer.js загружен');

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

        console.log('TacticalMapViewer: Initializing map...');

        this.container = document.getElementById(this.options.mapContainerId);
        if (!this.container) {
            console.error(
                `Map container with ID "${this.options.mapContainerId}" not found.`
            );
            return;
        }

        console.log('TacticalMapViewer: Container found:', this.container);

        // Важно! Сначала инициализируем карту
        this.initMap();

        // Затем создаем менеджер гео-объектов
        console.log('MapGeoObjectManager импортирован:', MapGeoObjectManager);
        try {
            console.log('Creating new MapGeoObjectManager instance...');
            this.geoObjectManager = new MapGeoObjectManager(this);
            console.log('GeoObjectManager initialized:', this.geoObjectManager);

            // Загружаем объекты ТОЛЬКО ПОСЛЕ создания менеджера
            const mapId = this.container.getAttribute('data-map-id');
            if (mapId) {
                this.loadGeoObjects(mapId);
            }
        } catch (error) {
            console.error('Error creating GeoObjectManager:', error);
        }
    }

    /**
     * Initialize the Leaflet map
     */
    initMap() {
        try {
            // Получаем настройки карты из атрибутов контейнера
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

            console.log('Map settings:', { mapId, centerLat, centerLng, zoom });

            // Создаем Leaflet-карту
            this.map = L.map(this.container, {
                center: [centerLat, centerLng],
                zoom: zoom,
                zoomControl: true,
            });

            // Добавляем слой тайлов OpenStreetMap
            L.tileLayer(this.options.tileUrl, {
                attribution: this.options.attribution,
                maxZoom: this.options.maxZoom,
            }).addTo(this.map);

            // Принудительно обновляем размер карты после инициализации
            setTimeout(() => {
                this.map.invalidateSize();
                console.log('Map size invalidated');
            }, 100);

            console.log('Leaflet map initialized successfully', this.map);

            // Делаем объект карты доступным глобально
            window.tacticalMap = this;
            console.log('Map exposed globally as window.tacticalMap');

            // Генерируем пользовательское событие для оповещения других скриптов
            const mapReadyEvent = new CustomEvent('tactical-map-ready', {
                detail: { map: this },
            });
            document.dispatchEvent(mapReadyEvent);
        } catch (error) {
            console.error('Error initializing map:', error);
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
        console.log(
            'TacticalMapViewer.loadGeoObjects called with mapId:',
            mapId
        );
        console.log('this.geoObjectManager:', this.geoObjectManager);

        if (!this.geoObjectManager) {
            console.error('GeoObjectManager not initialized');
            return;
        }

        if (typeof this.geoObjectManager.loadGeoObjects !== 'function') {
            console.error(
                'loadGeoObjects is not a function on geoObjectManager',
                this.geoObjectManager
            );
            return;
        }

        this.geoObjectManager.loadGeoObjects(mapId);
    }

    /**
     * Enable drawing mode for creating geo objects
     */
    enableDrawingMode(type, callback) {
        console.log(
            'TacticalMapViewer.enableDrawingMode called with type:',
            type
        );

        if (!this.geoObjectManager) {
            console.error('GeoObjectManager not initialized');
            return;
        }

        if (typeof this.geoObjectManager.enableDrawingMode !== 'function') {
            console.error(
                'enableDrawingMode is not a function on geoObjectManager',
                this.geoObjectManager
            );
            return;
        }

        this.geoObjectManager.enableDrawingMode(type, callback);
    }

    /**
     * Disable drawing mode
     */
    disableDrawingMode() {
        console.log('TacticalMapViewer.disableDrawingMode called');

        if (!this.geoObjectManager) {
            console.error('GeoObjectManager not initialized');
            return;
        }

        if (typeof this.geoObjectManager.disableDrawingMode !== 'function') {
            console.error(
                'disableDrawingMode is not a function on geoObjectManager',
                this.geoObjectManager
            );
            return;
        }

        this.geoObjectManager.disableDrawingMode();
    }

    /**
     * Clear temporary objects from the map
     */
    clearTempObjects() {
        console.log('TacticalMapViewer.clearTempObjects called');

        if (!this.geoObjectManager) {
            console.error('GeoObjectManager not initialized');
            return;
        }

        if (typeof this.geoObjectManager.clearTempObjects !== 'function') {
            console.error(
                'clearTempObjects is not a function on geoObjectManager',
                this.geoObjectManager
            );
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
        console.log(
            'TacticalMapViewer.setDrawingCursor called with type:',
            type
        );

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
        console.log('TacticalMapViewer.resetCursor called');

        if (this.container) {
            this.container.style.cursor = 'default';
        }
    }
}

// Инициализация карты при загрузке DOM
document.addEventListener('DOMContentLoaded', function () {
    console.log('DOM loaded, initializing TacticalMapViewer');

    // Создаем экземпляр TacticalMapViewer
    const mapViewer = new TacticalMapViewer();

    // Загружаем гео-объекты ПОСЛЕ полной инициализации
    const mapContainer = document.getElementById('map-container');
    if (mapContainer && mapViewer.geoObjectManager) {
        const mapId = mapContainer.getAttribute('data-map-id');
        if (mapId) {
            console.log(
                'Loading geo objects for map ID after initialization:',
                mapId
            );
            setTimeout(() => {
                mapViewer.loadGeoObjects(mapId);
            }, 500);
        }
    }
});

export default TacticalMapViewer;
