// Livewire supplies Alpine; no second Alpine instance is loaded.
const applyTheme = theme => {
    const selectedTheme = theme === 'dark' ? 'dark' : 'light';
    document.documentElement.dataset.theme = selectedTheme;
    document.querySelectorAll('[data-theme-toggle]').forEach(button => {
        const label = `Switch to ${selectedTheme === 'dark' ? 'light' : 'dark'} theme`;
        button.setAttribute('aria-label', label);
        button.title = label;
    });
};
applyTheme(document.documentElement.dataset.theme);
document.addEventListener('click', event => {
    if (!event.target.closest('[data-theme-toggle]')) {
        return;
    }
    const theme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    applyTheme(theme);
    try {
        localStorage.setItem('summit-theme', theme);
    } catch {}
});
window.addEventListener('storage', event => {
    if (event.key === 'summit-theme' || event.key === null) {
        applyTheme(event.newValue);
    }
});
window.addEventListener('pageshow', () => {
    try {
        applyTheme(localStorage.getItem('summit-theme'));
    } catch {}
});
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
