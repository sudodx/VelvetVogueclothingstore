<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/database.php';

try {
    $connection = database();
    $connection->beginTransaction();
    if (!(int) $connection->query('SELECT COUNT(*) FROM categories')->fetchColumn()) {
        $insert = $connection->prepare('INSERT INTO categories (name,parentId) VALUES (?,?)');
        foreach (['Men' => ['Shirts','Trousers','Jackets'], 'Women' => ['Dresses','Tops','Skirts'], 'Kids' => ['Boys','Girls','Baby']] as $name => $children) {
            $insert->execute([$name,null]);
            $parentId = (int) $connection->lastInsertId();
            foreach ($children as $child) $insert->execute([$child,$parentId]);
        }
    }
    if (!(int) $connection->query('SELECT COUNT(*) FROM products')->fetchColumn()) {
        $categories = $connection->query('SELECT name,id FROM categories WHERE parentId IS NOT NULL')->fetchAll(PDO::FETCH_KEY_PAIR);
        $insert = $connection->prepare('INSERT INTO products (name,description,categoryId,gender,color,price,imageUrl) VALUES (?,?,?,?,?,?,?)');
        $stock = $connection->prepare('INSERT INTO stocks VALUES (?,?,?)');
        $products = [
            ['Classic White Shirt','Breathable cotton shirt for formal and casual wear.','Shirts','Unisex','White',29.99,'shirts/classic-white-shirt.jpg',['Small'=>5,'Large'=>10,'XL'=>15]],
            ['Slim Fit Chinos','Smart-casual chinos with a modern slim fit.','Trousers','Men','Navy',39.99,'trousers/slim-fit-chinos.jpg',['Medium'=>20,'Large'=>12]],
            ['Floral Summer Dress','Lightweight dress ideal for weekend outings.','Dresses','Women','Floral',49.99,'dresses/floral-summer-dress.jpg',['Small'=>8,'Medium'=>14,'Large'=>13]],
            ['Urban Denim Jacket','Layer-friendly denim jacket with timeless style.','Jackets','Unisex','Blue',59.99,'jackets/urban-denim-jacket.jpg',['Large'=>10,'XL'=>15]]
        ];
        foreach ($products as $product) {
            $insert->execute([$product[0],$product[1],$categories[$product[2]],$product[3],$product[4],$product[5],'assets/img/categories/'.$product[6]]);
            $productId = (int) $connection->lastInsertId();
            foreach ($product[7] as $size => $quantity) $stock->execute([$productId,$size,$quantity]);
        }
    }
    $connection->commit();
    echo "Categories and demo products are ready. Register a customer from login.html.\n";
    echo "For admin registration set ADMIN_REGISTRATION_KEY to a private random value of at least 24 characters.\n";
} catch (Throwable $error) {
    if (isset($connection) && $connection->inTransaction()) $connection->rollBack();
    fwrite(STDERR, "Setup failed. Import backend/schema.sql and check DB_HOST, DB_PORT, DB_NAME, DB_USER and DB_PASSWORD.\n");
    exit(1);
}