<?php
session_start();
if (!isset($_SESSION['userid'])) {
    header("Location: login.php");
    exit;
}

require_once 'db.php';
//expiry_date
try {
    $sales = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) AS total FROM invoices3 WHERE type = 'sale'")->fetch(PDO::FETCH_ASSOC);
    $purchases = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) AS total FROM invoices3 WHERE type = 'purchase'")->fetch(PDO::FETCH_ASSOC);
    $profit = ($sales['total'] ?? 0) - ($purchases['total'] ?? 0);

    $stats = $pdo->query("SELECT type, COUNT(*) AS count, COALESCE(SUM(total_amount),
     0) AS total FROM invoices3 GROUP BY type")->fetchAll(PDO::FETCH_ASSOC);
    $expired = $pdo->query("SELECT name, quantity, expiredate FROM medicines WHERE expiredate < CURDATE() AND quantity > 0 ORDER BY expiredate ASC")->fetchAll(PDO::FETCH_ASSOC);
    $lowStock = $pdo->query("SELECT name, quantity FROM medicines  WHERE quantity <= 10 ORDER BY quantity ASC")->fetchAll(PDO::FETCH_ASSOC);
    $topProducts = $pdo->query("SELECT m.name, SUM(ii.quantity) AS total_quantity,
     SUM(ii.total_price) AS total_sales 
     FROM invoice_items ii JOIN medicines m ON ii.medicine_id = m.medicineid
      GROUP BY ii.medicine_id ORDER BY total_quantity DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
    $monthly = $pdo->query("SELECT DATE_FORMAT(date, '%Y-%m') AS month, COUNT(*) AS invoices_count,
     COALESCE(SUM(total_amount), 0) AS total_amount FROM invoices3
      GROUP BY month ORDER BY month DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('فشل في جلب بيانات التقرير: ' . $e->getMessage());
}

function formatCurrency($value) {
    return number_format((float)$value, 2) . ' ج.م';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>التقارير</title>
    <style>
        body { background: #f6f8fb; font-family: Tahoma, Arial, sans-serif; color: #222; margin: 0; padding: 0; }
        .page { max-width: 1150px; margin: 0 auto; padding: 30px; }
        h1, h2, h3 { margin: 0 0 18px; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 2px 14px rgba(15, 23, 42, 0.08); padding: 24px; margin-bottom: 24px; }
        .grid { display: grid; gap: 18px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        .badge { display: inline-block; padding: 6px 12px; border-radius: 999px; font-size: 0.88rem; }
        .badge-sales { background: #1e88e5; color: #fff; }
        .badge-purchase { background: #47b16f; color: #fff; }
        .badge-profit { background: #f59e0b; color: #fff; }
        .table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        .table th, .table td { padding: 12px 14px; border: 1px solid #e5e7eb; text-align: right; }
        .table th { background: #f3f4f6; color: #111827; }
        .table tbody tr:nth-child(even) { background: #fafafa; }
        .table tbody tr:hover { background: #eef2ff; }
        .btn { display: inline-block; padding: 10px 18px; margin: 0 8px 8px 0; border-radius: 8px; text-decoration: none; color: #fff; font-weight: 600; }
        .btn-primary { background: #2563eb; }
        .btn-success { background: #16a34a; }
        .btn-info { background: #0ea5e9; }
        .btn-secondary { background: #6b7280; }
        .top-actions { text-align: right; margin-bottom: 16px; }
        .summary-box { border-radius: 14px; padding: 24px; background: #ffffff; }
        .summary-stat { font-size: 1.75rem; font-weight: 700; margin-bottom: 8px; }
        .summary-label { color: #4b5563; }
        .section-title { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 12px; }
        .section-title h2 { font-size: 1.15rem; }
        @media print {
            .top-actions, .btn { display: none !important; }
            body { background: #fff; }
            .card { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="top-actions">
            <a href="index.php" class="btn btn-secondary">العودة للوحة التحكم</a>
            <a href="report.php?export=excel" class="btn btn-success">تصدير Excel</a>
            <button onclick="window.print()" class="btn btn-info">طباعة التقرير</button>
        </div>

        <div class="card">
            <div class="section-title">
                <h1>لوحة تقارير الصيدليه</h1>
                <span class="badge badge-sales">تاريخ: <?= date('Y-m-d') ?></span>
            </div>
            <div class="grid">
                <div class="summary-box">
                    <div class="summary-stat"><?= formatCurrency($sales['total'] ?? 0) ?></div>
                    <div class="summary-label">إجمالي المبيعات</div>
                </div>
                <div class="summary-box">
                    <div class="summary-stat"><?= formatCurrency($purchases['total'] ?? 0) ?></div>
                    <div class="summary-label">إجمالي المشتريات</div>
                </div>
                <div class="summary-box">
                    <div class="summary-stat"><?= formatCurrency($profit) ?></div>
                    <div class="summary-label">الربح الصافي</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="section-title">
                <h2>إجماليات الفواتير حسب النوع</h2>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>نوع الفاتورة</th>
                        <th>عدد الفواتير</th>
                        <th>إجمالي المبلغ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats as $row): ?>
                    <tr>
                        <td><?= $row['type'] === 'purchase' ? 'شراء' : 'بيع' ?></td>
                        <td><?= $row['count'] ?></td>
                        <td><?= formatCurrency($row['total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <div class="section-title">
                <h2>أهم المنتجات مبيعاً</h2>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>الدواء</th>
                        <th>الكمية المباعة</th>
                        <th>إجمالي المبيعات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($topProducts) > 0): ?>
                        <?php foreach ($topProducts as $product): ?>
                            <tr>
                                <td><?= htmlspecialchars($product['name']) ?></td>
                                <td><?= $product['total_quantity'] ?></td>
                                <td><?= formatCurrency($product['total_sales']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3">لا توجد بيانات مبيعات حالياً.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="grid">
            <div class="card">
                <div class="section-title">
                    <h2>الأدوية منتهية الصلاحية</h2>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>الدواء</th>
                            <th>الكمية</th>
                            <th>تاريخ الانتهاء</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($expired) > 0): ?>
                            <?php foreach ($expired as $med): ?>
                                <tr>
                                    <td><?= htmlspecialchars($med['name']) ?></td>
                                    <td><?= $med['quantity'] ?></td>
                                    <td><?= $med['expiredate'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3">لا توجد أدوية منتهية الصلاحية.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <div class="section-title">
                    <h2>الأدوية منخفضة الكمية (≤ 10)</h2>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>الدواء</th>
                            <th>الكمية</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($lowStock) > 0): ?>
                            <?php foreach ($lowStock as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['name']) ?></td>
                                    <td><?= $item['quantity'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="2">لا توجد أدوية منخفضة الكمية.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="section-title">
                <h2>ملخص شهري للفواتير</h2>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>الشهر</th>
                        <th>عدد الفواتير</th>
                        <th>إجمالي المبلغ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($monthly) > 0): ?>
                        <?php foreach ($monthly as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['month']) ?></td>
                                <td><?= $row['invoices_count'] ?></td>
                                <td><?= formatCurrency($row['total_amount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3">لا توجد بيانات شهرية.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>