<script>
    (() => {
        let theme = 'light';
        try {
            theme = localStorage.getItem('summit-theme') === 'dark' ? 'dark' : 'light';
        } catch {}
        document.documentElement.dataset.theme = theme;
    })();
</script>
