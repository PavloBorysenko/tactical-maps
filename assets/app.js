/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.scss';
import './styles/sides.css';

// Import Font Awesome icons
import '@fortawesome/fontawesome-free/css/all.css';

// Import Bootstrap components
import { Modal, Tooltip } from 'bootstrap';

// Import Leaflet and fix default icons path
import L from 'leaflet';
L.Icon.Default.prototype.options.imagePath = '/build/images/leaflet/';

// Initialize Bootstrap components when DOM is loaded
document.addEventListener('DOMContentLoaded', function () {
    // Initialize all modals
    const modals = document.querySelectorAll('.modal');
    modals.forEach((modal) => {
        new Modal(modal);
    });

    // Initialize all tooltips
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach((tooltip) => {
        new Tooltip(tooltip);
    });
});
