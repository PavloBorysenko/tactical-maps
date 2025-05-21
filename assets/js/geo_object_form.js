/**
 * JavaScript for GeoObject form interaction with the map
 */
document.addEventListener('DOMContentLoaded', function () {
    initGeoObjectForm();
});

/**
 * Initialize the form for working with geo objects
 */
function initGeoObjectForm() {
    // Form elements
    const form = document.getElementById('geo-object-form');
    if (!form) return;

    const typeSelect = document.querySelector('.geo-object-type');
    const ttlSelect = document.querySelector('.geo-object-ttl');
    const geoJsonInput = document.querySelector('.geo-object-geojson');
    const titleInput = document.querySelector('.geo-object-title');
    const mapIdInput = document.querySelector('.geo-object-map-id');

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

        // Validate required fields
        if (!titleInput.value.trim()) {
            alert('Please enter a title');
            titleInput.focus();
            return;
        }

        if (!geoJsonInput.value) {
            alert('Please create a geo object on the map');
            return;
        }

        // Collect form data
        const formData = new FormData(form);

        // Determine URL and method based on the mode
        let url = '/geo-object/new';
        if (currentMode === 'edit' && currentObjectId) {
            url = `/geo-object/${currentObjectId}/update`;
        }

        // Submit the request
        fetch(url, {
            method: 'POST',
            body: formData,
        })
            .then((response) => response.json())
            .then((data) => {
                if (data.success) {
                    // Refresh objects on the map
                    refreshGeoObjects();

                    // Reset form
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
                    typeSelect.value = data.object.type || 'point';
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
     * Enable drawing mode on the map
     */
    function enableDrawingMode(type) {
        drawingMode = true;

        // If there's a function to enable drawing mode on the map
        if (map && map.enableDrawingMode) {
            map.enableDrawingMode(type, function (geoJson) {
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

        switch (type) {
            case 'point':
                helpText.textContent = 'Click on the map to place a point.';
                break;
            case 'polygon':
                helpText.textContent =
                    'Click on the map to add polygon points. Double-click to finish.';
                break;
            case 'circle':
                helpText.textContent =
                    'First click sets the center, second click sets the radius.';
                break;
            case 'line':
                helpText.textContent =
                    'Click on the map to add line points. Double-click to finish.';
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
        const mapId = mapIdInput.value;
        if (!mapId) return;

        // If there's a function to load objects on the map
        if (map && map.loadGeoObjects) {
            map.loadGeoObjects(mapId);
        }
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
    };

    // Initialize form
    setCreateMode();
    if (typeSelect.value) {
        updateTypeHelp(typeSelect.value);
    }
}
