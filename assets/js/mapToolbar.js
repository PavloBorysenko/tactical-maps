import L from 'leaflet';

/**
 * Map Toolbar - Additional tools for map interaction
 */
export default class MapToolbar {
    constructor(map, mapData = {}, baseLayers = null, layerControl = null) {
        this.map = map;
        this.mapData = mapData; // Данные карты (центр, зум и т.д.)
        this.baseLayers = baseLayers; // Базовые слои карты
        this.layerControl = layerControl; // Контроль слоев Leaflet
        this.toolbar = null;
        this.coordinatesMode = false;
        this.distanceMode = false;
        this.distanceMarkers = [];
        this.distancePolyline = null;
        this.coordinateTooltip = null;
        this.currentLayer = null;

        // Find current active layer
        if (this.baseLayers) {
            const layerNames = Object.keys(this.baseLayers);
            this.map.eachLayer((layer) => {
                layerNames.forEach((name) => {
                    if (this.baseLayers[name] === layer) {
                        this.currentLayer = layer;
                    }
                });
            });
        }

        this.init();
    }

    /**
     * Initialize toolbar
     */
    init() {
        this.createToolbar();
        this.attachEventListeners();
    }

    /**
     * Create toolbar UI
     */
    createToolbar() {
        // Create toolbar container
        this.toolbar = L.DomUtil.create(
            'div',
            'map-toolbar map-toolbar-horizontal'
        );

        // Create buttons
        this.createCenterButton();
        this.createLayerSelector();
        this.createCoordinatesToggle();
        this.createDistanceToggle();

        // Add to map
        this.map.getContainer().appendChild(this.toolbar);

        // Hide default layer control if it exists
        if (this.layerControl) {
            const layerControlElement = this.layerControl.getContainer();
            if (layerControlElement) {
                layerControlElement.style.display = 'none';
            }
        }
    }

    /**
     * Create center button
     */
    createCenterButton() {
        const centerBtn = L.DomUtil.create(
            'button',
            'toolbar-btn center-btn toolbar-icon-center',
            this.toolbar
        );
        centerBtn.innerHTML = ''; // Удаляем символ, будем использовать CSS
        centerBtn.title = 'Center Map';
        centerBtn.type = 'button';

        // Try to load custom icon
        this.loadCustomIcon(centerBtn, 'center');

        L.DomEvent.on(centerBtn, 'click', (e) => {
            L.DomEvent.stopPropagation(e);
            this.centerMap();
        });
    }

    /**
     * Create layer selector
     */
    createLayerSelector() {
        if (!this.baseLayers) return;

        const layerWrapper = L.DomUtil.create(
            'div',
            'toolbar-layer-selector-wrapper',
            this.toolbar
        );

        // Create button with icon
        const layerButton = L.DomUtil.create(
            'button',
            'toolbar-btn layer-btn toolbar-icon-layer',
            layerWrapper
        );
        layerButton.innerHTML = ''; // Icon will be set via CSS
        layerButton.title = 'Select Map Layer';
        layerButton.type = 'button';

        // Try to load custom icon
        this.loadCustomIcon(layerButton, 'layer');

        // Create dropdown menu
        const dropdownMenu = L.DomUtil.create(
            'div',
            'toolbar-layer-dropdown',
            layerWrapper
        );
        dropdownMenu.style.display = 'none';

        // Get layer names and current layer
        const layerNames = Object.keys(this.baseLayers);
        let currentLayerName = 'Satellite'; // Default layer from mapLayers.js

        // Find current active layer
        this.map.eachLayer((layer) => {
            layerNames.forEach((name) => {
                if (this.baseLayers[name] === layer) {
                    currentLayerName = name;
                }
            });
        });

        // Create dropdown items for each layer
        layerNames.forEach((layerName) => {
            const dropdownItem = L.DomUtil.create(
                'div',
                'toolbar-layer-dropdown-item',
                dropdownMenu
            );
            dropdownItem.textContent = layerName;
            dropdownItem.dataset.layer = layerName;

            if (layerName === currentLayerName) {
                dropdownItem.classList.add('active');
            }

            // Handle layer selection
            L.DomEvent.on(dropdownItem, 'click', (e) => {
                L.DomEvent.stopPropagation(e);
                this.changeLayer(layerName);
                this.updateActiveLayerInDropdown(layerName);
                this.hideLayerDropdown();
            });
        });

        // Handle button click to toggle dropdown
        L.DomEvent.on(layerButton, 'click', (e) => {
            L.DomEvent.stopPropagation(e);
            this.toggleLayerDropdown();
        });

        // Store references
        this.layerButton = layerButton;
        this.layerDropdown = dropdownMenu;

        // Prevent map interaction when using selector
        L.DomEvent.disableClickPropagation(layerWrapper);
        L.DomEvent.disableScrollPropagation(layerWrapper);

        // Close dropdown when clicking outside
        document.addEventListener('click', () => {
            this.hideLayerDropdown();
        });
    }

