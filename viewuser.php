<?php
// ==============================================================================
// 1. بدء الجلسة والمصادقة والتحقق من صلاحية مدير النظام
// ==============================================================================

// تضمين ملف المصادقة الذي يفحص تسجيل الدخول والاتصال بقاعدة البيانات $pdo
require_once 'auth.php'; // حماية الصفحة

// السماح لـ admin فقط بمدخلات ومستخدمي النظام لسلامة الوصول
if (!checkRole('admin')) {
    die("❌ ليس لديك صلاحية للوصول لصفحة إدارة المستخدمين.");
}

// ==============================================================================
// 2. جلب جميع المستخدمين المسجلين في النظام
// ==============================================================================

try {
    $stmt = $pdo->query("SELECT userid, name, role FROM users ORDER BY userid ASC"); // جلب بيانات المستخدمين بدون التشفير
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC); // تحويل النتائج لمصفوفة
} catch (PDOException $e) {
    die("❌ فشل جلب قائمة المستخدمين: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المستخدمين - صيدلية الشرية</title>

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
                <i class="fa-solid fa-user-gear me-2"></i> قائمة مستخدمي النظام والصلاحيات
            </h3>
            <div>
                <a href="index.php" class="btn btn-outline-secondary me-2">
                    <i class="fa-solid fa-arrow-right me-1"></i> الرئيسية
                </a>
                <a href="add_user.php" class="btn btn-success">
                    <i class="fa-solid fa-user-plus me-1"></i> إضافة مستخدم جديد
                </a>
            </div>
        </div>

        <!-- جدول المستخدمين -->
        <div class="table-responsive">
            <table class="table table-hover table-bordered text-center align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>الرقم</th>
                        <th>اسم المستخدم 👤</th>
                        <th>الصلاحية / الدور 🛡️</th>
                        <th>الإجراءات ⚙️</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($users) > 0): ?>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?= $u['userid'] ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($u['name']) ?></td>
                                <td>
                                    <?php if ($u['role'] === 'admin'): ?>
                                        <span class="badge bg-danger">مدير النظام (admin)</span>
                                    <?php elseif ($u['role'] === 'manager'): ?>
                                        <span class="badge bg-warning text-dark">مدير صيدلية (manager)</span>
                                    <?php else: ?>
                                        <span class="badge bg-info text-dark">مستخدم عادي (user)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="edit_user.php?id=<?= $u['userid'] ?>" class="btn btn-warning" title="تعديل الحساب">
                                            <i class="fa-solid fa-pen-to-square"></i> تعديل
                                        </a>
                                        <?php if ($u['userid'] != $_SESSION['userid']): ?>
                                            <a href="delete_user.php?id=<?= $u['userid'] ?>" class="btn btn-danger" onclick="return confirm('هل أنت تأكد من رغبتك في حذف هذا الحساب؟');" title="حذف الحساب">
                                                <i class="fa-solid fa-user-xmark"></i> حذف
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                لا يوجد مستخدمون مسجلون حالياً.
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
