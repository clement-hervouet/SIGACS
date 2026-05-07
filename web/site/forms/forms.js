// ─── GLOBALS (accessible from injected <script> tags in partials) ────────────
var mainContent    = null;
var navTree        = null;
var modalContainer = null;

// ─── UTILITY: fetch HTML into a target element ────────────────────────────────
async function loadHTML(url, target) {
    try {
        const res = await fetch(url);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        target.innerHTML = await res.text();

        // Re-execute any <script> tags injected via innerHTML
        target.querySelectorAll('script').forEach(oldScript => {
            const newScript = document.createElement('script');
            [...oldScript.attributes].forEach(a => newScript.setAttribute(a.name, a.value));
            newScript.textContent = oldScript.textContent;
            oldScript.replaceWith(newScript);
        });

    } catch (e) {
        target.innerHTML = '<p style="color:red">Erreur de chargement.</p>';
    }
}

// ─── CONTENT SWAP ─────────────────────────────────────────────────────────────
function loadContent(url) {
    loadHTML(url, mainContent).then(() => bindContentLinks());
}

// ─── MODAL ────────────────────────────────────────────────────────────────────
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

// ─── FORM SUBMIT ──────────────────────────────────────────────────────────────
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
        await refreshMenu();
        if (data.redirect) loadContent(data.redirect);
    } else {
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

// ─── MENU REFRESH ─────────────────────────────────────────────────────────────
async function refreshMenu() {
    await loadHTML('menu/content_ajax.php', navTree);
    bindMenuClicks();
    bindTreeToggles();
}

// ─── CONTENT LINK BINDING (inside #mainContent partials) ──────────────────────
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

// ─── MENU CLICK BINDING ───────────────────────────────────────────────────────
function bindMenuClicks() {
    navTree.querySelectorAll('.line[data-action]').forEach(line => {
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

// ─── TREE TOGGLE ──────────────────────────────────────────────────────────────
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

// ─── INIT ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    mainContent    = document.getElementById('mainContent');
    navTree        = document.getElementById('navigationTree');
    modalContainer = document.getElementById('modalContainer');

    // Click outside modal → close
    modalContainer.addEventListener('click', (e) => {
        if (e.target === modalContainer) closeModal();
    });

    bindMenuClicks();
    bindTreeToggles();
});