    /**
     * Toggle layer dropdown visibility
     */
    toggleLayerDropdown() {
        if (!this.layerDropdown) return;

        const isVisible = this.layerDropdown.style.display !== 'none';
        if (isVisible) {
            this.hideLayerDropdown();
        } else {
            this.showLayerDropdown();
        }
    }

    /**
     * Show layer dropdown
     */
    showLayerDropdown() {
        if (!this.layerDropdown) return;

        this.layerDropdown.style.display = 'block';
        this.layerButton.classList.add('active');
    }

    /**
     * Hide layer dropdown
     */
    hideLayerDropdown() {
        if (!this.layerDropdown) return;

        this.layerDropdown.style.display = 'none';
        this.layerButton.classList.remove('active');
    }

    /**
     * Update active layer in dropdown
     */
    updateActiveLayerInDropdown(activeLayerName) {
        if (!this.layerDropdown) return;

        const items = this.layerDropdown.getElementsByClassName(
            'toolbar-layer-dropdown-item'
        );
        Array.from(items).forEach((item) => {
            item.classList.remove('active');
            if (item.dataset.layer === activeLayerName) {
                item.classList.add('active');
            }
        });
    }

    /**
     * Change map layer
     */
    changeLayer(layerName) {
        if (!this.baseLayers || !this.baseLayers[layerName]) return;

        // Remove current layer
        if (this.currentLayer) {
            this.map.removeLayer(this.currentLayer);
        }

        // Add new layer
        this.currentLayer = this.baseLayers[layerName];
        this.map.addLayer(this.currentLayer);

        // Move layer to back so geo objects stay on top
        this.currentLayer.bringToBack();
    }

    /**
     * Create coordinates toggle
     */
    createCoordinatesToggle() {
        const coordWrapper = L.DomUtil.create(
            'div',
            'toolbar-toggle-wrapper',
            this.toolbar
        );

        const coordCheckbox = L.DomUtil.create(
            'input',
            'toolbar-checkbox',
            coordWrapper
        );
        coordCheckbox.type = 'checkbox';
        coordCheckbox.id = 'coord-toggle';

        const coordLabel = L.DomUtil.create(
            'label',
            'toolbar-label toolbar-icon-coordinates',
            coordWrapper
        );
        coordLabel.htmlFor = 'coord-toggle';
        coordLabel.innerHTML = ''; // Удаляем символ, будем использовать CSS
        coordLabel.title = 'Coordinates Mode';

        // Try to load custom icon
        this.loadCustomIcon(coordLabel, 'coordinates');

        L.DomEvent.on(coordCheckbox, 'change', (e) => {
            L.DomEvent.stopPropagation(e);
            this.toggleCoordinatesMode(e.target.checked);
        });
    }

    /**
     * Create distance toggle
     */
    createDistanceToggle() {
        const distWrapper = L.DomUtil.create(
            'div',
            'toolbar-toggle-wrapper',
            this.toolbar
        );

        const distCheckbox = L.DomUtil.create(
            'input',
            'toolbar-checkbox',
            distWrapper
        );
        distCheckbox.type = 'checkbox';
        distCheckbox.id = 'distance-toggle';

        const distLabel = L.DomUtil.create(
            'label',
            'toolbar-label toolbar-icon-distance',
            distWrapper
        );
        distLabel.htmlFor = 'distance-toggle';
        distLabel.innerHTML = ''; // Удаляем символ, будем использовать CSS
        distLabel.title = 'Distance Measurement';

        // Try to load custom icon
        this.loadCustomIcon(distLabel, 'distance');

        L.DomEvent.on(distCheckbox, 'change', (e) => {
            L.DomEvent.stopPropagation(e);
            this.toggleDistanceMode(e.target.checked);
        });
    }

