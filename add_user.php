<?php
// ==============================================================================
// 1. بدء الجلسة والمصادقة والتحقق من صلاحية مدير النظام
// ==============================================================================

// تضمين ملف المصادقة الذي يفحص تسجيل الدخول والاتصال بقاعدة البيانات $pdo
require_once 'auth.php'; // حماية الصفحة

// السماح فقط لمدير النظام (admin) بإنشاء حسابات مستخدمين جديدة
if (!checkRole('admin')) {
    die("❌ ليس لديك صلاحية لإضافة مستخدمين.");
}

$message = ''; // متغير لتخزين رسائل النجاح
$error = '';   // متغير لتخزين رسائل الخطأ

// ==============================================================================
// 2. معالجة نموذج إضافة مستخدم جديد عند الإرسال (POST)
// ==============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $name = trim($_POST['name'] ?? ''); // اسم المستخدم الجديد
    $password = trim($_POST['password'] ?? ''); // كلمة المرور
    $role = trim($_POST['role'] ?? 'user'); // الصلاحية والدور (admin, manager, user)

    // التحقق من الحقول الإجبارية
    if (empty($name) || empty($password)) {
        $error = "❌ يرجى تعبئة اسم المستخدم وكلمة المرور.";
    } elseif (!in_array($role, ['admin', 'manager', 'user'], true)) {
        $error = "❌ الصلاحية المحددة غير صحيحة.";
    } else {
        try {
            // التشفير الآمن لكلمة المرور باستخدام خوارزمية BCRYPT
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            // استعلام إدراج المستخدم الجديد المجهز بالكامل لحماية من ثغرات SQL Injection
            $sql = "INSERT INTO users (name, password, role) VALUES (:name, :pass, :role)";
            $stmt = $pdo->prepare($sql); // تجهيز الاستعلام
            $stmt->execute([
                ':name' => $name,
                ':pass' => $passwordHash,
                ':role' => $role
            ]); // تنفيذ الاستعلام الآمن

            // تسجيل العملية في سجل الأمان
            $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_name, action, details) VALUES (:uname, 'إضافة مستخدم', :details)");
            $logStmt->execute([
                ':uname' => $_SESSION['name'],
                ':details' => "تم إضافة مستخدم جديد باسم: $name وصلاحية: $role"
            ]);

            $message = "✅ تم إضافة المستخدم بنجاح!";
        } catch (PDOException $e) {
            // في حالة تكرار اسم المستخدم
            if ($e->getCode() == 23000) {
                $error = "❌ اسم المستخدم مستخدم بالفعل، يرجى اختيار اسم آخر.";
            } else {
                $error = "❌ حدث خطأ أثناء إضافة المستخدم: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة مستخدم جديد - صيدلية الشرية</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background-color: #f4f6f9;
            direction: rtl;
            padding-bottom: 40px;
        }

        .form-card {
            background-color: #ffffff;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            max-width: 600px;
            margin: 40px auto;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="form-card">
        
        <!-- الترويسة الأزرار -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold text-primary mb-0">
                <i class="fa-solid fa-user-plus me-2"></i> إضافة مستخدم جديد
            </h3>
            <a href="viewuser.php" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-right me-1"></i> العودة لجميع المستخدمين
            </a>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success rounded-3 mb-3"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger rounded-3 mb-3"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- نموذج إضافة مستخدم -->
        <form method="post" action="add_user.php">
            
            <!-- اسم المستخدم -->
            <div class="mb-3">
                <label for="name" class="form-label fw-bold">👤 اسم المستخدم لتسجيل الدخول <span class="text-danger">*</span>:</label>
                <input type="text" name="name" id="name" class="form-control" required placeholder="أدخل اسم المستخدم بالإنجليزية أو العربية">
            </div>

            <!-- كلمة المرور -->
            <div class="mb-3">
                <label for="password" class="form-label fw-bold">🔒 كلمة المرور <span class="text-danger">*</span>:</label>
                <input type="password" name="password" id="password" class="form-control" required placeholder="أدخل كلمة المرور الحساب">
            </div>

            <!-- اختيار الصلاحية والدور -->
            <div class="mb-4">
                <label for="role" class="form-label fw-bold">🛡️ الصلاحية والدور بالسيستم <span class="text-danger">*</span>:</label>
                <select name="role" id="role" class="form-select" required>
                    <option value="user">مستخدم عادي (user) - إنشاء فواتير وتقارير وعملاء</option>
                    <option value="manager">مدير صيدلية (manager) - إدارة الأدوية والفواتير والعملاء والموظفين</option>
                    <option value="admin">مدير النظام (admin) - جميع الصلاحيات والنسخ الاحتياطي</option>
                </select>
            </div>

            <!-- أزرار الإرسال والإلغاء -->
            <div class="d-grid gap-2">
                <button type="submit" name="add_user" class="btn btn-success py-2 fw-bold">
                    <i class="fa-solid fa-check me-1"></i> إنشاء الحساب
                </button>
                <a href="viewuser.php" class="btn btn-secondary py-2">إلغاء</a>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
