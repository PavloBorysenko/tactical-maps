/**
 * Observer Management JavaScript
 * Handles token copying and other observer management functionality
 */

class ObserverManagement {
    constructor() {
        this.init();
    }

    /**
     * Initialize observer management functionality
     */
    init() {
        console.log('ObserverManagement: Initializing...');

        // Setup token copying functionality
        this.setupTokenCopying();

        // Setup other observer management features
        this.setupAccessLinkGeneration();
        this.setupTokenRefresh();
        this.setupUrlCopying();
    }

    /**
     * Setup token copying functionality for both index and show pages
     */
    setupTokenCopying() {
        // Handle copy buttons in observer index (multiple tokens)
        this.setupIndexTokenCopying();

        // Handle copy functionality in observer show page (single token)
        this.setupShowTokenCopying();
    }

    /**
     * Setup token copying for observer index page
     */
    setupIndexTokenCopying() {
        const copyButtons = document.querySelectorAll('.copy-token-btn');

        copyButtons.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();

                const token = button.getAttribute('data-token');
                if (!token) {
                    console.error(
                        'ObserverManagement: No token found in button data'
                    );
                    this.showError('No token to copy');
                    return;
                }

                this.copyToClipboard(token, button);
            });
        });

        console.log(
            `ObserverManagement: Setup ${copyButtons.length} copy token buttons`
        );
    }

    /**
     * Setup token copying for observer show page
     */
    setupShowTokenCopying() {
        // Make copyToken function available globally for onclick handlers
        window.copyToken = () => {
            const tokenInput = document.getElementById('tokenInput');
            if (!tokenInput) {
                console.error('ObserverManagement: Token input not found');
                return;
            }

            const token = tokenInput.value;
            const button = event.target.closest('button');

            this.copyToClipboard(token, button, true);
        };
    }

    /**
     * Setup URL copying functionality
     */
    setupUrlCopying() {
        const copyUrlButtons = document.querySelectorAll('.copy-url-btn');

        copyUrlButtons.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();

                const url = button.getAttribute('data-url');
                if (!url) {
                    console.error(
                        'ObserverManagement: No URL found in button data'
                    );
                    this.showError('No URL to copy');
                    return;
                }

                this.copyToClipboard(url, button);
            });
        });

        console.log(
            `ObserverManagement: Setup ${copyUrlButtons.length} copy URL buttons`
        );
    }

    /**
     * Copy text to clipboard with visual feedback
     */
    async copyToClipboard(text, button, isInputBased = false) {
        try {
            // Try modern clipboard API first
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
            } else {
                // Fallback for older browsers or non-HTTPS
                if (isInputBased) {
                    const tokenInput = document.getElementById('tokenInput');
                    tokenInput.select();
                    tokenInput.setSelectionRange(0, 99999);
                    document.execCommand('copy');
                } else {
                    // Create temporary input for copying
                    const tempInput = document.createElement('input');
                    tempInput.value = text;
                    document.body.appendChild(tempInput);
                    tempInput.select();
                    document.execCommand('copy');
                    document.body.removeChild(tempInput);
                }
            }

            // Show success feedback
            this.showCopySuccess(button);

            console.log('ObserverManagement: Text copied successfully');
        } catch (error) {
            console.error('ObserverManagement: Failed to copy text:', error);
            this.showError('Failed to copy to clipboard');
        }
    }

    /**
     * Show visual feedback when token is copied successfully
     */
    showCopySuccess(button) {
        if (!button) return;

        const originalContent = button.innerHTML;
        const originalClasses = button.className;

        // Update button to show success
        button.innerHTML = '<i class="fas fa-check"></i>';
        button.title = 'Copied!';

        // Update classes based on current button style
        if (button.classList.contains('btn-outline-primary')) {
            button.classList.remove('btn-outline-primary');
            button.classList.add('btn-success');
        } else if (button.classList.contains('btn-outline-secondary')) {
            button.classList.remove('btn-outline-secondary');
            button.classList.add('btn-success');
        } else {
            button.classList.add('btn-success');
        }

        // Disable button temporarily
        button.disabled = true;

        // Revert after 2 seconds
        setTimeout(() => {
            button.innerHTML = originalContent;
            button.className = originalClasses;
            button.title = button.classList.contains('copy-url-btn')
                ? 'Copy URL'
                : 'Copy token';
            button.disabled = false;
        }, 2000);
    }

    /**
     * Setup access link generation
     */
    setupAccessLinkGeneration() {
        const generateLinkButtons =
            document.querySelectorAll('.generate-link-btn');

        generateLinkButtons.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();

                const token = button.getAttribute('data-token');
                if (!token) {
                    console.error(
                        'ObserverManagement: No token found for link generation'
                    );
                    return;
                }

                const observerUrl = `${window.location.origin}/observer/${token}`;
                this.copyToClipboard(observerUrl, button);
            });
        });
    }

    /**
     * Setup token refresh functionality
     */
    setupTokenRefresh() {
        const refreshButtons = document.querySelectorAll('.refresh-token-btn');

        refreshButtons.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();

                const confirmRefresh = confirm(
                    'Are you sure you want to refresh this token? ' +
                        'The old token will become invalid and any existing links will stop working.'
                );

                if (confirmRefresh) {
                    const observerId = button.getAttribute('data-observer-id');
                    this.refreshObserverToken(observerId, button);
                }
            });
        });
    }

    /**
     * Refresh observer token via AJAX
     */
    async refreshObserverToken(observerId, button) {
        const originalContent = button.innerHTML;

        try {
            // Show loading state
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            button.disabled = true;

            const response = await fetch(
                `/admin/observers/${observerId}/refresh-token`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                }
            );

            if (response.ok) {
                const data = await response.json();

                if (data.success && data.newToken) {
                    // Update token in UI
                    this.updateTokenInUI(observerId, data.newToken);
                    this.showSuccess('Token refreshed successfully');
                } else {
                    throw new Error(data.message || 'Failed to refresh token');
                }
            } else {
                throw new Error(
                    `HTTP ${response.status}: ${response.statusText}`
                );
            }
        } catch (error) {
            console.error('ObserverManagement: Token refresh failed:', error);
            this.showError('Failed to refresh token: ' + error.message);
        } finally {
            // Restore button
            button.innerHTML = originalContent;
            button.disabled = false;
        }
    }

    /**
     * Update token in UI elements
     */
    updateTokenInUI(observerId, newToken) {
        // Update token input if on show page
        const tokenInput = document.getElementById('tokenInput');
        if (tokenInput) {
            tokenInput.value = newToken;
        }

        // Update data-token attributes
        const tokenButtons = document.querySelectorAll(
            `[data-observer-id="${observerId}"]`
        );
        tokenButtons.forEach((button) => {
            if (button.hasAttribute('data-token')) {
                button.setAttribute('data-token', newToken);
            }
        });

        // Update any displayed token text
        const tokenDisplays = document.querySelectorAll('.token-display');
        tokenDisplays.forEach((display) => {
            if (display.getAttribute('data-observer-id') === observerId) {
                display.textContent = newToken;
            }
        });

        // Update URL buttons with new token
        const urlButtons = document.querySelectorAll('.copy-url-btn');
        urlButtons.forEach((button) => {
            const currentUrl = button.getAttribute('data-url');
            if (currentUrl && currentUrl.includes('/observer/')) {
                const newUrl = currentUrl.replace(
                    /\/observer\/[^\/]+/,
                    `/observer/${newToken}`
                );
                button.setAttribute('data-url', newUrl);
            }
        });
    }

    /**
     * Show success message
     */
    showSuccess(message) {
        this.showAlert(message, 'success');
    }

    /**
     * Show error message
     */
    showError(message) {
        this.showAlert(message, 'danger');
    }

    /**
     * Show alert message
     */
    showAlert(message, type = 'info') {
        // Create alert element
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        alert.style.cssText =
            'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        alert.innerHTML = `
            <i class="fas fa-${
                type === 'success' ? 'check-circle' : 'exclamation-triangle'
            }"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        document.body.appendChild(alert);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    }

    /**
     * Get observer statistics
     */
    getStatistics() {
        const copyButtons = document.querySelectorAll('.copy-token-btn').length;
        const refreshButtons =
            document.querySelectorAll('.refresh-token-btn').length;
        const urlButtons = document.querySelectorAll('.copy-url-btn').length;

        return {
            copyButtonsCount: copyButtons,
            refreshButtonsCount: refreshButtons,
            urlButtonsCount: urlButtons,
            hasTokenInput: !!document.getElementById('tokenInput'),
        };
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function () {
    console.log('ObserverManagement: DOM loaded, initializing...');

    // Create global instance
    window.observerManagement = new ObserverManagement();

    // Add to window for debugging
    if (process.env.NODE_ENV === 'development') {
        window.getObserverManagementStats = () =>
            window.observerManagement.getStatistics();
    }
});

export default ObserverManagement;
