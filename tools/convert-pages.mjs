import { readFileSync, writeFileSync, mkdirSync, cpSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const source = path.join(root, 'VelvetVogueclothingstore');
const output = path.join(root, 'public');
mkdirSync(path.join(output, 'assets/js'), { recursive: true });
for (const directory of ['css', 'lib', 'img']) {
    cpSync(path.join(source, 'wwwroot', directory), path.join(output, 'assets', directory), { recursive: true });
}
for (const name of ['main.js']) {
    cpSync(path.join(source, 'wwwroot/js', name), path.join(output, 'assets/js', name));
}

const pages = {
    Index: 'index.html', Home: 'index.html', Products: 'products.html',
    Cart: 'cart.html', Login: 'login.html', Account: 'account.html',
    Orders: 'orders.html', Contact: 'contact.html', Privacy: 'privacy.html'
};
function links(text) {
    return text.replace(/<a\b[^>]*\basp-controller="(Home|Admin)"[^>]*>/g, tag => {
        const controller = /asp-controller="([^"]+)"/.exec(tag)[1];
        const action = /asp-action="([^"]+)"/.exec(tag)?.[1] || 'Index';
        const destination = controller === 'Admin' ? (action === 'Login' ? 'admin-login.html' : 'admin.html') : pages[action];
        if (!destination) throw new Error(`Unknown page: ${controller}/${action}`);
        return tag.replace(/\sasp-[\w-]+="[^"]*"/g, '').replace('<a', `<a href="${destination}"`);
    }).replaceAll('~/', 'assets/').replace(/\sasp-append-version="true"/g, '')
        .replaceAll('/Home/Account', 'account.html').replaceAll('/Home/Login', 'login.html')
        .replaceAll('"/admin/login"', '"admin-login.html"').replaceAll("'/admin/login'", "'admin-login.html'")
        .replaceAll('"/admin"', '"admin.html"').replaceAll("'/admin'", "'admin.html'");
}

const navigation = `<header class="border-bottom mb-4"><nav class="navbar navbar-expand-lg navbar-light bg-white container">
<a class="navbar-brand" href="index.html">Velvet Vogue</a>
<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#siteNav" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
<div class="collapse navbar-collapse" id="siteNav"><div class="navbar-nav me-auto">
${[['index','Home'],['products','Products'],['cart','Cart'],['orders','Orders'],['account','Account'],['contact','Contact']].map(([href, label]) => `<a class="nav-link" href="${href}.html">${label}</a>`).join('')}
</div><a class="nav-link" href="login.html" id="sessionLink">Login</a><a class="nav-link" href="admin-login.html">Admin</a><button type="button" id="adminLogoutBtn" class="btn btn-outline-dark d-none">Logout</button></div></nav></header>`;
const head = title => `<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>${title} - Velvet Vogue</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
<link href="assets/lib/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet"><link href="assets/css/site.css" rel="stylesheet">
<link href="assets/css/php-site.css" rel="stylesheet"></head><body>`;
const scripts = `<script src="assets/lib/jquery/dist/jquery.min.js"></script>
<script src="assets/lib/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/lib/easing/easing.min.js"></script><script src="assets/lib/owlcarousel/owl.carousel.min.js"></script>
<script src="assets/js/main.js"></script><script src="assets/js/transport.js"></script>
<script src="assets/js/store.js"></script><script src="assets/js/pages.js"></script>`;

