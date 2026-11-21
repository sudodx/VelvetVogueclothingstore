<?php
declare(strict_types=1);
require_once __DIR__ . '/database.php';

ini_set('display_errors', '0');
session_name('velvet_session');
session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax',
    'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'use_strict_mode' => true]);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function respond(mixed $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function fail(string $message, int $status = 400): never
{
    throw new DomainException($message, $status);
}

function query(string $sql, array $parameters = []): PDOStatement
{
    $statement = database()->prepare($sql);
    $statement->execute($parameters);
    return $statement;
}

function text(array $data, string $key, int $maximum = 255, bool $required = true): string
{
    if (isset($data[$key]) && !is_string($data[$key])) fail("Invalid {$key}.");
    $value = trim($data[$key] ?? '');
    if (($required && $value === '') || strlen($value) > $maximum) fail("Invalid {$key}.");
    return $value;
}

function positiveInt(mixed $value): int
{
    $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000000]]);
    if ($number === false) fail('A positive whole number is required.');
    return $number;
}

function user(bool $admin = false): array
{
    $account = isset($_SESSION['userId'])
        ? query('SELECT id, fullName, email, role FROM users WHERE id = ?', [$_SESSION['userId']])->fetch() : false;
    if (!$account) fail('Unauthorized. Please login.', 401);
    if ($admin && $account['role'] !== 'Admin') fail('Administrator access required.', 403);
    return $account;
}

