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
        return;
    }

    const typeSelect = document.querySelector('.geo-object-type');
    const ttlSelect = document.querySelector('.geo-object-ttl');
    const geoJsonInput = document.querySelector('.geo-object-geojson');
    const titleInput = document.querySelector('.geo-object-title');
    const mapIdInput = document.querySelector('.geo-object-map-id');
    const sideSelect = document.querySelector('.geo-object-side');

    // Ensure mapId is set correctly
    if (mapIdInput) {
        // Get mapId from the map container
        const mapContainer = document.getElementById('map-container');
        if (mapContainer) {
            const mapId = mapContainer.getAttribute('data-map-id');
            if (mapId) {
                mapIdInput.value = mapId;
            }
        }
    }

    const createBtn = document.getElementById('btn-create-geo');
    const updateBtn = document.getElementById('btn-update-geo');
    const cancelBtn = document.getElementById('btn-cancel-geo');

    // Icon selector elements
    const iconUrlInput = document.querySelector('.geo-object-icon-url');
    const iconGrid = document.getElementById('icon-grid');
    const clearIconBtn = document.getElementById('clear-icon-btn');

    let currentMode = 'create'; // 'create' or 'edit'
    let currentObjectId = null;
    let drawingMode = false;
    let availableIcons = [];

    // Get a reference to the map (should be available from a global object)
    const map = window.tacticalMap || null;

    if (!map) {
        return;
    }

    // Handle object type change
    typeSelect.addEventListener('change', function () {
        const type = this.value;

        if (type && type !== '') {
            // Enable drawing mode only if type is selected
            enableDrawingMode(type);
            // Update help text based on the selected type
            updateTypeHelp(type);
        } else {
            // Disable drawing mode if no type selected
            disableDrawingMode();
            // Show default help text
            updateTypeHelp('');
        }
    });

    // Handle cancel button
    cancelBtn.addEventListener('click', function (e) {
        e.preventDefault();
        resetForm();
        setCreateMode();
        disableDrawingMode();
    });

    // Handle icon clear button
    if (clearIconBtn) {
        clearIconBtn.addEventListener('click', function (e) {
            e.preventDefault();
            clearIconSelection();
        });
    }

    // Handle form submission (create or update)
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        // Check the basic fields
        if (!titleInput.value.trim()) {
            alert('Please enter a title');
            titleInput.focus();
            return;
        }

        // Check if type is selected
        if (!typeSelect.value || typeSelect.value === '') {
            alert('Please select a type for the geo object');
            typeSelect.focus();
            return;
        }

        // Special handling for polygon and line drawing in progress
        if (drawingMode && map && map.geoObjectManager) {
            const drawingStatus = map.geoObjectManager.getDrawingStatus();

            if (
                drawingStatus.isDrawing &&
                (drawingStatus.type === 'polygon' ||
                    drawingStatus.type === 'line')
            ) {
                if (!drawingStatus.canFinish) {
                    const minPoints = drawingStatus.minPoints;
                    const currentPoints = drawingStatus.pointCount;
                    const needed = minPoints - currentPoints;
                    alert(
                        `Please add ${needed} more point${
                            needed > 1 ? 's' : ''
                        } to complete the ${
                            drawingStatus.type
                        }. Currently you have ${currentPoints} point${
                            currentPoints !== 1 ? 's' : ''
                        }, need minimum ${minPoints}.`
                    );
                    return;
                }

                // Try to finish the drawing
                let finishResult = false;
                if (drawingStatus.type === 'polygon') {
                    finishResult =
                        map.geoObjectManager.finishPolygonFromButton();
                } else if (drawingStatus.type === 'line') {
                    finishResult = map.geoObjectManager.finishLineFromButton();
                }

                if (!finishResult) {
                    return; // Drawing couldn't be finished
                }

                // Continue with form submission after successful drawing completion
            }
        }

        if (!geoJsonInput.value) {
            alert('Please create a geo object on the map');
            return;
        }

        // Check if mapId is set
        if (!mapIdInput.value) {
            alert('Map ID is missing. Please refresh the page and try again.');
            return;
        }

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
            iconUrl: iconUrlInput ? iconUrlInput.value : null,
            sideId: sideSelect ? sideSelect.value : null,
        };

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

                    // Set side if available
                    if (sideSelect && data.object.sideId) {
                        sideSelect.value = data.object.sideId;
                    } else if (sideSelect) {
                        sideSelect.value = '';
                    }

                    // Set icon if available
                    if (data.object.iconUrl) {
                        selectIcon(data.object.iconUrl);
                    } else {
                        clearIconSelection();
                    }

                    // Update type help text
                    updateTypeHelp(data.object.type);

                    // Set edit mode
                    setEditMode(objectId);

                    // Enable drawing mode for all object types in edit mode
                    if (data.object.type) {
                        enableDrawingMode(data.object.type);

                        // Pre-populate geometry from existing data for all types
                        if (
                            map &&
                            map.geoObjectManager &&
                            data.object.geoJson
                        ) {
                            map.geoObjectManager.loadExistingGeometryForEdit(
                                data.object.geoJson,
                                data.object.type
                            );
                        }
                    }

                    // Display the object on the map (if the function exists)
                    if (map && map.showGeoJsonObject) {
                        map.showGeoJsonObject(
                            data.object.geoJson,
                            data.object.type,
                            data.object // Pass full object data for custom icons
                        );
                    }
                } else {
                    showErrorMessage(
                        data.message || 'Failed to load geo object data'
                    );
                }
            })
            .catch((error) => {
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

        // Reset type selection to placeholder
        typeSelect.value = '';

        // Update help text to default state
        updateTypeHelp('');

        // Clear temporary objects from the map
        if (map && map.clearTempObjects) {
            map.clearTempObjects();
        }

        // Clear edit markers if they exist
        if (
            map &&
            map.geoObjectManager &&
            map.geoObjectManager.clearEditPointMarkers
        ) {
            map.geoObjectManager.clearEditPointMarkers();
        }

        // Clear icon selection
        clearIconSelection();
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
        drawingMode = true;

        // Convert type for map compatibility
        const mapType = convertTypeForMap(type);

        // If there's a function to enable drawing mode on the map
        if (map && typeof map.enableDrawingMode === 'function') {
            map.enableDrawingMode(mapType, function (geoJson) {
                // Callback to be called after object creation
                geoJsonInput.value = JSON.stringify(geoJson);
            });
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

        // Check if we're in edit mode
        const isEditMode = currentMode === 'edit';

        switch (type) {
            case 'Point':
                helpText.textContent = isEditMode
                    ? 'Click on the map to change point location.'
                    : 'Click on the map to place a point.';
                break;
            case 'Polygon':
                helpText.innerHTML = isEditMode
                    ? 'EDIT MODE: Click to add new points. <strong>Right-click existing points to delete them.</strong><br>Click "Update" when finished editing.'
                    : 'Click on the map to add polygon points (minimum 3 required).<br><strong>Drag points to move • Right-click to delete • Click "Create" to finish.</strong>';
                break;
            case 'Line':
                helpText.innerHTML = isEditMode
                    ? 'EDIT MODE: Click to add new points. <strong>Right-click existing points to delete them.</strong><br>Click "Update" when finished editing.'
                    : 'Click on the map to add line points (minimum 2 required).<br><strong>Drag points to move • Right-click to delete • Click "Create" to finish.</strong>';
                break;
            case 'Circle':
                helpText.textContent = isEditMode
                    ? 'Click to change center or adjust radius. Click "Update" when satisfied.'
                    : 'First click sets center, second click sets radius. Drag yellow center or purple radius markers to adjust. Click "Create" when satisfied.';
                break;
            default:
                helpText.textContent =
                    'Select a type from the dropdown to start creating a geo object on the map.';
        }
    }

    /**
     * Refresh the list of objects on the map
     */
    function refreshGeoObjects() {
        const mapId = mapIdInput.value;

        if (!mapId) {
            return;
        }

        // Always fetch and update the list manually
        fetch(`/geo-object/by-map/${mapId}`)
            .then((response) => response.json())
            .then((data) => {
                if (data.success && data.objects) {
                    // Update the HTML list of objects
                    updateObjectsList(data.objects);

                    // Also update the map via geoObjectManager
                    if (
                        map &&
                        map.geoObjectManager &&
                        map.geoObjectManager.loadGeoObjects
                    ) {
                        map.geoObjectManager.loadGeoObjects(mapId);
                    }
                }
            })
            .catch((error) => {
                // Silent error handling
            });
    }

    /**
     * Update the HTML list of geo objects
     */
    function updateObjectsList(objects) {
        const listContainer = document.querySelector(
            '.geo-objects-list .list-group'
        );

        if (!listContainer) {
            // Try alternative selector
            const altContainer = document.querySelector(
                '.geo-objects-container'
            );
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
        objects.forEach((object, index) => {
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
        let iconColor = '#6c757d';
        if (object.type === 'Point') {
            iconClass = 'fas fa-map-marker-alt';
            iconColor = '#dc3545';
        } else if (object.type === 'Polygon') {
            iconClass = 'fas fa-draw-polygon';
            iconColor = '#28a745';
        } else if (object.type === 'Circle') {
            iconClass = 'fas fa-circle';
            iconColor = '#007bff';
        } else if (object.type === 'Line') {
            iconClass = 'fas fa-route';
            iconColor = '#ffc107';
        }

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

        // Format side display
        let sideDisplay = '';
        if (object.side && object.side.name) {
            sideDisplay = `<div class="side-info mt-1">
                <span class="badge" style="background-color: ${
                    object.side.color || '#6c757d'
                }; color: white;">
                    ${object.side.name}
                </span>
            </div>`;
        }

        // Create icon display (custom icon or type-based fallback)
        let iconDisplay = '';
        if (object.iconUrl) {
            iconDisplay = `
                <div class="geo-object-icon me-3">
                    <img src="${object.iconUrl}" 
                         alt="Object icon" 
                         class="geo-custom-icon"
                         style="width: 32px; height: 32px; object-fit: contain;">
                </div>`;
        } else {
            iconDisplay = `
                <div class="geo-object-icon me-3">
                    <i class="${iconClass} geo-type-icon-large" style="font-size: 24px; color: ${iconColor};"></i>
                </div>`;
        }

        div.innerHTML = `
            <div class="d-flex align-items-center">
                ${iconDisplay}
                <i class="${iconClass} me-2 geo-type-icon small" style="font-size: 12px; opacity: 0.7; color: #6c757d;"></i>
                <div>
                    <h5 class="mb-1">${object.title}</h5>
                    <small class="text-muted">${ttlDisplay}</small>
                    ${sideDisplay}
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
                        if (layer instanceof L.LayerGroup) {
                            // For LayerGroups, fit bounds of the group
                            if (layer.getBounds) {
                                map.getLeafletMap().fitBounds(
                                    layer.getBounds()
                                );
                            }
                            // Open popup on the first layer that has one
                            let popupOpened = false;
                            layer.eachLayer((sublayer) => {
                                if (
                                    !popupOpened &&
                                    sublayer.getPopup &&
                                    sublayer.getPopup()
                                ) {
                                    sublayer.openPopup();
                                    popupOpened = true;
                                }
                            });
                        } else {
                            // Original logic for single layers
                            if (layer.getLatLng) {
                                // For points
                                map.getLeafletMap().setView(
                                    layer.getLatLng(),
                                    16
                                );
                            } else if (layer.getBounds) {
                                // For polygons, circles, lines
                                map.getLeafletMap().fitBounds(
                                    layer.getBounds()
                                );
                            }

                            // Open popup if it exists
                            if (layer.getPopup && layer.getPopup()) {
                                layer.openPopup();
                            }
                        }
                    }
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
                                // Force complete refresh of both map and list
                                const mapId = mapIdInput.value;

                                if (mapId) {
                                    // Clear and reload map objects
                                    if (map && map.geoObjectManager) {
                                        map.geoObjectManager.clearGeoObjects();
                                        map.geoObjectManager.loadGeoObjects(
                                            mapId
                                        );
                                    } else {
                                        if (
                                            window.tacticalMap &&
                                            window.tacticalMap.geoObjectManager
                                        ) {
                                            window.tacticalMap.geoObjectManager.clearGeoObjects();
                                            window.tacticalMap.geoObjectManager.loadGeoObjects(
                                                mapId
                                            );
                                        } else {
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

    // Update help text to default state (no type selected)
    updateTypeHelp('');

    // Only update type help if there's already a value selected (for edit mode)
    if (typeSelect.value && typeSelect.value !== '') {
        updateTypeHelp(typeSelect.value);
        // Enable drawing mode for existing selection (in edit mode)
        enableDrawingMode(typeSelect.value);
    }

    // Load initial objects list
    refreshGeoObjects();

    // Load custom icons from server
    fetch('/api/icons')
        .then((response) => response.json())
        .then((data) => {
            if (data.success) {
                availableIcons = data.icons;
                renderIconGrid();
            } else {
                iconGrid.innerHTML =
                    '<div class="text-muted">No custom icons available</div>';
            }
        })
        .catch((error) => {
            console.error('Error loading icons:', error);
            iconGrid.innerHTML =
                '<div class="text-danger">Error loading icons</div>';
        });

    /**
     * Render icon grid
     */
    function renderIconGrid() {
        if (!iconGrid || !availableIcons) return;

        if (availableIcons.length === 0) {
            iconGrid.innerHTML =
                '<div class="text-muted">No custom icons available. Add PNG, JPG, or SVG files to /public/assets/icons/custom/</div>';
            return;
        }

        iconGrid.innerHTML = '';

        availableIcons.forEach((icon) => {
            const iconItem = document.createElement('div');
            iconItem.className = 'icon-item';
            iconItem.setAttribute('data-icon-url', icon.url);
            iconItem.title = `Click to select ${icon.name}`;

            iconItem.innerHTML = `
                <img src="${icon.url}" alt="${icon.name}" onerror="this.style.display='none'">
                <div class="icon-name">${icon.name}</div>
            `;

            iconItem.addEventListener('click', () => {
                selectIcon(icon.url);
            });

            iconGrid.appendChild(iconItem);
        });
    }

    /**
     * Select an icon
     */
    function selectIcon(iconUrl) {
        if (!iconUrlInput) return;

        // Update input field
        iconUrlInput.value = iconUrl;

        // Update visual selection
        document.querySelectorAll('.icon-item').forEach((item) => {
            item.classList.remove('selected');
        });

        const selectedItem = document.querySelector(
            `[data-icon-url="${iconUrl}"]`
        );
        if (selectedItem) {
            selectedItem.classList.add('selected');
        }
    }

    /**
     * Clear icon selection
     */
    function clearIconSelection() {
        if (!iconUrlInput) return;

        iconUrlInput.value = '';
        document.querySelectorAll('.icon-item').forEach((item) => {
            item.classList.remove('selected');
        });
    }
}
