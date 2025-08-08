/**
 * Observer Viewer Page JavaScript
 * Handles the initialization and data loading for observer map viewer
 */

class ObserverViewerPage {
    constructor() {
        this.geoObjects = [];
        this.mapInstance = null;
        this.isMapReady = false;

        this.init();
    }

    /**
     * Initialize the observer viewer page
     */
    init() {
        console.log('ObserverViewerPage: Initializing...');

        // Load geo objects data
        this.loadGeoObjectsData();

        // Listen for map ready event
        this.setupMapReadyListener();

        // Setup page enhancements
        this.setupPageEnhancements();
    }

    /**
     * Load geo objects data from the embedded JSON script
     */
    loadGeoObjectsData() {
        const geoObjectsScript = document.getElementById('geo-objects-data');

        if (!geoObjectsScript) {
            console.error(
                'ObserverViewerPage: No geo-objects-data script found'
            );
            this.showError('Failed to load map data. Please refresh the page.');
            return;
        }

        try {
            this.geoObjects = JSON.parse(geoObjectsScript.textContent);
            console.log(
                'ObserverViewerPage: Parsed geo objects:',
                this.geoObjects.length,
                'objects'
            );

            // Update objects count in UI
            this.updateObjectsCount(this.geoObjects.length);
        } catch (error) {
            console.error(
                'ObserverViewerPage: Error parsing geo objects:',
                error
            );
            this.showError(
                'Failed to parse map data. Please refresh the page.'
            );
        }
    }

    /**
     * Setup listener for tactical map ready event
     */
    setupMapReadyListener() {
        document.addEventListener('tactical-map-ready', (event) => {
            console.log(
                'ObserverViewerPage: Tactical map ready event received'
            );

            if (event.detail?.map && event.detail.map.loadGeoObjects) {
                this.mapInstance = event.detail.map;
                this.isMapReady = true;

                // Load geo objects into the map
                this.loadObjectsIntoMap();

                // Mark map container as loaded
                this.markMapAsLoaded();
            } else {
                console.error(
                    'ObserverViewerPage: Map instance not found in event'
                );
                this.showError(
                    'Failed to initialize map. Please refresh the page.'
                );
            }
        });
    }

    /**
     * Load geo objects into the map
     */
    loadObjectsIntoMap() {
        if (!this.mapInstance || !this.isMapReady) {
            console.warn('ObserverViewerPage: Map not ready yet');
            return;
        }

        try {
            this.mapInstance.loadGeoObjects(this.geoObjects);
            console.log(
                'ObserverViewerPage: Successfully loaded',
                this.geoObjects.length,
                'objects into map'
            );

            // Update status
            this.updateLoadingStatus('Map loaded successfully', 'success');
        } catch (error) {
            console.error(
                'ObserverViewerPage: Error loading objects into map:',
                error
            );
            this.showError(
                'Failed to load objects on map. Some features may not work properly.'
            );
        }
    }

    /**
     * Mark map container as loaded (removes loading animation)
     */
    markMapAsLoaded() {
        const mapContainer = document.getElementById('map-container');
        if (mapContainer) {
            mapContainer.classList.add('map-loaded');
        }
    }

    /**
     * Setup page enhancements
     */
    setupPageEnhancements() {
        // Add loading indicators
        this.addLoadingIndicators();

        // Setup refresh functionality
        this.setupRefreshButton();

        // Setup keyboard shortcuts
        this.setupKeyboardShortcuts();

        // Setup auto-refresh if needed
        this.setupAutoRefresh();
    }

    /**
     * Add loading indicators to the page
     */
    addLoadingIndicators() {
        const mapContainer = document.getElementById('map-container');
        if (mapContainer && !mapContainer.querySelector('.loading-indicator')) {
            const loadingDiv = document.createElement('div');
            loadingDiv.className =
                'loading-indicator position-absolute top-50 start-50 translate-middle text-center';
            loadingDiv.innerHTML = `
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading map...</span>
                </div>
                <div class="mt-2 text-muted">Loading map data...</div>
            `;

            mapContainer.appendChild(loadingDiv);

            // Remove loading indicator when map is ready
            document.addEventListener('tactical-map-ready', () => {
                setTimeout(() => {
                    loadingDiv.remove();
                }, 1000);
            });
        }
    }

