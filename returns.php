<?php
session_start();
if (!isset($_SESSION['userid'])) {
    header('Location: login.php');
    exit;
}

require_once 'db.php';

try {
    $stmt = $pdo->query(
        "SELECT c.id, c.name, c.phone, c.address,
                COUNT(i.id) AS sales_count,
                COALESCE(SUM(i.total_amount), 0) AS sales_total
           FROM customers c
           LEFT JOIN invoices3 i ON i.customer_id = c.id AND i.type = 'sale'
          GROUP BY c.id
          ORDER BY c.name ASC"
    );
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('خطأ في جلب بيانات العملاء: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>صفحة المرتجعات</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { direction: rtl; font-family: 'Cairo', sans-serif; background: #f7f9fc; padding: 30px; }
        .card { border-radius: 12px; box-shadow: 0 2px 14px rgba(0,0,0,0.08); }
        .table th, .table td { vertical-align: middle; }
        .no-data { text-align: center; color: #666; padding: 40px 0; }
    </style>
</head>
<body>
<div class="container">
    <div class="card p-4 mb-4">
        <h2 class="mb-3">صفحة المرتجعات</h2>
        <p class="text-muted">اختر العميل لتسجيل مرتجع جديد من فواتير المبيعات الخاصة به.</p>
        <a href="index.php" class="btn btn-secondary">العودة إلى القائمة الرئيسية</a>
    </div>

    <div class="card p-4">
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th>رقم العميل</th>
                        <th>اسم العميل</th>
                        <th>الهاتف</th>
                        <th>إجمالي مبيعات</th>
                        <th>عدد الفواتير</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($customers) === 0): ?>
                        <tr>
                            <td colspan="6" class="no-data">لا يوجد عملاء مسجلين.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($customers as $customer): ?>
                            <tr>
                                <td><?= htmlspecialchars($customer['id']) ?></td>
                                <td><?= htmlspecialchars($customer['name']) ?></td>
                                <td><?= htmlspecialchars($customer['phone']) ?></td>
                                <td><?= number_format($customer['sales_total'], 2) ?> ج.م</td>
                                <td><?= htmlspecialchars($customer['sales_count']) ?></td>
                                <td>
                                    <a href="customer_return.php?customer_id=<?= htmlspecialchars($customer['id']) ?>" class="btn btn-primary btn-sm">فتح مرتجعات</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
