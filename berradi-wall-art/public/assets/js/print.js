/* Print buttons on printable documents (CSP: no inline handlers). */
document.addEventListener('click', function (e) {
    if (e.target.closest('[data-print]')) { e.preventDefault(); window.print(); }
});
