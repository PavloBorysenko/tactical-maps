# Map Components Architecture

This directory contains refactored map components for the tactical-maps project.

## File Structure

### baseMapComponent.js

**Base class** for all map components. Contains common functionality:

-   Leaflet map initialization with layers
-   Toolbar management
-   Form utilities (get/set field values)
-   Extracting coordinates from HTML attributes
-   Cleanup and destruction methods

### mapViewer.js

**Map viewer component** for displaying and interacting with tactical maps:

-   Extends `BaseMapComponent`
-   Manages geo-objects through `MapGeoObjectManager`
-   Handles drawing events
-   Cursor management
-   Globally available as `window.tacticalMap`

### mapEditor.js

**Map editor component** for creating and editing maps:

-   Extends `BaseMapComponent`
-   Adds draggable center marker
-   Synchronizes map coordinates with form
-   Updates form fields when map changes

### mapLayers.js

**Map layers manager**:

-   Defines available base layers (Satellite, Street Map, Hybrid, etc.)
-   Sets default layer (Satellite)
-   Initializes map with layers

### mapToolbar.js

**Map toolbar**:

-   Map centering button
-   Layer selector with dropdown menu
-   Coordinate mode
-   Distance measurement tools

### mapGeoObjects.js

**Geo-objects map manager**:

-   Manages geo-objects display
-   Drawing modes (points, polygons, lines, circles)
-   Backend API interaction
-   Edit/delete functionality for popup buttons
-   Side filtering with legend control

### geoObjectForm.js

**Geo-objects form**:

-   Interface for creating and editing geo-objects
-   Form validation
-   Map integration
-   Global window.geoObjectForm registration

### home.js

**Home page scripts**:

-   Basic home page functionality

## Refactoring Benefits

### 1. **DRY Principle**

Common code extracted to `BaseMapComponent`, eliminating duplication

### 2. **Inheritance**

Clear class hierarchy with shared methods in the base class

### 3. **Readability**

Code became more structured and understandable

### 4. **Maintainability**

Easier to add new map components and maintain existing ones

### 5. **Testability**

Individual methods are easier to test in isolation

## Usage

### Creating a new map component

```javascript
import BaseMapComponent from './baseMapComponent';

class MyMapComponent extends BaseMapComponent {
    constructor(options = {}) {
        super();
        // Your logic
        this.init();
    }

    init() {
        const coordinates = this.getMapCoordinatesFromContainer(
            this.container,
            { lat: 0, lng: 0, zoom: 10 }
        );

        this.initializeLeafletMap(this.container, coordinates);
        this.initializeToolbar({
            centerLat: coordinates.lat,
            centerLng: coordinates.lng,
            zoom: coordinates.zoom,
        });
    }
}
```

### BaseMapComponent Methods

#### Map Initialization

-   `getMapCoordinatesFromContainer(container, defaults)` - extract coordinates from HTML attributes
-   `initializeLeafletMap(container, coordinates, options)` - create Leaflet map
-   `initializeToolbar(mapData)` - create toolbar

#### Utilities

-   `getValueFromField(fieldId, fallback)` - get value from form field
-   `setFieldValue(fieldId, value)` - set form field value
-   `setElementText(elementId, text)` - set element text content
-   `invalidateMapSizeAfterDelay(delay)` - update map size

#### Cleanup

-   `destroy()` - destroy component and cleanup resources
-   `getLeafletMap()` - get Leaflet map instance

## Configuration

### Map Layers

Configuration in `mapLayers.js`:

-   Satellite (default)
-   Street Map
-   Hybrid
-   Topographic
-   Light Theme
-   Dark Theme

### Toolbar

Configuration in `mapToolbar.js`:

-   Icons loaded from `/assets/images/toolbar-icons/`
-   Custom icon support with emoji fallback
-   Responsive design for mobile devices

## Features

### Popup Buttons

Geo-objects display popup windows with Edit and Delete buttons that:

-   Handle loading order dependencies between mapViewer.js and geoObjectForm.js
-   Include error handling and retry logic
-   Support both creation and editing modes
-   Provide visual feedback and logging

### Side Filtering

Map objects can be filtered by sides using:

-   Legend control with checkboxes
-   Color-coded side indicators
-   Real-time visibility toggling
-   Sidebar list synchronization

### Drawing Modes

Support for creating various geo-object types:

-   **Points** - Single click placement with custom icons
-   **Polygons** - Multi-click creation with minimum 3 points
-   **Lines** - Multi-click creation with minimum 2 points
-   **Circles** - Two-click creation (center + radius)

### Edit Mode

All geo-object types support editing with:

-   Draggable control points
-   Visual feedback during editing
-   Right-click to delete points (polygons/lines)
-   Real-time geometry updates

## File Naming Convention

All JavaScript files follow **camelCase** naming convention for consistency and professionalism:

-   ✅ `baseMapComponent.js`
-   ✅ `mapViewer.js`
-   ✅ `mapEditor.js`
-   ✅ `mapLayers.js`
-   ✅ `mapToolbar.js`
-   ✅ `mapGeoObjects.js`
-   ✅ `geoObjectForm.js`
-   ✅ `home.js`

## Webpack Integration

All components are properly configured in `webpack.config.js` with entry points:

```javascript
.addEntry('mapEditor', './assets/js/mapEditor.js')
.addEntry('mapViewer', './assets/js/mapViewer.js')
.addEntry('geoObjectForm', './assets/js/geoObjectForm.js')
```

## Dependencies

-   **Leaflet.js** - Core mapping library
-   **Bootstrap** - UI styling
-   **Symfony Webpack Encore** - Asset management
