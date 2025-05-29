/**
 * JavaScript for GeoObject form interaction with the map
 */
document.addEventListener('DOMContentLoaded', function () {
    initGeoObjectForm();
});

/**
 * Initialize the form for working with geo objects
 */
function initGeoObjectForm(mapInstance) {
    // Form elements
    const form = document.getElementById('geo-object-form');
    if (!form) {
        console.log('Geo object form not found, skipping initialization');
        return;
    }

    console.log('Found geo object form, setting up handlers');

    const typeSelect = document.querySelector('.geo-object-type');
    const ttlSelect = document.querySelector('.geo-object-ttl');
    const geoJsonInput = document.querySelector('.geo-object-geojson');
    const titleInput = document.querySelector('.geo-object-title');
    const mapIdInput = document.querySelector('.geo-object-map-id');

    // Debug: Check if all elements are found
    console.log('Form elements found:', {
        typeSelect: !!typeSelect,
        ttlSelect: !!ttlSelect,
        geoJsonInput: !!geoJsonInput,
        titleInput: !!titleInput,
        mapIdInput: !!mapIdInput,
    });

    // Ensure mapId is set correctly
    if (mapIdInput) {
        // Get mapId from the map container
        const mapContainer = document.getElementById('map-container');
        if (mapContainer) {
            const mapId = mapContainer.getAttribute('data-map-id');
            if (mapId) {
                mapIdInput.value = mapId;
                console.log('Map ID set to:', mapId);
            } else {
                console.error('Map ID not found in map container');
            }
        }
    } else {
        console.error('Map ID input field not found');
    }

    const createBtn = document.getElementById('btn-create-geo');
    const updateBtn = document.getElementById('btn-update-geo');
    const cancelBtn = document.getElementById('btn-cancel-geo');

    let currentMode = 'create'; // 'create' or 'edit'
    let currentObjectId = null;
    let drawingMode = false;

    // Get a reference to the map (should be available from a global object)
    const map = window.tacticalMap || null;

    if (!map) {
        console.error(
            'Map object not found. Make sure the map is initialized before the form.'
        );
        return;
    }

    // Handle object type change
    typeSelect.addEventListener('change', function () {
        const type = this.value;

        // Enable drawing mode and reset current temporary objects
        enableDrawingMode(type);

        // Update help text based on the selected type
        updateTypeHelp(type);
    });

    // Handle cancel button
    cancelBtn.addEventListener('click', function (e) {
        e.preventDefault();
        resetForm();
        setCreateMode();
        disableDrawingMode();
    });

    // Handle form submission (create or update)
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        // Check the basic fields
        if (!titleInput.value.trim()) {
            alert('Please enter a title');
            titleInput.focus();
            return;
        }

        console.log('geoJsonInput element:', geoJsonInput);
        console.log(
            'geoJsonInput value:',
            geoJsonInput ? geoJsonInput.value : 'element not found'
        );
        console.log(
            'geoJsonInput value length:',
            geoJsonInput ? geoJsonInput.value.length : 0
        );

        if (!geoJsonInput.value) {
            alert('Please create a geo object on the map');
            return;
        }

        // Check if mapId is set
        if (!mapIdInput.value) {
            alert('Map ID is missing. Please refresh the page and try again.');
            return;
        }

        console.log('Submitting form with mapId:', mapIdInput.value);

        // Collect the form data as JSON instead of FormData
        let geoJsonData;
        try {
            geoJsonData = JSON.parse(geoJsonInput.value);
        } catch (e) {
            alert('Invalid GeoJSON format');
            return;
        }

        const jsonData = {
            title: titleInput.value.trim(),
            description: document.querySelector('.geo-object-description')
                .value,
            type: typeSelect.value,
            ttl: parseInt(ttlSelect.value) || 0,
            geoJson: geoJsonData, // Send as object, not string
            hash: document.querySelector('.geo-object-hash').value,
            mapId: mapIdInput.value,
        };

        console.log('JSON data being sent:', jsonData);

        // Determine the URL and method depending on the mode
        let url = '/geo-object/new';
        if (currentMode === 'edit' && currentObjectId) {
            url = `/geo-object/${currentObjectId}/update`;
        }

        // Send the request as JSON
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(jsonData),
        })
            .then((response) => response.json())
            .then((data) => {
                if (data.success) {
                    // Update the list of objects on the map
                    refreshGeoObjects();

                    // Reset the form
                    resetForm();
                    setCreateMode();
                    disableDrawingMode();

                    // Show success message
                    showSuccessMessage(
                        currentMode === 'edit'
                            ? 'Geo object updated successfully'
                            : 'Geo object created successfully'
                    );
                } else {
                    showErrorMessage(data.message || 'An error occurred');
                }
            })
            .catch((error) => {
                console.error('Error submitting form:', error);
                showErrorMessage(
                    'Failed to save geo object. Please try again.'
                );
            });
    });

    /**
     * Set creation mode for the form
     */
    function setCreateMode() {
        currentMode = 'create';
        currentObjectId = null;

        // Update button visibility
        if (createBtn) createBtn.style.display = 'inline-block';
        if (updateBtn) updateBtn.style.display = 'none';

        // Update form title
        const formTitle = document.querySelector('.geo-form-title');
        if (formTitle) {
            formTitle.textContent = 'Create New Geo Object';
        }
    }

    /**
     * Set edit mode for the form
     */
    function setEditMode(objectId) {
        currentMode = 'edit';
        currentObjectId = objectId;

        // Update button visibility
        if (createBtn) createBtn.style.display = 'none';
        if (updateBtn) updateBtn.style.display = 'inline-block';

        // Update form title
        const formTitle = document.querySelector('.geo-form-title');
        if (formTitle) {
            formTitle.textContent = 'Edit Geo Object';
        }
    }

    /**
     * Load object data for editing
     */
    function loadObjectData(objectId) {
        fetch(`/geo-object/${objectId}`)
            .then((response) => response.json())
            .then((data) => {
                if (data.success && data.object) {
                    // Fill the form with object data
                    titleInput.value = data.object.title || '';
                    document.querySelector('.geo-object-description').value =
                        data.object.description || '';
                    typeSelect.value = data.object.type || 'Point';
                    geoJsonInput.value = JSON.stringify(
                        data.object.geoJson || {}
                    );

                    // Set TTL if available
                    if (ttlSelect && data.object.ttl !== undefined) {
                        ttlSelect.value = data.object.ttl;
                    }

                    // Set hash if available
                    const hashInput =
                        document.querySelector('.geo-object-hash');
                    if (hashInput && data.object.hash) {
                        hashInput.value = data.object.hash;
                    }

                    // Set map ID
                    if (mapIdInput && data.object.mapId) {
                        mapIdInput.value = data.object.mapId;
                    }

                    // Update type help text
                    updateTypeHelp(data.object.type);

                    // Set edit mode
                    setEditMode(objectId);

                    // Display the object on the map (if the function exists)
                    if (map && map.showGeoJsonObject) {
                        map.showGeoJsonObject(
                            data.object.geoJson,
                            data.object.type
                        );
                    }
                } else {
                    showErrorMessage(
                        data.message || 'Failed to load geo object data'
                    );
                }
            })
            .catch((error) => {
                console.error('Error loading object data:', error);
                showErrorMessage(
                    'Failed to load geo object data. Please try again.'
                );
            });
    }

    /**
     * Reset form to initial state
     */
    function resetForm() {
        form.reset();
        geoJsonInput.value = '';

        // Clear temporary objects from the map
        if (map && map.clearTempObjects) {
            map.clearTempObjects();
        }
    }

    /**
     * Convert form geometry type to map-compatible type
     */
    function convertTypeForMap(formType) {
        const typeMap = {
            Point: 'point',
            Polygon: 'polygon',
            Circle: 'circle',
        };
        return typeMap[formType] || formType.toLowerCase();
    }

    /**
     * Enable drawing mode on the map
     */
    function enableDrawingMode(type) {
        console.log('Enabling drawing mode for type:', type);
        console.log('Map object available:', !!map);
        console.log(
            'Map enableDrawingMode function available:',
            !!(map && typeof map.enableDrawingMode === 'function')
        );

        drawingMode = true;

        // Convert type for map compatibility
        const mapType = convertTypeForMap(type);
        console.log('Converted type for map:', mapType);

        // If there's a function to enable drawing mode on the map
        if (map && typeof map.enableDrawingMode === 'function') {
            map.enableDrawingMode(mapType, function (geoJson) {
                // Callback to be called after object creation
                console.log('GeoJSON received from drawing:', geoJson);
                console.log('Setting geoJsonInput value');
                geoJsonInput.value = JSON.stringify(geoJson);
                console.log('geoJsonInput value set to:', geoJsonInput.value);
            });
        } else {
            console.error('map.enableDrawingMode is not a function', map);
        }
    }

    /**
     * Disable drawing mode
     */
    function disableDrawingMode() {
        drawingMode = false;

        // If there's a function to disable drawing mode
        if (map && map.disableDrawingMode) {
            map.disableDrawingMode();
        }
    }

    /**
     * Update help text based on selected object type
     */
    function updateTypeHelp(type) {
        const helpText = document.getElementById('geo-type-help');
        if (!helpText) return;

        switch (type) {
            case 'Point':
                helpText.textContent = 'Click on the map to place a point.';
                break;
            case 'Polygon':
                helpText.textContent =
                    'Click on the map to add polygon points. Double-click to finish.';
                break;
            case 'Circle':
                helpText.textContent =
                    'First click sets the center, second click sets the radius.';
                break;
            default:
                helpText.textContent =
                    'Select type and then click on the map to create.';
        }
    }

    /**
     * Refresh the list of objects on the map
     */
    function refreshGeoObjects() {
        console.log('=== refreshGeoObjects called ===');
        const mapId = mapIdInput.value;
        console.log('Map ID:', mapId);

        if (!mapId) {
            console.log('No mapId found, returning');
            return;
        }

        console.log('Map object:', map);
        console.log('Map object type:', typeof map);

        if (map) {
            console.log(
                'Available map functions:',
                Object.getOwnPropertyNames(map)
            );
            console.log(
                'map.loadGeoObjects available:',
                typeof map.loadGeoObjects
            );
        }

        // Always fetch and update the list manually
        console.log('Fetching objects manually to update list');
        fetch(`/geo-object/by-map/${mapId}`)
            .then((response) => response.json())
            .then((data) => {
                console.log('Fetched objects for list update:', data);
                if (data.success && data.objects) {
                    // Update the HTML list of objects
                    updateObjectsList(data.objects);
                }
            })
            .catch((error) => {
                console.error('Error fetching objects for list:', error);
            });

        // If there's a function to load objects on the map
        if (map && map.loadGeoObjects) {
            console.log('Calling map.loadGeoObjects with mapId:', mapId);
            map.loadGeoObjects(mapId);
        } else {
            console.log(
                'map.loadGeoObjects not available, trying alternative methods'
            );

            // Try alternative method - reload the page or manually fetch and display objects
            if (map && map.refreshObjects) {
                console.log('Trying map.refreshObjects');
                map.refreshObjects();
            } else if (map && map.reload) {
                console.log('Trying map.reload');
                map.reload();
            } else {
                console.log(
                    'No refresh method found, objects already fetched above'
                );
            }
        }
    }

    /**
     * Update the HTML list of geo objects
     */
    function updateObjectsList(objects) {
        console.log('Updating objects list with:', objects);

        const listContainer = document.querySelector(
            '.geo-objects-list .list-group'
        );
        if (!listContainer) {
            console.log('Objects list container not found');
            return;
        }

        // Clear existing list
        listContainer.innerHTML = '';

        if (objects.length === 0) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-info';
            alertDiv.innerHTML =
                '<i class="fas fa-info-circle me-2"></i> No geo objects available for this map.';
            listContainer.parentElement.replaceWith(alertDiv);
            return;
        }

        // Add each object to the list
        objects.forEach((object) => {
            const listItem = createObjectListItem(object);
            listContainer.appendChild(listItem);
        });

        // Re-attach event listeners for the new elements
        attachObjectListEventListeners();
    }

    /**
     * Create HTML element for a geo object list item
     */
    function createObjectListItem(object) {
        const div = document.createElement('div');
        div.className =
            'list-group-item d-flex justify-content-between align-items-center geo-object-item';
        div.setAttribute('data-id', object.id);
        div.setAttribute('data-type', object.type);
        div.setAttribute('data-hash', object.hash);

        // Get icon based on type
        let iconClass = 'fas fa-map';
        if (object.type === 'Point') iconClass = 'fas fa-map-marker-alt';
        else if (object.type === 'Polygon') iconClass = 'fas fa-draw-polygon';
        else if (object.type === 'Circle') iconClass = 'fas fa-circle';
        else if (object.type === 'Line') iconClass = 'fas fa-route';

        // Format TTL display
        let ttlDisplay = 'Unlimited time';
        if (object.ttl && object.ttl > 0) {
            if (object.ttl >= 3600) {
                const hours = Math.floor(object.ttl / 3600);
                const minutes = Math.floor((object.ttl % 3600) / 60);
                ttlDisplay = `${hours} hour${hours !== 1 ? 's' : ''}`;
                if (minutes > 0) {
                    ttlDisplay += ` ${minutes} min`;
                }
            } else if (object.ttl >= 60) {
                ttlDisplay = `${Math.floor(object.ttl / 60)} min`;
            } else {
                ttlDisplay = `${object.ttl} sec`;
            }
        }

        div.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="${iconClass} me-2 geo-type-icon"></i>
                <div>
                    <h5 class="mb-1">${object.title}</h5>
                    <small class="text-muted">${ttlDisplay}</small>
                </div>
            </div>
            <div class="btn-group">
                <button class="btn btn-sm btn-outline-primary geo-object-focus" 
                        title="Focus on map" 
                        data-id="${object.id}">
                    <i class="fas fa-search-location"></i>
                </button>
                <button class="btn btn-sm btn-outline-warning geo-object-edit" 
                        title="Edit object" 
                        data-id="${object.id}">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger geo-object-delete" 
                        title="Delete object" 
                        data-id="${object.id}">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;

        return div;
    }

    /**
     * Attach event listeners to object list items
     */
    function attachObjectListEventListeners() {
        // Focus buttons
        document.querySelectorAll('.geo-object-focus').forEach((btn) => {
            btn.addEventListener('click', function () {
                const objectId = this.getAttribute('data-id');
                console.log('Focus button clicked for object:', objectId);

                if (
                    map &&
                    map.geoObjectManager &&
                    map.geoObjectManager.geoObjectLayers
                ) {
                    const layerInfo =
                        map.geoObjectManager.geoObjectLayers[objectId];
                    if (layerInfo && layerInfo.layer) {
                        const layer = layerInfo.layer;

                        // Focus on the object
                        if (layer.getLatLng) {
                            // For points
                            map.getLeafletMap().setView(layer.getLatLng(), 16);
                        } else if (layer.getBounds) {
                            // For polygons, circles, lines
                            map.getLeafletMap().fitBounds(layer.getBounds());
                        }

                        // Open popup if it exists
                        if (layer.getPopup) {
                            layer.openPopup();
                        }
                    } else {
                        console.error('Layer not found for object:', objectId);
                    }
                } else {
                    console.error('Map or geoObjectManager not available');
                }
            });
        });

        // Edit buttons
        document.querySelectorAll('.geo-object-edit').forEach((btn) => {
            btn.addEventListener('click', function () {
                const objectId = this.getAttribute('data-id');
                if (window.geoObjectForm && window.geoObjectForm.setEditMode) {
                    window.geoObjectForm.setEditMode(objectId);
                }
            });
        });

        // Delete buttons - handle directly without modal
        document.querySelectorAll('.geo-object-delete').forEach((btn) => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const objectId = this.getAttribute('data-id');
                const objectTitle =
                    this.closest('.geo-object-item').querySelector(
                        'h5'
                    ).textContent;

                if (
                    confirm(`Are you sure you want to delete "${objectTitle}"?`)
                ) {
                    // Send delete request directly
                    fetch(`/geo-object/${objectId}/delete`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                    })
                        .then((response) => response.json())
                        .then((data) => {
                            if (data.success) {
                                console.log(
                                    'Object deleted successfully from list'
                                );

                                // Force complete refresh of both map and list
                                const mapId = mapIdInput.value;
                                console.log('MapId for refresh:', mapId);
                                console.log('Map object:', map);
                                console.log(
                                    'Window.tacticalMap:',
                                    window.tacticalMap
                                );

                                if (mapId) {
                                    console.log(
                                        'Forcing complete refresh after deletion'
                                    );

                                    // Clear and reload map objects
                                    if (map && map.geoObjectManager) {
                                        console.log(
                                            'Using local map reference'
                                        );
                                        console.log('Clearing map objects');
                                        map.geoObjectManager.clearGeoObjects();
                                        console.log('Reloading map objects');
                                        map.geoObjectManager.loadGeoObjects(
                                            mapId
                                        );
                                    } else {
                                        console.log(
                                            'Map or geoObjectManager not available, trying global reference'
                                        );
                                        if (
                                            window.tacticalMap &&
                                            window.tacticalMap.geoObjectManager
                                        ) {
                                            console.log(
                                                'Using global tacticalMap reference'
                                            );
                                            window.tacticalMap.geoObjectManager.clearGeoObjects();
                                            window.tacticalMap.geoObjectManager.loadGeoObjects(
                                                mapId
                                            );
                                        } else {
                                            console.error(
                                                'No map reference available for refresh!'
                                            );
                                            console.log(
                                                'Trying to trigger map refresh via event'
                                            );

                                            // Try to trigger refresh via custom event
                                            const refreshEvent =
                                                new CustomEvent(
                                                    'geo-objects-refresh',
                                                    {
                                                        detail: {
                                                            mapId: mapId,
                                                        },
                                                    }
                                                );
                                            document.dispatchEvent(
                                                refreshEvent
                                            );
                                        }
                                    }

                                    // Also refresh the list manually
                                    console.log('Refreshing objects list');
                                    refreshGeoObjects();
                                }

                                alert('Object deleted successfully');
                            } else {
                                alert(
                                    'Error deleting object: ' +
                                        (data.message || 'Unknown error')
                                );
                            }
                        })
                        .catch((error) => {
                            console.error('Error deleting object:', error);
                            alert('Error deleting object. Please try again.');
                        });
                }
            });
        });
    }

    /**
     * Display success message
     */
    function showSuccessMessage(message) {
        // Simple implementation that can be replaced with more complex notifications
        alert(message);
    }

    /**
     * Display error message
     */
    function showErrorMessage(message) {
        // Simple implementation that can be replaced with more complex notifications
        alert('Error: ' + message);
    }

    // Export public methods for use from other scripts
    window.geoObjectForm = {
        setEditMode: function (objectId) {
            loadObjectData(objectId);
        },
        resetForm: resetForm,
        refreshObjects: refreshGeoObjects,
        updateObjectsList: updateObjectsList,
    };

    // Initialize form
    setCreateMode();
    if (typeSelect.value) {
        updateTypeHelp(typeSelect.value);
    }

    // Load initial objects list
    refreshGeoObjects();
}
