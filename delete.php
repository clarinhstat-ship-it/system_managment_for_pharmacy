

<?php
require_once 'db.php';

// التحقق من وجود المعرف في الرابط
if (!isset($_GET['medicineid'])) {
    die("المعرف غير موجود!");
}

$medicineid = $_GET['medicineid'];

// تنفيذ الحذف باستخدام PDO
try {
    $stmt = $pdo->prepare("DELETE FROM medicines WHERE medicineid = :id");
    $stmt->bindParam(':id', $medicineid, PDO::PARAM_INT);
    $stmt->execute();

    // إعادة التوجيه بعد الحذف
    header("Location: medicines.php");
    exit;
} catch (PDOException $e) {
    die("فشل في الحذف: " . $e->getMessage());
}
?>




<a href="edit_medicine.php?medicineid=<?= $medicine['medicineid'] ?>" class="btn btn-sm btn-outline-warning">تعديل</a>

<a href="delete_medicine.php?medicineid=<?= $medicine['medicineid'] ?>" 
   class="btn btn-sm btn-outline-danger"
   onclick="return confirm('هل أنت متأكد أنك تريد حذف هذا الدواء؟');">
   حذف
</a>