    /**
     * Try to load custom icon for element
     * @param {HTMLElement} element - Element to set background image
     * @param {string} iconName - Name of the icon (center, coordinates, distance)
     */
    loadCustomIcon(element, iconName) {
        const iconPaths = [
            `/build/images/toolbar-icons/${iconName}.svg`,
            `/build/images/toolbar-icons/${iconName}.png`,
            `/build/images/toolbar-icons/${iconName}.jpg`,
        ];

        // Try each path
        const tryLoadImage = (pathIndex = 0) => {
            if (pathIndex >= iconPaths.length) {
                // No custom icon found, use CSS fallback
                return;
            }

            const img = new Image();
            img.onload = () => {
                // Image loaded successfully, set as background
                element.style.backgroundImage = `url('${iconPaths[pathIndex]}')`;
            };
            img.onerror = () => {
                // Try next path
                tryLoadImage(pathIndex + 1);
            };
            img.src = iconPaths[pathIndex];
        };

        tryLoadImage();
    }

    /**
     * Attach map event listeners
     */
    attachEventListeners() {
        this.map.on('click', (e) => {
            if (this.coordinatesMode) {
                this.showCoordinates(e);
            }

            if (this.distanceMode) {
                this.addDistancePoint(e);
            }
        });
    }

    /**
     * Center map to original coordinates
     */
    centerMap() {
        const centerLat = this.mapData.centerLat || 51.505;
        const centerLng = this.mapData.centerLng || -0.09;
        const zoom = this.mapData.zoom || 13;

        this.map.setView([centerLat, centerLng], zoom, {
            animate: true,
            duration: 1.0,
        });

        // Show temporary indicator
        this.showTemporaryIndicator([centerLat, centerLng]);
    }

    /**
     * Show temporary center indicator
     */
    showTemporaryIndicator(latlng) {
        const marker = L.circleMarker(latlng, {
            radius: 8,
            color: '#007bff',
            fillColor: '#007bff',
            fillOpacity: 0.8,
            weight: 2,
        }).addTo(this.map);

        // Remove after animation
        setTimeout(() => {
            this.map.removeLayer(marker);
        }, 2000);
    }

    /**
     * Toggle coordinates mode
     */
    toggleCoordinatesMode(enabled) {
        this.coordinatesMode = enabled;

        if (enabled) {
            this.map.getContainer().style.cursor = 'crosshair';
            this.disableDistanceMode();
        } else {
            this.map.getContainer().style.cursor = '';
            this.hideCoordinateTooltip();
        }
    }

    /**
     * Show coordinates tooltip
     */
    showCoordinates(e) {
        const lat = e.latlng.lat.toFixed(6);
        const lng = e.latlng.lng.toFixed(6);

        // Remove existing tooltip
        this.hideCoordinateTooltip();

        // Create new tooltip
        this.coordinateTooltip = L.popup({
            closeButton: true,
            autoClose: false,
            closeOnClick: false,
            className: 'coordinate-popup',
        })
            .setLatLng(e.latlng)
            .setContent(
                `
            <div class="coordinate-content">
                <strong>📍 Coordinates:</strong><br>
                <div class="coord-values">
                    <div><strong>Lat:</strong> ${lat}</div>
                    <div><strong>Lng:</strong> ${lng}</div>
                </div>
                <button class="copy-btn" data-coords="${lat}, ${lng}">📋 Copy</button>
            </div>
        `
            )
            .openOn(this.map);

        // Add click event listener to copy button after popup is created
        setTimeout(() => {
            const copyBtn = document.querySelector(
                '.coordinate-popup .copy-btn'
            );
            if (copyBtn) {
                copyBtn.addEventListener('click', (event) => {
                    this.handleCopyCoordinates(event, lat, lng);
                });
            }
        }, 100);
    }

    /**
     * Handle coordinates copying with animation
     */
    handleCopyCoordinates(event, lat, lng) {
        const button = event.target;
        const coordsText = `${lat}, ${lng}`;

        // Add copying animation class
        button.classList.add('copying');

        // Copy to clipboard
        navigator.clipboard
            .writeText(coordsText)
            .then(() => {
                // Remove copying class and add copied class
                button.classList.remove('copying');
                button.classList.add('copied');

                // Change button text temporarily
                const originalText = button.innerHTML;
                button.innerHTML = '✅ Copied!';

                // Reset button after animation
                setTimeout(() => {
                    button.classList.remove('copied');
                    button.innerHTML = originalText;
                }, 1500);
            })
            .catch(() => {
                // Handle copy failure
                button.classList.remove('copying');
                button.classList.add('copying'); // Quick pulse for error

                setTimeout(() => {
                    button.classList.remove('copying');
                }, 300);
            });
    }

