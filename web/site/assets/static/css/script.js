document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.navigation_tree .line').forEach(line => {
        line.addEventListener('click', (e) => {
            e.preventDefault();
            
            const li = line.closest('li');
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
});