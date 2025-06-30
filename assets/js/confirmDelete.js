// Confirm Delete functionality
document.addEventListener('DOMContentLoaded', function () {
    // Handle all delete forms with confirmation
    const deleteForms = document.querySelectorAll('.confirm-delete-form');

    deleteForms.forEach(function (form) {
        form.addEventListener('submit', function (e) {
            const message =
                this.dataset.confirmMessage ||
                'Are you sure you want to delete this item?';
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    });
});
