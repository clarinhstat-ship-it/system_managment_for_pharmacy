<?php
// ==============================================================================
// 1. المصادقة والتحقق من الصلاحيات
// ==============================================================================

// تضمين ملف المصادقة auth.php لحماية الصفحة
require_once 'auth.php'; // يفحص الجلسة والاتصال بقاعدة البيانات

// السماح فقط لمدير النظام (admin) بحذف الأدوية لسلامة النظام والمخزون
if (!checkRole('admin')) {
    die("❌ ليس لديك صلاحية لحذف الأدوية."); // إلغاء في حال عدم التمتع بصلاحية المدير
}

// ==============================================================================
// 2. استقبال الرقم التعريفي للدواء والتأكد منه
// ==============================================================================

$medicineid = isset($_GET['medicineid']) ? (int)$_GET['medicineid'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

if ($medicineid > 0) {
    try {
        // جلب اسم الدواء أولاً لتدوينه في سجل الأمان قبل الحذف
        $getStmt = $pdo->prepare("SELECT name FROM medicines WHERE medicineid = :id LIMIT 1");
        $getStmt->execute([':id' => $medicineid]);
        $med = $getStmt->fetch();

        if ($med) {
            $medName = $med['name'];

            // استعلام حذف الدواء المجهز بالكامل لحماية من ثغرات SQL Injection
            $deleteStmt = $pdo->prepare("DELETE FROM medicines WHERE medicineid = :id");
            $deleteStmt->execute([':id' => $medicineid]);

            // تسجيل عملية الحذف في سجل الأمان (audit_logs)
            $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_name, action, details) VALUES (:uname, 'حذف دواء', :details)");
            $logStmt->execute([
                ':uname' => $_SESSION['name'],
                ':details' => "قام المدير بحذف الدواء رقم: $medicineid ($medName)"
            ]);
        }
    } catch (PDOException $e) {
        die("❌ فشل عملية الحذف: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
    }
}

// إعادة توجيه المستخدم تلقائياً لصفحة الأدوية الرئيسية بعد الحذف
header("Location: medicines.php");
exit;
?>