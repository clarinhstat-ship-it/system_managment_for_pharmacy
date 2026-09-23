
<?php
 // 1. بدء الجلسة (Session) للتحقق من تسجيل الدخول
session_start();

// 2. التحقق من تسجيل الدخول - إذا لم يكن المستخدم مسجل دخوله، يتم تحويله لصفحة تسجيل الدخول
if (!isset($_SESSION['userid'])) {
    header("Location: login.php");
    exit;
}
// استدعاء الاتصال بقاعدة البيانات
require_once 'db.php';
// التحقق إذا تم إرسال النموذج (زر الحفظ)
//  جلب قائمة الأدوية
$suppliers = $pdo->query("SELECT supplierid, name ,phone FROM suppliers");
$suppliers = $suppliers->fetchAll(PDO::FETCH_ASSOC);
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // جلب البيانات من النموذج
    $name = $_POST['name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $dateproduction = $_POST['dateproduction'];
    $expiredate = $_POST['expiredate'];
    $quantity = $_POST['quantity'];
    $price_sales = $_POST['price_sales'];
    // $adminid = $_POST['adminid'];

    try {
        // إعداد استعلام الإدخال (INSERT)
        $stmt = $pdo->prepare("INSERT INTO medicines (name, description, price, expiredate, quantity,price_sales,dateproduction) 
                               VALUES (:name, :description, :price, :expiredate, :quantity,:price_sales,:dateproduction)");

        // ربط البيانات باستخدام bindParam لحمايتها من الحقن

        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':price', $price);
        $stmt->bindParam(':dateproduction', $dateproduction);
        $stmt->bindParam(':expiredate', $expiredate);
        $stmt->bindParam(':quantity', $quantity);
        $stmt->bindParam(':price_sales', $price_sales);
        // $stmt->bindParam(':adminid', $adminid);

        // تنفيذ الاستعلام
        $stmt->execute();

        // بعد النجاح، الانتقال إلى صفحة الأدوية
        header("Location: add.php");
        exit;
    } catch (PDOException $e) {
         die("خطأ في الإدخال: " . $e->getMessage());
         header("Location: add.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>إضافة دواء جديد</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
     
        body {
            background-color: #eef2f3;
            font-family: 'Cairo', sans-serif;
            direction: rtl;
        }

        .form-box {
            background-color: white;
            border-radius: 15px;
            padding: 30px;
            margin-top: 60px;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
        }

        h2 {
            margin-bottom: 25px;
            text-align: center;
        } 

       /* @import url(style.css);   استدعاا ملف تنسيق خارجي */
       </style>
       <!-- <link rel="stylesheet"  href="style.css" type="text/css " media="screen" > -->
</head>
<body>
<div class="container col-md-6">
    <div class="form-box">
        <h2>إضافة دواء جديد</h2>
        <form method="post">
        <td>
                        <select name="supplierid[]" class="form-control medSelect" required onchange="updatePrice(this)">
                            <option value="">اختر مورد</option>
                            <?php foreach ($suppliers as $med): ?>
                                <option value="<?= $med['supplierid'] ?>" data-price="<?= $med['phone'] ?>">
                                    <?= htmlspecialchars($med['name']) ?>

                                 </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
            <div class="mb-3">
                <label for="name" class="form-label">اسم الدواء:</label>
                <input type="text" name="name" id="name" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">الوصف:</label>
                <textarea name="description" id="description" class="form-control" ></textarea>
            </div>

            <div class="mb-3">
                <label for="price" class="form-label">السعر:</label>
                <input type="number" name="price" id="price" class="form-control" step="0.01" required>
                
            </div>
            <div class="mb-3">
                <label for="adminid" class="form-label">سعر البيع:</label>
                <input type="number" name="price_sales" id="adminid" class="form-control" required >
            </div>
            <div class="mb-3">
                <label for="adminid" class="form-label">تاريخ الانتاج:</label>
                <input type="date" name="dateproduction" id="adminid" class="form-control"  >
            </div>

            <div class="mb-3">
                <label for="expiredate" class="form-label">تاريخ الانتهاء:</label>
                <input type="date" name="expiredate" id="expiredate" class="form-control" required>
                <!-- datetime-local   
                tel  useing this is type input for number phone 
                -->
            </div>

            <div class="mb-3">
                <label for="quantity" class="form-label">الكمية:</label>
                <input type="number" name="quantity" id="quantity" class="form-control" required>
            </div>

            <div class="text-center">
                <button type="submit" class="btn btn-success">حفظ الدواء</button>
                <a href="medicines.php" class="btn btn-secondary">رجوع</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>