
<?php
// 1. بدء الجلسة للتحقق من تسجيل الدخول
session_start();

// 2. التحقق من تسجيل الدخول
if (!isset($_SESSION['userid'])) {
    header("HTTP/1.1 401 Unauthorized");
    echo json_encode(['error' => 'غير مصرح بالوصول']);
    exit;
}

// 3. تضمين ملف اتصال قاعدة البيانات
require_once 'db.php';

// 4. التحقق من وجود معرف الفاتورة في الرابط
if (!isset($_GET['invoice_id']) || !is_numeric($_GET['invoice_id'])) {
    header("HTTP/1.1 400 Bad Request");
    echo json_encode(['error' => 'معرف الفاتورة غير صحيح']);
    exit;
}

$invoice_id = (int)$_GET['invoice_id'];

try {
    // 5. جلب أدوية الفاتورة
    $stmt = $pdo->prepare("        SELECT ii.*, m.name as medicine_name 
        FROM invoice_items ii
        JOIN medicines m ON ii.medicine_id = m.id
        WHERE ii.invoice_id = ?
        ORDER BY ii.id
    ");
    $stmt->execute([$invoice_id]);
    $items = $stmt->fetchAll();
    
    if (empty($items)) {
        header("HTTP/1.1 404 Not Found");
        echo json_encode(['error' => 'لا توجد أصناف لهذه الفاتورة']);
        exit;
    }
    
    // 6. إرجاع البيانات بصيغة JSON
    header('Content-Type: application/json');
    echo json_encode(['items' => $items]);
    exit;
} catch (PDOException $e) {
    error_log("خطأ في جلب أدوية الفاتورة: " . $e->getMessage());
    header("HTTP/1.1 500 Internal Server Error");
    echo json_encode(['error' => 'حدث خطأ تقني']);
    exit;
}
?>
