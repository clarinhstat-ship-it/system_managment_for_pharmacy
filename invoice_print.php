<?php
// ==============================================================================
// 1. بدء الجلسة والمصادقة وجلب بيانات الفاتورة
// ==============================================================================

// تضمين ملف المصادقة للحماية والاتصال بقاعدة البيانات PDO
require_once 'auth.php'; // حماية الصفحة

$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user = isset($_GET['name']) ;

if ($invoice_id <= 0) {
    header("Location: invoice_all.php");
    exit;
}

try {
    // 1. جلب الفاتورة من جدول الفواتير الرئيسي invoices3
    $stmtInv = $pdo->prepare("SELECT * FROM invoices3 WHERE id = :id LIMIT 1");
    $stmtInv->execute([':id' => $invoice_id]);
    $invoice = $stmtInv->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        die("❌ الفاتورة المطلوبة غير موجودة.");
    }

    // 2. جلب تفاصيل الأدوية المباعة للفاتورة عبر JOIN مع جدول الأدوية medicines
    $stmtItems = $pdo->prepare("SELECT ii.*, m.name as medicine_name, m.barcode 
                                FROM invoice_items ii 
                                JOIN medicines m ON ii.medicine_id = m.medicineid 
                                WHERE ii.invoice_id = :id");
    $stmtItems->execute([':id' => $invoice_id]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("❌ خطأ في جلب تفاصيل الفاتورة: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طباعة فاتورة رقم #<?= $invoice['id'] ?> - صيدلية الشرية</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background-color: #f8f9fa;
            direction: rtl;
        }

        /* حاوية الفاتورة للطباعة العادية */
        .invoice-card {
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            max-width: 800px;
            margin: 20px auto;
        }

        /* قواعد التنسيق الخاصة بالطباعة Thermal (80mm) والـ A4 */
        @media print {
            .no-print {
                display: none !important; /* إخفاء أزرار الطباعة والعودة أثناء الطباعة الحقيقية */
            }

            body {
                background: #fff !important;
                padding: 0 !important;
            }

            .invoice-card {
                box-shadow: none !important;
                border: none !important;
                padding: 10px !important;
                margin: 0 !important;
                max-width: 100% !important;
            }
        }
    </style>
</head>
<body>

<!-- أزرار الطباعة والتحكم العلوية (تختفي أثناء الطباعة) -->
<div class="container text-center my-3 no-print">
    <button onclick="window.print()" class="btn btn-success px-4 me-2">
        <i class="fa-solid fa-print me-1"></i> طباعة الفاتورة (Print / PDF)
    </button>
    <a href="invoce.php" class="btn btn-primary px-4 me-2">
        <i class="fa-solid fa-plus me-1"></i> فاتورة جديدة
    </a>
    <a href="invoice_all.php" class="btn btn-outline-secondary px-4">
        <i class="fa-solid fa-list me-1"></i> جميع الفواتير
    </a>
</div>

<!-- بطاقة الفاتورة القابلة للطباعة -->
<div class="container">
    <div class="invoice-card">
        
        <!-- ترويسة الفاتورة والمعلومات الرئيسية -->
        <div class="row align-items-center mb-4 border-bottom pb-3">
            <div class="col-6">
                <h3 class="fw-bold text-primary mb-1">💊 صيدلية الشرية المركزية</h3>
                <p class="text-muted small mb-0">العنوان: شارع الصيدليات - الهاتف: 0123456789</p>
            </div>
            <div class="col-6 text-end">
                <h4 class="fw-bold text-dark mb-1">فاتورة <?= ($invoice['type'] === 'purchase') ? 'شراء' : 'بيع' ?></h4>
                <p class="mb-0"><strong>رقم الفاتورة:</strong> #<?= $invoice['id'] ?></p>
                <p class="mb-0 text-muted small"><strong>التاريخ:</strong> <?= $invoice['created_at'] ?></p>
            </div>
        </div>

        <!-- بيانات العميل الكاشير -->
        <div class="row mb-4 bg-light p-3 rounded-3">
            <div class="col-6">
                <p class="mb-1"><strong>اسم العميل / المورد:</strong> <?= htmlspecialchars($invoice['customer_supplier_name'] ?? 'عميل نقدي') ?></p>
            </div>
            <div class="col-6 text-end">
                <p class="mb-1"><strong>نوع العملية:</strong> <?= ($invoice['type'] === 'purchase') ? 'مشتريات' : 'مبيعات نقدية' ?></p>
            </div>
        </div>

        <!-- جدول تفاصيل المنتجات المباعة -->
        <div class="table-responsive mb-4">
            <table class="table table-bordered text-center align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>اسم الدواء</th>
                        <th>الكمية</th>
                        <th>سعر الوحدة</th>
                        <th>الإجمالي</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($items as $item): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td class="fw-bold text-start ps-3"><?= htmlspecialchars($item['medicine_name']) ?></td>
                            <td><?= $item['quantity'] ?></td>
                            <td><?= number_format($item['price'], 2) ?> ر.س</td>
                            <td class="fw-bold"><?= number_format($item['total'], 2) ?> ر.س</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- الإجمالي والخصم والصافي النهائي -->
        <div class="row justify-content-end">
            <div class="col-md-5">
                <div class="border rounded-3 p-3 bg-light">
                    <div class="d-flex justify-content-between mb-2">
                        <span>المجموع الفرعي:</span>
                        <span><?= number_format($invoice['total_amount'], 2) ?> ر.س</span>
                    </div>
                    <?php if ($invoice['discount'] > 0): ?>
                        <div class="d-flex justify-content-between mb-2 text-danger">
                            <span>الخصم:</span>
                            <span>- <?= number_format($invoice['discount'], 2) ?> ر.س</span>
                        </div>
                    <?php endif; ?>
                    <?php if ($invoice['tax'] > 0): ?>
                        <div class="d-flex justify-content-between mb-2 text-primary">
                            <span>الضريبة:</span>
                            <span>+ <?= number_format($invoice['tax'], 2) ?> ر.س</span>
                        </div>
                    <?php endif; ?>
                    <hr>
                    <div class="d-flex justify-content-between fw-bold fs-5 text-success">
                        <span>الصافي النهائي:</span>
                        <span><?= number_format($invoice['net_total'] > 0 ? $invoice['net_total'] : $invoice['total_amount'], 2) ?> ر.س</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- تذييل الفاتورة -->
        <div class="text-center mt-5 pt-3 border-top text-muted small">
            <p class="mb-1">شكراً لتعاملكم مع صيدلية الشرية المركزية - نتمنى لكم دوام الصحة والعافية ❤️</p>
            <p class="mb-0">تم طباعة الفاتورة آلياً بواسطة نظام الصيدلية
         <?= isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'المدير'; ?>

            </p>
        </div>
    </div>
</div>

</body>
</html>
