<?php
// ==============================================================================
// 1. بدء الجلسة والمصادقة والتحقق من صلاحيات المدير
// ==============================================================================

// تضمين ملف المصادقة الذي يفحص الجلسة والاتصال بقاعدة البيانات $pdo
require_once 'auth.php'; // حماية الصفحة

// التحقق من صلاحية admin فقط لإدارة الموردين
if (!checkRole('admin')) {
    die("❌ ليس لديك صلاحية للوصول لصفحة الموردين.");
}

// ==============================================================================
// 2. جلب جميع الموردين من قاعدة البيانات
// ==============================================================================

try {
    $stmt = $pdo->query("SELECT * FROM suppliers ORDER BY supplierid DESC"); // جلب جميع الموردين
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC); // مصفوفة ترابطية
} catch (PDOException $e) {
    die("❌ فشل جلب بيانات الموردين: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الموردين - صيدلية الشرية</title>

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
        
        <!-- الترويسة الأزرار -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h3 class="fw-bold text-primary mb-0">
                <i class="fa-solid fa-truck-field me-2"></i> قائمة الموردين
            </h3>
            <div>
                <a href="index.php" class="btn btn-outline-secondary me-2">
                    <i class="fa-solid fa-arrow-right me-1"></i> الرئيسية
                </a>
                <a href="add_suppliers.php" class="btn btn-success">
                    <i class="fa-solid fa-plus me-1"></i> إضافة مورد جديد
                </a>
            </div>
        </div>

        <!-- جدول الموردين -->
        <div class="table-responsive">
            <table class="table table-hover table-bordered text-center align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>الرقم</th>
                        <th>اسم المورد / الشركة</th>
                        <th>رقم الهاتف 📞</th>
                        <th>البريد الإلكتروني ✉️</th>
                        <th>العنوان 📍</th>
                        <th>الإجراءات ⚙️</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($suppliers) > 0): ?>
                        <?php foreach ($suppliers as $sup): ?>
                            <tr>
                                <td><?= $sup['supplierid'] ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($sup['name']) ?></td>
                                <td><?= htmlspecialchars($sup['phone'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($sup['email'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($sup['address'] ?? '-') ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="edit_supplier.php?id=<?= $sup['supplierid'] ?>" class="btn btn-warning" title="تعديل المورد">
                                            <i class="fa-solid fa-pen-to-square"></i> تعديل
                                        </a>
                                        <a href="delete_supplier.php?id=<?= $sup['supplierid'] ?>" class="btn btn-danger" onclick="return confirm('هل أنت تأكد من رغبتك في حذف هذا المورد؟');" title="حذف المورد">
                                            <i class="fa-solid fa-trash"></i> حذف
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                لا يوجد موردون مسجلون حالياً.
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
