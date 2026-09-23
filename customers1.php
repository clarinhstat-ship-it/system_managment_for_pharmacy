
<?php
require_once 'db.php'; // ✅ ربط قاعدة البيانات باستخدام PDO

// ✅ جلب كل العملاء من الجدول
try {
    $stmt = $pdo->query("SELECT * FROM customers"); // ✅ تنفيذ استعلام جلب كل السجلات
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC); // ✅ تخزين النتائج في مصفوفة
} catch (PDOException $e) {
    die("⚠️ خطأ في جلب العملاء: " . $e->getMessage()); // ❌ عرض رسالة في حالة فشل الجلب
}
?>

<!-- ✅ HTML وواجهة العرض -->
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>قائمة العملاء</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { direction: rtl; font-family: 'Cairo', sans-serif; background-color: #f0f0f0; }
        .container { margin-top: 40px; }
        .box { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .header { display: flex; justify-content: space-between; align-items: center; }
    </style>
</head>
<body>
<div class="container">
    <div class="box">
        <!-- ✅ العنوان وزر إضافة عميل -->
        <div class="header mb-4">
            <h4>قائمة العملاء</h4>
            <a href="add_customer.php" class="btn btn-success">➕ إضافة عميل جديد</a>
        </div>
    <div class="text-center mt-4">
        <a href="index.php"class="btn btn-secondary">رجوع إلى الرئيسية</a>
    </div>
        <!-- ✅ التحقق من وجود عملاء -->
        <?php if (count($customers) > 0): ?>
            <table class="table table-bordered text-center">
                <thead class="table-dark">
                    <tr>
                        <th>الرقم</th>
                        <th>الاسم الاول</th>
                        <th>الاسم الاخر</th>
                        <th>الهاتف</th>
                        <th>البريد</th>
                        <th>العنوان</th>
                        <th>الإجراءات</th> <!-- ✅ التعديل والحذف -->
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $cus): ?> <!-- ✅ تكرار على كل عميل -->
                        <tr>
                            <td><?= $cus['customerid'] ?></td> <!-- ✅ رقم العميل -->
                            <td><?= htmlspecialchars($cus['firstname']) ?></td> <!-- ✅ اسم العميل -->
                            <td><?= htmlspecialchars($cus['lastname']) ?></td> <!-- ✅ اسم العميل -->
                            <td><?= htmlspecialchars($cus['phone']) ?></td> <!-- ✅ الهاتف -->
                            <td><?= htmlspecialchars($cus['email']) ?></td> <!-- ✅ البريد -->
                            <td><?= htmlspecialchars($cus['address']) ?></td> <!-- ✅ العنوان -->
                            <td>
                                <!-- ✅ زر التعديل -->
                                <a href="edit_customer.php?customerid=<?= $cus['customerid'] ?>" class="btn btn-warning btn-sm">تعديل</a>

                                <!-- ✅ زر الحذف -->
                                <a href="delete_customer.php?customerid=<?= $cus['customerid'] ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('هل أنت متأكد من حذف هذا العميل؟')">
                                    حذف
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?> <!-- ✅ نهاية التكرار -->
                </tbody>
            </table>
        <?php else: ?>
            <!-- ✅ إذا لم يوجد عملاء -->
            <p class="text-danger">لا يوجد عملاء حالياً.</p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>