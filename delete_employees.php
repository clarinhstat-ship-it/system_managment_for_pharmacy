
 <?php
 // 1. بدء الجلسة (Session) للتحقق من تسجيل الدخول
session_start();

// 2. التحقق من تسجيل الدخول - إذا لم يكن المستخدم مسجل دخوله، يتم تحويله لصفحة تسجيل الدخول
if (!isset($_SESSION['userid'])) {
    header("Location: index.php");
    exit;
}

// استدعاء الاتصال بقاعدة البيانات
require_once 'db.php';

// التحقق من وجود معرف المستخدم في الرابط (GET)
if (!isset($_GET['employeid'])) {
    die("المعرف غير موجود!"); // إيقاف التنفيذ إذا لم يُرسل المعرف
}

// جلب المعرف من الرابط
$employeid = $_GET['employeid'];
// var_dump($_GET);
echo"<br>";
try {
    // تجهيز استعلام الحذف باستخدام PDO
    $stmt = $pdo->prepare("DELETE FROM employees WHERE employeid = :id");

    // ربط المعرف بالاستعلام لحمايته من الحقن
    $stmt->bindParam(':id', $employeid, PDO::PARAM_INT);

    // تنفيذ الاستعلام
    $stmt->execute();

    // إعادة التوجيه إلى صفحة الموظفين بعد الحذف
    header("Location: view_employees.php");
    exit;

} catch (PDOException $e) {
    // في حال حدوث خطأ أثناء الحذف، يتم إظهار رسالة الخطأ
    die("خطأ في الحذف: " . $e->getMessage());
}
// var_dump( $e->getMessage());
?>

