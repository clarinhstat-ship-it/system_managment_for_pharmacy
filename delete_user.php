<?php
// ==============================================================================
// 1. المصادقة والتحقق من صلاحيات المدير
// ==============================================================================

// تضمين ملف المصادقة auth.php لحماية الصفحة
require_once 'auth.php'; // يفحص الجلسة والاتصال بقاعدة البيانات

// السماح فقط لمدير النظام (admin) بحذف الحسابات
if (!checkRole('admin')) {
    die("❌ ليس لديك صلاحية لحذف المستخدمين.");
}

$deleteUserId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_GET['userid']) ? (int)$_GET['userid'] : 0);

// منع المدير من حذف حسابه الشخصي المسجل به حالياً
if ($deleteUserId > 0 && $deleteUserId != $_SESSION['userid']) {
    try {
        // جلب اسم المستخدم قبل الحذف لتدوينه في السجل
        $getStmt = $pdo->prepare("SELECT name FROM users WHERE userid = :id LIMIT 1");
        $getStmt->execute([':id' => $deleteUserId]);
        $u = $getStmt->fetch();

        if ($u) {
            $uName = $u['name'];

            // حذف الحساب باستعلام مجهز آمن من SQL Injection
            $delStmt = $pdo->prepare("DELETE FROM users WHERE userid = :id");
            $delStmt->execute([':id' => $deleteUserId]);

            // تسجيل العملية في سجل الأمان
            $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_name, action, details) VALUES (:uname, 'حذف مستخدم', :details)");
            $logStmt->execute([
                ':uname' => $_SESSION['name'],
                ':details' => "قام المدير بحذف حساب المستخدم رقم: $deleteUserId ($uName)"
            ]);
        }
    } catch (PDOException $e) {
        die("❌ فشل عملية الحذف: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
    }
}

// التوجيه تلقائياً لصفحة المستخدمين
header("Location: viewuser.php");
exit;
?>
