
<?php
// // 1. بدء الجلسة للتحقق من تسجيل الدخول
 session_start();

// // 2. التحقق من تسجيل الدخول
 if (!isset($_SESSION['userid'])) {
     header("Location: login.php");
     exit;
 }

// 3. تضمين ملف اتصال قاعدة البيانات
require_once 'db.php';

// 4. التحقق من وجود معرف العميل في الرابط
if (!isset($_GET['customer_id']) || !is_numeric($_GET['customer_id'])) {
    die("معرف العميل غير صحيح");
}

$customer_id = (int)$_GET['customer_id'];

// 5. جلب معلومات العميل
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

if (!$customer) {
    die("العميل غير موجود");
}
/*
// 6. جلب فواتير العميل لعرضها في قائمة منسدلة
$stmt = $pdo->prepare(" SELECT i.* 
    FROM invoices i
    WHERE i.customer_id = ? AND i.type = 'sale'
    ORDER BY i.date DESC
");
$stmt->execute([$customer_id]);
$invoices = $stmt->fetchAll();
*/
// 6. جلب فواتير العميل لعرضها في قائمة منسدلة
$stmt = $pdo->prepare(" SELECT i.* 
    FROM invoices3 i
    WHERE i.customer_id = ? AND i.type = 'sale'
    ORDER BY i.date DESC
");
$stmt->execute([$customer_id]);
$invoices = $stmt->fetchAll();

// 7. جلب الأدوية (سيتم تحديثها عند اختيار فاتورة)
$medicines = [];

// 8. معالجة إنشاء مرتجعات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_return'])) {
    $invoice_id = (int)$_POST['invoice_id'];
    $return_date = $_POST['return_date'];
    $total = 0;
    $items = [];
    
    // 8.1. جمع بيانات الأصناف المرتجعة
    foreach ($_POST['medicine_id'] as $index => $med_id) {
        if ($med_id && isset($_POST['quantity'][$index]) && $_POST['quantity'][$index] > 0) {
            $qty = (int)$_POST['quantity'][$index];
            $stmt = $pdo->prepare("SELECT id, name, selling_price FROM medicines WHERE id = ?");
            $stmt->execute([$med_id]);
            $med = $stmt->fetch();
            
            if (!$med) {
                $error = "الدواء المحدد غير موجود";
                break;
            }
            
            $unit_price = $med['selling_price'];
            $total_price = $unit_price * $qty;
            $total += $total_price;
            
            $items[] = [
                'id' => $med_id,
                'quantity' => $qty,
                'unit_price' => $unit_price,
                'total_price' => $total_price
            ];
        }
    }
    
    // 8.2. إنشاء مرتجعات إذا كانت البيانات صحيحة
    if (empty($error) && count($items) > 0) {
        try {
            // 8.3. بدء المعاملة (Transaction)
            $pdo->beginTransaction();
            
            // 8.4. إنشاء فاتورة مرتجعات
            $return_number = 'RET-' . time();
            $stmt = $pdo->prepare("INSERT INTO invoices (invoice_number, type, customer_id, total_amount, date) 
                                  VALUES (?, 'return', ?, ?, ?)");
            $stmt->execute([
                $return_number,
                $customer_id,
                $total,
                $return_date
            ]);
            
            $return_id = $pdo->lastInsertId();
            
            // 8.5. إضافة تفاصيل المرتجعات
            foreach ($items as $item) {
                $stmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, medicine_id, quantity, unit_price, total_price) 
                                      VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $return_id,
                    $item['id'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['total_price']
                ]);
                
                // 8.6. تحديث كمية الدواء في المخزون
                $pdo->prepare("UPDATE medicines SET quantity = quantity + ? WHERE id = ?")
                    ->execute([$item['quantity'], $item['id']]);
            }
            
            // 8.7. إضافة معاملة للعميل (دائن)
            $stmt = $pdo->prepare("INSERT INTO customer_transactions 
                                  (customer_id, invoice_id, transaction_type, amount, description) 
                                  VALUES (?, ?, 'return', ?, ?)");
            $stmt->execute([
                $customer_id,
                $return_id,
                $total,
                "مرتجعات من فاتورة " . $_POST['invoice_number']
            ]);
            
            // 8.8. تحديث رصيد العميل
            $stmt = $pdo->prepare("UPDATE customer_accounts SET balance = balance - ? WHERE customer_id = ?");
            $stmt->execute([$total, $customer_id]);
            
            // 8.9. إتمام المعاملة
            $pdo->commit();
            
            $success = "تم تسجيل المرتجعات بنجاح: $return_number";
            header("Location: customer_details.php?id=$customer_id&success=1");
            exit;
        } catch (PDOException $e) {
            // 8.10. التراجع عن التغييرات في حالة الخطأ
            $pdo->rollBack();
            error_log("خطأ في تسجيل مرتجعات: " . $e->getMessage());
            $error = "حدث خطأ أثناء حفظ البيانات";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>مرتجعات للعميل: <?= htmlspecialchars($customer['name']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script>
        // 9. وظيفة لجلب أدوية الفاتورة عند اختيار فاتورة
        function loadInvoiceItems() {
            const invoiceId = document.querySelector('[name="invoice_id"]').value;
            
            if (!invoiceId) return;
            
            // 9.1. إظهار مؤشر التحميل
            document.getElementById('items-container').innerHTML = 
                '<div style="text-align: center; padding: 20px;">جاري تحميل أدوية الفاتورة...</div>';
            
            // 9.2. جلب أدوية الفاتورة عبر AJAX
            fetch('get_invoice_items.php?invoice_id=' + invoiceId)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        document.getElementById('items-container').innerHTML = 
                            '<div class="alert alert-danger">' + data.error + '</div>';
                        return;
                    }
                    
                    let html = '';
                    
                    // 9.3. إنشاء حقول لكل دواء في الفاتورة
                    data.items.forEach(item => {
                        html += `
                        <div class="item-row" style="background: #f8fafc; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                            <input type="hidden" name="medicine_id[]" value="${item.medicine_id}">
                            
                            <div class="form-row" style="display: flex; gap: 15px;">
                                <div class="form-group" style="flex: 2;">
                                    <label>اسم الدواء</label>
                                    <div class="form-control-static">${item.medicine_name}</div>
                                </div>
                                
                                <div class="form-group" style="flex: 1;">
                                    <label>الكمية المباعة</label>
                                    <div class="form-control-static">${item.quantity}</div>
                                </div>
                                
                                <div class="form-group" style="flex: 1;">
                                    <label>الكمية المرتجعة</label>
                                    <input type="number" name="quantity[]" class="form-control" 
                                           value="0" min="0" max="${item.quantity}" required>
                                </div>
                            </div>
                        </div>
                        `;
                    });
                    
                    document.getElementById('items-container').innerHTML = html;
                })
                .catch(error => {
                    console.error('خطأ:', error);
                    document.getElementById('items-container').innerHTML = 
                        '<div class="alert alert-danger">حدث خطأ أثناء جلب بيانات الفاتورة</div>';
                });
        }
    </script>
</head>
<body>
    <div class="container">
        <h2 class="page-title">مرتجعات للعميل: <?= htmlspecialchars($customer['name']) ?></h2>
        <a href="customer_details.php?id=<?= $customer_id ?>" class="btn btn-outline-primary">العودة لتفاصيل العميل</a>

        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $success ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <!-- 10. نموذج مرتجعات -->
        <div class="card">
            <div class="card-header">
                <h3>مرتجعات</h3>
            </div>
            <div class="card-body">
                <form method="POST" id="returnForm">
                    <input type="hidden" name="invoice_number" id="invoice_number">
                    
                    <div class="form-row" style="display: flex; gap: 15px; margin-bottom: 20px;">
                        <div class="form-group" style="flex: 1;">
                            <label>اختر الفاتورة *</label>
                            <select name="invoice_id" class="form-control" required onchange="loadInvoiceItems()">
                                <option value="">اختر فاتورة مبيعات</option>
                                <?php foreach ($invoices as $inv): ?>
                                    <option value="<?= $inv['id'] ?>">
                                        <?= htmlspecialchars($inv['invoice_number']) ?> - 
                                        <?= htmlspecialchars($inv['date']) ?> - 
                                        <?= number_format($inv['total_amount'], 2) ?> ج.م
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group" style="flex: 1;">
                            <label>التاريخ *</label>
                            <input type="date" name="return_date" class="form-control" 
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    
                    <h4>أصناف المرتجعات</h4>
                    <div id="items-container" style="min-height: 100px;">
                        <div style="text-align: center; padding: 30px; color: #666;">
                            يرجى اختيار فاتورة لعرض أصنافها
                        </div>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee;">
                        <h4 style="margin: 0; color: #666;">سيتم عرض إجمالي المرتجعات هنا</h4>
                        <button type="submit" name="create_return" class="btn btn-primary" style="padding: 10px 25px; font-size: 1.1rem;">
                            <i class="fas fa-undo"></i> تسجيل المرتجعات
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
