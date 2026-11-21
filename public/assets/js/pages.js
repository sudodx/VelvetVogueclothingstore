(() => {
    const transport = window.StoreTransport;
    const escapeHtml = transport.escapeHtml;
    async function request(route, method = 'GET', body) {
        const response = await transport.fetch(route, {
            method, headers: body ? { 'Content-Type': 'application/json' } : {},
            body: body ? JSON.stringify(body) : undefined
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Request failed.');
        return data;
    }
    function alertAt(id, message, success = false) {
        const element = document.getElementById(id);
        if (!element) return;
        element.className = `alert alert-${success ? 'success' : 'danger'}`;
        element.setAttribute('role', 'status');
        element.textContent = message;
    }
    async function session() {
        try {
            const me = await request('/api/auth/me');
            const link = document.getElementById('sessionLink');
            if (link) { link.textContent = me.fullName; link.href = me.role === 'Admin' ? 'admin.html' : 'account.html'; }
            document.getElementById('adminLogoutBtn')?.classList.remove('d-none');
            if (document.getElementById('adminOverview') && me.role !== 'Admin') location.replace('admin-login.html');
        } catch {
            if (document.getElementById('adminOverview')) location.replace('admin-login.html');
        }
    }
    async function home() {
        if (!document.getElementById('homeCategories')) return;
        try {
            const [categories, products] = await Promise.all([request('/api/products/categories'), request('/api/products')]);
            document.getElementById('homeCategories').innerHTML = categories.map(category => `
                <div class="nav-item dropdown w-100"><a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">${escapeHtml(category.name)}</a>
                <div class="dropdown-menu">${category.subCategories.map(subcategory => `<a class="dropdown-item" href="products.html?${escapeHtml(new URLSearchParams({ mainCategory: category.name, subcategory }))}">${escapeHtml(subcategory)}</a>`).join('')}</div></div>`).join('');
            const cards = items => items.map(product => `<div class="col-6 col-lg-3"><a class="text-decoration-none text-dark" href="products.html?search=${encodeURIComponent(product.name)}"><img class="product-photo" src="${escapeHtml(product.imageUrl)}" alt="${escapeHtml(product.name)}" loading="lazy"><h3 class="h6 mt-3">${escapeHtml(product.name)}</h3><p>LKR ${Number(product.price).toFixed(2)}</p></a></div>`).join('');
            document.getElementById('featuredProducts').innerHTML = cards(products.slice(0, 4)) || '<p>No products available.</p>';
            document.getElementById('newProducts').innerHTML = cards([...products].sort((first, second) => new Date(second.dateAdded) - new Date(first.dateAdded)).slice(0, 4));
        } catch (error) {
            document.getElementById('featuredProducts').textContent = error.message;
        }
    }
    for (const [id, route] of [['adminLoginForm', '/api/auth/admin-login'], ['adminRegisterForm', '/api/auth/admin-register']]) {
        document.getElementById(id)?.addEventListener('submit', async event => {
            event.preventDefault();
            try {
                await request(route, 'POST', Object.fromEntries(new FormData(event.currentTarget)));
                if (id === 'adminLoginForm') location.href = 'admin.html';
                else { event.target.reset(); alertAt('adminLoginAlert', 'Admin account created. You can sign in now.', true); }
            } catch (error) { alertAt('adminLoginAlert', error.message); }
        });
    }
    document.getElementById('adminLogoutBtn')?.addEventListener('click', async () => {
        try { await request('/api/auth/logout', 'POST'); location.href = 'login.html'; }
        catch (error) { alertAt('adminAlert', error.message); }
    });
    document.getElementById('contactForm')?.addEventListener('submit', async event => {
        event.preventDefault();
        const form = event.currentTarget;
        const button = form.querySelector('button');
        button.disabled = true;
        try { const result = await request('/api/contact', 'POST', Object.fromEntries(new FormData(form))); form.reset(); alertAt('contactAlert', result.message, true); }
        catch (error) { alertAt('contactAlert', error.message); }
        finally { button.disabled = false; }
    });
    for (const form of document.querySelectorAll('#registerForm, #adminRegisterForm')) {
        const password = form.querySelector('[name="password"]');
        if (password) { password.minLength = 12; password.maxLength = 72; password.autocomplete = 'new-password'; }
    }
    const pageInitializers = [
        ['productsGrid', 'initProductsPage'], ['cartItemsBody', 'initCartPage'],
        ['loginForm', 'initLoginPage'], ['userDetailsSection', 'initAccountPage'],
        ['ordersContainer', 'initOrdersPage'], ['adminOverview', 'initAdminPage']
    ];
    for (const [id, initializer] of pageInitializers) {
        if (document.getElementById(id)) window.StoreApp[initializer]().catch(error => {
            const alert = document.querySelector('[id$="Alert"], #authStatus');
            if (alert) alertAt(alert.id, error.message);
        });
    }
    session();
    home();
})();