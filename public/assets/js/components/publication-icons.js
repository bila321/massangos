/**
 * Publication Icons System - JavaScript Handler
 * Manages the 4-icon publication system with modal popup
 */

// Open publication modal with specific type
function openPublicationModal(type) {
    const modal = document.getElementById('publicationModal');
    if (modal) {
        modal.classList.add('show');
        // Store the selected type for potential use
        modal.dataset.selectedType = type;
        // Redirect to upload.php with type parameter
        window.location.href = baseUrl + 'upload.php?type=' + encodeURIComponent(type);
    }
}

// Close publication modal
function closePublicationModal() {
    const modal = document.getElementById('publicationModal');
    if (modal) {
        modal.classList.remove('show');
    }
}

// Close modal when clicking outside of it
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('publicationModal');
    if (modal) {
        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                closePublicationModal();
            }
        });
    }

    // Close modal with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closePublicationModal();
        }
    });

    // Attach click handlers to publication icon buttons
    const buttons = document.querySelectorAll('.publication-icon-btn');
    buttons.forEach(button => {
        button.addEventListener('click', function() {
            const type = this.dataset.type;
            if (type) {
                openPublicationModal(type);
            }
        });
    });
});
