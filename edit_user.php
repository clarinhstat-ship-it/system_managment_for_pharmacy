<?php
// ==============================================================================
// 1. بدء الجلسة والمصادقة والتحقق من صلاحية مدير النظام
// ==============================================================================

// تضمين ملف المصادقة الذي يفحص تسجيل الدخول والاتصال بقاعدة البيانات $pdo
require_once 'auth.php'; // حماية الصفحة

// السماح لـ admin فقط بتعديل حسابات المستخدمين
if (!checkRole('admin')) {
    die("❌ ليس لديك صلاحية لتعديل حسابات المستخدمين.");
}

$message = ''; // رسائل النجاح
$error = '';   // رسائل الخطأ

$editUserId = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : (isset($_REQUEST['userid']) ? (int)$_REQUEST['userid'] : 0);

if ($editUserId <= 0) {
    header("Location: viewuser.php");
    exit;
}

// ==============================================================================
// 2. معالجة حفظ التعديلات (POST)
// ==============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $name = trim($_POST['name'] ?? '');
    $password = trim($_POST['password'] ?? ''); // اختياري في حالة الرغبة بفي تغيير كلمة المرور
    $role = trim($_POST['role'] ?? 'user');

    if (empty($name) || !in_array($role, ['admin', 'manager', 'user'], true)) {
        $error = "❌ يرجى تعبئة اسم المستخدم والصلاحية بشكل صحيح.";
    } else {
        try {
            if (!empty($password)) {
                // إذا تم كتابة كلمة مرور جديدة تقوم بالتشفير والتحديث
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET name = :name, password = :pass, role = :role WHERE userid = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':name' => $name, ':pass' => $newHash, ':role' => $role, ':id' => $editUserId]);
            } else {
                // التحديث بدون تغيير كلمة المرور
                $sql = "UPDATE users SET name = :name, role = :role WHERE userid = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':name' => $name, ':role' => $role, ':id' => $editUserId]);
            }

            // تسجيل العملية في سجل الأمان
            $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_name, action, details) VALUES (:uname, 'تعديل مستخدم', :details)");
            $logStmt->execute([
                ':uname' => $_SESSION['name'],
                ':details' => "تم تعديل حساب المستخدم رقم: $editUserId ($name) والصلاحية: $role"
            ]);

            $message = "✅ تم تحديث بيانات المستخدم بنجاح!";
        } catch (PDOException $e) {
            $error = "❌ حدث خطأ أثناء التحديث: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }
}

// جلب بيانات المستخدم الحالية
try {
    $stmt = $pdo->prepare("SELECT userid, name, role FROM users WHERE userid = :id LIMIT 1");
    $stmt->execute([':id' => $editUserId]);
    $user = $stmt->fetch();

    if (!$user) {
        die("❌ المستخدم غير موجود بالسيستم.");
    }
} catch (PDOException $e) {
    die("❌ خطأ في جلب بيانات المستخدم: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل بيانات المستخدم - صيدلية الشرية</title>

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
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold text-warning text-dark mb-0">
                <i class="fa-solid fa-user-pen me-2"></i> تعديل حساب (رقم: <?= $user['userid'] ?>)
            </h3>
            <a href="viewuser.php" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-right me-1"></i> جميع المستخدمين
            </a>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success rounded-3 mb-3"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger rounded-3 mb-3"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" action="edit_user.php?id=<?= $user['userid'] ?>">
            
            <div class="mb-3">
                <label for="name" class="form-label fw-bold">👤 اسم المستخدم <span class="text-danger">*</span>:</label>
                <input type="text" name="name" id="name" class="form-control" required value="<?= htmlspecialchars($user['name']) ?>">
            </div>

            <div class="mb-3">
                <label for="password" class="form-label fw-bold">🔒 كلمة المرور الجديدة (أتركها فارغة إذا لا تريد تغييرها):</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="اكتب كلمة مرور جديدة أو اتركها فارغة">
            </div>

            <div class="mb-4">
                <label for="role" class="form-label fw-bold">🛡️ الصلاحية والدور بالسيستم <span class="text-danger">*</span>:</label>
                <select name="role" id="role" class="form-select" required>
                    <option value="user" <?= ($user['role'] === 'user') ? 'selected' : '' ?>>مستخدم عادي (user)</option>
                    <option value="manager" <?= ($user['role'] === 'manager') ? 'selected' : '' ?>>مدير صيدلية (manager)</option>
                    <option value="admin" <?= ($user['role'] === 'admin') ? 'selected' : '' ?>>مدير النظام (admin)</option>
                </select>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" name="update_user" class="btn btn-warning py-2 fw-bold text-dark">
                    <i class="fa-solid fa-floppy-disk me-1"></i> حفظ التعديلات
                </button>
                <a href="viewuser.php" class="btn btn-secondary py-2">إلغاء</a>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
