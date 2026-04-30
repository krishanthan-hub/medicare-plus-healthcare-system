// login.js

document.addEventListener('DOMContentLoaded', function() {
    const redirectElement = document.querySelector('[data-redirect]');
    if (redirectElement) {
        const url = redirectElement.getAttribute('data-redirect');
        setTimeout(function() {
            window.location.href = url;
        }, 1500);
    }
});