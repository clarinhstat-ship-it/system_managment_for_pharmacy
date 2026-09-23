<?php
// ==============================================================================
// 1. بدء الجلسة والمصادقة والتحقق من التخويل
// ==============================================================================

// تضمين ملف المصادقة الذي يفحص تسجيل الدخول والاتصال بقاعدة البيانات $pdo
require_once 'auth.php'; // حماية الصفحة

// السماح لـ admin و manager بجرى وإدارة الموظفين
if (!checkRole(['admin', 'manager'])) {
    die("❌ ليس لديك صلاحية للوصول لصفحة إدارة الموظفين.");
}

$userRole = $_SESSION['role'] ?? 'user';

// ==============================================================================
// 2. جلب قائمة جميع الموظفين من قاعدة البيانات
// ==============================================================================

try {
    $stmt = $pdo->query("SELECT * FROM employees ORDER BY employeid DESC");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("❌ فشل جلب قائمة الموظفين: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الموظفين - صيدلية الشرية</title>

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
                <i class="fa-solid fa-user-tie me-2"></i> قائمة الموظفين والكادر الطبي
            </h3>
            <div>
                <a href="index.php" class="btn btn-outline-secondary me-2">
                    <i class="fa-solid fa-arrow-right me-1"></i> الرئيسية
                </a>
                <a href="add_employees.php" class="btn btn-success">
                    <i class="fa-solid fa-user-plus me-1"></i> إضافة موظف جديد
                </a>
            </div>
        </div>

        <!-- جدول الموظفين -->
        <div class="table-responsive">
            <table class="table table-hover table-bordered text-center align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>الرقم</th>
                        <th>اسم الموظف 👤</th>
                        <th>رقم الهاتف 📞</th>
                        <th>المسمى الوظيفي 💼</th>
                        <th>الراتب الشهرى 💵</th>
                        <th>الإجراءات ⚙️</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($employees) > 0): ?>
                        <?php foreach ($employees as $emp): ?>
                            <tr>
                                <td><?= $emp['employeid'] ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($emp['name']) ?></td>
                                <td><?= htmlspecialchars($emp['phone'] ?? '-') ?></td>
                                <td><span class="badge bg-info text-dark"><?= htmlspecialchars($emp['job_title'] ?? 'موظف') ?></span></td>
                                <td class="fw-bold text-success"><?= number_format($emp['salary'], 2) ?> ر.س</td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="edit_employees.php?id=<?= $emp['employeid'] ?>" class="btn btn-warning" title="تعديل بيانات الموظف">
                                            <i class="fa-solid fa-pen-to-square"></i> تعديل
                                        </a>
                                        <?php if ($userRole === 'admin'): ?>
                                            <a href="delete_employees.php?id=<?= $emp['employeid'] ?>" class="btn btn-danger" onclick="return confirm('هل أنت تأكد من رغبتك في حذف هذا الموظف؟');" title="حذف الموظف">
                                                <i class="fa-solid fa-trash"></i> حذف
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                لا يوجد موظفون مسجلون حالياً.
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
