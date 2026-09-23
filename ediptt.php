
<?php
 // 1. بدء الجلسة (Session) للتحقق من تسجيل الدخول
session_start();

// 2. التحقق من تسجيل الدخول - إذا لم يكن المستخدم مسجل دخوله، يتم تحويله لصفحة تسجيل الدخول
if (!isset($_SESSION['userid'])) {
    header("Location: login.php");
    exit;
}
require_once 'db.php';

// التحقق من وجود معرف الدواء في الرابط
if (!isset($_GET['medicineid'])) {
    die("المعرف غير موجود!");
}

$medicineid = $_GET['medicineid'];

// جلب بيانات الدواء من قاعدة البيانات
try {
    $stmt = $pdo->prepare("SELECT * FROM medicines WHERE medicineid = :id");
    $stmt->bindParam(':id', $medicineid, PDO::PARAM_INT);
    $stmt->execute();
    $medicine = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$medicine) {
        die("الدواء غير موجود!");
    }
} catch (PDOException $e) {
    die("خطأ في الجلب: " . $e->getMessage());
}

// تنفيذ التعديل إذا تم إرسال النموذج
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $expiredate = $_POST['expiredate'];
    $quantity = $_POST['quantity'];
    // $adminid = $_POST['adminid'];
    
    try {
        $updateStmt = $pdo->prepare("UPDATE medicines 
            SET name = :name, description = :description, price = :price, 
                expiredate = :expiredate, quantity = :quantity
            WHERE medicineid = :id");

$updateStmt->bindParam(':name', $name);
$updateStmt->bindParam(':description', $description);
$updateStmt->bindParam(':price', $price);
$updateStmt->bindParam(':expiredate', $expiredate);
$updateStmt->bindParam(':quantity', $quantity);
// $updateStmt->bindParam(':adminid', $adminid);
$updateStmt->bindParam(':id', $medicineid);

$updateStmt->execute();

header("Location: medicines.php");
exit;
} catch (PDOException $e) {
    die("خطأ في التعديل: " . $e->getMessage());
}
}
?>

<!DOCTYPE html>
<html lang="ar">
    <head>
        <meta charset="UTF-8">
        <title>تعديل دواء</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body {
                background-color: #f0f0f0;
                font-family: 'Cairo', sans-serif;
                direction: rtl;
            }
            
            .form-box {
                background-color: white;
                padding: 30px;
                margin-top: 50px;
                border-radius: 10px;
                box-shadow: 0 0 15px rgba(0,0,0,0.1);
            }
            
            h2 {
                text-align: center;
                margin-bottom: 20px;
            }
            </style>
</head>
<body>
    
    <div class="container col-md-6">
    <div class="form-box">
        <h2>تعديل بيانات دواء</h2>
        <form method="post">
            <div class="mb-3">
                <label class="form-label">اسم الدواء:</label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($medicine['name']) ?>" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label">الوصف:</label>
                <textarea name="description" class="form-control" required><?= htmlspecialchars($medicine['description']) ?></textarea>
            </div>
            
            <div class="mb-3">
                <label class="form-label">السعر:</label>
                <input type="number" step="0.01" name="price" class="form-control" value="<?= htmlspecialchars($medicine['price']) ?>" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label">تاريخ الانتهاء:</label>
                <input type="date" name="expiredate" class="form-control" value="<?= htmlspecialchars($medicine['expiredate']) ?>" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label">الكمية:</label>
                <input type="number" name="quantity" class="form-control" value="<?= htmlspecialchars($medicine['quantity']) ?>" required>
            </div>
            <!-- 
                <div class="mb-3">
                    <label class="form-label">معرف المسؤول (adminid):</label>
                    <input type="number" name="adminid" class="form-control" value="<?= htmlspecialchars($medicine['adminid']) ?>" required>
                </div> -->
                
                <div class="text-center">
                    <button type="submit" class="btn btn-warning">تحديث البيانات</button>
                    <a href="medicines.php" class="btn btn-secondary">إلغاء</a>
                </div>
            </form>
        </div>
    </div>
    
</body>
</html>

رابط تعديل الدواء 
<a href="edept.php?medicineid=<?= $medicine['medicineid'] ?>" class="btn btn-sm btn-outline-warning">تعديل</a>
