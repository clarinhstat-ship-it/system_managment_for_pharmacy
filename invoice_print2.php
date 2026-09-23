<?php
require_once 'db.php'; // ✅ الاتصال بقاعدة البيانات

// ✅ التحقق من وجود رقم الفاتورة في الرابط
if (!isset($_GET['saleid']) || empty($_GET['saleid'])) {
    die("❌ لم يتم تحديد رقم الفاتورة.");
}

$saleid = $_GET['saleid']; // ✅ تخزين رقم الفاتورة

// ✅ جلب رأس الفاتورة
$stmt = $pdo->prepare("SELECT * FROM sales WHERE saleid = :id");
$stmt->execute([':id' => $saleid]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
    die("⚠️ الفاتورة غير موجودة.");
}

// ✅ جلب تفاصيل الأصناف داخل الفاتورة
$stmt = $pdo->prepare("SELECT si.*, m.name AS medicine_name
                       FROM sale_items si
                       JOIN medicines m ON si.medicineid = m.medicineid
                       WHERE si.saleid = :id");
$stmt->execute([':id' => $saleid]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- ✅ صفحة HTML للطباعة -->
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>طباعة الفاتورة</title>
    <style>
        body {
            direction: rtl;
            font-family: 'Cairo', sans-serif;
            margin: 30px;
            background: white;
        }

        .invoice-box {
            max-width: 800px;
            margin: auto;
            padding: 30px;
            border: 2px solid #000;
        }

        h2, h4 {
            text-align: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
        }

        th, td {
            border: 1px solid #000;
            padding: 10px;
            text-align: center;
        }

        .total-row td {
            font-weight: bold;
        }

        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 16px;
        }

        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body onload="window.print()"> <!-- ✅ يتم الطباعة تلقائيًا -->

<div class="invoice-box">
    <!-- ✅ عنوان الصيدلية -->
    <h2>صيدلية الحياة</h2>
    <h4>فاتورة مبيعات</h4>

    <!-- ✅ بيانات الفاتورة -->
    <p><strong>رقم الفاتورة:</strong> <?= $sale['saleid'] ?></p>
    <p><strong>اسم العميل:</strong> <?= htmlspecialchars($sale['customer_name']) ?></p>
    <p><strong>التاريخ:</strong> <?= $sale['sale_date'] ?></p>

    <!-- ✅ جدول الأصناف -->
    <table>
        <thead>
            <tr>
                <th>اسم الدواء</th>
                <th>السعر</th>
                <th>الكمية</th>
                <th>المجموع</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['medicine_name']) ?></td>
                    <td><?= number_format($item['unit_price'], 2) ?> ريال</td>
                    <td><?= $item['quantity'] ?></td>
                    <td><?= number_format($item['unit_price'] * $item['quantity'], 2) ?> ريال</td>
                </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="3">الإجمالي</td>
                <td><?= number_format($sale['total_price'], 2) ?> ريال</td>
            </tr>
        </tbody>
    </table>

    <!-- ✅ أسفل الفاتورة -->
    <div class="footer">
        <p>شكرًا لتعاملكم معنا</p>
        <p>رقم التواصل: 777-777-777</p>
    </div>
</div>

<!-- ✅ زر للعودة -->
<div class="text-center no-print" style="margin-top: 20px;">
    <a href="sales.php" class="btn btn-secondary">🔙 الرجوع</a>
</div>

</body>
</html>

