// Livewire supplies Alpine; no second Alpine instance is loaded.
const connectionBanner = () => document.getElementById('connection-status');
const updateConnection = () => {
    const banner = connectionBanner();
    if (banner) {
        banner.hidden = navigator.onLine;
    }
};
window.addEventListener('offline', updateConnection);
window.addEventListener('online', updateConnection);
window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-submitting]').forEach(form => delete form.dataset.submitting);
    updateConnection();
});
document.addEventListener('submit', event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.hasAttribute('wire:submit')) {
        return;
    }
    if (!navigator.onLine) {
        event.preventDefault();
        updateConnection();
        connectionBanner()?.focus();
        return;
    }
    if (form.dataset.submitting) {
        event.preventDefault();
        return;
    }
    if (!event.defaultPrevented) {
        form.dataset.submitting = 'true';
    }
});
