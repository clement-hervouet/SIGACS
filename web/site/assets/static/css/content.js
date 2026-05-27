document.addEventListener('DOMContentLoaded', () => {

    const contentArea = document.getElementById('contentArea');

    function loadContent(url) {
        fetch(url)
            .then(r => r.text())
            .then(html => { contentArea.innerHTML = html; })
            .catch(() => { contentArea.innerHTML = '<p class="no-data">// erreur de chargement</p>'; });
    }

    // Délégation sur tous les liens .line dans le nav tree
    document.querySelector('.navigation_tree').addEventListener('click', e => {
        const line = e.target.closest('.line[data-content]');
        if (!line) return;
        e.preventDefault();
        loadContent(line.dataset.content);
    });

});