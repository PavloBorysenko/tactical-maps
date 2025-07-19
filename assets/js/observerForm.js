/**
 * Observer Form JavaScript functionality
 * Uses the universal IconSelector module
 */

document.addEventListener('DOMContentLoaded', function () {
    initObserverForm();
});

/**
 * Initialize observer form
 */
function initObserverForm() {
    // Initialize icon selector for observer forms
    const observerIconSelector = new IconSelector({
        inputSelector: '.observer-icon-url',
        gridSelector: '#observer-icon-grid',
        clearButtonSelector: '#observer-clear-icon-btn',
        itemClassName: 'observer-icon-item',
        nameClassName: 'observer-icon-name',
        selectedClassName: 'selected',
        loadingText: 'Loading icons...',
        errorText: 'Error loading icons',
        emptyText: 'No custom icons available',
        emptyHelpText:
            'Add PNG, JPG, or SVG files to /public/assets/icons/custom/',
    });

    // Store reference globally if needed for other functionality
    window.observerIconSelector = observerIconSelector;
}
