document.addEventListener('DOMContentLoaded', () => {

// Container injected into the page to hold the loaded modal
var modalContainer = document.getElementById("modalContainer");

function openModal(file) {
  fetch(file)
    .then(function(response) { return response.text(); })
    .then(function(html) {
      modalContainer.innerHTML = html;
      modalContainer.style.display = "block";

      // Bind cancel button inside the freshly loaded modal
      var cancel = modalContainer.querySelector(".cancel");
      if (cancel) cancel.onclick = closeModal;
    });
}

function closeModal() {
  modalContainer.style.display = "none";
  modalContainer.innerHTML = "";
}

// Nav buttons
var newSerreBtn = document.getElementById("newSerreBtn");
var newBacBtn = document.getElementById("newBacBtn");
var newCultureBtn = document.getElementById("newCultureBtn");

if (newSerreBtn) newSerreBtn.onclick = function() { openModal("forms/new/new_serre.html");}
if (newBacBtn)   newBacBtn.onclick   = function() { openModal("forms/new/new_bac.html"); }
if (newCultureBtn) newCultureBtn.onclick = function() { openModal("forms/new/new_culture.html"); }

// Click outside the modal content to close
window.onclick = function(event) {
  if (event.target == modalContainer) {
    closeModal();
  }
}

});

function bindContentLinks() {
    mainContent.querySelectorAll('[data-action]').forEach(el => {
        el.addEventListener('click', (e) => {
            e.preventDefault();
            const action = el.dataset.action;
            const target = el.dataset.target;
            if (action === 'content') loadContent(target);
            if (action === 'modal')   openModal(target);
        });
    });
}

function loadContent(url) {
    loadHTML(url, mainContent).then(() => bindContentLinks());
}

document.addEventListener('DOMContentLoaded', () => {

    // ─── ELEMENTS ────────────────────────────────────────────────
    const mainContent   = document.getElementById('mainContent');
    const navTree       = document.getElementById('navigationTree');
    const modalContainer = document.getElementById('modalContainer');

    // ─── UTILITY: fetch HTML into a target element ────────────────
    async function loadHTML(url, target) {
        try {
            const res = await fetch(url);
            if (!res.ok) throw new Error('HTTP ' + res.status);
            target.innerHTML = await res.text();
        } catch (e) {
            target.innerHTML = '<p style="color:red">Erreur de chargement.</p>';
        }
    }

    // ─── CONTENT SWAP ─────────────────────────────────────────────
    // Fetches a PHP partial and injects it into #mainContent
    function loadContent(url) {
        loadHTML(url, mainContent);
    }

    // ─── MODAL ────────────────────────────────────────────────────
    function openModal(url, serreId = null) {
        loadHTML(url, modalContainer).then(() => {
            modalContainer.style.display = 'block';

            // Inject serre id into hidden input (new bac form)
            if (serreId !== null) {
                const input = modalContainer.querySelector('input[name="serre"]');
                if (input) input.value = serreId;
            }

            // Bind cancel button
            const cancel = modalContainer.querySelector('.cancel');
            if (cancel) cancel.onclick = closeModal;

            // Intercept form submit
            const form = modalContainer.querySelector('form');
            if (form) form.addEventListener('submit', handleFormSubmit);
        });
    }

    function closeModal() {
        modalContainer.style.display = 'none';
        modalContainer.innerHTML = '';
    }

    // Click outside modal content → close
    modalContainer.addEventListener('click', (e) => {
        if (e.target === modalContainer) closeModal();
    });

    // ─── FORM SUBMIT ──────────────────────────────────────────────
    // Sends form via fetch, expects JSON back from the PHP handler
    async function handleFormSubmit(e) {
        e.preventDefault();
        const form = e.target;

        // Clear previous errors
        form.querySelectorAll('.form-error').forEach(el => el.remove());

        const res = await fetch(form.getAttribute('action'), {
            method: 'POST',
            body: new FormData(form),
        });

        const raw = await res.text();
        console.log('PHP response:', raw);

        let data;
        try {
            data = JSON.parse(raw);
        } catch(e) {
            console.error('Not JSON. Raw response:', raw);
            showErrors(form, ['Erreur serveur — voir console.']);
            return;
        }

        if (data.success) {
            closeModal();
            // Refresh the menu tree from DB
            await refreshMenu();
            // If the response tells us what was created, load its detail page
            if (data.redirect) loadContent(data.redirect);
        } else {
            // Show errors inside the form
            showErrors(form, data.errors ?? ['Erreur inconnue.']);
        }
    }

    function showErrors(form, errors) {
        const footer = form.querySelector('.form-footer');
        const div = document.createElement('div');
        div.className = 'form-error';
        div.style.color = 'red';
        div.style.padding = '8px';
        div.innerHTML = errors.map(e => `<p>${e}</p>`).join('');
        footer.before(div);
    }

    // ─── MENU REFRESH ─────────────────────────────────────────────
    // Re-fetches the menu from DB and rebinds all handlers
    async function refreshMenu() {
        await loadHTML('menu/content_ajax.php', navTree);
        bindMenuClicks();
        bindTreeToggles();
    }

    // ─── MENU CLICK BINDING ───────────────────────────────────────
    // Reads data-action and data-target on every .line
    function bindMenuClicks() {
        navTree.querySelectorAll('.line[data-action]').forEach(line => {
            // Clone to remove any old listeners
            const fresh = line.cloneNode(true);
            line.replaceWith(fresh);

            fresh.addEventListener('click', (e) => {
                e.preventDefault();
                const action  = fresh.dataset.action;
                const target  = fresh.dataset.target;
                const serreId = fresh.dataset.serreId ?? null;

                if (action === 'content') loadContent(target);
                if (action === 'modal')   openModal(target, serreId);
            });
        });
    }

    // ─── TREE TOGGLE (from old script.js) ─────────────────────────
    // Expand/collapse serre and culture sub-lists
    function bindTreeToggles() {
        navTree.querySelectorAll('.line').forEach(line => {
            line.addEventListener('click', (e) => {
                e.preventDefault();

                const li      = line.closest('li');
                const childUl = li.querySelector(':scope > ul');
                if (!childUl) return;

                const isOpen = childUl.classList.contains('open');
                childUl.classList.toggle('open');

                const toggleImg = line.querySelector('img[src*="square"]');
                if (toggleImg) {
                    toggleImg.src = isOpen
                        ? 'assets/static/icons/navigation_tree/square-plus.svg'
                        : 'assets/static/icons/navigation_tree/square-minus.svg';
                }
            });
        });
    }

    // ─── INIT ─────────────────────────────────────────────────────
    bindMenuClicks();
    bindTreeToggles();

});