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
    // 6.1. إعداد الاستعلام لجلب الفاتورة مع معلومات المورد/العميل والمستخدم
    $stmt = $pdo->prepare(" SELECT 
    
            invoices3.*, 
            suppliers.name AS supplier_name,
            /*users.name AS created_by_user   */
        FROM invoices3
        /*LEFT JOIN suppliers ON invoices3.customer_supplier_name = suppliers.name*/
       /* LEFT JOIN users ON invoices3.created_by = users.userid*/
        WHERE invoices3.id = ?
        /*
        invoices3.*, suppliers.name AS supplier_name
        FROM invoices3
        LEFT JOIN suppliers ON invoices3.customer_supplier_name = suppliers.name
        WHERE invoices3.id = ?  */
    ");
    
    // 6.2. تنفيذ الاستعلام مع معرف الفاتورة
    $stmt->execute([$invoice_id]);
    
    // 6.3. جلب البيانات في متغير
    $invoice = $stmt->fetch();
    
    // 6.4. التحقق من وجود الفاتورة
    if (!$invoice) {
        die("الفاتورة غير موجودة. قد تكون قد تم حذفها.");
    }
    
    // 7. جلب تفاصيل الفاتورة من جدول invoice_items
    // 7.1. إعداد الاستعلام لجلب تفاصيل الفاتورة مع أسماء الأدوية
    $stmt = $pdo->prepare("SELECT 
            invoice_items.*, 
            medicines.name AS medicine_name,
            medicines.barcode AS barcode
        FROM invoice_items
        JOIN medicines ON invoice_items.medicine_id = medicines.medicineid
        WHERE invoice_items.invoice_id = ?
        ORDER BY invoice_items.id
    ");
        
    // 7.2. تنفيذ الاستعلام مع معرف الفاتورة
    $stmt->execute([$invoice_id]);
    
    // 7.3. جلب جميع تفاصيل الفاتورة في مصفوفة
    $items = $stmt->fetchAll();
    
    // 7.4. التحقق من وجود تفاصيل للفاتورة
    if (empty($items)) {
        $items = []; // جعل المصفوفة فارغة بدلاً من إيقاف البرنامج
    }
    
    // 8. حساب الإجماليات
    $subtotal = $invoice['total_amount'];
    $tax_amount = $subtotal * 0.15;
    $grand_total = $subtotal + $tax_amount;
    $remaining_amount = $grand_total - $invoice['paid_amount'];
    
} catch (PDOException $e) {
    // 9. التعامل مع الأخطاء التقنية
    // 9.1. تسجيل الخطأ في ملف السجلات (مهم للتصحيح)
    error_log("خطأ في جلب تفاصيل الفاتورة: " . $e->getMessage());
    
    // 9.2. عرض رسالة خطأ للمستخدم دون كشف تفاصيل تقنية
    die("حدث خطأ تقني. يرجى المحاولة لاحقاً أو الاتصال بالدعم الفني.".$e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تفاصيل الفاتورة #<?= $invoice['invoice_number'] ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        <!-- 11. رأس الصفحة -->
        <div class="action-buttons no-print" style="justify-content: space-between; margin-top: 20px;">
            <a href="invoce.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-right"></i> العودة إلى سجل الفواتير
            </a>
            <h2 class="page-title" style="margin: 0; color: var(--primary-color);">تفاصيل الفاتورة</h2>
            <div style="width: 120px;"></div> <!-- عنصر فارغ للمحاذاة -->
        </div>

        <!-- 12. محتوى الفاتورة -->
        <div class="card">
            <div class="card-body">
                <!-- 12.1. رأس الفاتورة -->
                <div class="invoice-header">
                    <div class="pharmacy-info">
                        <div class="pharmacy-name">صيدلية الشرية المركزية</div>
                        <div class="pharmacy-subtitle">نضمن لكم الجودة والثقة</div>
                    </div>
                    <div style="font-size: 1.4rem; margin: 15px 0; font-weight: bold;">
                        <?= $invoice['type'] == 'purchase' ? 'فاتورة شراء' : 'فاتورة بيع' ?>
                    </div>
                </div>

                <!-- 12.2. معلومات الفاتورة الأساسية -->
                <div class="section-header">
                    <i class="fas fa-info-circle"></i> معلومات الفاتورة الأساسية
                </div>
                <div class="invoice-meta">
                    <div class="meta-item">
                        <div class="meta-label"><i class="fas fa-hashtag"></i> رقم الفاتورة</div>
                        <div class="meta-value"><?= htmlspecialchars($invoice['invoice_number']) ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label"><i class="fas fa-calendar-alt"></i> تاريخ الفاتورة</div>
                        <div class="meta-value"><?= htmlspecialchars($invoice['date']) ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">
                            <i class="fas fa-user-tag"></i> 
                            <?= $invoice['type'] == 'purchase' ? 'اسم المورد' : 'اسم العميل' ?>
                        </div>
                        <div class="meta-value"><?= htmlspecialchars($invoice['customer_supplier_name']) ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label"><i class="fas fa-user"></i> منشئ الفاتورة</div>
                        <div class="meta-value"><?= htmlspecialchars($invoice['created_by_user'] ?? 'غير محدد') ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label"><i class="fas fa-file-invoice"></i> نوع الفاتورة</div>
                        <div class="meta-value">
                            <span class="status-badge <?= $invoice['type'] == 'purchase' ? 'status-pending' : 'status-completed' ?>">
                                <?= $invoice['type'] == 'purchase' ? 'شراء' : 'بيع' ?>
                            </span>
                        </div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label"><i class="fas fa-clock"></i> وقت الإنشاء</div>
                        <div class="meta-value"><?= htmlspecialchars($invoice['created_at'] ?? 'غير محدد') ?></div>
                    </div>
                </div>

                <!-- 12.3. تفاصيل الأصناف -->
                <div class="items-section">
                    <div class="section-header">
                        <i class="fas fa-list-ul"></i> تفاصيل الأصناف
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>اسم الدواء</th>
                                    <th>الباركود</th>
                                    <th>الكمية</th>
                                    <th>سعر الوحدة</th>
                                    <th>الإجمالي</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($items)): ?>
                                    <?php $counter = 1; ?>
                                    <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><?= $counter++ ?></td>
                                        <td><?= htmlspecialchars($item['medicine_name']) ?></td>
                                        <td><?= htmlspecialchars($item['barcode'] ?? 'غير متوفر') ?></td>
                                        <td><?= number_format($item['quantity']) ?></td>
                                        <td><?= number_format($item['unit_price'], 2) ?> ج.م</td>
                                        <td><?= number_format($item['total_price'], 2) ?> ج.م</td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6">
                                            <div class="empty-state">
                                                <i class="fas fa-box-open"></i>
                                                <p>لا توجد أصناف في هذه الفاتورة</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 12.4. ملخص الفاتورة -->
                <div class="total-section">
                    <div class="section-header" style="border-radius: 8px 8px 0 0; margin: -25px -25px 25px -25px;">
                        <i class="fas fa-calculator"></i> ملخص الفاتورة
                    </div>
                    <div class="total-row">
                        <span>الإجمالي الفرعي:</span>
                        <span><?= number_format($subtotal, 2) ?> ج.م</span>
                    </div>
                    <div class="total-row">
                        <span>الضريبة (15%):</span>
                        <span><?= number_format($tax_amount, 2) ?> ج.م</span>
                    </div>
                    <div class="total-row">
                        <span>المبلغ المدفوع:</span>
                        <span class="amount-paid"><?= number_format($invoice['paid_amount'] ?? 0, 2) ?> ج.م</span>
                    </div>
                    <div class="total-row">
                        <span>المبلغ المتبقي:</span>
                        <span class="amount-remaining"><?= number_format($remaining_amount, 2) ?> ج.م</span>
                    </div>
                    <div class="total-row">
                        <span>المجموع الكلي:</span>
                        <span><?= number_format($grand_total, 2) ?> ج.م</span>
                    </div>
                </div>

                <!-- 12.5. حالة الدفع -->
                <div class="meta-item" style="margin-top: 20px; text-align: center;">
                    <div class="meta-label"><i class="fas fa-credit-card"></i> حالة الدفع</div>
                    <div class="meta-value">
                        <?php
                        $paid_amount = $invoice['paid_amount'] ?? 0;
                        if ($paid_amount == 0) {
                            $status_class = 'status-pending';
                            $status_text = 'غير مدفوع';
                        } elseif ($paid_amount < $grand_total) {
                            $status_class = 'status-partial';
                            $status_text = 'مدفوع جزئياً';
                        } else {
                            $status_class = 'status-completed';
                            $status_text = 'مدفوع بالكامل';
                        }
                        ?>
                        <span class="status-badge <?= $status_class ?>" style="font-size: 1.1rem; padding: 8px 20px;">
                            <?= $status_text ?>
                        </span>
                    </div>
                </div>

                <!-- 12.6. أزرار الإجراءات -->
                <div class="action-buttons no-print">
                    <a href="invoice_print.php?id=<?= $invoice['id'] ?>" class="btn btn-primary" target="_blank">
                        <i class="fas fa-print"></i> طباعة الفاتورة
                    </a>
                    <a href="edit_invoice.php?id=<?= $invoice['id'] ?>" class="btn btn-warning">
                        <i class="fas fa-edit"></i> تعديل الفاتورة
                    </a>
                    <a href="payment_invoice.php?id=<?= $invoice['id'] ?>" class="btn btn-success">
                        <i class="fas fa-money-bill-wave"></i> تسديد دفعة
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 13. إضافة تفاعلية بسيطة للصفحة
        document.addEventListener('DOMContentLoaded', function() {
            // إضافة تأثيرات للعناصر عند التمرير
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);
            
            // تطبيق التأثير على العناصر
            const animatedElements = document.querySelectorAll('.meta-item, .items-section, .total-section');
            animatedElements.forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(el);
            });
        });
    </script>
</body>
</html>
