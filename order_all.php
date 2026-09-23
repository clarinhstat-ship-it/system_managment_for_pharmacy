<?php

/*
require_once 'db.php'; // ✅ الاتصال بقاعدة البيانات

// ✅ جلب جميع الطلبات مع أسماء الموردين
$stmt = $pdo->prepare("  SELECT o.*, s.suppliername 
    FROM orders o 
    LEFT JOIN suppliers s ON o.supplierid = s.supplierid 
    ORDER BY o.order_date DESC
");
$stmt->execute();
$order = $stmt->fetchAll(PDO::FETCH_ASSOC); // ✅ تخزين الطلبات في مصفوفة
*/





// بدء الجلسة والتحقق من تسجيل الدخول
session_start();
if (!isset($_SESSION['userid'])) {
    header('Location: login.php');
    exit;
}

require_once 'db.php';

try {
    $stmt = $pdo->prepare("
        SELECT o.*, s.name AS suppliername 
    FROM orders o 
    LEFT JOIN suppliers s ON o.supplierid  = s.supplierid  
    ORDER BY o.order_date DESC
   " );
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('فشل في جلب الطلبات: ' . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>إدارة الطلبات</title>
    <style>
        body {
            direction: rtl;
            font-family: 'Cairo', sans-serif;
            margin: 40px;
            background-color: #f2f2f2;
        }

        h2 {
            text-align: center;
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
        }

        th, td {
            padding: 12px;
            border: 1px solid #ccc;
            text-align: center;
        }

        th {
            background-color: #007bff;
            color: white;
        }

        .add-btn {
            display: inline-block;
            margin-bottom: 20px;
            padding: 10px 20px;
            background-color: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        .edit-btn {
            background-color: #ffc107;
            padding: 6px 10px;
            color: black;
            border-radius: 4px;
            text-decoration: none;
        }
    </style>
</head>
<body>

<h2>📝 الطلبات</h2>

<!-- ✅ زر إضافة طلب جديد -->
<a href="add_order.php" class="add-btn">➕ إضافة طلب جديد</a>
<a href="index.php" class="add-btn">
                <i class="add-btn"></i> الرئيسية
            </a>
<!-- ✅ جدول عرض الطلبات -->
<table>
    <thead>
        <tr>
            <th>المعرف</th>
            <th>اسم المورد</th>
            <th>تاريخ الطلب</th>
            <th>حالة الطلب</th>
            <th>الإجراءات</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($orders as $order): ?>
            <tr>
                <td><?= $order['orderid'] ?></td>
                <td><?= htmlspecialchars($order['suppliername'] ?? 'غير معروف') ?></td>
                <td><?= $order['order_date'] ?></td>
                <td><?= htmlspecialchars($order['status']) ?></td>
                <td>
                    <a href="view_order.php?id=<?= $order['orderid'] ?>" class="edit-btn">📄 عرض</a>
                    <a href="shipping.php?orderid=<?= $order['orderid'] ?>" class="edit-btn" style="background:#17a2b8; margin-left:6px;">🚚 شحن</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>



