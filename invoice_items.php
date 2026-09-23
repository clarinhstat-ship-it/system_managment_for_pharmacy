<?php
// 1. بدء الجلسة (Session) للتحقق من تسجيل الدخول
session_start();

// 2. التحقق من تسجيل الدخول - إذا لم يكن المستخدم مسجل دخوله، يتم تحويله لصفحة تسجيل الدخول
if (!isset($_SESSION['userid'])) {
    header("Location: index.php");
    exit;
}

// 3. تضمين ملف اتصال قاعدة البيانات
require_once 'db.php';

// 4. التحقق من وجود معرف الفاتورة في الرابط وصحته
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // 4.1. إذا لم يكن المعرف موجوداً أو ليس عدداً، نعرض رسالة خطأ ونوقف التنفيذ
    die("معرف الفاتورة غير صحيح. يرجى التأكد من الرابط المستخدم.");
}

// 5. تحويل معرف الفاتورة إلى رقم صحيح (Integer) لتجنب الثغرات الأمنية
$invoice_id = (int)$_GET['id'];

try {
    // 6. جلب معلومات الفاتورة الأساسية من جدول invoices
    // 6.1. إعداد الاستعلام لجلب الفاتورة مع معلومات المورد/العميل
    // $stmt = $pdo->prepare("
    //     SELECT i.*, s.name as supplier_name 
    //     FROM invoices i
    //     LEFT JOIN suppliers s ON i.customer_supplier_name = s.name
    //     WHERE i.id = ?
    // ");
    $stmt = $pdo->prepare("SELECT invoices3.*, suppliers.name AS supplier_name
        FROM invoices3
        LEFT JOIN suppliers ON invoices3.customer_supplier_name = suppliers.name
        WHERE invoices3.id = ?
    "); // (تعديل 2)
    
    // 6.2. تنفيذ الاستعلام مع معرف الفاتورة
    $stmt->execute([$invoice_id]);
    
    // 6.3. جلب البيانات في متغير
    $invoice = $stmt->fetch();
    if( $invoice){echo "foooooor";}
    else {echo "not fending the elemnet";}
    // 6.4. التحقق من وجود الفاتورة
    if (!$invoice) {
        die("الفاتورة غير موجودة. قد تكون قد تم حذفها.");
    }
    // if( $invoice) echo "yes invoice in database";
    // else echo "not find invoice in project";
    
    // 7. جلب تفاصيل الفاتورة من جدول invoice_items
    // 7.1. إعداد الاستعلام لجلب تفاصيل الفاتورة مع أسماء الأدوية
    // $stmt = $pdo->prepare("
    //     SELECT ii.*, m.name as medicine_name 
    //     FROM invoice_items ii
    //     JOIN medicines m ON ii.medicine_id = m.id
    //     WHERE ii.invoice_id = ?
    //     ORDER BY ii.id
    // ");
    $stmt = $pdo->prepare("SELECT invoice_items.*, medicines.name AS medicine_name 
             FROM invoice_items
             JOIN medicines ON invoice_items.medicine_id = medicines.medicineid
             WHERE invoice_items.invoice_id = ?
             ORDER BY invoice_items.id
         "); // (تعديل 6)
        
    // 7.2. تنفيذ الاستعلام مع معرف الفاتورة
    $stmt->execute([$invoice_id]);
    
    // 7.3. جلب جميع تفاصيل الفاتورة في مصفوفة
    $items = $stmt->fetchAll();
    
    // 7.4. التحقق من وجود تفاصيل للفاتورة
    if (empty($items)) {
        die("لا توجد تفاصيل للفاتورة. قد تكون البيانات تالفة.");
    }
} catch (PDOException $e) {
    // 8. التعامل مع الأخطاء التقنية
    // 8.1. تسجيل الخطأ في ملف السجلات (مهم للتصحيح)
    error_log("خطأ في جلب تفاصيل الفاتورة: " . $e->getMessage());
    
    // 8.2. عرض رسالة خطأ للمستخدم دون كشف تفاصيل تقنية
    die("حدث خطأ تقني. يرجى المحاولة لاحقاً أو الاتصال بالدعم الفني.");
}
?>
<?php

?>
<?php

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تفاصيل الفاتورة #<?= $invoice['invoice_number'] ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <style>
         /* 10. أنماط إضافية لصفحة التفاصيل */
        :root {
            --primary-color: #2c7be5;
            --secondary-color: #6c757d;
            --success-color: #00b894;
            --warning-color: #fdcb6e;
            --danger-color: #e74c3c;
            --light-bg: #f8fafc;
            --border-color: #e9ecef;
        }
        
        .invoice-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--primary-color);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-top: 20px;
        }
        
        .invoice-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .meta-item {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-right: 4px solid var(--primary-color);
            transition: transform 0.3s ease;
        }
        
        .meta-item:hover {
            transform: translateY(-5px);
        }
        
        .meta-label {
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 8px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .meta-value {
            font-size: 1.1rem;
            color: #2d3748;
            font-weight: 500;
        }
        
        .items-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 25px;
        }
        
        .section-header {
            background: linear-gradient(135deg, var(--primary-color), #1a56db);
            color: white;
            padding: 15px 20px;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th {
            background-color: var(--light-bg);
            padding: 15px 12px;
            text-align: center;
            font-weight: 600;
            color: var(--secondary-color);
            border-bottom: 2px solid var(--border-color);
        }
        
        .table td {
            padding: 12px;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }
        
        .table tbody tr:hover {
            background-color: rgba(44, 123, 229, 0.05);
        }
        
        .total-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-top: 25px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 1.1rem;
        }
        
        .total-row:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 1.3rem;
            color: var(--primary-color);
            margin-top: 10px;
            padding-top: 15px;
            border-top: 2px solid var(--border-color);
        }
        
        .amount-paid {
            color: var(--success-color);
            font-weight: bold;
        }
        
        .amount-remaining {
            color: var(--danger-color);
            font-weight: bold;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-warning {
            background: var(--warning-color);
            color: #2d3748;
        }
        
        .btn-success {
            background: var(--success-color);
            color: white;
        }
        
        .btn-outline-primary {
            background: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-partial {
            background: #cce7ff;
            color: #004085;
        }
        
        .pharmacy-info {
            text-align: center;
            margin-bottom: 10px;
        }
        
        .pharmacy-name {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .pharmacy-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        @media print {
            .no-print {
                display: none;
            }
            
            body {
                background: white !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .container {
                box-shadow: none !important;
                padding: 0 !important;
                max-width: 100% !important;
            }
            
            .invoice-header {
                background: #667eea !important;
                -webkit-print-color-adjust: exact;
            }
            
            .btn {
                display: none;
            }
        }
        
        @media (max-width: 768px) {
            .invoice-meta {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--secondary-color);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: var(--border-color);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- 10. رأس الصفحة -->
        <h2 class="page-title">تفاصيل الفاتورة</h2>
        <a href="invoice_all.php" class="btn btn-outline-primary">العودة إلى سجل الفواتير</a>

        <!-- 11. محتوى الفاتورة -->
        <div class="card">
            <div class="card-body">
                <!-- 11.1. رأس الفاتورة -->
                <div class="invoice-header">
                    <h1 style="color: #2c7be5; margin-bottom: 5px;">صيدلية الشرية المركزية</h1>
                    <div style="font-size: 1.2rem; margin-bottom: 15px;"><?= $invoice['type'] == 'purchase' ? 'فاتورة شراء' : 'فاتورة بيع' ?></div>
                    <div class="invoice-meta">
                        <div class="meta-item">
                            <div class="meta-label">رقم الفاتورة</div>
                            <div class="meta-value"><?= htmlspecialchars($invoice['invoice_number']) ?></div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">التاريخ</div>
                            <div class="meta-value"><?= htmlspecialchars($invoice['date']) ?></div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-label"><?= $invoice['type'] == 'purchase' ? 'المورد' : 'العميل' ?></div>
                            <div class="meta-value"><?= htmlspecialchars($invoice['customer_supplier_name']) ?></div>
                        </div>
                    </div>
                </div>

                <!-- 11.2. تفاصيل الأصناف -->
                <h3>تفاصيل الأصناف</h3>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>اسم الدواء</th>
                                <th>الكمية</th>
                                <th>سعر الوحدة</th>
                                <th>الإجمالي</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <!-- 11.2.1. رقم التسلسل -->
                                <td><?= $counter++ ?></td>
                                
                                <!-- 11.2.2. اسم الدواء -->
                                <td><?= htmlspecialchars($item['medicine_name']) ?></td>
                                
                                <!-- 11.2.3. الكمية -->
                                <td><?= $item['quantity'] ?></td>
                                
                                <!-- 11.2.4. سعر الوحدة -->
                                <td><?= number_format($item['unit_price'], 2) ?> ج.م</td>
                                
                                <!-- 11.2.5. الإجمالي الفرعي -->
                                <td><?= number_format($item['total_price'], 2) ?> ج.م</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- 11.3. ملخص الفاتورة -->
                <div class="total-section">
                    <div class="total-row">
                        <span>الإجمالي الفرعي:</span>
                        <span><?= number_format($invoice['total_amount'], 2) ?> ج.م</span>
                    </div>
                    <div class="total-row">
                        <!-- <span>الضريبة (15%):</span>
                        <span>< //number_format($invoice['total_amount'] * 0.15, 2) ?> ج.م</span> -->
                    </div>
                    <div class="total-row">
                        <span>المجموع الكلي:</span>
                        <span><?= number_format($invoice['total_amount'] ) ?> ج.م</span>
                    </div>
                </div>

                <!-- 11.4. أزرار الإجراءات -->
                <div class="action-buttons no-print">
                    <a href="invoice_print.php?id=<?= $invoice['id'] ?>" class="btn btn-primary" target="_blank">
                        <i class="fas fa-print"></i> طباعة الفاتورة
                    </a>
                    <a href="edit_invoice.php?id=<?= $invoice['id'] ?>" class="btn btn-warning">
                        <i class="fas fa-edit"></i> تعديل الفاتورة
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
