<?php
 // 1. بدء الجلسة (Session) للتحقق من تسجيل الدخول
session_start();

// 2. التحقق من تسجيل الدخول - إذا لم يكن المستخدم مسجل دخوله، يتم تحويله لصفحة تسجيل الدخول
if (!isset($_SESSION['userid'])) {
    header("Location: login.php");
    exit;
}
require_once 'db.php'; // ✅ الاتصال بقاعدة البيانات

// ✅ التحقق من إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ✅ أخذ بيانات العميل من النموذج
    $firstname    = $_POST['firstname'];
    $lastname    = $_POST['lastname'];
    $phone   = $_POST['phone'];
    $email   = $_POST['email'];
    $address = $_POST['address'];

    // ✅ تنفيذ أمر الإدخال باستخدام PDO
    try {
        $stmt = $pdo->prepare("INSERT INTO customers (firstname,lastname, phone, email, address) 
                               VALUES (:firstname,:lastname, :phone, :email, :address)");
        $stmt->execute([
            ':firstname'    => $firstname,
            ':lastname'    => $lastname,
            ':phone'   => $phone,
            ':email'   => $email,
            ':address' => $address
        ]);

        // ✅ الرجوع إلى صفحة العملاء بعد الإضافة
        header("Location: customers.php");
        exit;

    } catch (PDOException $e) {
        die("❌ خطأ في إضافة العميل: " . $e->getMessage());
    }
}
?>

<!-- ✅ واجهة النموذج HTML -->
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>إضافة عميل</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { direction: rtl; background: #f7f7f7; font-family: 'Cairo', sans-serif; }
        .form-box {
            background: white;
            max-width: 600px;
            margin: 60px auto;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

<div class="form-box">
    <h4 class="mb-4 text-center">إضافة عميل جديد</h4>
    <form method="post">
        <div class="mb-3">
            <label>الاسم الاول :</label>
            <input type="text" name="firstname" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>الاسم الاخر:</label>
            <input type="text" name="lastname" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>رقم الهاتف:</label>
            <input type="text" name="phone" class="form-control">
        </div>

        <div class="mb-3">
            <label>البريد الإلكتروني:</label>
            <input type="email" name="email" class="form-control">
        </div>

        <div class="mb-3">
            <label>العنوان:</label>
            <textarea name="address" class="form-control"></textarea>
        </div>

        <div class="text-center">
            <button type="submit" class="btn btn-success">💾 إضافة</button>
            <a href="customers.php" class="btn btn-secondary">رجوع</a>
        </div>
        
    <div class="text-center mt-4">
        <a href="index.php" class="btn btn-secondary">رجوع إلى الرئيسية</a>
    </div>
    </form>
</div>

</body>
</html>