    /**
     * Hide coordinate tooltip
     */
    hideCoordinateTooltip() {
        if (this.coordinateTooltip) {
            this.map.closePopup(this.coordinateTooltip);
            this.coordinateTooltip = null;
        }
    }

    /**
     * Toggle distance mode
     */
    toggleDistanceMode(enabled) {
        this.distanceMode = enabled;

        if (enabled) {
            this.map.getContainer().style.cursor = 'crosshair';
            this.disableCoordinatesMode();
            this.clearDistanceMeasurement();
        } else {
            this.map.getContainer().style.cursor = '';
            this.clearDistanceMeasurement();
        }
    }

    /**
     * Add distance measurement point
     */
    addDistancePoint(e) {
        // Add marker
        const marker = L.circleMarker(e.latlng, {
            radius: 6,
            color: '#ff6b35',
            fillColor: '#ff6b35',
            fillOpacity: 0.8,
            weight: 2,
        }).addTo(this.map);

        this.distanceMarkers.push(marker);

        // If we have 2 or more points, draw line and show distance
        if (this.distanceMarkers.length >= 2) {
            this.updateDistanceLine();
        }
    }

    /**
     * Update distance line and measurement
     */
    updateDistanceLine() {
        // Remove existing polyline
        if (this.distancePolyline) {
            this.map.removeLayer(this.distancePolyline);
        }

        // Create points array
        const points = this.distanceMarkers.map((marker) => marker.getLatLng());

        // Create polyline
        this.distancePolyline = L.polyline(points, {
            color: '#ff6b35',
            weight: 3,
            opacity: 0.8,
            dashArray: '5, 10',
        }).addTo(this.map);

        // Calculate total distance
        let totalDistance = 0;
        for (let i = 0; i < points.length - 1; i++) {
            totalDistance += points[i].distanceTo(points[i + 1]);
        }

        // Show compact distance popup on last point
        const lastPoint = points[points.length - 1];
        const distanceText = this.formatDistance(totalDistance);
        const pointNumber = points.length;

        L.popup({
            closeButton: false,
            autoClose: false,
            closeOnClick: false,
            className: 'distance-popup',
            offset: [0, -10], // Offset popup slightly above the point
        })
            .setLatLng(lastPoint)
            .setContent(
                `<div class="distance-content">
                    <div class="distance-point-number">#${pointNumber}</div>
                    <div class="distance-value">${distanceText}</div>
                </div>`
            )
            .openOn(this.map);
    }

    /**
     * Format distance for display
     */
    formatDistance(distance) {
        if (distance < 1000) {
            return `${distance.toFixed(2)} m`;
        } else {
            return `${(distance / 1000).toFixed(2)} km`;
        }
    }

    /**
     * Clear distance measurement
     */
    clearDistanceMeasurement() {
        // Remove markers
        this.distanceMarkers.forEach((marker) => {
            this.map.removeLayer(marker);
        });
        this.distanceMarkers = [];

        // Remove polyline
        if (this.distancePolyline) {
            this.map.removeLayer(this.distancePolyline);
            this.distancePolyline = null;
        }

        // Close distance popups
        this.map.eachLayer((layer) => {
            if (
                layer instanceof L.Popup &&
                layer.getElement().classList.contains('distance-popup')
            ) {
                this.map.closePopup(layer);
            }
        });
    }

    /**
     * Disable coordinates mode
     */
    disableCoordinatesMode() {
        const coordCheckbox = document.getElementById('coord-toggle');
        if (coordCheckbox) {
            coordCheckbox.checked = false;
            this.toggleCoordinatesMode(false);
        }
    }

    /**
     * Disable distance mode
     */
    disableDistanceMode() {
        const distCheckbox = document.getElementById('distance-toggle');
        if (distCheckbox) {
            distCheckbox.checked = false;
            this.toggleDistanceMode(false);
        }
    }

    /**
     * Destroy toolbar
     */
    destroy() {
        if (this.toolbar && this.toolbar.parentNode) {
            this.toolbar.parentNode.removeChild(this.toolbar);
        }

        this.clearDistanceMeasurement();
        this.hideCoordinateTooltip();
        this.hideLayerDropdown();
        this.map.getContainer().style.cursor = '';

        // Clear references
        this.layerButton = null;
        this.layerDropdown = null;
    }
}
