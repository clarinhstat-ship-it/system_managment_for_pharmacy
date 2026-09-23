<?php
// ==============================================================================
// 1. بدء الجلسة والمصادقة والتحقق من التخويل
// ==============================================================================

// تضمين ملف المصادقة الذي يفحص تسجيل الدخول ويتصل بقاعدة البيانات $pdo
require_once 'auth.php'; // حماية الصفحة

// ==============================================================================
// 2. جلب جميع الفواتير المسجلة بالنظام مرتبة حسب الأحدث
// ==============================================================================

try {
    // استعلام جلب الفواتير من جدول الفواتير الرئيسي invoices3 مع ترتيبها تنازلياً حسب التاريخ والأحدث
    $sql = "SELECT * FROM invoices3 ORDER BY id DESC";
    $stmt = $pdo->query($sql); // تنفيذ الاستعلام
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC); // جلب كافة الفواتير كـ مصفوفة ترابطية
} catch (PDOException $e) {
    die("❌ فشل جلب قائمة الفواتير: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>قائمة الفواتير - صيدلية الشرية</title>

    <!-- تضمين Bootstrap و FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background-color: #f4f6f9;
            direction: rtl;
            padding-bottom: 40px;
        }

        .box-container {
            background-color: #ffffff;
            border-radius: 14px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-top: 25px;
        }

        .table th, .table td {
            vertical-align: middle;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="box-container">
        
        <!-- ترويسة الصفحة والأزرار -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h3 class="fw-bold text-primary mb-0">
                <i class="fa-solid fa-receipt me-2"></i> قائمة الفواتير المسجلة
            </h3>
            <div>
                <a href="index.php" class="btn btn-outline-secondary me-2">
                    <i class="fa-solid fa-arrow-right me-1"></i> الرئيسية
                </a>
                <a href="invoce.php" class="btn btn-success">
                    <i class="fa-solid fa-plus me-1"></i> إنشاء فاتورة جديدة (POS)
                </a>
            </div>
        </div>

        <!-- جدول الفواتير -->
        <div class="table-responsive">
            <table class="table table-hover table-bordered text-center align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>رقم الفاتورة</th>
                        <th>اسم العميل / المورد</th>
                        <th>نوع الفاتورة</th>
                        <th>التاريخ والوقت 📅</th>
                        <th>المبلغ الصافي 💰</th>
                        <th>عرض والطباعة 🔍</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($invoices) > 0): ?>
                        <?php foreach ($invoices as $inv): ?>
                            <tr>
                                <td class="fw-bold">#<?= $inv['id'] ?></td>
                                <td><?= htmlspecialchars($inv['customer_supplier_name'] ?? 'عميل نقدي') ?></td>
                                <td>
                                    <?php if ($inv['type'] === 'purchase'): ?>
                                        <span class="badge bg-warning text-dark">مشتريات</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">مبيعات</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-secondary"><?= htmlspecialchars($inv['created_at']) ?></td>
                                <td class="fw-bold text-success">
                                    <?= number_format($inv['net_total'] > 0 ? $inv['net_total'] : $inv['total_amount'], 2) ?> ر.س
                                </td>
                                <td>
                                    <!-- زر عرض وطباعة الفاتورة -->
                                    <a href="invoice_print.php?id=<?= $inv['id'] ?>" class="btn btn-info btn-sm text-white">
                                        <i class="fa-solid fa-print me-1"></i> طباعة الفاتورة
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                لا توجد فواتير مسجلة في النظام حتى الآن.
                            </td>
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