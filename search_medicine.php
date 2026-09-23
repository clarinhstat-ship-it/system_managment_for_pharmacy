<?php
require_once 'db.php'; // الاتصال بقاعدة البيانات

// التحقق من وجود الاسم
if (!isset($_GET['name']) || empty($_GET['name'])) {
    die("❌ لم يتم إدخال اسم للبحث.");
}
$name = $_GET['name']; // أخذ الاسم من المستخدم

// تنفيذ البحث باستخدام LIKE للعثور على أي اسم مشابه
try {
    $stmt = $pdo->prepare("SELECT * FROM medicines WHERE name LIKE :name");
    $searchTerm = "%" . $name . "%"; // للبحث الجزئي داخل الاسم
    $stmt->bindParam(':name', $searchTerm);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("⚠️ خطأ في البحث: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>نتائج البحث عن دواء</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            direction: rtl;
            font-family: 'Cairo', sans-serif;
            background-color: #f7f7f7;
        }

        .container {
            margin-top: 40px;
        }

        .results-box {
            background-color: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
<div class="container">
    <div class="results-box">
        <h4 class="mb-4">نتائج البحث عن: <span class="text-primary"><?= htmlspecialchars($name) ?></span></h4>

        <?php if (count($results) > 0): ?>
        <table class="table table-bordered text-center">
            <thead class="table-dark">
                <tr>
                    <th>رقم الدواء</th>
                    <th>اسم الدواء</th>
                    <th>الوصف</th>
                    <th>السعر</th>
                    <th>الكمية</th>
                    <th>تاريخ الانتهاء</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $med): ?>
                    <tr>
                        <td><?= $med['medicineid'] ?></td>
                        <td><?= htmlspecialchars($med['name']) ?></td>
                        <td><?= htmlspecialchars($med['description']) ?></td>
                        <td><?= $med['price'] ?></td>
                        <td><?= $med['quantity'] ?></td>
                        <td><?= $med['expiredate'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p class="text-danger">لا توجد نتائج مطابقة.</p>
        <?php endif; ?>

        <div class="mt-3">
            <a href="medicines.php" class="btn btn-secondary">الرجوع إلى قائمة الأدوية</a>
        </div>
    </div>
</div>


</body>
</html>