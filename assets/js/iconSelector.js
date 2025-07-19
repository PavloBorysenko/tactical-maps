/**
 * Universal Icon Selector Module
 * Can be used for any form that needs icon selection functionality
 */

class IconSelector {
    constructor(config) {
        this.config = {
            inputSelector: null,
            gridSelector: null,
            clearButtonSelector: null,
            itemClassName: 'icon-item',
            nameClassName: 'icon-name',
            selectedClassName: 'selected',
            loadingText: 'Loading icons...',
            errorText: 'Error loading icons',
            emptyText: 'No custom icons available',
            emptyHelpText:
                'Add PNG, JPG, or SVG files to /public/assets/icons/custom/',
            ...config,
        };

        this.iconUrlInput = null;
        this.iconGrid = null;
        this.clearIconBtn = null;
        this.availableIcons = [];

        this.init();
    }

    /**
     * Initialize the icon selector
     */
    init() {
        // Find elements
        this.iconUrlInput = document.querySelector(this.config.inputSelector);
        this.iconGrid = document.querySelector(this.config.gridSelector);
        this.clearIconBtn = document.querySelector(
            this.config.clearButtonSelector
        );

        // Check if required elements exist
        if (!this.iconUrlInput || !this.iconGrid) {
            return false; // Elements not found, exit gracefully
        }

        // Set up clear button
        if (this.clearIconBtn) {
            this.clearIconBtn.addEventListener('click', () => {
                this.clearSelection();
            });
        }

        // Load icons from server
        this.loadIcons();

        return true;
    }

    /**
     * Load icons from the API
     */
    loadIcons() {
        // Show loading state
        this.iconGrid.innerHTML = `<div class="text-muted">${this.config.loadingText}</div>`;

        fetch('/api/icons')
            .then((response) => response.json())
            .then((data) => {
                if (data.success) {
                    this.availableIcons = data.icons;
                    this.renderGrid();

                    // Set selected icon if there's already a value
                    if (this.iconUrlInput.value) {
                        this.selectByUrl(this.iconUrlInput.value);
                    }
                } else {
                    this.iconGrid.innerHTML = `<div class="text-muted">${this.config.emptyText}</div>`;
                }
            })
            .catch((error) => {
                console.error('Error loading icons:', error);
                this.iconGrid.innerHTML = `<div class="text-danger">${this.config.errorText}</div>`;
            });
    }

    /**
     * Render the icon grid
     */
    renderGrid() {
        if (!this.iconGrid || !this.availableIcons) return;

        if (this.availableIcons.length === 0) {
            this.iconGrid.innerHTML = `
                <div class="text-muted">
                    ${this.config.emptyText}. ${this.config.emptyHelpText}
                </div>
            `;
            return;
        }

        this.iconGrid.innerHTML = '';

        this.availableIcons.forEach((icon) => {
            const iconItem = document.createElement('div');
            iconItem.className = this.config.itemClassName;
            iconItem.setAttribute('data-icon-url', icon.url);
            iconItem.title = `Click to select ${icon.name}`;

            iconItem.innerHTML = `
                <img src="${icon.url}" alt="${icon.name}" onerror="this.style.display='none'">
                <div class="${this.config.nameClassName}">${icon.name}</div>
            `;

            iconItem.addEventListener('click', () => {
                this.select(icon.url);
            });

            this.iconGrid.appendChild(iconItem);
        });
    }

    /**
     * Select an icon
     */
    select(iconUrl) {
        if (!this.iconUrlInput) return;

        // Update input field
        this.iconUrlInput.value = iconUrl;

        // Update visual selection
        this.updateVisualSelection(iconUrl);

        // Trigger change event for form validation
        this.iconUrlInput.dispatchEvent(new Event('change', { bubbles: true }));
    }

    /**
     * Select icon by URL (for editing existing items)
     */
    selectByUrl(iconUrl) {
        this.updateVisualSelection(iconUrl);
    }

    /**
     * Update visual selection
     */
    updateVisualSelection(iconUrl) {
        // Remove previous selection
        const allItems = document.querySelectorAll(
            `.${this.config.itemClassName}`
        );
        allItems.forEach((item) => {
            item.classList.remove(this.config.selectedClassName);
        });

        // Add selection to current item
        if (iconUrl) {
            const selectedItem = document.querySelector(
                `[data-icon-url="${iconUrl}"]`
            );
            if (selectedItem) {
                selectedItem.classList.add(this.config.selectedClassName);
            }
        }
    }

    /**
     * Clear icon selection
     */
    clearSelection() {
        if (!this.iconUrlInput) return;

        this.iconUrlInput.value = '';
        this.updateVisualSelection('');

        // Trigger change event
        this.iconUrlInput.dispatchEvent(new Event('change', { bubbles: true }));
    }

    /**
     * Get currently selected icon URL
     */
    getSelectedIcon() {
        return this.iconUrlInput ? this.iconUrlInput.value : null;
    }

    /**
     * Set selected icon programmatically
     */
    setSelectedIcon(iconUrl) {
        this.select(iconUrl);
    }

    /**
     * Refresh icons (reload from server)
     */
    refresh() {
        this.loadIcons();
    }
}

// Export for use in other modules
window.IconSelector = IconSelector;
