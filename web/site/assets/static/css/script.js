document.querySelectorAll('.navigation_tree .line').forEach(line => {
    line.addEventListener('click', (e) => {
        e.preventDefault();
        
        const li = line.closest('li');
        const childUl = li.querySelector(':scope > ul');
        
        if (!childUl) return; // no children, nothing to toggle
        
        childUl.style.display = childUl.style.display === 'none' ? '' : 'none';
    });
});