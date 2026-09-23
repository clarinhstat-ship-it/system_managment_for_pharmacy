<?php
session_start();
if (!isset($_SESSION['userid'])) {
    header('Location: login.php');
    exit;
}

require_once 'db.php';

// جلب الموردين لعرضهم في قائمة الاختيار
try {
    $supStmt = $pdo->query("SELECT supplierid, name FROM suppliers ORDER BY name ASC");
    $suppliers = $supStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('خطأ في جلب الموردين: ' . $e->getMessage());
}

// معالجة الإرسال
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplierid = isset($_POST['supplierid']) && $_POST['supplierid'] !== '' ? (int)$_POST['supplierid'] : null;
    $order_date = !empty($_POST['order_date']) ? $_POST['order_date'] : date('Y-m-d');
    $status = isset($_POST['status']) ? trim($_POST['status']) : 'pending';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : null;

    // تحقق بسيط
    if ($order_date === '' ) {
        die('تاريخ الطلب مطلوب');
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO orders (supplierid, order_date, status, notes) VALUES (:supplierid, :order_date, :status, :notes)");
        $stmt->execute([
            ':supplierid' => $supplierid,
            ':order_date' => $order_date,
            ':status' => $status,
            ':notes' => $notes
        ]);

        header('Location: order_all.php');
        exit;
    } catch (PDOException $e) {
        die('خطأ في حفظ الطلب: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="utf-8">
    <title>إضافة طلب جديد</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { direction: rtl; font-family: 'Cairo', sans-serif; background:#f2f2f2; padding:30px; }
        .form-box { background: #fff; padding:25px; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.08); max-width:720px; margin:0 auto; }
    </style>
</head>
<body>
<div class="form-box">
    <h2 class="text-center">إضافة طلب جديد</h2>
    <form method="post">
        <div class="mb-3">
            <label class="form-label">المورد</label>
            <select name="supplierid" class="form-control">
                <option value="">اختر موردًا (اختياري)</option>
                <?php foreach ($suppliers as $s): ?>
                    <option value="<?= (int)$s['supplierid'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">تاريخ الطلب</label>
            <input type="date" name="order_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">حالة الطلب</label>
            <select name="status" class="form-control">
                <option value="pending">قيد الانتظار</option>
                <option value="ordered">تم الطلب</option>
                <option value="received">تم الاستلام</option>
                <option value="cancelled">ملغي</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">ملاحظات (اختياري)</label>
            <textarea name="notes" class="form-control" rows="4"></textarea>
        </div>

        <div class="text-center">
            <button type="submit" class="btn btn-success">حفظ الطلب</button>
            <a href="order_all.php" class="btn btn-secondary">الغاء والرجوع</a>
        </div>
    </form>
</div>
</body>
</html>
