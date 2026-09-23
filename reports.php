<?php
// ==============================================================================
// 1. بدء الجلسة والمصادقة وجلب بيانات التقارير
// ==============================================================================

// تضمين ملف المصادقة الذي يفحص تسجيل الدخول ويتصل بقاعدة البيانات $pdo
require_once 'auth.php'; // حماية الصفحة لجميع المستخدمين حسب أدوارهم

// ==============================================================================
// 2. حساب ومعالجة البيانات المالية والإحصائية
// ==============================================================================

try {
    // 1. استعلام جلب إجمالي المبيعات المكتملة من جدول الفواتير الرئيسي invoices3
    $stmtSales = $pdo->query("SELECT SUM(total_amount) as total, SUM(discount) as total_discount, SUM(tax) as total_tax FROM invoices3 WHERE type = 'sale'");
    $salesData = $stmtSales->fetch(PDO::FETCH_ASSOC);
    $totalSales = (float)($salesData['total'] ?? 0); // إجمالي المبيعات
    $totalDiscount = (float)($salesData['total_discount'] ?? 0); // إجمالي الخصم المقدم
    $totalTax = (float)($salesData['total_tax'] ?? 0); // إجمالي الضريبة المضافة

    // 2. استعلام جلب إجمالي المشتريات من الفواتير
    $stmtPurchases = $pdo->query("SELECT SUM(total_amount) as total FROM invoices3 WHERE type = 'purchase'");
    $purchasesData = $stmtPurchases->fetch(PDO::FETCH_ASSOC);
    $totalPurchases = (float)($purchasesData['total'] ?? 0); // إجمالي الشراء من الموردين

    // 3. حساب الربح الصافي التقريبي (المبيعات - المشتريات)
    $netProfit = $totalSales - $totalPurchases;

    // 4. استعلام جلب الأدوية منتهية الصلاحية أو القريبة من الانتهاء (خلال 60 يوماً القادمة)
    $stmtExpired = $pdo->query("SELECT medicines.*, suppliers.name as supplier_name 
                                FROM medicines 
                                LEFT JOIN suppliers ON medicines.supplierid = suppliers.supplierid 
                                WHERE expiredate IS NOT NULL AND expiredate <= DATE_ADD(CURDATE(), INTERVAL 60 DAY) 
                                ORDER BY expiredate ASC");
    $expiredMedicines = $stmtExpired->fetchAll(PDO::FETCH_ASSOC);

    // 5. استعلام جلب نواقص المخزون (الأدوية التي يقل رصيدها عن أو يساوي 5 قطع)
    $stmtLowStock = $pdo->query("SELECT medicines.*, suppliers.name as supplier_name 
                                 FROM medicines 
                                 LEFT JOIN suppliers ON medicines.supplierid = suppliers.supplierid 
                                 WHERE quantity <= 5 
                                 ORDER BY quantity ASC");
    $lowStockMedicines = $stmtLowStock->fetchAll(PDO::FETCH_ASSOC);

    // 6. استعلام ملخص الفواتير حسب النوع (مبيعات ومشتريات)
    $stmtStats = $pdo->query("SELECT type, COUNT(*) as count, SUM(total_amount) as total_sum FROM invoices3 GROUP BY type");
    $invoiceStats = $stmtStats->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("❌ خطأ في إعداد التقرير المالي: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

// ==============================================================================
// 3. معالجة تصدير التقرير لملف Excel عند طلب المستخدم
// ==============================================================================

if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    // إرسال ترويسات الاستجابة لإجبار المتصفح على تنزيل ملف Excel بتشفير UTF-8
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="تقرير_صيدلية_الشرية_' . date('Y-m-d') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // طباعة ترويسة HTML بسيطة بتشفير UTF-8 داخل ملف الـ Excel
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
    echo '<h2>📊 التقرير المالي لنظام صيدلية الشرية المركزية - تاريخ: ' . date('Y-m-d') . '</h2>';
    
    echo '<h3>1. الإحصائيات المالية العامة</h3>';
    echo '<table border="1" style="border-collapse:collapse; width:100%; text-align:right;">';
    echo '<tr style="background:#f2f2f2;"><th>الوصف</th><th>القيمة (ر.س)</th></tr>';
    echo '<tr><td>إجمالي المبيعات</td><td>' . number_format($totalSales, 2) . '</td></tr>';
    echo '<tr><td>إجمالي المشتريات</td><td>' . number_format($totalPurchases, 2) . '</td></tr>';
    echo '<tr><td>إجمالي الخصومات</td><td>' . number_format($totalDiscount, 2) . '</td></tr>';
    echo '<tr><td>إجمالي الضرائب</td><td>' . number_format($totalTax, 2) . '</td></tr>';
    echo '<tr style="font-weight:bold;"><td>الربح الصافي التقريبي</td><td>' . number_format($netProfit, 2) . '</td></tr>';
    echo '</table>';

    echo '<h3>2. الأدوية المنتهية أو القريبة من الانتهاء</h3>';
    echo '<table border="1" style="border-collapse:collapse; width:100%; text-align:right;">';
    echo '<tr style="background:#f2f2f2;"><th>الباركود</th><th>اسم الدواء</th><th>تاريخ الانتهاء</th><th>الكمية المتبقية</th><th>المورد</th></tr>';
    foreach ($expiredMedicines as $med) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($med['barcode'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($med['name']) . '</td>';
        echo '<td>' . htmlspecialchars($med['expiredate']) . '</td>';
        echo '<td>' . $med['quantity'] . '</td>';
        echo '<td>' . htmlspecialchars($med['supplier_name'] ?? '-') . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>التقارير المالية والمخزون - صيدلية الشرية</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background-color: #f4f6f9;
            direction: rtl;
            padding-bottom: 40px;
        }

        .report-card {
            background-color: #ffffff;
            border-radius: 14px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }

        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: #fff !important;
            }
            .report-card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
        }
    </style>
</head>
<body>

<div class="container my-4">
    
    <!-- ترويسة التقارير وأزرار التصدير (تختفي عند الطباعة) -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2 no-print">
        <h3 class="fw-bold text-primary mb-0">
            <i class="fa-solid fa-chart-pie me-2"></i> التقارير الشاملة والإحصائيات
        </h3>
        <div>
            <a href="index.php" class="btn btn-outline-secondary me-2">
                <i class="fa-solid fa-arrow-right me-1"></i> الرئيسية
            </a>
            <button onclick="window.print()" class="btn btn-info text-white me-2">
                <i class="fa-solid fa-print me-1"></i> طباعة التقرير
            </button>
            <a href="reports.php?export=excel" class="btn btn-success">
                <i class="fa-solid fa-file-excel me-1"></i> تصدير لـ Excel
            </a>
        </div>
    </div>

    <!-- ============================================================================== -->
    <!-- 1. كروت الملخص المالي                                                          -->
    <!-- ============================================================================== -->
    <div class="row g-3 mb-4">
        <!-- إجمالي المبيعات -->
        <div class="col-md-3">
            <div class="card bg-success text-white p-3 shadow-sm border-0 rounded-3">
                <h6 class="text-white-50">💰 إجمالي المبيعات</h6>
                <h3 class="fw-bold mb-0"><?= number_format($totalSales, 2) ?> ر.س</h3>
            </div>
        </div>

        <!-- إجمالي المشتريات -->
        <div class="col-md-3">
            <div class="card bg-warning text-dark p-3 shadow-sm border-0 rounded-3">
                <h6 class="text-dark-50">🛒 إجمالي المشتريات</h6>
                <h3 class="fw-bold mb-0"><?= number_format($totalPurchases, 2) ?> ر.س</h3>
            </div>
        </div>

        <!-- إجمالي الخصومات والضرائب -->
        <div class="col-md-3">
            <div class="card bg-info text-white p-3 shadow-sm border-0 rounded-3">
                <h6 class="text-white-50">🏷️ الخصومات / 📑 الضرائب</h6>
                <h5 class="fw-bold mb-0">خصم: <?= number_format($totalDiscount, 2) ?> | ضريبة: <?= number_format($totalTax, 2) ?></h5>
            </div>
        </div>

        <!-- الربح الصافي التقريبي -->
        <div class="col-md-3">
            <div class="card <?= ($netProfit >= 0) ? 'bg-primary' : 'bg-danger' ?> text-white p-3 shadow-sm border-0 rounded-3">
                <h6 class="text-white-50">📈 صافي الأرباح المتوقعة</h6>
                <h3 class="fw-bold mb-0"><?= number_format($netProfit, 2) ?> ر.س</h3>
            </div>
        </div>
    </div>

    <!-- ============================================================================== -->
    <!-- 2. تقرير الأدوية منتهية الصلاحية والقريبة من الانتهاء                         -->
    <!-- ============================================================================== -->
    <div class="report-card">
        <h5 class="fw-bold text-danger mb-3">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> تقرير الأدوية المنتهية أو القريبة من الانتهاء (خلال 60 يوماً)
        </h5>

        <div class="table-responsive">
            <table class="table table-bordered table-hover text-center align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>الرقم</th>
                        <th>الباركود</th>
                        <th>اسم الدواء</th>
                        <th>تاريخ الانتهاء ⏳</th>
                        <th>الكمية المتبقية 📦</th>
                        <th>المورد 🚚</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($expiredMedicines) > 0): ?>
                        <?php foreach ($expiredMedicines as $med): ?>
                            <?php 
                            $isExpiredNow = (strtotime($med['expiredate']) <= time());
                            ?>
                            <tr class="<?= $isExpiredNow ? 'table-danger' : 'table-warning' ?>">
                                <td><?= $med['medicineid'] ?></td>
                                <td><span class="badge bg-secondary font-monospace"><?= htmlspecialchars($med['barcode'] ?? '-') ?></span></td>
                                <td class="fw-bold"><?= htmlspecialchars($med['name']) ?></td>
                                <td>
                                    <?php if ($isExpiredNow): ?>
                                        <span class="badge bg-danger">منتهي الان (<?= htmlspecialchars($med['expiredate']) ?>)</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">ينتهي قريباً (<?= htmlspecialchars($med['expiredate']) ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold"><?= $med['quantity'] ?></td>
                                <td><?= htmlspecialchars($med['supplier_name'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-success py-3">
                                <i class="fa-solid fa-circle-check me-1"></i> ممتااااز! لا توجد أي أدوية منتهية الصلاحية أو قريبة من الانتهاء حالياً.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ============================================================================== -->
    <!-- 3. تقرير نواقص المخزون (الكمية <= 5)                                         -->
    <!-- ============================================================================== -->
    <div class="report-card">
        <h5 class="fw-bold text-warning text-dark mb-3">
            <i class="fa-solid fa-boxes-packing me-2"></i> تقرير نواقص المخزون (الكمية أقل من أو تساوي 5 قطع)
        </h5>

        <div class="table-responsive">
            <table class="table table-bordered table-hover text-center align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>الرقم</th>
                        <th>الباركود</th>
                        <th>اسم الدواء</th>
                        <th>سعر البيع</th>
                        <th>الكمية المتبقية 📦</th>
                        <th>المورد 🚚</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($lowStockMedicines) > 0): ?>
                        <?php foreach ($lowStockMedicines as $med): ?>
                            <tr class="table-warning">
                                <td><?= $med['medicineid'] ?></td>
                                <td><span class="badge bg-secondary font-monospace"><?= htmlspecialchars($med['barcode'] ?? '-') ?></span></td>
                                <td class="fw-bold"><?= htmlspecialchars($med['name']) ?></td>
                                <td><?= number_format($med['price_sales'], 2) ?> ر.س</td>
                                <td><span class="badge bg-danger"><?= $med['quantity'] ?> (نواقص)</span></td>
                                <td><?= htmlspecialchars($med['supplier_name'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-success py-3">
                                <i class="fa-solid fa-circle-check me-1"></i> لا توجد أي نواقص في المخزون، جميع الأدوية بكميات كافية.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ============================================================================== -->
    <!-- 4. ملخص الفواتير والعمليات المسجلة                                            -->
    <!-- ============================================================================== -->
    <div class="report-card">
        <h5 class="fw-bold text-secondary mb-3">
            <i class="fa-solid fa-receipt me-2"></i> ملخص عمليات الفواتير المسجلة
        </h5>

        <div class="table-responsive">
            <table class="table table-bordered text-center align-middle">
                <thead class="table-secondary">
                    <tr>
                        <th>نوع الفاتورة</th>
                        <th>عدد الفواتير الإجمالي</th>
                        <th>إجمالي المبالغ المسجلة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($invoiceStats) > 0): ?>
                        <?php foreach ($invoiceStats as $stat): ?>
                            <tr>
                                <td class="fw-bold"><?= ($stat['type'] === 'purchase') ? 'فواتير شراء (مشتريات)' : 'فواتير بيع (مبيعات)' ?></td>
                                <td><?= number_format($stat['count']) ?> فاتورة</td>
                                <td class="fw-bold text-success"><?= number_format($stat['total_sum'], 2) ?> ر.س</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="text-center text-muted">لا توجد فواتير مسجلة حتى الآن.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>