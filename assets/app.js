/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// Import main stylesheet - modular SCSS architecture
import './styles/app.scss';

// Import Font Awesome icons
import '@fortawesome/fontawesome-free/css/all.css';

// Import Bootstrap components
import { Modal, Tooltip } from 'bootstrap';

// Import Leaflet and fix default icons path
import L from 'leaflet';
L.Icon.Default.prototype.options.imagePath = '/build/images/leaflet/';

// Import background image as module (avoids resolve-url-loader issues)
import backgroundImage from './images/vector-topographic.avif';

// Initialize Bootstrap components when DOM is loaded
document.addEventListener('DOMContentLoaded', function () {
    // Set background image dynamically
    document.body.style.backgroundImage = `url('${backgroundImage}')`;

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
