import assert from 'node:assert/strict';
import { test } from 'node:test';
import { randomUUID } from 'node:crypto';

const base = process.env.TEST_BASE_URL || 'http://localhost:8080/';
const enabled = process.env.VELVET_ALLOW_TEST_WRITES === '1';
function client() {
    let cookie = '';
    let token = '';
    return async (route, method = 'GET', body, csrf = true) => {
        const url = new URL('api.php', base);
        const original = new URL(route, base);
        url.search = original.search;
        url.searchParams.set('route', original.pathname);
        const response = await fetch(url, {
            method, headers: { Cookie: cookie, 'Content-Type': 'application/json', ...(csrf && token ? { 'X-CSRF-Token': token } : {}) },
            body: body === undefined ? undefined : JSON.stringify(body)
        });
        for (const header of response.headers.getSetCookie()) cookie = header.split(';')[0];
        const data = await response.json();
        if (data.csrfToken) token = data.csrfToken;
        return { status: response.status, data };
    };
}

test('PHP/MySQL storefront workflows and access boundaries', { skip: !enabled }, async context => {
    const customer = client();
    const other = client();
    const admin = client();
    const suffix = randomUUID();
    const address = { recipientName: 'Test Customer', phoneNumber: '0771234567', line1: '16 Main Street',
        line2: '', city: 'Jaffna', state: 'Northern', postalCode: '40000', country: 'Sri Lanka', isDefaultShipping: true };
    const registration = { ...address, fullName: 'Test Customer', email: `test-${suffix}@example.test`, password: randomUUID() };
    await context.test('Session and CSRF protection', async () => {
        assert.equal((await customer('/api/session')).status, 200);
        assert.equal((await customer('/api/auth/register','POST',registration,false)).status, 403);
        assert.equal((await customer('/api/auth/me')).status, 401);
        assert.equal((await customer('/api/admin/overview')).status, 401);
    });
    let product;
    let stock;
    let originalQuantity;
    await context.test('Products, filters and static HTML', async () => {
        const products = await customer('/api/products');
        assert.equal(products.status, 200);
        assert.ok(products.data.length >= 4);
        product = products.data.find(item => item.sizes.length);
        stock = product.sizes[0];
        originalQuantity = stock.quantity;
        const filtered = await customer(`/api/products?search=${encodeURIComponent(product.name)}`);
        assert.ok(filtered.data.every(item => item.name.includes(product.name)));
        const injection = await customer("/api/products?search=' OR 1=1 --");
        assert.deepEqual(injection.data, []);
        for (const page of ['index','products','login','account','cart','orders','contact','privacy','admin','admin-login']) {
            const response = await fetch(new URL(`${page}.html`, base));
            assert.equal(response.status, 200);
            assert.doesNotMatch(await response.text(), /@foreach|@model|asp-controller|@ViewData/);
        }
        const privateFile = await fetch(new URL('backend/schema.sql',base));
        assert.doesNotMatch(await privateFile.text(), /CREATE TABLE|CREATE DATABASE/);
    });
    let addressId;
    await context.test('Registration, profile and addresses', async () => {
        assert.equal((await customer('/api/auth/register','POST',registration)).status,201);
        assert.equal((await customer('/api/auth/me')).data.role,'Customer');
        const addresses = await customer('/api/addresses');
        assert.equal(addresses.data.length,1);
        addressId = addresses.data[0].id;
        assert.equal((await customer('/api/addresses','POST',{ ...address, city:'Colombo' })).status,200);
        const refreshed = (await customer('/api/addresses')).data;
        assert.equal(refreshed.filter(item => item.isDefaultShipping).length,1);
        assert.equal((await customer('/api/auth/profile','PUT',{ fullName:'Updated Customer',email:registration.email })).status,200);
        assert.equal((await customer('/api/auth/me')).data.fullName,'Updated Customer');
    });
    let itemId;
    await context.test('Cart quantities and ownership', async () => {
        assert.equal((await customer('/api/cart/add','POST',{ productId:product.id,selectedSize:stock.size,quantity:1 })).status,200);
        itemId = (await customer('/api/cart')).data.items[0].id;
        assert.equal((await customer(`/api/cart/item/${itemId}`,'PUT',{ quantity:0 })).status,400);
        assert.equal((await customer(`/api/cart/item/${itemId}`,'PUT',{ quantity:originalQuantity+1 })).status,409);
        assert.equal((await customer('/api/cart')).data.items[0].quantity,1);
        await other('/api/session');
        assert.equal((await other('/api/auth/register','POST',{ ...registration,email:`other-${suffix}@example.test` })).status,201);
        assert.equal((await other(`/api/cart/item/${itemId}`,'DELETE')).status,404);
        assert.equal((await other(`/api/addresses/${addressId}`,'PUT',address)).status,404);
        assert.equal((await customer('/api/admin/products','POST',{})).status,403);
    });
    let orderId;
    await context.test('Checkout totals, snapshots and inventory', async () => {
        const denied = await other('/api/orders/checkout','POST',{ userAddressId:addressId });
        assert.equal(denied.status,400);
        const order = await customer('/api/orders/checkout','POST',{ userAddressId:addressId,paymentMethod:'Cash on Delivery' });
        assert.equal(order.status,201);
        orderId = order.data.id;
        assert.equal(order.data.totalAmount,Math.round((product.price+10)*100)/100);
        assert.equal((await customer('/api/cart')).data.items.length,0);
        assert.equal((await customer(`/api/products/${product.id}`)).data.sizeStocks.find(item => item.size === stock.size).quantity,originalQuantity-1);
        await customer(`/api/addresses/${addressId}`,'PUT',{ ...address,city:'Changed City' });
        const orders = (await customer('/api/orders/mine')).data;
        assert.equal(orders[0].shippingAddress.city,'Jaffna');
        assert.equal(orders[0].items[0].productNameSnapshot,product.name);
        assert.equal((await customer('/api/orders/checkout','POST',{ userAddressId:addressId })).status,400);
    });
    await context.test('Contact storage and logout/login', async () => {
        assert.equal((await customer('/api/contact','POST',{ name:'Test',email:registration.email,subject:'Test inquiry',message:'Integration test message' })).status,201);
        assert.equal((await customer('/api/auth/logout','POST')).status,200);
        assert.equal((await customer('/api/cart')).status,401);
        assert.equal((await customer('/api/auth/login','POST',{ email:registration.email,password:'invalid password' })).status,401);
        assert.equal((await customer('/api/auth/login','POST',registration)).status,200);
    });
    await context.test('Admin permissions, product validation and cancellation', { skip: !process.env.TEST_ADMIN_KEY }, async () => {
        await admin('/api/session');
        const details = { fullName:'Test Admin',email:`admin-${suffix}@example.test`,password:randomUUID(),adminSecretKey:process.env.TEST_ADMIN_KEY };
        assert.equal((await admin('/api/auth/admin-register','POST',{ ...details,adminSecretKey:'wrong' })).status,403);
        assert.equal((await admin('/api/auth/admin-register','POST',details)).status,201);
        assert.equal((await admin('/api/auth/login','POST',details)).status,401);
        assert.equal((await admin('/api/auth/admin-login','POST',details)).status,200);
        assert.equal((await admin('/api/admin/overview')).status,200);
        assert.equal((await admin('/api/admin/products','POST',{})).status,400);
        const categories = (await admin('/api/admin/categories')).data;
        const newProduct = { name:'Integration Product',description:'Test product',categoryId:categories[0].subCategories[0].id,
            gender:'Unisex',color:'White',price:12.50,imageUrl:product.imageUrl,sizeStocks:[{size:'Small',quantity:3}] };
        const created = await admin('/api/admin/products','POST',newProduct);
        assert.equal(created.status,200);
        assert.equal((await admin(`/api/admin/products/${created.data.id}`,'PUT',{ ...newProduct,price:15 })).status,200);
        assert.equal((await admin(`/api/admin/products/${created.data.id}`)).data.price,15);
        assert.equal((await admin(`/api/admin/products/${created.data.id}`,'DELETE')).status,200);
        assert.equal((await admin(`/api/admin/orders/${orderId}/status`,'PUT',{status:'Delivered'})).status,409);
        assert.equal((await admin(`/api/admin/orders/${orderId}/status`,'PUT',{status:'Cancelled'})).status,200);
        assert.equal((await admin(`/api/admin/orders/${orderId}/status`,'PUT',{status:'Cancelled'})).status,200);
        assert.equal((await customer(`/api/products/${product.id}`)).data.sizeStocks.find(item => item.size === stock.size).quantity,originalQuantity);
    });
});