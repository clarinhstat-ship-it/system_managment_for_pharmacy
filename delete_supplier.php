
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
if (!isset($_GET['supplierid'])) {
    die("المعرف غير موجود!"); // إيقاف التنفيذ إذا لم يُرسل المعرف
}

// جلب المعرف من الرابط
$supplierid = $_GET['supplierid'];
var_dump($_GET);
echo"<br>";
try {
    // تجهيز استعلام الحذف باستخدام PDO
    $stmt = $pdo->prepare("DELETE FROM users WHERE supplierid = :id");

    // ربط المعرف بالاستعلام لحمايته من الحقن
    $stmt->bindParam(':id', $supplierid, PDO::PARAM_INT);

    // تنفيذ الاستعلام
    $stmt->execute();

    // إعادة التوجيه إلى صفحة المستخدمين بعد الحذف
    header("Location: suppliers.php");
    exit;

} catch (PDOException $e) {
    // في حال حدوث خطأ أثناء الحذف، يتم إظهار رسالة الخطأ
    die("خطأ في الحذف: " . $e->getMessage());
}
// var_dump( $e->getMessage());
?>

