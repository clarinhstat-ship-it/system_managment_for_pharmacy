<?php
// ==============================================================================
// 1. إعدادات وقيم الاتصال بقاعدة البيانات
// ==============================================================================

// تحديد عنوان خادم قاعدة البيانات (المضيف المحلي في بيئة XAMPP)
$host = 'localhost'; // المضيف (localhost) للاتصال بالخادم المحلي

// تحديد اسم قاعدة البيانات الخاصة بنظام الصيدلية
$dbname = 'pharmacy_db'; // اسم قاعدة البيانات المستخدمة للمشروع

// تحديد اسم مستخدم قاعدة البيانات الافتراضي في XAMPP
$username = 'root'; // اسم المستخدم الافتراضي لقواعد بيانات MySQL في XAMPP

// تحديد كلمة مرور قاعدة البيانات (فارغة افتراضياً في XAMPP)
$password = ''; // كلمة المرور الخاصة بمستخدم root

try {
    // ==============================================================================
    // 2. إنشاء كائن PDO وتكوين خيارات الاتصال الآمن
    // ==============================================================================

    // إنشاء اتصال جديد بـ PDO مع تحديد المضيف، اسم قاعدة البيانات، المنفذ، وترميز utf8mb4 ليدعم اللغة العربية كلياً
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;port=3306;charset=utf8mb4", $username, $password);

    // ضبط وضع إظهار الأخطاء في PDO ليرمي استثناءات (Exceptions) عند وجود أي خطأ في استعلامات SQL للتعامل معه بأمان
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ضبط النمط الافتراضي لجلب البيانات ليكون مصفوفات ترابطية (FETCH_ASSOC) لسهولة التعامل مع الأسماء
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // ==============================================================================
    // 3. التحديث والتهيئة التلقائية لجداول وقواعد البيانات (Auto Schema Migration)
    // ==============================================================================

    // إنشاء جدول المستخدمين (users) إذا لم يكن موجوداً لضمان عدم حدوث خطأ عند تشغيل النظام أول مرة
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        userid INT AUTO_INCREMENT PRIMARY KEY, -- الرقم التعريفي الفريد للمستخدم (مفتاح رئيسي تلقائي الزيادة)
        name VARCHAR(100) NOT NULL UNIQUE,      -- اسم المستخدم الفريد لتسجيل الدخول
        password VARCHAR(255) NOT NULL,        -- كلمة المرور (مهيأة للتشفير الآمن)
        role VARCHAR(50) NOT NULL DEFAULT 'user' -- دور وصلاحية المستخدم (admin, manager, user)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"); // محرك البيانات InnoDB لضمان العلاقات والسرعة

    // إنشاء جدول الأدوية (medicines) لضمان احتوائه على جميع الحقول المطلوبة
    $pdo->exec("CREATE TABLE IF NOT EXISTS medicines (
        medicineid INT AUTO_INCREMENT PRIMARY KEY, -- الرقم التعريفي للدواء
        barcode VARCHAR(100) DEFAULT NULL UNIQUE,  -- كود الباركود الفريد للدواء
        name VARCHAR(150) NOT NULL,               -- اسم الدواء
        description TEXT DEFAULT NULL,            -- وصف وملاحظات الدواء
        supplierid INT DEFAULT NULL,              -- الرقم التعريفي للمورد
        price DECIMAL(10,2) NOT NULL DEFAULT 0,   -- سعر الشراء من المورد
        price_sales DECIMAL(10,2) NOT NULL DEFAULT 0, -- سعر البيع للعميل
        dateproduction DATE DEFAULT NULL,         -- تاريخ الإنتاج
        expiredate DATE DEFAULT NULL,             -- تاريخ انتهاء الصلاحية
        quantity INT NOT NULL DEFAULT 0           -- الكمية المتوفرة بالمخزون
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // إضافة حقل الباركود لجدول الأدوية إذا تم التحديث من إصدار قديم
    try {
        // تنفيذ أمر إضافة عمود barcode إذا لم يكن موجوداً
        $pdo->exec("ALTER TABLE medicines ADD COLUMN barcode VARCHAR(100) DEFAULT NULL UNIQUE AFTER medicineid");
    } catch (PDOException $ex) {
        // تجاهل الخطأ في حالة كان العمود موجوداً بالفعل
    }

    // إنشاء جدول العملاء (customers)
    $pdo->exec("CREATE TABLE IF NOT EXISTS customers (
        id INT AUTO_INCREMENT PRIMARY KEY, -- الرقم التعريفي للعميل
        name VARCHAR(100) NOT NULL,        -- اسم العميل
        phone VARCHAR(30) DEFAULT NULL,    -- رقم هاتف العميل
        address TEXT DEFAULT NULL          -- عنوان العميل
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // إنشاء جدول الموردين (suppliers)
    $pdo->exec("CREATE TABLE IF NOT EXISTS suppliers (
        supplierid INT AUTO_INCREMENT PRIMARY KEY, -- الرقم التعريفي للمورد
        name VARCHAR(100) NOT NULL,               -- اسم شركة/شخص المورد
        phone VARCHAR(30) DEFAULT NULL,           -- رقم الهاتف
        email VARCHAR(100) DEFAULT NULL,          -- البريد الإلكتروني
        address TEXT DEFAULT NULL                 -- العنوان
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // إنشاء جدول الموظفين (employees)
    $pdo->exec("CREATE TABLE IF NOT EXISTS employees (
        employee_id INT AUTO_INCREMENT PRIMARY KEY, -- الرقم التعريفي للموظف
        name VARCHAR(100) NOT NULL,                 -- اسم الموظف
        phone VARCHAR(30) DEFAULT NULL,             -- الهاتف
        job_title VARCHAR(100) DEFAULT NULL,        -- المسمى الوظيفي
        salary DECIMAL(10,2) DEFAULT 0              -- الراتب الشهرى
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // إنشاء جدول الفواتير الرئيسي (invoices3) لتوحيد استخدام الفواتير في كل النظام
    $pdo->exec("CREATE TABLE IF NOT EXISTS invoices3 (
        id INT AUTO_INCREMENT PRIMARY KEY,        -- رقم الفاتورة الفريد
        customer_id INT DEFAULT NULL,              -- كود العميل (في حالة فواتير البيع)
        customer_supplier_name VARCHAR(150) DEFAULT NULL, -- اسم العميل أو المورد
        type VARCHAR(20) NOT NULL DEFAULT 'sale', -- نوع الفاتورة (sale: بيع، purchase: شراء)
        total_amount DECIMAL(10,2) NOT NULL DEFAULT 0, -- إجمالي المبلغ قبل الخصم والضريبة
        discount DECIMAL(10,2) NOT NULL DEFAULT 0,     -- قيمة الخصم المقدم
        tax DECIMAL(10,2) NOT NULL DEFAULT 0,          -- قيمة الضريبة المضافة
        net_total DECIMAL(10,2) NOT NULL DEFAULT 0,    -- الصافي النهائي للفاتورة
        user_id INT DEFAULT NULL,                      -- كود الموظف/الكاشير الذي أنشأ الفاتورة
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP -- تاريخ ووقت إنشاء الفاتورة
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // إنشاء جدول تفاصيل عناصر الفاتورة (invoice_items)
    $pdo->exec("CREATE TABLE IF NOT EXISTS invoice_items (
        id INT AUTO_INCREMENT PRIMARY KEY, -- الرقم التعريفي لسطر الفاتورة
        invoice_id INT NOT NULL,           -- رقم الفاتورة التابع لها
        medicine_id INT NOT NULL,          -- كود الدواء المباع
        quantity INT NOT NULL,             -- الكمية المباعة
        price DECIMAL(10,2) NOT NULL,      -- سعر الوحدة وقت البيع
        total DECIMAL(10,2) NOT NULL       -- إجمالي المبلغ للسطر (الكمية * السعر)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // إنشاء جدول الطلبات الشراء (orders)
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        orderid INT AUTO_INCREMENT PRIMARY KEY, -- رقم الطلب
        supplierid INT NOT NULL,               -- كود المورد
        order_date DATE DEFAULT NULL,          -- تاريخ الطلب
        status VARCHAR(50) DEFAULT 'pending'   -- حالة الطلب (قيد الانتظار، مكتمل، ملغي)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // إنشاء جدول سجل النشاطات والأمان (audit_logs) لتتبع كافة التغييرات والعمليات المنجزة
    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,        -- رقم السجل
        user_name VARCHAR(100) NOT NULL,          -- اسم المستخدم الذي قام بالإجراء
        action VARCHAR(255) NOT NULL,             -- وصف الإجراء أو التغيير
        details TEXT DEFAULT NULL,                -- التفاصيل الإضافية للعملية
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP -- توقيت حدوث العملية
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // ==============================================================================
    // 4. التحقق من وجود مستخدم مسجل مسبقاً وإنشاء مستخدم مدير افتراضي آمن عند الحاجة
    // ==============================================================================
    $checkUserStmt = $pdo->query("SELECT COUNT(*) FROM users"); // استعلام لعد المستخدمين الحاليين
    if ($checkUserStmt->fetchColumn() == 0) { // في حالة عدم وجود أي مستخدم بالنظام
        // التشفير الآمن لكلمة المرور الافتراضية 'admin123'
        $defaultPasswordHash = password_hash('admin123', PASSWORD_DEFAULT); // استخدام خوارزمية BCRYPTION القياسية
        // إدراج حساب المدير الافتراضي بنجاح
        $insertAdminStmt = $pdo->prepare("INSERT INTO users (name, password, role) VALUES ('admin', ?, 'admin')"); // تجهيز الاستعلام
        $insertAdminStmt->execute([$defaultPasswordHash]); // تنفيذ الاستعلام الآمن
    }

} catch (PDOException $e) {
    // إيقاف التنفيذ عند فشل الاتصال بقاعدة البيانات وطباعة رسالة الخطأ بشكل دقيق
    die("❌ فشل الاتصال بقاعدة البيانات: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
?>