for (const [view, filename] of Object.entries(pages).filter(([view]) => view !== 'Index')) {
    let markup = readFileSync(path.join(source, 'Views/Home', `${view}.cshtml`), 'utf8');
    markup = markup.replace(/^@model[^\r\n]*[\r\n]+/, '').replace(/@\{[\s\S]*?\}\s*/, '');
    markup = markup.replace(/@section Scripts\s*\{[\s\S]*$/, '');
    markup = markup.replaceAll('@ViewData["Title"]', view === 'Privacy' ? 'Privacy Policy' : view);
    if (view === 'Home') {
        markup = markup.replace(/@foreach \(var category in Model.Categories\)[\s\S]*?(?=\s*<\/div>\s*<\/nav>)/,
            '<div id="homeCategories"></div>');
        markup = markup.replace(/@if \(isAuthenticated\)\s*\{([\s\S]*?)\}/, '$1');
        markup = markup.replace(/@for \(var i = 0;[\s\S]*?(?=\s*<\/div>\s*<button class="carousel-control-prev")/,
            `<div class="carousel-item active" style="height:410px"><img class="img-fluid w-100 h-100 object-fit-cover" src="assets/img/categories/jackets/urban-denim-jacket.jpg" alt="Urban Denim Jacket"><div class="carousel-caption"><h2>Velvet Vogue</h2><a href="products.html" class="btn btn-light">Shop Now</a></div></div>`);
        markup = markup.replace(/@foreach \(var product in Model.FeaturedProducts\)[\s\S]*?\n        \}/, '<div id="featuredProducts" class="row g-3 m-0"></div>');
        markup = markup.replace(/@foreach \(var product in Model.NewArrivalProducts\)[\s\S]*?\n        \}/, '<div id="newProducts" class="row g-3 m-0"></div>');
        markup = markup.replace('<form>', '<form action="products.html">').replace('placeholder="Search for products"', 'name="search" placeholder="Search for products"');
    }
    if (view === 'Contact') {
        markup = markup.replace('<form>', '<div id="contactAlert" role="status" class="alert d-none"></div><form id="contactForm">')
            .replace('placeholder="Your Name"', 'name="name" required maxlength="150" placeholder="Your Name"')
            .replace('placeholder="Your Email"', 'name="email" required maxlength="254" placeholder="Your Email"')
            .replace('placeholder="Subject"', 'name="subject" required maxlength="200" placeholder="Subject"')
            .replace('placeholder="Message"', 'name="message" required maxlength="5000" placeholder="Message"')
            .replace('type="button">Send Message', 'type="submit">Send Message');
    }
    markup = links(markup);
    if (/@(?:model|foreach|if|for|section|\{|Url\.|ViewData|product\.|category\.|slide\.)|asp-/.test(markup)) {
        throw new Error(`Unconverted Razor in ${view}`);
    }
    writeFileSync(path.join(output, filename), `${head(view)}${navigation}<main class="${view === 'Home' ? '' : 'container'} pb-4">${markup}</main><footer class="border-top py-3 text-center">&copy; 2026 Velvet Vogue</footer>${scripts}</body></html>\n`);
}

for (const [view, filename] of [['Index', 'admin.html'], ['Login', 'admin-login.html']]) {
    let markup = readFileSync(path.join(source, 'Views/Admin', `${view}.cshtml`), 'utf8').replace(/@\{[\s\S]*?\}\s*/, '');
    markup = markup.replace(/@section Scripts\s*\{[\s\S]*$/, '');
    markup = markup.replace('name="imageUrl" placeholder="Uploaded image path" readonly required', 'name="imageUrl" placeholder="Uploaded image path" readonly');
    markup = markup.replaceAll('class="card mb-4"', 'class="border-bottom mb-4"').replace('class="card"', 'class="mb-4"');
    writeFileSync(path.join(output, filename), `${head('Admin')}${navigation}<main class="container pb-4">${links(markup)}</main>${scripts}</body></html>\n`);
}

let store = readFileSync(path.join(source, 'wwwroot/js/store.js'), 'utf8');
store = links(store).replaceAll('await fetch(', 'await window.StoreTransport.fetch(');
store = store.replace('window.StoreApp = (() => {', `window.StoreApp = (() => {\n    const escapeHtml = window.StoreTransport.escapeHtml;`);
store = store.replace(/\$\{((?:p|a|i|o|c|order)\.(?:name|description|category|gender|imageUrl|productName|recipientName|phoneNumber|line1|city|state|postalCode|country|customer|customerName|status|paymentMethod))\}/g, '${escapeHtml($1)}');
store = store.replaceAll('${s}</option>', '${escapeHtml(s)}</option>').replaceAll('value="${s}"', 'value="${escapeHtml(s)}"');
store = store.replaceAll('${order.shippingAddress.recipientName}', '${escapeHtml(order.shippingAddress.recipientName)}')
    .replaceAll('${order.shippingAddress.line1}', '${escapeHtml(order.shippingAddress.line1)}')
    .replaceAll('${order.shippingAddress.city}', '${escapeHtml(order.shippingAddress.city)}')
    .replaceAll('${i.productNameSnapshot}', '${escapeHtml(i.productNameSnapshot)}');
store = store.replace('if (gender) query.set("gender", gender);', 'if (gender) query.set("gender", gender);\n            if (initialQuery.get("search")) query.set("search", initialQuery.get("search"));');
store = store.replace('const card = button.closest(".card");', 'const card = button.closest(".card");');
writeFileSync(path.join(output, 'assets/js/store.js'), store);
console.log('Converted 10 Razor pages to standalone HTML and copied static assets.');