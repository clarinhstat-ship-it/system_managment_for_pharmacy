
<?php
 // 1. بدء الجلسة (Session) للتحقق من تسجيل الدخول
session_start();

// 2. التحقق من تسجيل الدخول - إذا لم يكن المستخدم مسجل دخوله، يتم تحويله لصفحة تسجيل الدخول
if (!isset($_SESSION['userid'])) {
    header("Location: login.php");
    exit;
}
require_once 'db.php'; // ✅ الاتصال بقاعدة البيانات

// ✅ التحقق من وجود معرف العميل في الرابط (GET)
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("❌ لم يتم تحديد العميل المطلوب تعديله."); // ❌ إيقاف إذا لم يتم إرسال معرف
}

$customerid = $_GET['id']; // ✅ تخزين المعرف في متغير

// ✅ جلب بيانات العميل من قاعدة البيانات حسب المعرف
try {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = :id"); // ✅ تجهيز الاستعلام
    $stmt->execute([':id' => $customerid]); // ✅ تنفيذ الاستعلام مع تمرير المعرف
    $customer = $stmt->fetch(PDO::FETCH_ASSOC); // ✅ استخراج البيانات كمصفوفة

    if (!$customer) { // ✅ التحقق إذا كان العميل موجود أم لا
        die("⚠️ العميل غير موجود."); // ❌ رسالة إذا لم يجد السجل
    }
} catch (PDOException $e) {
    die("❌ خطأ في جلب بيانات العميل: " . $e->getMessage()); // ❌ عرض رسالة في حالة الخطأ
}

// ✅ تنفيذ التحديث إذا تم إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = $_POST['name'];    // ✅ اسم العميل بعد التعديل
    $phone   = $_POST['phone'];   // ✅ رقم الهاتف بعد التعديل
    $email   = $_POST['email'];   // ✅ البريد بعد التعديل
    $address = $_POST['address']; // ✅ العنوان بعد التعديل

    try {
        // ✅ تجهيز أمر التحديث
        $updateStmt = $pdo->prepare("UPDATE customers 
                                     SET name = :name, phone = :phone, email = :email, address = :address 
                                     WHERE id = :id");
        // ✅ تنفيذ التحديث مع البيانات الجديدة
        $updateStmt->execute([
            ':name'    => $name,
            ':phone'   => $phone,
            ':email'   => $email,
            ':address' => $address,
            ':id'      => $customerid
        ]);

        // ✅ الرجوع إلى صفحة العملاء بعد التعديل
        header("Location: customers.php");
        exit;

    } catch (PDOException $e) {
        die("❌ خطأ أثناء التعديل: " . $e->getMessage()); // ❌ عرض الخطأ إذا فشل التعديل
    }
}
?>

<!-- ✅ واجهة HTML لتعديل بيانات العميل -->
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>تعديل عميل</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { direction: rtl; background: #f0f0f0; font-family: 'Cairo', sans-serif; }
        .form-box {
            background: white;
            max-width: 600px;
            margin: 60px auto;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

    <div class="text-center mt-4">
        <a href="index.php" class="btn btn-secondary">رجوع إلى الرئيسية</a>
    </div>
<div class="form-box">
    <h4 class="mb-4 text-center">تعديل بيانات العميل</h4>
    <form method="post">
        <div class="mb-3">
            <label>اسم العميل:</label>
            <input type="text" name="name" class="form-control" 
                   value="<?= htmlspecialchars($customer['name']) ?>" required>
        </div>
        
        <div class="mb-3">
            <label>رقم الهاتف:</label>
            <input type="text" name="phone" class="form-control" 
                   value="<?= htmlspecialchars($customer['phone']) ?>">
        </div>

        <div class="mb-3">
            <label>البريد الإلكتروني:</label>
            <input type="email" name="email" class="form-control" 
                   value="<?= htmlspecialchars($customer['email']) ?>">
        </div>

        <div class="mb-3">
            <label>العنوان:</label>
            <textarea name="address" class="form-control"><?= htmlspecialchars($customer['address']) ?></textarea>
        </div>

        <div class="text-center">
            <button type="submit" class="btn btn-primary">💾 حفظ التعديلات</button>
            <a href="customers.php" class="btn btn-secondary">رجوع</a>
        </div>
    </form>
</div>

</body>
</html>
