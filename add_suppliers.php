<?php

 // 1. بدء الجلسة (Session) للتحقق من تسجيل الدخول
session_start();

// 2. التحقق من تسجيل الدخول - إذا لم يكن المستخدم مسجل دخوله، يتم تحويله لصفحة تسجيل الدخول
if (!isset($_SESSION['userid'])) {
    header("Location: login.php");
    exit;
}
require_once 'db.php'; // الاتصال بقاعدة البيانات

// إذا تم إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $address = $_POST['address'];
    $qauntity = $_POST['qauntity'];//الكميه
    try {
        // إدخال البيانات في جدول الموردين
        $stmt = $pdo->prepare("INSERT INTO suppliers (name, phone, email, address,qauntity) 
                               VALUES (:name, :phone, :email, :address,:qauntity)");
        $stmt->execute([
            ':name' => $name,
            ':phone' => $phone,
            ':email' => $email,
            ':address' => $address,
            ':qauntity' => $qauntity
        ]);

        // الرجوع لصفحة عرض الموردين
        header("Location: suppliers.php");
        exit;

    } catch (PDOException $e) {
        echo "خطأ أثناء إضافة المورد: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>إضافة مورد جديد</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { direction: rtl; font-family: 'Cairo', sans-serif; background-color: #f0f0f0; }
        .form-box {
            background-color: white;
            max-width: 600px;
            margin: 60px auto;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
    </style>
           <link rel="stylesheet"  href="style.css" type="text/css " media="screen" >
</head>
<body>

<div class="form-box">
    <h3 class="mb-4 text-center">إضافة مورد جديد</h3>
    <form method="post">
        <div class="mb-3">
            <label>اسم المورد:</label>
            <input type="text" name="name" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>رقم الهاتف:</label>
            <input type="text" name="phone" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>البريد الإلكتروني:</label>
            <input type="email" name="email" class="form-control">
        </div>       
        <div class="mb-3">
            <label>العنوان:</label>
            <textarea name="address" class="form-control"></textarea>
        </div>
        
                <div class="mb-3">
                    <label> الكميه:</label>
                    <input type="number" name="qauntity" class="form-control">
                </div>

        <div class="text-center">
            <button type="submit" class="btn btn-success">إضافة</button>
            <a href="suppliers.php" class="btn btn-secondary">إلغاء</a>
        </div>
    </form>
</div>

</body>
</html>
<!-- ✅ حقل اختيار المورد -->
<div class="mb-3">
    <label>المورد:</label> <!-- ✅ عنوان الحقل -->
    <select name="supplierid" class="form-control" required> <!-- ✅ قائمة منسدلة ترسل المعرف -->
        <option value="">اختر المورد</option> <!-- ✅ خيار افتراضي -->
        <?php foreach ($suppliers as $sup): ?> <!-- ✅ تكرار على الموردين -->
            <option value="<?= $sup['supplierid'] ?>">
                <?= htmlspecialchars($sup['name']) ?> <!-- ✅ عرض اسم المورد -->
            </option>
        <?php endforeach; ?>
    </select>
</div>

create table

suppliers ( supplierid int PRIMARY key AUTO_INCREMENT ,
    name varchar(202),
           phone int ,
           email varchar(200), 
           address varchar(100),
           qauntity int,
          adminid int); 


 