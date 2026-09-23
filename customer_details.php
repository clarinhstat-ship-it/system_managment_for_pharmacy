

<?php
// 1. بدء الجلسة للتحقق من تسجيل الدخول
session_start();
/*
// 2. التحقق من تسجيل الدخول
if (!isset($_SESSION['userid'])) {
    header("Location: login.php");
    exit;
}
*/
require_once 'auth.php';
// 3. تضمين ملف اتصال قاعدة البيانات
require_once 'db.php';

// 4. التحقق من وجود معرف العميل في الرابط
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("معرف العميل غير صحيح");
}

$customer_id = (int)$_GET['id'];

try {
    // 5. جلب معلومات العميل الأساسية
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch();
    
    if (!$customer) {
        die("العميل غير موجود");
    }
    
    // 6. جلب رصيد العميل
    $stmt = $pdo->prepare("  SELECT balance 
        FROM customer_accounts 
        WHERE customer_id = ?
    ");
    $stmt->execute([$customer_id]);
    $account = $stmt->fetch();
    $balance = $account ? $account['balance'] : 0.00;
    
    // 7. جلب معاملات العميل الأخيرة
    $stmt = $pdo->prepare("SELECT customer_transactions.*, invoices.invoice_number, invoices.date 
        FROM customer_transactions customer_transactions
        LEFT JOIN invoices3 invoices ON customer_transactions.invoice_id = invoices.id
        WHERE customer_transactions.customer_id = ?
        ORDER BY customer_transactions.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$customer_id]);
    $transactions = $stmt->fetchAll();
    
    // 8. جلب فواتير العميل النشطة
    $stmt = $pdo->prepare(" SELECT invoices3.* 
        FROM invoices3 invoices3
        WHERE invoices3.customer_id = ? AND invoices3.type = 'sale'
        ORDER BY invoices3.date DESC
        LIMIT 10
    ");
    $stmt->execute([$customer_id]);
    $invoices = $stmt->fetchAll();
} catch (PDOException $e) {
    die("حدث خطأ في جلب بيانات العميل: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تفاصيل العميل: <?= htmlspecialchars($customer['name']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
</head>
<body>
    <div class="container">
        <h2 class="page-title">تفاصيل العميل</h2>
        <a href="customers.php" class="btn btn-outline-primary">العودة إلى قائمة العملاء</a>

        <!-- 9. معلومات العميل الأساسية -->
        <div class="card">
            <div class="card-header">
                <h3>معلومات العميل: <?= htmlspecialchars($customer['name']) ?></h3>
            </div>
            <div class="card-body">
                <div class="form-row" style="display: flex; gap: 15px; margin-bottom: 20px;">
                    <div class="form-group" style="flex: 1;">
                        <label>الهاتف</label>
                        <div class="form-control-static"><?= htmlspecialchars($customer['phone']) ?></div>
                    </div>
                    
                    <div class="form-group" style="flex: 1;">
                        <label>البريد الإلكتروني</label>
                        <div class="form-control-static"><?= htmlspecialchars($customer['email']) ?></div>
                    </div>
                    
                    <div class="form-group" style="flex: 1;">
                        <label>الحد الائتماني</label>
                        <div class="form-control-static"><?= number_format($customer['credit_limit'], 2) ?> ج.م</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>العنوان</label>
                    <div class="form-control-static"><?= nl2br(htmlspecialchars($customer['address'])) ?></div>
                </div>
                
                <!-- 10. رصيد العميل -->
                <div style="margin-top: 20px; padding: 15px; background: #f8fafc; border-radius: 8px;">
                    <h4 style="margin-bottom: 15px;">الحساب المالي</h4>
                    <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 200px;">
                            <div style="font-size: 1.4rem; font-weight: bold; color: <?= $balance > 0 ? '#e53e52' : '#38a169' ?>">
                                <?= number_format(abs($balance), 2) ?> ج.م
                            </div>
                            <div style="color: #666;">
                                <?= $balance > 0 ? 'مدين' : 'دائن' ?>
                            </div>
                        </div>
                        
                        <div style="flex: 1; min-width: 200px;">
                            <div style="font-weight: 600; margin-bottom: 5px;">الحد الائتماني</div>
                            <div><?= number_format($customer['credit_limit'], 2) ?> ج.م</div>
                        </div>
                        
                        <div style="flex: 1; min-width: 200px;">
                            <div style="font-weight: 600; margin-bottom: 5px;">الاستخدام</div>
                            <div>
                                <?php 
                                $usage = $customer['credit_limit'] > 0 ? 
                                    min(100, ($balance / $customer['credit_limit']) * 100) : 0;
                                ?>
                                <div style="background: #e2e8f0; height: 20px; border-radius: 10px; overflow: hidden;">
                                    <div style="background: <?= $usage > 80 ? '#e53e52' : ($usage > 50 ? '#dd6b20' : '#38a169') ?>;
                                          height: 100%; width: <?= $usage ?>%; text-align: center; line-height: 20px; color: white;">
                                        <?= round($usage) ?>%
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if($_SESSION['role']==='admin'|| $_SESSION['role']==='manager'):?>
                    <div style="margin-top: 20px; display: flex; gap: 10px;">
                        <a href="customer_payment.php?customer_id=<?= $customer_id ?>" class="btn btn-success">
                            <i class="fas fa-money-bill-wave"></i> سند قبض
                            <a href="customer_return.php?customer_id=<?= $customer_id ?>" class="btn btn-warning">
                                <i class="fas fa-undo"></i> مرتجعات
                            </a>
                            <?php endif;?>
                        </a>
                        <a href="customer_invoice.php?customer_id=<?= $customer_id ?>" class="btn btn-primary">
                            <i class="fas fa-file-invoice"></i> فاتورة مبيعات
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- 11. معاملات العميل الأخيرة -->
        <div class="card">
            <div class="card-header">
                <h3>المعاملات الأخيرة</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>التاريخ</th>
                                <th>النوع</th>
                                <th>رقم الفاتورة</th>
                                <th>المبلغ</th>
                                <th>الوصف</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $t): 
                                // 11.1. تحديد لون حسب نوع المعاملة
                                $type_class = '';
                                $type_text = '';
                                
                                switch ($t['transaction_type']) {
                                    case 'sale':
                                        $type_class = 'text-danger';
                                        $type_text = 'مبيعات';
                                        break;
                                    case 'payment':
                                        $type_class = 'text-success';
                                        $type_text = 'سند قبض';
                                        break;
                                    case 'return':
                                        $type_class = 'text-info';
                                        $type_text = 'مرتجعات';
                                        break;
                                    case 'cash_sale':
                                        $type_class = 'text-primary';
                                        $type_text = 'مبيعات نقدية';
                                        break;
                                }
                            ?>
                            <tr>
                                <td><?= date('Y-m-d H:i', strtotime($t['created_at'])) ?></td>
                                <td class="<?= $type_class ?>"><?= $type_text ?></td>
                                <td><?= $t['invoice_number'] ?: '-' ?></td>
                                <td><?= number_format($t['amount'], 2) ?> ج.م</td>
                                <td><?= htmlspecialchars($t['description']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 12. فواتير العميل -->
        <div class="card">
            <div class="card-header">
                <h3>الفواتير النشطة</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>رقم الفاتورة</th>
                                <th>التاريخ</th>
                                <th>المبلغ</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $inv): ?>
                            <tr>
                                <td><?= htmlspecialchars($inv['invoice_number']) ?></td>
                                <td><?= htmlspecialchars($inv['date']) ?></td>
                                <td><?= number_format($inv['total_amount'], 2) ?> ج.م</td>
                                <td>
                                    <?php 
                                    // 12.1. تحديد حالة الفاتورة (مدفوعة جزئياً، غير مدفوعة)
                                    $stmt = $pdo->prepare("
                                        SELECT SUM(amount) as total_paid 
                                        FROM customer_transactions 
                                        WHERE invoice_id = ? AND transaction_type IN ('payment', 'return')
                                    ");
                                    $stmt->execute([$inv['id']]);
                                    $payment = $stmt->fetch();
                                    $total_paid = $payment ? $payment['total_paid'] : 0;
                                    
                                    $remaining = $inv['total_amount'] - $total_paid;
                                    ?>
                                    
                                    <?php if ($remaining <= 0): ?>
                                        <span style="color: #38a169; font-weight: bold;">مدفوعة بالكامل</span>
                                    <?php elseif ($total_paid > 0): ?>
                                        <span style="color: #dd6b20;">مدفوعة جزئياً</span>
                                    <?php else: ?>
                                        <span style="color: #e53e52;">غير مدفوعة</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="invoice_details.php?id=<?= $inv['id'] ?>" class="btn btn-info btn-sm" target="_blank">
                                        <i class="fas fa-eye"></i> تفاصيل
                                    </a>
                                    <a href="invoice_print.php?id=<?= $inv['id'] ?>" class="btn btn-primary btn-sm" target="_blank">
                                        <i class="fas fa-print"></i> طباعة
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
