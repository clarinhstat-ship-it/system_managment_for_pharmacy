<?php
// ==============================================================================
// 1. بدء الجلسة والمصادقة والتحقق من التخويل
// ==============================================================================

// تضمين ملف المصادقة الذي يفحص الجلسة والاتصال بقاعدة البيانات $pdo
require_once 'auth.php'; // حماية الصفحة

// السماح لمدير النظام admin أو مدير الصيدلية manager بجرى تفاصيل المخزون
if (!checkRole(['admin', 'manager'])) {
    die("❌ ليس لديك صلاحية للوصول لصفحة إدارة تفاصيل المخزون.");
}

// ==============================================================================
// 2. جلب جميع الأدوية والمخزون مرتبة حسب الأكثر كمية وإبراز النواقص
// ==============================================================================

try {
    $stmt = $pdo->query("SELECT medicines.*, suppliers.name as supplier_name 
                         FROM medicines 
                         LEFT JOIN suppliers ON medicines.supplierid = suppliers.supplierid 
                         ORDER BY medicines.quantity ASC"); // الترتيب تصاعدياً لإظهار النواقص أولاً
    $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC); // تحويل النتائج مصفوفة
} catch (PDOException $e) {
    die("❌ فشل جلب بيانات الجرد والمخزون: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة تفاصيل المخزون والجرد - صيدلية الشرية</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background-color: #f4f6f9;
            direction: rtl;
            padding-bottom: 40px;
        }

        .box-container {
            background-color: #ffffff;
            border-radius: 14px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-top: 25px;
        }

        .table th, .table td {
            vertical-align: middle;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="box-container">
        
        <!-- الترويسة والأزرار -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h3 class="fw-bold text-primary mb-0">
                <i class="fa-solid fa-warehouse me-2"></i> تفاصيل المخزون وجدول الجرد
            </h3>
            <div>
                <a href="index.php" class="btn btn-outline-secondary me-2">
                    <i class="fa-solid fa-arrow-right me-1"></i> الرئيسية
                </a>
                <a href="add.php" class="btn btn-success">
                    <i class="fa-solid fa-plus me-1"></i> إضافة كمية/دواء جديد
                </a>
            </div>
        </div>

        <!-- جدول تفاصيل المخزون -->
        <div class="table-responsive">
            <table class="table table-hover table-bordered text-center align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>رقم الدواء</th>
                        <th>الباركود 🏷️</th>
                        <th>اسم الدواء 💊</th>
                        <th>المورد 🚚</th>
                        <th>سعر الشراء</th>
                        <th>سعر البيع</th>
                        <th>تاريخ الانتهاء ⏳</th>
                        <th>الكمية بالمخزن 📦</th>
                        <th>تحديث ⚙️</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($medicines) > 0): ?>
                        <?php foreach ($medicines as $row): ?>
                            <?php $isLow = ($row['quantity'] <= 5); ?>
                            <tr class="<?= $isLow ? 'table-warning' : '' ?>">
                                <td><?= $row['medicineid'] ?></td>
                                <td><span class="badge bg-secondary font-monospace"><?= htmlspecialchars($row['barcode'] ?? '-') ?></span></td>
                                <td class="fw-bold"><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= htmlspecialchars($row['supplier_name'] ?? '-') ?></td>
                                <td><?= number_format($row['price'], 2) ?> ر.س</td>
                                <td class="fw-bold text-success"><?= number_format($row['price_sales'], 2) ?> ر.س</td>
                                <td><?= htmlspecialchars($row['expiredate'] ?? '-') ?></td>
                                <td>
                                    <?php if ($isLow): ?>
                                        <span class="badge bg-danger"><?= $row['quantity'] ?> (نواقص)</span>
                                    <?php else: ?>
                                        <span class="badge bg-success"><?= $row['quantity'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="edept.php?medicineid=<?= $row['medicineid'] ?>" class="btn btn-warning btn-sm" title="تعديل الكمية أو السعر">
                                        <i class="fa-solid fa-pen-to-square"></i> تعديل
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                لا يوجد أدوية في المخزون.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
