
<?php
 // 1. بدء الجلسة (Session) للتحقق من تسجيل الدخول
session_start();

// 2. التحقق من تسجيل الدخول - إذا لم يكن المستخدم مسجل دخوله، يتم تحويله لصفحة تسجيل الدخول
if (!isset($_SESSION['userid'])) {
    header("Location: login.php");
    exit;
}
require_once 'db.php'; // الاتصال بقاعدة البيانات

//  التحقق من وجود معرف المورد
if (!isset($_GET['supplierid']) || empty($_GET['supplierid'])) {
    die("❌ لم يتم تحديد المورد المطلوب تعديله.");
}

$supplierid = $_GET['supplierid'];

//  جلب بيانات المورد من قاعدة البيانات
try {
    $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE supplierid = :id");
    $stmt->execute([':id' => $supplierid]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$supplier) {
        die("⚠️ المورد غير موجود.");
    }
} catch (PDOException $e) {
    die("❌ خطأ في الاتصال: " . $e->getMessage());
}

//  تحديث البيانات عند إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = $_POST['name'];
    $phone   = $_POST['phone'];
    $email   = $_POST['email'];
    $address = $_POST['address'];
    $qauntity = $_POST['qauntity'];

    try {
        $updateStmt = $pdo->prepare("UPDATE suppliers 
                                     SET name = :name, phone = :phone, email = :email, address = :address, qauntity=:qauntity 
                                     WHERE supplierid = :id");
        $updateStmt->execute([
            ':name'    => $name,
            ':phone'   => $phone,
            ':email'   => $email,
            ':address' => $address,
            ':qauntity' => $qauntity,
            ':id'      => $supplierid
        ]);

        //  الرجوع لصفحة الموردين بعد التعديل
        header("Location: suppliers.php");
        exit;

    } catch (PDOException $e) {
        die("❌ خطأ أثناء التعديل: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>تعديل مورد</title>
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
</head>
<body>

<div class="form-box">
    <h3 class="mb-4 text-center">تعديل بيانات المورد</h3>
    <form method="post">
        <div class="mb-3">
            <label>اسم المورد:</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($supplier['name']) ?>" required>
        </div>

        <div class="mb-3">
            <label>رقم الهاتف:</label>
            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($supplier['phone']) ?>" required>
        </div>

        <div class="mb-3">
            <label>البريد الإلكتروني:</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($supplier['email']) ?>">
        </div>

        <div class="mb-3">
            <label>العنوان:</label>
            <textarea name="address" class="form-control"><?= htmlspecialchars($supplier['address']) ?></textarea>
        </div>
        
                <div class="mb-3">
                    <label> الكميه:</label>
                    <input type="number" name="qauntity" class="form-control"><?= htmlspecialchars($supplier['qauntity']) ?>
                </div>


        <div class="text-center">
            <button type="submit" class="btn btn-primary">💾 حفظ التعديلات</button>
            <a href="suppliers.php" class="btn btn-secondary">رجوع</a>
        </div>
    </form>
</div>

</body>
</html>


