/**
 * BERRADI PRINT - JavaScript Frontend
 */

document.addEventListener('DOMContentLoaded', function() {
    // Auto-dismiss des alertes après 5 secondes
    document.querySelectorAll('.alert-dismissible').forEach(function(alert) {
        setTimeout(function() {
            var bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }, 5000);
    });

    // Animation au scroll
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in-visible');
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.card, .category-card, .product-card').forEach(function(el) {
        observer.observe(el);
    });
});