    /**
     * Setup refresh functionality
     */
    setupRefreshButton() {
        // Add refresh button to observer info panel
        const observerPanel = document.querySelector(
            '.observer-info-panel .row .col-md-4'
        );
        if (observerPanel && !observerPanel.querySelector('.refresh-btn')) {
            const refreshBtn = document.createElement('button');
            refreshBtn.className = 'btn btn-light btn-sm refresh-btn mt-2';
            refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
            refreshBtn.onclick = () => this.refreshData();

            observerPanel.appendChild(refreshBtn);
        }
    }

    /**
     * Setup keyboard shortcuts
     */
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (event) => {
            // F5 or Ctrl+R - Refresh
            if (event.key === 'F5' || (event.ctrlKey && event.key === 'r')) {
                event.preventDefault();
                this.refreshData();
            }

            // Escape - Focus map
            if (event.key === 'Escape') {
                const mapContainer = document.getElementById('map-container');
                if (mapContainer) {
                    mapContainer.focus();
                }
            }
        });
    }

    /**
     * Setup auto-refresh functionality
     */
    setupAutoRefresh() {
        // Auto-refresh every 5 minutes for active TTL objects
        setInterval(() => {
            const activeObjects = this.geoObjects.filter(
                (obj) => obj.ttl && !obj.isExpired
            );
            if (activeObjects.length > 0) {
                console.log(
                    'ObserverViewerPage: Auto-refreshing due to TTL objects'
                );
                this.refreshData();
            }
        }, 5 * 60 * 1000); // 5 minutes
    }

    /**
     * Refresh page data
     */
    refreshData() {
        const refreshBtn = document.querySelector('.refresh-btn');
        if (refreshBtn) {
            refreshBtn.innerHTML =
                '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
            refreshBtn.disabled = true;
        }

        // Reload the page to get fresh data
        setTimeout(() => {
            window.location.reload();
        }, 500);
    }

    /**
     * Update objects count in UI
     */
    updateObjectsCount(count) {
        const countElements = document.querySelectorAll('[data-objects-count]');
        countElements.forEach((element) => {
            element.textContent = count;
        });

        // Update in observer stats
        const statsElements = document.querySelectorAll(
            '.observer-stats small'
        );
        statsElements.forEach((element) => {
            if (element.textContent.includes('active objects')) {
                element.innerHTML = `<i class="fas fa-map-marker-alt"></i> ${count} active objects`;
            }
        });
    }

    /**
     * Update loading status message
     */
    updateLoadingStatus(message, type = 'info') {
        const statusElement = document.querySelector('.loading-status');
        if (statusElement) {
            statusElement.className = `alert alert-${type} loading-status`;
            statusElement.textContent = message;

            // Auto-hide success messages
            if (type === 'success') {
                setTimeout(() => {
                    statusElement.style.display = 'none';
                }, 3000);
            }
        }
    }

    /**
     * Show error message to user
     */
    showError(message) {
        console.error('ObserverViewerPage:', message);

        // Create or update error alert
        let errorAlert = document.querySelector('.observer-error-alert');
        if (!errorAlert) {
            errorAlert = document.createElement('div');
            errorAlert.className =
                'alert alert-warning alert-dismissible fade show observer-error-alert';
            errorAlert.innerHTML = `
                <i class="fas fa-exclamation-triangle"></i>
                <span class="error-message"></span>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            // Insert after observer info panel
            const infoPanel = document.querySelector('.observer-info-panel');
            if (infoPanel) {
                infoPanel.insertAdjacentElement('afterend', errorAlert);
            }
        }

        const messageSpan = errorAlert.querySelector('.error-message');
        if (messageSpan) {
            messageSpan.textContent = message;
        }
    }

    /**
     * Get current page statistics
     */
    getStatistics() {
        return {
            totalObjects: this.geoObjects.length,
            activeObjects: this.geoObjects.filter((obj) => !obj.isExpired)
                .length,
            expiredObjects: this.geoObjects.filter((obj) => obj.isExpired)
                .length,
            objectTypes: [...new Set(this.geoObjects.map((obj) => obj.type))],
            isMapReady: this.isMapReady,
            mapInstance: !!this.mapInstance,
        };
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function () {
    console.log('ObserverViewerPage: DOM loaded, initializing...');

    // Create global instance
    window.observerViewerPage = new ObserverViewerPage();

    // Add to window for debugging
    if (process.env.NODE_ENV === 'development') {
        window.getObserverStats = () =>
            window.observerViewerPage.getStatistics();
    }
});

export default ObserverViewerPage;