function addressValues(array $data): array
{
    $values = [];
    foreach (['recipientName' => 150, 'phoneNumber' => 30, 'line1' => 255, 'line2' => 255,
        'city' => 100, 'state' => 100, 'postalCode' => 30, 'country' => 100] as $key => $limit) {
        $values[] = text($data, $key, $limit, $key !== 'line2');
    }
    $values[] = filter_var($data['isDefaultShipping'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    return $values;
}

function saveAddress(array $data, int $userId, ?int $id = null): int
{
    $values = addressValues($data);
    if ($id && !query('SELECT id FROM addresses WHERE id = ? AND userId = ? FOR UPDATE', [$id, $userId])->fetch()) {
        fail('Address not found.', 404);
    }
    if ($values[8]) query('UPDATE addresses SET isDefaultShipping = 0 WHERE userId = ?', [$userId]);
    if ($id) {
        query('UPDATE addresses SET recipientName=?,phoneNumber=?,line1=?,line2=?,city=?,state=?,postalCode=?,country=?,isDefaultShipping=? WHERE id=? AND userId=?', [...$values, $id, $userId]);
        return $id;
    }
    query('INSERT INTO addresses (recipientName,phoneNumber,line1,line2,city,state,postalCode,country,isDefaultShipping,userId) VALUES (?,?,?,?,?,?,?,?,?,?)', [...$values, $userId]);
    return (int) database()->lastInsertId();
}

function categories(bool $admin = false): array
{
    $parents = query('SELECT id,name FROM categories WHERE parentId IS NULL ORDER BY name')->fetchAll();
    foreach ($parents as &$parent) {
        $children = query('SELECT id,name FROM categories WHERE parentId = ? ORDER BY name', [$parent['id']])->fetchAll();
        $parent['subCategories'] = $admin ? $children : array_column($children, 'name');
    }
    return $parents;
}

function products(bool $admin = false, ?int $id = null): array
{
    $conditions = [];
    $values = [];
    if ($id !== null) { $conditions[] = 'p.id = ?'; $values[] = $id; }
    foreach (['gender' => 'p.gender', 'categoryId' => 'p.categoryId', 'mainCategoryId' => 'c.parentId'] as $key => $column) {
        if (!empty($_GET[$key]) && ($admin || $key === 'gender')) {
            $conditions[] = "{$column} = ?"; $values[] = text($_GET, $key, 100);
        }
    }
    $subcategory = $_GET['subcategory'] ?? $_GET['category'] ?? '';
    if ($subcategory !== '') { $conditions[] = 'c.name = ?'; $values[] = text(['name' => $subcategory], 'name', 100); }
    elseif (!empty($_GET['mainCategory'])) { $conditions[] = 'parent.name = ?'; $values[] = text($_GET, 'mainCategory', 100); }
    if (!empty($_GET['search'])) {
        $conditions[] = '(p.name LIKE ? OR p.description LIKE ? OR p.gender LIKE ?)';
        $search = '%' . text($_GET, 'search', 200) . '%';
        array_push($values, $search, $search, $search);
    }
    foreach (['minPrice' => '>=', 'maxPrice' => '<='] as $key => $operator) {
        if (isset($_GET[$key])) {
            if (!is_numeric($_GET[$key])) fail('Invalid price filter.');
            $conditions[] = "p.price {$operator} ?"; $values[] = $_GET[$key];
        }
    }
    if ($admin && ($_GET['lastWeekOnly'] ?? '') === 'true') $conditions[] = 'p.dateAdded >= UTC_TIMESTAMP() - INTERVAL 7 DAY';
    $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
    $rows = query('SELECT p.*, c.name AS category, parent.name AS mainCategory, c.parentId AS mainCategoryId FROM products p JOIN categories c ON c.id=p.categoryId LEFT JOIN categories parent ON parent.id=c.parentId' . $where . ' ORDER BY p.name', $values)->fetchAll();
    foreach ($rows as &$row) {
        $row['price'] = (float) $row['price'];
        $stocks = query('SELECT size,quantity FROM stocks WHERE productId=? ORDER BY size', [$row['id']])->fetchAll();
        $row['sizes'] = array_values(array_filter($stocks, fn(array $stock): bool => $stock['quantity'] > 0));
        $row['sizeStocks'] = $stocks;
        $row['totalStock'] = array_sum(array_column($stocks, 'quantity'));
        $row['dateAdded'] = str_replace(' ', 'T', $row['dateAdded']) . 'Z';
    }
    return $rows;
}

function saveProduct(array $data, ?int $id): int
{
    if ($id && !query('SELECT id FROM products WHERE id=? FOR UPDATE', [$id])->fetch()) fail('Product not found.', 404);
    $category = positiveInt($data['categoryId'] ?? null);
    if (!query('SELECT id FROM categories WHERE id=? AND parentId IS NOT NULL', [$category])->fetch()) fail('Select a subcategory.');
    $price = $data['price'] ?? null;
    if (!is_numeric($price) || !is_finite((float) $price) || $price < 0 || $price > 9999999999.99) fail('Invalid price.');
    $image = text($data, 'imageUrl', 1000);
    if (!preg_match('~^(?:assets/img/[a-zA-Z0-9_./-]+|image\.php\?name=[a-f0-9]{32}\.(?:jpg|png|webp))$~D', $image) || str_contains($image, '..')) fail('Upload a product image.');
    $gender = text($data, 'gender', 30);
    if (!in_array($gender, ['Men','Women','Kids','Unisex'], true)) fail('Invalid gender.');
    $date = text($data, 'dateAdded', 40, false);
    try { $date = $date === '' ? gmdate('Y-m-d H:i:s') : (new DateTimeImmutable($date))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); }
    catch (Exception) { fail('Invalid product date.'); }
    $values = [text($data, 'name', 200), text($data, 'description', 10000), $category,
        $gender, text($data, 'color', 80), round((float) $price, 2), $image, $date];
    $stocks = $data['sizeStocks'] ?? [];
    if (!is_array($stocks) || !$stocks || count($stocks) > 4) fail('Choose at least one size.');
    $seen = [];
    foreach ($stocks as $stock) {
        if (!is_array($stock) || !in_array($stock['size'] ?? '', ['Small','Medium','Large','XL'], true)
            || isset($seen[$stock['size']])) fail('Invalid or duplicate size.');
        $seen[$stock['size']] = positiveInt($stock['quantity'] ?? null);
    }
    if ($id) query('UPDATE products SET name=?,description=?,categoryId=?,gender=?,color=?,price=?,imageUrl=?,dateAdded=? WHERE id=?', [...$values, $id]);
    else {
        query('INSERT INTO products (name,description,categoryId,gender,color,price,imageUrl,dateAdded) VALUES (?,?,?,?,?,?,?,?)', $values);
        $id = (int) database()->lastInsertId();
    }
    query('DELETE FROM stocks WHERE productId=?', [$id]);
    foreach ($seen as $size => $quantity) query('INSERT INTO stocks VALUES (?,?,?)', [$id, $size, $quantity]);
    return $id;
}

function cart(int $userId, bool $lock = false): array
{
    $rows = query('SELECT cart.*,p.name AS productName,p.imageUrl,p.price FROM cart JOIN products p ON p.id=cart.productId WHERE cart.userId=? ORDER BY cart.id' . ($lock ? ' FOR UPDATE' : ''), [$userId])->fetchAll();
    foreach ($rows as &$row) {
        $row['price'] = (float) $row['price'];
        $row['lineTotal'] = round($row['quantity'] * $row['price'], 2);
    }
    return $rows;
}

function rateLimit(string $action, int $maximum): void
{
    $key = hash('sha256', $action . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'local'));
    query('INSERT INTO rate_limits (id,attempts,expiresAt) VALUES (?,1,UTC_TIMESTAMP()+INTERVAL 15 MINUTE) ON DUPLICATE KEY UPDATE attempts=IF(expiresAt < UTC_TIMESTAMP(),1,attempts+1),expiresAt=IF(expiresAt < UTC_TIMESTAMP(),UTC_TIMESTAMP()+INTERVAL 15 MINUTE,expiresAt)', [$key]);
    if (query('SELECT attempts FROM rate_limits WHERE id=?', [$key])->fetchColumn() > $maximum) fail('Too many attempts. Try again in 15 minutes.', 429);
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $route = $_GET['route'] ?? '';
    if (!is_string($route)) fail('Invalid route.');
    $_SESSION['csrfToken'] ??= bin2hex(random_bytes(32));
    if ($method === 'GET' && $route === '/api/session') respond(['csrfToken' => $_SESSION['csrfToken']]);
    if (!in_array($method, ['GET','HEAD'], true) && !hash_equals($_SESSION['csrfToken'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) fail('Invalid session token. Reload the page.', 403);
    $data = [];
    if (str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        $raw = file_get_contents('php://input', false, null, 0, 100001);
        if (strlen($raw) > 100000) fail('Request is too large.', 413);
        if ($raw !== '') {
            try { $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR); }
            catch (JsonException) { fail('Invalid JSON.'); }
            if (!is_array($data)) fail('A JSON object is required.');
        }
    }

    if ($route === '/api/auth/me' && $method === 'GET') respond(user());
    if ($route === '/api/auth/logout' && $method === 'POST') {
        unset($_SESSION['userId']);
        session_regenerate_id(true);
        respond(['message' => 'Logged out.']);
    }
    if (in_array($route, ['/api/auth/register','/api/auth/admin-register','/api/auth/login','/api/auth/admin-login'], true) && $method === 'POST') {
        rateLimit('auth', 30);
        $email = strtolower(text($data, 'email', 254));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Enter a valid email address.');
        $password = $data['password'] ?? '';
        if (!is_string($password) || strlen($password) > 72) fail('Password must be at most 72 bytes.');
        $admin = str_contains($route, 'admin-');
        if (str_ends_with($route, 'register')) {
            if (strlen($password) < 12) fail('Use a password of at least 12 characters.');
            if ($admin) {
                $secret = getenv('ADMIN_REGISTRATION_KEY');
                if (!$secret || strlen($secret) < 24 || !hash_equals($secret, text($data, 'adminSecretKey', 256))) fail('Admin registration is disabled or the key is invalid.', 403);
            }
            $name = text($data, 'fullName', 150);
            if (!$admin) addressValues($data);
            database()->beginTransaction();
            query('INSERT INTO users (fullName,email,passwordHash,role) VALUES (?,?,?,?)', [$name,$email,password_hash($password, PASSWORD_DEFAULT),$admin ? 'Admin' : 'Customer']);
            $userId = (int) database()->lastInsertId();
            if (!$admin) saveAddress($data, $userId);
            database()->commit();
            if (!$admin) { session_regenerate_id(true); $_SESSION['userId'] = $userId; }
            respond(['id' => $userId, 'email' => $email, 'fullName' => $name], 201);
        }
        $account = query('SELECT * FROM users WHERE email=?', [$email])->fetch();
        if (!$account || !password_verify($password, $account['passwordHash']) || ($account['role'] === 'Admin') !== $admin) fail('Invalid credentials or login type.', 401);
        session_regenerate_id(true);
        $_SESSION['userId'] = $account['id'];
        respond(user());
    }
    if ($route === '/api/auth/profile' && $method === 'PUT') {
        $account = user();
        $email = strtolower(text($data, 'email', 254));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Enter a valid email.');
        query('UPDATE users SET fullName=?,email=? WHERE id=?', [text($data,'fullName',150),$email,$account['id']]);
        respond(user());
    }

    if ($route === '/api/products/categories' && $method === 'GET') respond(categories());
    if ($route === '/api/products' && $method === 'GET') respond(products());
    if (preg_match('~^/api/products/(\d+)$~D', $route, $match) && $method === 'GET') {
        $rows = products(false, (int) $match[1]);
        if (!$rows) fail('Product not found.', 404);
        respond($rows[0]);
    }

    if (preg_match('~^/api/addresses(?:/(\d+))?$~D', $route, $match)) {
        $account = user();
        $id = isset($match[1]) ? (int) $match[1] : null;
        if ($method === 'GET' && !$id) {
            $addresses = query('SELECT * FROM addresses WHERE userId=? ORDER BY isDefaultShipping DESC,id', [$account['id']])->fetchAll();
            foreach ($addresses as &$address) $address['isDefaultShipping'] = (bool) $address['isDefaultShipping'];
            respond($addresses);
        }
        if (($method === 'POST' && !$id) || ($method === 'PUT' && $id)) {
            database()->beginTransaction();
            query('SELECT id FROM users WHERE id=? FOR UPDATE', [$account['id']]);
            $saved = saveAddress($data, $account['id'], $id);
            database()->commit();
            respond(['id' => $saved]);
        }
        if ($method === 'DELETE' && $id) {
            $deleted = query('DELETE FROM addresses WHERE id=? AND userId=?', [$id,$account['id']])->rowCount();
            if (!$deleted) fail('Address not found.', 404);
            respond(['message' => 'Address deleted.']);
        }
    }

    if ($route === '/api/cart' && $method === 'GET') {
        $items = cart(user()['id']);
        respond(['items' => $items, 'total' => round(array_sum(array_column($items, 'lineTotal')), 2)]);
    }
    if (($route === '/api/cart/add' && $method === 'POST') || (preg_match('~^/api/cart/item/(\d+)$~D', $route, $match) && in_array($method, ['PUT','DELETE'], true))) {
        $account = user();
        database()->beginTransaction();
        query('SELECT id FROM users WHERE id=? FOR UPDATE', [$account['id']]);
        if ($method === 'POST') {
            $productId = positiveInt($data['productId'] ?? null);
            $size = text($data, 'selectedSize', 10);
            if (!in_array($size, ['Small','Medium','Large','XL'], true)) fail('Invalid size.');
            $quantity = positiveInt($data['quantity'] ?? null);
            $item = query('SELECT * FROM cart WHERE userId=? AND productId=? AND selectedSize=? FOR UPDATE', [$account['id'],$productId,$size])->fetch();
            $quantity += $item ? $item['quantity'] : 0;
        } else {
            $item = query('SELECT * FROM cart WHERE id=? AND userId=? FOR UPDATE', [(int) $match[1],$account['id']])->fetch();
            if (!$item) fail('Cart item not found.', 404);
            $productId = $item['productId']; $size = $item['selectedSize'];
            $quantity = $method === 'DELETE' ? 0 : positiveInt($data['quantity'] ?? null);
        }
        if ($method === 'DELETE') query('DELETE FROM cart WHERE id=? AND userId=?', [$item['id'],$account['id']]);
        else {
            $available = query('SELECT quantity FROM stocks WHERE productId=? AND size=? FOR UPDATE', [$productId,$size])->fetchColumn();
            if ($available === false || $available < $quantity) fail('Insufficient stock for this size.', 409);
            if ($item) query('UPDATE cart SET quantity=? WHERE id=?', [$quantity,$item['id']]);
            else query('INSERT INTO cart (userId,productId,selectedSize,quantity) VALUES (?,?,?,?)', [$account['id'],$productId,$size,$quantity]);
        }
        database()->commit();
        respond(['message' => 'Cart updated.']);
    }

    if ($route === '/api/orders/checkout' && $method === 'POST') {
        $account = user();
        if (($data['paymentMethod'] ?? 'Cash on Delivery') !== 'Cash on Delivery') fail('Only Cash on Delivery is available.');
        database()->beginTransaction();
        query('SELECT id FROM users WHERE id=? FOR UPDATE', [$account['id']]);
        $addressId = positiveInt($data['userAddressId'] ?? null);
        $address = query('SELECT * FROM addresses WHERE id=? AND userId=? FOR UPDATE', [$addressId,$account['id']])->fetch();
        if (!$address) fail('Shipping address not found.');
        $items = cart($account['id'], true);
        if (!$items) fail('Cart is empty.');
        $totalCents = 1000;
        foreach ($items as $item) $totalCents += (int) round($item['price'] * 100) * $item['quantity'];
        $total = $totalCents / 100;
        query('INSERT INTO orders (userId,addressId,shippingAddress,totalAmount) VALUES (?,?,?,?)', [$account['id'],$addressId,json_encode($address, JSON_THROW_ON_ERROR),$total]);
        $orderId = (int) database()->lastInsertId();
        foreach ($items as $item) {
            $changed = query('UPDATE stocks SET quantity=quantity-? WHERE productId=? AND size=? AND quantity>=?', [$item['quantity'],$item['productId'],$item['selectedSize'],$item['quantity']])->rowCount();
            if (!$changed) fail('Insufficient stock. Refresh your cart.', 409);
            query('INSERT INTO order_items (orderId,productId,productName,selectedSize,quantity,unitPrice) VALUES (?,?,?,?,?,?)', [$orderId,$item['productId'],$item['productName'],$item['selectedSize'],$item['quantity'],$item['price']]);
        }
        query('DELETE FROM cart WHERE userId=?', [$account['id']]);
        database()->commit();
        respond(['id' => $orderId,'totalAmount' => $total,'status' => 'Pending','paymentMethod' => 'Cash on Delivery'], 201);
    }
    if ($route === '/api/orders/mine' && $method === 'GET') {
        $orders = query('SELECT id,status,totalAmount,createdAt AS createdAtUtc,shippingAddress FROM orders WHERE userId=? ORDER BY id DESC', [user()['id']])->fetchAll();
        foreach ($orders as &$order) {
            $order['totalAmount'] = (float) $order['totalAmount'];
            $order['createdAtUtc'] = str_replace(' ', 'T', $order['createdAtUtc']) . 'Z';
            $order['paymentMethod'] = 'Cash on Delivery';
            $order['shippingAddress'] = json_decode($order['shippingAddress'], true, 64, JSON_THROW_ON_ERROR);
            $order['items'] = query('SELECT productId,productName AS productNameSnapshot,selectedSize AS selectedSizeSnapshot,quantity,unitPrice,quantity*unitPrice AS lineTotal FROM order_items WHERE orderId=?', [$order['id']])->fetchAll();
        }
        respond($orders);
    }

    if (str_starts_with($route, '/api/admin/')) {
        user(true);
        if ($route === '/api/admin/categories' && $method === 'GET') respond(categories(true));
        if ($route === '/api/admin/overview' && $method === 'GET') {
            respond(query("SELECT (SELECT COUNT(*) FROM users) AS userCount,(SELECT COUNT(*) FROM products) AS productCount,(SELECT COUNT(*) FROM orders) AS orderCount,(SELECT COUNT(*) FROM orders WHERE status='Pending') AS pendingOrderCount,(SELECT COUNT(*) FROM cart) AS cartItemCount")->fetch());
        }
        if ($route === '/api/admin/products/upload-image' && $method === 'POST') {
            $file = $_FILES['file'] ?? null;
            if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024) fail('Choose a JPG, PNG or WebP image up to 5 MB.');
            $type = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            $extensions = ['image/jpeg' => 'jpg','image/png' => 'png','image/webp' => 'webp'];
            if (!isset($extensions[$type]) || !getimagesize($file['tmp_name'])) fail('Invalid image.');
            $name = bin2hex(random_bytes(16)) . '.' . $extensions[$type];
            $directory = __DIR__ . '/uploads';
            if (!is_dir($directory) && !mkdir($directory, 0750, true)) throw new RuntimeException('Upload directory unavailable.');
            if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $name)) throw new RuntimeException('Upload failed.');
            respond(['imageUrl' => 'image.php?name=' . $name], 201);
        }
        if (preg_match('~^/api/admin/products(?:/(\d+))?$~D', $route, $match)) {
            $id = isset($match[1]) ? (int) $match[1] : null;
            if ($method === 'GET') {
                $rows = products(true, $id);
                if ($id && !$rows) fail('Product not found.', 404);
                respond($id ? $rows[0] : $rows);
            }
            if (($method === 'POST' && !$id) || ($method === 'PUT' && $id)) {
                database()->beginTransaction();
                $saved = saveProduct($data, $id);
                database()->commit();
                respond(['id' => $saved]);
            }
            if ($method === 'DELETE' && $id) {
                if (!query('DELETE FROM products WHERE id=?', [$id])->rowCount()) fail('Product not found.', 404);
                respond(['message' => 'Product deleted.']);
            }
        }
        if ($route === '/api/admin/orders' && $method === 'GET') {
            respond(query('SELECT o.id,o.status,o.totalAmount,o.createdAt AS createdAtUtc,u.fullName AS customer FROM orders o JOIN users u ON u.id=o.userId ORDER BY o.id DESC')->fetchAll());
        }
        if ($route === '/api/admin/cart-items' && $method === 'GET') {
            respond(query('SELECT u.fullName AS customerName,p.name AS productName,c.quantity,c.quantity*p.price AS lineTotal FROM cart c JOIN users u ON u.id=c.userId JOIN products p ON p.id=c.productId')->fetchAll());
        }
        if (preg_match('~^/api/admin/orders/(\d+)/status$~D', $route, $match) && $method === 'PUT') {
            $status = text($data, 'status', 20);
            database()->beginTransaction();
            $order = query('SELECT * FROM orders WHERE id=? FOR UPDATE', [(int) $match[1]])->fetch();
            if (!$order) fail('Order not found.', 404);
            $allowed = ['Pending' => ['Processing','Cancelled'], 'Processing' => ['Shipped','Cancelled'], 'Shipped' => ['Delivered'], 'Delivered' => [], 'Cancelled' => []];
            if ($status !== $order['status'] && !in_array($status, $allowed[$order['status']], true)) fail('Invalid order status transition.', 409);
            if ($status === 'Cancelled' && $order['status'] !== 'Cancelled') {
                foreach (query('SELECT * FROM order_items WHERE orderId=?', [$order['id']])->fetchAll() as $item) {
                    query('INSERT INTO stocks (productId,size,quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity=quantity+?', [$item['productId'],$item['selectedSize'],$item['quantity'],$item['quantity']]);
                }
            }
            query('UPDATE orders SET status=? WHERE id=?', [$status,$order['id']]);
            database()->commit();
            respond(['message' => 'Order updated.']);
        }
    }

    if ($route === '/api/contact' && $method === 'POST') {
        rateLimit('contact', 5);
        $email = text($data, 'email', 254);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Enter a valid email.');
        query('INSERT INTO contact_messages (name,email,subject,message) VALUES (?,?,?,?)', [text($data,'name',150),$email,text($data,'subject',200),text($data,'message',5000)]);
        respond(['message' => 'Your message has been received.'], 201);
    }
    fail('Endpoint or method not found.', 404);
} catch (DomainException $error) {
    if (isset($connection)) unset($connection);
    respond(['message' => $error->getMessage()], $error->getCode() ?: 400);
} catch (PDOException $error) {
    error_log('Velvet Vogue database error: ' . $error->getCode());
    if ($error->getCode() === '23000') respond(['message' => 'This email already exists, or this record is in use.'], 409);
    respond(['message' => 'Database unavailable. Check the PHP database configuration.'], 503);
} catch (Throwable $error) {
    error_log('Velvet Vogue API error: ' . get_class($error));
    respond(['message' => 'The request could not be completed.'], 500);
}