CREATE DATABASE IF NOT EXISTS velvet_vogue CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE velvet_vogue;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fullName VARCHAR(150) NOT NULL,
    email VARCHAR(254) NOT NULL UNIQUE,
    passwordHash VARCHAR(255) NOT NULL,
    role ENUM('Customer', 'Admin') NOT NULL DEFAULT 'Customer'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    parentId INT NULL,
    FOREIGN KEY (parentId) REFERENCES categories(id),
    UNIQUE KEY category_name (parentId, name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    categoryId INT NOT NULL,
    gender VARCHAR(30) NOT NULL,
    color VARCHAR(80) NOT NULL,
    price DECIMAL(12,2) NOT NULL CHECK (price >= 0),
    imageUrl VARCHAR(1000) NOT NULL,
    dateAdded DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (categoryId) REFERENCES categories(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stocks (
    productId INT NOT NULL,
    size ENUM('Small','Medium','Large','XL') NOT NULL,
    quantity INT NOT NULL CHECK (quantity >= 0),
    PRIMARY KEY (productId, size),
    FOREIGN KEY (productId) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    userId INT NOT NULL,
    recipientName VARCHAR(150) NOT NULL,
    phoneNumber VARCHAR(30) NOT NULL,
    line1 VARCHAR(255) NOT NULL,
    line2 VARCHAR(255) NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    postalCode VARCHAR(30) NOT NULL,
    country VARCHAR(100) NOT NULL,
    isDefaultShipping BOOLEAN NOT NULL DEFAULT FALSE,
    FOREIGN KEY (userId) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    userId INT NOT NULL,
    productId INT NOT NULL,
    selectedSize ENUM('Small','Medium','Large','XL') NOT NULL,
    quantity INT NOT NULL CHECK (quantity > 0),
    UNIQUE KEY cart_item (userId, productId, selectedSize),
    FOREIGN KEY (userId) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (productId) REFERENCES products(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    userId INT NOT NULL,
    addressId INT NOT NULL,
    shippingAddress JSON NOT NULL,
    totalAmount DECIMAL(12,2) NOT NULL,
    status ENUM('Pending','Processing','Shipped','Delivered','Cancelled') NOT NULL DEFAULT 'Pending',
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (userId) REFERENCES users(id),
    FOREIGN KEY (addressId) REFERENCES addresses(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    orderId INT NOT NULL,
    productId INT NOT NULL,
    productName VARCHAR(200) NOT NULL,
    selectedSize ENUM('Small','Medium','Large','XL') NOT NULL,
    quantity INT NOT NULL CHECK (quantity > 0),
    unitPrice DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (orderId) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (productId) REFERENCES products(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rate_limits (
    id CHAR(64) PRIMARY KEY,
    attempts INT NOT NULL,
    expiresAt DATETIME NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(254) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;