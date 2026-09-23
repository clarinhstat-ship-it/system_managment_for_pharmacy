<?php
// 1. بدء الجلسة للتحقق من تسجيل الدخول
session_start();

// 2. التحقق من تسجيل الدخول
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

// 6. جلب قائمة الأدوية
$medicines = $pdo->prepare("SELECT id, name, selling_price FROM medicines")->fetchAll();

// 7. معالجة إنشاء فاتورة مبيعات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_sale'])) {
    $date = $_POST['date'];
    $total = 0;
    $items = [];
    
    // 7.1. جمع بيانات الأصناف
    foreach ($_POST['medicine_id'] as $index => $med_id) {
        if ($med_id && isset($_POST['quantity'][$index]) && $_POST['quantity'][$index] > 0) {
            $qty = (int)$_POST['quantity'][$index];
            $stmt = $pdo->prepare("SELECT id, name, selling_price, quantity FROM medicines WHERE id = ?");
            $stmt->execute([$med_id]);
            $med = $stmt->fetch();
            
            if (!$med || $med['quantity'] < $qty) {
                $error = "الكمية غير كافية للدواء: " . $med['name'];
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
    
    // 7.2. إنشاء الفاتورة إذا كانت البيانات صحيحة
    if (empty($error) && count($items) > 0) {
        try {
            // 7.3. بدء المعاملة (Transaction) لضمان سلامة البيانات
            $pdo->beginTransaction();
            
            // 7.4. إنشاء الفاتورة في جدول الفواتير
            $invoice_number = 'INV-' . time();
            $stmt = $pdo->prepare("INSERT INTO invoices3 (invoice_number, type, customer_id, total_amount, date) 
                                  VALUES (?, 'sale', ?, ?, ?)");
            $stmt->execute([
                $invoice_number,
                $customer_id,
                $total,
                $date
            ]);
            
            $invoice_id = $pdo->lastInsertId();
            
            // 7.5. إضافة تفاصيل الفاتورة
            foreach ($items as $item) {
                $stmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, medicine_id, quantity, unit_price, total_price) 
                                      VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $invoice_id,
                    $item['id'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['total_price']
                ]);
                
                // 7.6. تحديث كمية الدواء في المخزون
                $pdo->prepare("UPDATE medicines SET quantity = quantity - ? WHERE id = ?")
                    ->execute([$item['quantity'], $item['id']]);
            }
            
            // 7.7. إضافة معاملة للعميل (مدين)
            $stmt = $pdo->prepare("INSERT INTO customer_transactions 
                                  (customer_id, invoice_id, transaction_type, amount, description) 
                                  VALUES (?, ?, 'sale', ?, ?)");
            $stmt->execute([
                $customer_id,
                $invoice_id,
                $total,
                "فاتورة مبيعات رقم " . $invoice_number
            ]);
            
            // 7.8. تحديث رصيد العميل
            $stmt = $pdo->prepare("UPDATE customer_accounts SET balance = balance + ? WHERE customer_id = ?");
            $stmt->execute([$total, $customer_id]);
            
            // 7.9. إتمام المعاملة
            $pdo->commit();
            
            $success = "تم إنشاء فاتورة المبيعات بنجاح: $invoice_number";
            header("Location: customer_details.php?id=$customer_id&success=1");
            exit;
        } catch (PDOException $e) {
            // 7.10. التراجع عن التغييرات في حالة الخطأ
            $pdo->rollBack();
            error_log("خطأ في إنشاء فاتورة مبيعات: " . $e->getMessage());
            $error = "حدث خطأ أثناء حفظ الفاتورة";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>فاتورة مبيعات للعميل: <?= htmlspecialchars($customer['name']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
</head>
<body>
    <div class="container">
        <h2 class="page-title">فاتورة مبيعات للعميل: <?= htmlspecialchars($customer['name']) ?></h2>
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

        <!-- 8. نموذج إنشاء فاتورة مبيعات -->
        <div class="card">
            <div class="card-header">
                <h3>فاتورة مبيعات</h3>
            </div>
            <div class="card-body">
                <form method="POST" id="saleForm">
                    <div class="form-row" style="display: flex; gap: 15px; margin-bottom: 20px;">
                        <div class="form-group" style="flex: 1;">
                            <label>التاريخ *</label>
                            <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    
                    <h4>أصناف الفاتورة</h4>
                    <div id="items-container">
                        <div class="item-row" style="background: #f8fafc; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                            <div class="form-row" style="display: flex; gap: 15px;">
                                <div class="form-group" style="flex: 2;">
                                    <select name="medicine_id[]" class="form-control" required>
                                        <option value="">اختر دواء</option>
                                        <?php foreach ($medicines as $m): ?>
                                            <option value="<?= $m['id'] ?>" 
                                                    data-price="<?= $m['selling_price'] ?>">
                                                <?= htmlspecialchars($m['name']) ?> - 
                                                سعر البيع: <?= $m['selling_price'] ?> ج.م
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group" style="flex: 1;">
                                    <input type="number" name="quantity[]" class="form-control" 
                                           placeholder="الكمية" min="1" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" onclick="addSaleItem()" class="btn btn-outline-primary" style="margin-bottom: 20px;">
                        <i class="fas fa-plus"></i> إضافة صنف آخر
                    </button>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee;">
                        <h4 style="margin: 0;">إجمالي الفاتورة: <span id="total-amount">0.00</span> ج.م</h4>
                        <button type="submit" name="create_sale" class="btn btn-primary" style="padding: 10px 25px; font-size: 1.1rem;">
                            <i class="fas fa-save"></i> حفظ الفاتورة
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // 9. وظائف JavaScript لإدارة الفاتورة
        function addSaleItem() {
            const container = document.getElementById('items-container');
            const newRow = document.createElement('div');
            newRow.className = 'item-row';
            newRow.style = "background: #f8fafc; padding: 15px; border-radius: 6px; margin-bottom: 15px;";
            
            newRow.innerHTML = `
                <div class="form-row" style="display: flex; gap: 15px;">
                    <div class="form-group" style="flex: 2;">
                        <select name="medicine_id[]" class="form-control" required onchange="calculateTotal()">
                            <option value="">اختر دواء</option>
                            <?php foreach ($medicines as $m): ?>
                                <option value="<?= $m['id'] ?>" 
                                        data-price="<?= $m['selling_price'] ?>">
                                    <?= htmlspecialchars($m['name']) ?> - 
                                    سعر البيع: <?= $m['selling_price'] ?> ج.م
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group" style="flex: 1;">
                        <input type="number" name="quantity[]" class="form-control" 
                               placeholder="الكمية" min="1" required onchange="calculateTotal()">
                        <button type="button" onclick="removeSaleItem(this)" class="btn btn-danger btn-sm" 
                                style="margin-top: 5px; width: 100%;">
                            <i class="fas fa-trash"></i> إزالة
                        </button>
                    </div>
                </div>
            `;
            
            container.appendChild(newRow);
            calculateTotal();
        }
        
        function removeSaleItem(button) {
            const row = button.closest('.item-row');
            row.remove();
            calculateTotal();
        }
        
        function calculateTotal() {
            let total = 0;
            
            document.querySelectorAll('.item-row').forEach(row => {
                const select = row.querySelector('select');
                const quantityInput = row.querySelector('[name="quantity[]"]');
                
                if (select.value && quantityInput.value) {
                    const option = select.options[select.selectedIndex];
                    const price = parseFloat(option.dataset.price);
                    const quantity = parseInt(quantityInput.value);
                    
                    total += price * quantity;
                }
            });
            
            document.getElementById('total-amount').textContent = total.toFixed(2);
        }
        
        // 10. تحديث إجمالي الفاتورة عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            calculateTotal();
            
            // إضافة مستمع للأحداث لتحديث الإجمالي تلقائياً
            document.querySelectorAll('select, [name="quantity[]"]').forEach(element => {
                element.addEventListener('change', calculateTotal);
            });
        });
    </script>
    <!-- !-- ✅ أسفل الفاتورة -->
    <div class="footer">
        <p>شكرًا لتعاملكم معنا</p>
        <p>رقم التواصل: 777-777-777</p>
    </div>
</div>
</body>
</html>
