<?php
// ==============================================================================
// 1. بدء الجلسة والمصادقة والتحقق من التخويل
// ==============================================================================

// تضمين ملف المصادقة الذي يفحص تسجيل الدخول والاتصال بقاعدة البيانات $pdo
require_once 'auth.php'; // حماية الصفحة لجميع المستخدمين

$userRole = $_SESSION['role'] ?? 'user'; // جلب صلاحية المستخدم الحالي

// ==============================================================================
// 2. جلب جميع العملاء من قاعدة البيانات مرتبين هجائياً
// ==============================================================================

try {
    $stmt = $pdo->query("SELECT * FROM customers ORDER BY name ASC"); // استعلام جلب العملاء
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC); // تحويل البيانات لمصفوفة
} catch (PDOException $e) {
    die("❌ فشل جلب بيانات العملاء: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة العملاء - صيدلية الشرية</title>

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
        
        <!-- الترويسة وأزرار التحكم -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h3 class="fw-bold text-primary mb-0">
                <i class="fa-solid fa-users me-2"></i> قائمة العملاء
            </h3>
            <div>
                <a href="index.php" class="btn btn-outline-secondary me-2">
                    <i class="fa-solid fa-arrow-right me-1"></i> الرئيسية
                </a>
                <!-- زر إضافة عميل متاح للجميع -->
                <a href="add_customer.php" class="btn btn-success">
                    <i class="fa-solid fa-user-plus me-1"></i> إضافة عميل جديد
                </a>
            </div>
        </div>

        <!-- جدول العملاء -->
        <div class="table-responsive">
            <table class="table table-hover table-bordered text-center align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>الرقم</th>
                        <th>اسم العميل</th>
                        <th>رقم الهاتف 📞</th>
                        <th>العنوان 📍</th>
                        <th>الإجراءات ⚙️</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($customers) > 0): ?>
                        <?php foreach ($customers as $cust): ?>
                            <tr>
                                <td><?= $cust['id'] ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($cust['name']) ?></td>
                                <td><?= htmlspecialchars($cust['phone'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($cust['address'] ?? '-') ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <!-- زر تعديل العميل -->
                                        <a href="edit_customer.php?id=<?= $cust['id'] ?>" class="btn btn-warning" title="تعديل البيانات">
                                            <i class="fa-solid fa-pen-to-square"></i> تعديل
                                        </a>
                                        <!-- زر حذف العميل متاح للمدراء -->
                                        <?php if ($userRole === 'admin'): ?>
                                            <a href="delete_customer.php?id=<?= $cust['id'] ?>" class="btn btn-danger" onclick="return confirm('هل أنت تأكد من رغبتك في حذف هذا العميل؟');" title="حذف العميل">
                                                <i class="fa-solid fa-trash"></i> حذف
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                لا يوجد عملاء مسجلون حالياً.
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
