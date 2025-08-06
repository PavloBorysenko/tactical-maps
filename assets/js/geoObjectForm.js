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

    // Initialize icon selector for geo object forms
    const geoObjectIconSelector = new IconSelector({
        inputSelector: '.geo-object-icon-url',
        gridSelector: '#icon-grid',
        clearButtonSelector: '#clear-icon-btn',
        itemClassName: 'icon-item',
        nameClassName: 'icon-name',
        selectedClassName: 'selected',
        loadingText: 'Loading icons...',
        errorText: 'Error loading icons',
        emptyText: 'No custom icons available',
        emptyHelpText:
            'Add PNG, JPG, or SVG files to /public/assets/icons/custom/',
    });

    let currentMode = 'create'; // 'create' or 'edit'
    let currentObjectId = null;
    let drawingMode = false;

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

    // Handle TTL change
    ttlSelect.addEventListener('change', function () {
        // You can add any logic here for TTL changes if needed
    });

    // Clear icon button event
    if (geoObjectIconSelector) {
        const clearIconBtn = document.querySelector('#clear-icon-btn');
        if (clearIconBtn) {
            clearIconBtn.addEventListener('click', () => {
                geoObjectIconSelector.clearSelection();
            });
        }
    }

    // Create button
    createBtn.addEventListener('click', function () {
        const type = typeSelect.value;
        if (!type || type === '') {
            showErrorMessage('Please select a geometry type first');
            return;
        }

        if (!geoJsonInput.value) {
            showErrorMessage('Please draw the geometry on the map first');
            return;
        }

        if (!titleInput.value.trim()) {
            showErrorMessage('Please enter a title for the geo object');
            return;
        }

        // Submit form - function will handle data collection
        submitGeoObjectForm(null, 'create');
    });

    // Update button
    updateBtn.addEventListener('click', function () {
        if (!currentObjectId) {
            showErrorMessage('No object selected for update');
            return;
        }

        if (!titleInput.value.trim()) {
            showErrorMessage('Please enter a title for the geo object');
            return;
        }

        // Submit form - function will handle data collection
        submitGeoObjectForm(null, 'update');
    });

    // Cancel button
    cancelBtn.addEventListener('click', function () {
        resetForm();
    });

    /**
     * Submit geo object form
     */
    function submitGeoObjectForm(formData, action) {
        const url =
            action === 'create'
                ? '/geo-object/new'
                : `/geo-object/${currentObjectId}/update`;

        showObjectsLoading();

        // Get form elements
        const descriptionInput = document.querySelector(
            '.geo-object-description'
        );
        const hashInput = document.querySelector('.geo-object-hash');

        // Convert FormData back to JSON format as expected by the controller
        const jsonData = {
            title: titleInput.value.trim(),
            description: descriptionInput ? descriptionInput.value : '',
            type: typeSelect.value,
            ttl: parseInt(ttlSelect.value) || 0,
            hash: hashInput ? hashInput.value : '',
            mapId: mapIdInput.value,
            sideId: sideSelect ? sideSelect.value : null,
        };

        // Handle geoJson - parse if it's a string
        let geoJsonData = null;
        if (geoJsonInput.value) {
            try {
                geoJsonData =
                    typeof geoJsonInput.value === 'string'
                        ? JSON.parse(geoJsonInput.value)
                        : geoJsonInput.value;
            } catch (e) {
                showErrorMessage('Invalid GeoJSON format');
                return;
            }
        }
        jsonData.geoJson = geoJsonData;

        // Add icon URL if available
        if (geoObjectIconSelector) {
            jsonData.iconUrl = geoObjectIconSelector.getSelectedIcon();
        }

        // Send as JSON instead of FormData
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
                    // Refresh objects list
                    refreshGeoObjects();

                    // Show success message
                    if (action === 'create') {
                        console.log('Geo object created successfully');
                        // Reset form only after create
                        resetForm();
                    } else {
                        console.log('Geo object updated successfully');
                        // For updates, just show a temporary success indicator
                        showSuccessMessage('Object updated successfully');
                    }
                } else {
                    showErrorMessage(data.message || 'Error saving geo object');
                    refreshGeoObjects(); // Refresh anyway to show current state
                }
            })
            .catch((error) => {
                console.error('Error:', error);
                showErrorMessage('Network error occurred');
                refreshGeoObjects(); // Refresh anyway to show current state
            });
    }

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
                if (data.success) {
                    const obj = data.object;

                    // Populate form fields with correct field names from server response
                    if (titleInput) titleInput.value = obj.title || ''; // Changed from obj.name
                    if (typeSelect) typeSelect.value = obj.type || ''; // Changed from obj.geometryType
                    if (ttlSelect)
                        ttlSelect.value =
                            obj.ttl !== undefined && obj.ttl !== null
                                ? obj.ttl
                                : ''; // Fix for TTL=0 (unlimited)
                    if (sideSelect) sideSelect.value = obj.sideId || '';
                    if (geoJsonInput)
                        geoJsonInput.value = JSON.stringify(obj.geoJson) || ''; // Changed from obj.geometry and ensure JSON string

                    // Set icon if available
                    if (obj.iconUrl && geoObjectIconSelector) {
                        geoObjectIconSelector.setSelectedIcon(obj.iconUrl);
                    } else if (geoObjectIconSelector) {
                        geoObjectIconSelector.clearSelection();
                    }

                    // Update type help text with correct field name
                    updateTypeHelp(obj.type || '');

                    // Enable drawing mode for the current type
                    if (obj.type) {
                        enableDrawingMode(obj.type);

                        // Load existing geometry for editing mode with draggable markers
                        if (
                            map &&
                            map.geoObjectManager &&
                            map.geoObjectManager.loadExistingGeometryForEdit
                        ) {
                            map.geoObjectManager.loadExistingGeometryForEdit(
                                obj.geoJson,
                                obj.type
                            );
                        }
                    }

                    // Focus on the object on the map if possible
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
                            } else {
                                // For single layers
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
                            }
                        }
                    }

                    // Set edit mode
                    setEditMode(objectId);

                    console.log(
                        'Geo object loaded for editing:',
                        obj.title,
                        'TTL:',
                        obj.ttl
                    );
                } else {
                    showErrorMessage('Error loading object data');
                }
            })
            .catch((error) => {
                console.error('Error loading object:', error);
                showErrorMessage('Error loading object data');
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

        // Clear icon selection using IconSelector API
        if (geoObjectIconSelector) {
            geoObjectIconSelector.clearSelection();
        }

        // Return to create mode
        setCreateMode();

        // Disable drawing mode
        disableDrawingMode();
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
            showObjectsError('Map ID is missing');
            return;
        }

        // Show loading state
        showObjectsLoading();

        // Always fetch and update the list manually
        fetch(`/geo-object/by-map/${mapId}`)
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
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
                } else {
                    showObjectsError(
                        data.message || 'Failed to load geo objects'
                    );
                }
            })
            .catch((error) => {
                console.error('Error loading geo objects:', error);
                showObjectsError(
                    'Failed to load geo objects. Please check your connection.'
                );
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
            return;
        }

        // Hide loading indicator
        const loadingIndicator = document.getElementById('geo-objects-loading');
        if (loadingIndicator) {
            loadingIndicator.remove();
        }

        // Clear existing list (except loading indicator which is already removed)
        listContainer.innerHTML = '';

        if (!objects || objects.length === 0) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-info';
            alertDiv.innerHTML =
                '<i class="fas fa-info-circle me-2"></i> No geo objects available for this map.';
            listContainer.appendChild(alertDiv);
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
     * Show loading state in the objects list
     */
    function showObjectsLoading() {
        const listContainer = document.querySelector(
            '.geo-objects-list .list-group'
        );

        if (!listContainer) {
            return;
        }

        listContainer.innerHTML = `
            <div class="alert alert-info" id="geo-objects-loading">
                <i class="fas fa-spinner fa-spin me-2"></i> Loading geo objects...
            </div>
        `;
    }

    /**
     * Show error state in the objects list
     */
    function showObjectsError(message = 'Failed to load geo objects') {
        const listContainer = document.querySelector(
            '.geo-objects-list .list-group'
        );

        if (!listContainer) {
            return;
        }

        // Hide loading indicator
        const loadingIndicator = document.getElementById('geo-objects-loading');
        if (loadingIndicator) {
            loadingIndicator.remove();
        }

        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-danger';
        alertDiv.innerHTML = `
            <i class="fas fa-exclamation-triangle me-2"></i> ${message}
            <button class="btn btn-sm btn-outline-danger ms-2" onclick="window.geoObjectForm.refreshObjects()">
                <i class="fas fa-redo"></i> Retry
            </button>
        `;
        listContainer.appendChild(alertDiv);
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

        // TTL Status Icon
        let ttlStatusIcon = '';
        if (object.isExpired !== undefined) {
            if (object.isExpired) {
                ttlStatusIcon =
                    '<i class="fas fa-eye-slash text-danger" title="Object has expired (not visible)" style="font-size: 14px;"></i>';
            } else {
                ttlStatusIcon =
                    '<i class="fas fa-eye text-success" title="Object is visible" style="font-size: 14px;"></i>';
            }
        }

        // Format TTL display using new fields
        let ttlDisplay = 'Unlimited time';
        if (object.remainingTtl !== undefined && object.remainingTtl !== null) {
            if (object.isExpired) {
                ttlDisplay =
                    '<span class="text-danger"><i class="fas fa-exclamation-triangle"></i> Expired</span>';
            } else {
                const remaining = object.remainingTtl;
                if (remaining >= 3600) {
                    const hours = Math.floor(remaining / 3600);
                    const minutes = Math.floor((remaining % 3600) / 60);
                    ttlDisplay = `Expires in: ${hours} hour${
                        hours !== 1 ? 's' : ''
                    }`;
                    if (minutes > 0) {
                        ttlDisplay += ` ${minutes} min`;
                    }
                } else if (remaining >= 60) {
                    ttlDisplay = `Expires in: ${Math.floor(
                        remaining / 60
                    )} min`;
                } else {
                    ttlDisplay = `Expires in: ${remaining} sec`;
                }
            }
        } else if (object.ttl && object.ttl > 0) {
            // Fallback to old TTL field if new fields are not available
            if (object.ttl >= 3600) {
                const hours = Math.floor(object.ttl / 3600);
                const minutes = Math.floor((object.ttl % 3600) / 60);
                ttlDisplay = `Expires in: ${hours} hour${
                    hours !== 1 ? 's' : ''
                }`;
                if (minutes > 0) {
                    ttlDisplay += ` ${minutes} min`;
                }
            } else if (object.ttl >= 60) {
                ttlDisplay = `Expires in: ${Math.floor(object.ttl / 60)} min`;
            } else {
                ttlDisplay = `Expires in: ${object.ttl} sec`;
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
                <div class="ttl-status-icon me-2">
                    ${ttlStatusIcon}
                </div>
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
                console.log('Focus button clicked for object:', objectId);

                if (
                    map &&
                    map.geoObjectManager &&
                    map.geoObjectManager.geoObjectLayers
                ) {
                    const layerInfo =
                        map.geoObjectManager.geoObjectLayers[objectId];
                    console.log('Layer info found:', !!layerInfo, layerInfo);

                    if (layerInfo && layerInfo.layer) {
                        const layer = layerInfo.layer;
                        console.log('Layer type:', layer.constructor.name);

                        // Focus on the object
                        if (layer instanceof L.LayerGroup) {
                            console.log('Handling LayerGroup');
                            // For LayerGroups, fit bounds of the group
                            if (
                                layer.getBounds &&
                                typeof layer.getBounds === 'function'
                            ) {
                                const bounds = layer.getBounds();
                                console.log('LayerGroup bounds:', bounds);
                                map.getLeafletMap().fitBounds(bounds);
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
                            console.log('Handling single layer');
                            // Original logic for single layers
                            if (
                                layer.getLatLng &&
                                typeof layer.getLatLng === 'function'
                            ) {
                                // For points
                                const latlng = layer.getLatLng();
                                console.log('Point location:', latlng);
                                map.getLeafletMap().setView(latlng, 16);
                            } else if (
                                layer.getBounds &&
                                typeof layer.getBounds === 'function'
                            ) {
                                // For polygons, circles, lines
                                const bounds = layer.getBounds();
                                console.log('Layer bounds:', bounds);
                                map.getLeafletMap().fitBounds(bounds);
                            }

                            // Open popup if it exists
                            if (layer.getPopup && layer.getPopup()) {
                                layer.openPopup();
                            }
                        }
                    } else {
                        console.warn('Layer not found for object:', objectId);

                        // Fallback: try to refresh objects and center on the area
                        if (map && map.getLeafletMap) {
                            // If we can't find the layer, at least zoom to a reasonable level
                            const currentZoom = map.getLeafletMap().getZoom();
                            if (currentZoom < 10) {
                                map.getLeafletMap().setZoom(14);
                            }
                        }
                    }
                } else {
                    console.warn('Map or geoObjectManager not available');
                }
            });
        });

        // Edit buttons
        document.querySelectorAll('.geo-object-edit').forEach((btn) => {
            btn.addEventListener('click', function () {
                const objectId = this.getAttribute('data-id');
                console.log('Edit button clicked for object:', objectId);
                if (window.geoObjectForm && window.geoObjectForm.setEditMode) {
                    window.geoObjectForm.setEditMode(objectId);
                } else {
                    console.error('geoObjectForm.setEditMode not available');
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
        // Create a toast notification
        const toast = document.createElement('div');
        toast.className =
            'alert alert-success alert-dismissible fade show position-fixed';
        toast.style.cssText = `
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        `;
        toast.innerHTML = `
            <i class="fas fa-check-circle me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        document.body.appendChild(toast);

        // Auto-remove after 4 seconds
        setTimeout(() => {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 4000);
    }

    /**
     * Display error message
     */
    function showErrorMessage(message) {
        // Create a toast notification for errors
        const toast = document.createElement('div');
        toast.className =
            'alert alert-danger alert-dismissible fade show position-fixed';
        toast.style.cssText = `
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        `;
        toast.innerHTML = `
            <i class="fas fa-exclamation-triangle me-2"></i>Error: ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        document.body.appendChild(toast);

        // Auto-remove after 6 seconds (longer for errors)
        setTimeout(() => {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 6000);
    }

    // Export public methods for use from other scripts
    window.geoObjectForm = {
        setEditMode: function (objectId) {
            loadObjectData(objectId);
        },
        resetForm: resetForm,
        refreshObjects: refreshGeoObjects,
        updateObjectsList: updateObjectsList,
        showObjectsLoading: showObjectsLoading,
        showObjectsError: showObjectsError,
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
    // This part is now handled by the IconSelector module
    // geoObjectIconSelector.loadIcons();
}
