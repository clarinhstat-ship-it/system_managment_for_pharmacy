<?php
// ==============================================================================
// 1. بدء الجلسة والمصادقة للتحقق من أمان المستخدم
// ==============================================================================

// تضمين ملف المصادقة auth.php لحماية الصفحة والاتصال بقاعدة البيانات PDO
require_once 'auth.php'; // حماية الصفحة لجميع المستخدمين المسجلين

$msg = '';   // متغير لرسائل النجاح
$error = ''; // متغير لرسائل الخطأ

$userId = $_SESSION['userid']; // كود المستخدم الحالى من الجلسة
$userName = $_SESSION['name']; // اسم المستخدم الحالى من الجلسة

// ==============================================================================
// 2. معالجة تغيير كلمة المرور عند تقديم النموذج (POST)
// ==============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $old_password = trim($_POST['old_password'] ?? ''); // كلمة المرور القديمة
    $new_password = trim($_POST['new_password'] ?? ''); // كلمة المرور الجديدة
    $confirm_password = trim($_POST['confirm_password'] ?? ''); // تأكيد كلمة المرور الجديدة

    if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
        $error = "❌ يرجى تعبئة كافة الحقول المطلوب إدخالها.";
    } elseif ($new_password !== $confirm_password) {
        $error = "❌ كلمة المرور الجديدة وتأكيدها غير متطابقين!";
    } elseif (strlen($new_password) < 6) {
        $error = "❌ يجب أن تحتوي كلمة المرور الجديدة على 6 أحرف على الأقل للأمان.";
    } else {
        try {
            // جلب كلمة المرور الحالية المخزنة في قاعدة البيانات بأمان باستخدام Prepared Statements
            $stmt = $pdo->prepare("SELECT password FROM users WHERE userid = :id LIMIT 1");
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch();

            if ($user) {
                // التحقق من صحة كلمة المرور القديمة المشفرة أو العادية
                $isValidOld = false;
                if (password_verify($old_password, $user['password'])) {
                    $isValidOld = true;
                } elseif ($old_password === $user['password']) {
                    $isValidOld = true;
                }

                if ($isValidOld) {
                    // تشفير كلمة المرور الجديدة بأمان باستخدام BCRYPT القياسية
                    $newPasswordHash = password_hash($new_password, PASSWORD_DEFAULT);

                    // تحديث كلمة المرور في قاعدة البيانات باستعلام مجهز آمن من SQL Injection
                    $updateStmt = $pdo->prepare("UPDATE users SET password = :hash WHERE userid = :id");
                    $updateStmt->execute([':hash' => $newPasswordHash, ':id' => $userId]);

                    // تسجيل العملية في سجل الأمان
                    $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_name, action, details) VALUES (:uname, 'تغيير كلمة المرور', 'قام المستخدم بتغيير كلمة المرور الخاصة به بنجاح')");
                    $logStmt->execute([':uname' => $userName]);

                    $msg = "✅ تم تغيير كلمة المرور بنجاح!";
                } else {
                    $error = "❌ كلمة المرور القديمة التي أدخلتها غير صحيحة.";
                }
            }
        } catch (PDOException $e) {
            $error = "❌ حدث خطأ أثناء تغيير كلمة المرور: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تغيير كلمة المرور - صيدلية الشرية</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background-color: #f4f6f9;
            direction: rtl;
            padding-bottom: 40px;
        }

        .card-custom {
            background-color: #ffffff;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            max-width: 500px;
            margin: 40px auto;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="card-custom">
        
        <!-- رأس الصفحة -->
        <div class="text-center mb-4">
            <h3 class="fw-bold text-primary">
                <i class="fa-solid fa-key me-2"></i> تغيير كلمة المرور
            </h3>
            <p class="text-muted small">مرحباً بك عزيزي <strong><?= htmlspecialchars($userName) ?></strong> في قسم تغيير كلمة المرور</p>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="alert alert-success rounded-3 mb-3"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger rounded-3 mb-3"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- نموذج تغيير كلمة المرور -->
        <form method="post" action="change_password.php">
            
            <!-- كلمة المرور القديمة -->
            <div class="mb-3">
                <label for="old_password" class="form-label fw-bold">🔒 كلمة المرور القديمة:</label>
                <input type="password" name="old_password" id="old_password" class="form-control" required placeholder="أدخل كلمة المرور القديمة">
            </div>

            <!-- كلمة المرور الجديدة -->
            <div class="mb-3">
                <label for="new_password" class="form-label fw-bold">🔑 كلمة المرور الجديدة:</label>
                <input type="password" name="new_password" id="new_password" class="form-control" required placeholder="أدخل كلمة المرور الجديدة (6 أحرف على الأقل)">
            </div>

            <!-- تأكيد كلمة المرور الجديدة -->
            <div class="mb-4">
                <label for="confirm_password" class="form-label fw-bold">🔁 تأكيد كلمة المرور الجديدة:</label>
                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required placeholder="أعد كتابة كلمة المرور الجديدة">
            </div>

            <!-- أزرار الحفظ والإلغاء -->
            <div class="d-grid gap-2">
                <button type="submit" name="change_password" class="btn btn-primary py-2 fw-bold">
                    <i class="fa-solid fa-floppy-disk me-1"></i> حفظ كلمة المرور الجديدة
                </button>
                <a href="index.php" class="btn btn-outline-secondary py-2">
                    <i class="fa-solid fa-arrow-right me-1"></i> العودة للصفحة الرئيسية
                </a>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
