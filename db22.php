
<?php
// إعدادات الاتصال بقاعدة البيانات

$host = 'localhost';
$dbname = 'pharmacy_db';
$username = 'root'; 
$password = '';    
// $host = 'localhost';              // المضيف (عادة localhost في XAMPP)
// $dbname = ' pharmacy_db';         // اسم قاعدة البيانات التي أنشأتها
// $username = 'root ';              // اسم المستخدم (افتراضي في XAMPP هو root)
// $password = '';                  // كلمة المرور (غالبًا فارغة في XAMPP)
$port='3308';

try {
    // إنشاء الاتصال باستخدام PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);

    // ضبط وضع الأخطاء على "الاستثناءات" ليظهر الأخطاء عند حدوثها
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ✅ تم الاتصال بنجاح، يمكن الآن استخدام $pdo في أي صفحة
} catch (PDOException $e) {
    // ❌ في حال فشل الاتصال، اطبع رسالة الخطأ وأوقف التنفيذ
    die("فشل الاتصال بقاعدة البيانات: " . $e->getMessage());
}

// try {
//     $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
//     $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
// } catch (PDOException $e) {
//     die("فشل الاتصال: " . $e->getMessage());
// }
?>
<!-- -- 1. إنشاء قاعدة البيانات (إذا لم تكن موجودة)
CREATE DATABASE IF NOT EXISTS pharmacy_db;
USE pharmacy_db;

-- 2. جدول المستخدمين (لتسجيل الدخول)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. جدول الموردين
CREATE TABLE suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. جدول الأدوية
CREATE TABLE medicines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    generic_name VARCHAR(100),
    supplier_id INT,
    purchase_price DECIMAL(10,2) NOT NULL,
    selling_price DECIMAL(10,2) NOT NULL,
    quantity INT DEFAULT 0,
    expiry_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
);

-- 5. جدول الفواتير (نوع: شراء أو بيع)
CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    type ENUM('purchase', 'sale', 'return', 'cash_sale') NOT NULL,
    customer_supplier_name VARCHAR(100),
    customer_id INT,
    total_amount DECIMAL(10,2) NOT NULL,
    date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
);

-- 6. جدول تفاصيل الفاتورة
CREATE TABLE invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT,
    medicine_id INT,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE
);

-- 7. جدول العملاء (مُضاف حديثاً)
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    email VARCHAR(100),
    credit_limit DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 8. جدول حسابات العملاء (للمديونية)
CREATE TABLE customer_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    balance DECIMAL(10,2) DEFAULT 0.00,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- 9. جدول معاملات العملاء (كل العمليات)
CREATE TABLE customer_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    invoice_id INT,
    transaction_type ENUM('sale', 'payment', 'return', 'cash_sale') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL
);

-- 10. تحديث جدول الفواتير لإضافة معرف العميل (إذا لم يتم إضافته مسبقاً)
ALTER TABLE invoices
MODIFY COLUMN type ENUM('purchase', 'sale', 'return', 'cash_sale') NOT NULL,
ADD COLUMN customer_id INT NULL AFTER customer_supplier_name,
ADD FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL;

-- 11. إدخال مستخدم افتراضي (admin / كلمة المرور: 123)
DELETE FROM users;
INSERT INTO users (username, password) VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- 12. إدخال بيانات تجريبية (اختياري)
-- إضافة مورد افتراضي
INSERT INTO suppliers (name, phone, address) VALUES 
('مورد أدوية المتميز', '0555555555', 'الرياض، المملكة العربية السعودية');

-- إضافة أدوية تجريبية
INSERT INTO medicines (name, generic_name, supplier_id, purchase_price, selling_price, quantity, expiry_date) VALUES
('بروفين', 'ايبوبروفين', 1, 10.00, 15.00, 100, '2025-12-31'),
('بانادول', 'باراسيتامول', 1, 8.00, 12.00, 150, '2024-06-30'),
('أموكسيل', 'أموكسيسيلين', 1, 15.00, 25.00, 80, '2024-09-15');

-- إضافة عملاء تجريبيين
INSERT INTO customers (name, phone, address, email, credit_limit) VALUES
('عميل نقد', '0500000000', 'الرياض', 'nagd@example.com', 0.00),
('عميل ائتمان', '0511111111', 'الرياض', 'credit@example.com', 5000.00);

-- إنشاء حسابات للعملاء
INSERT INTO customer_accounts (customer_id) VALUES (1), (2); -->
