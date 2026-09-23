<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
require_once 'db.php';

// التحقق من وجود معرف الفاتورة
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("معرف الفاتورة غير صحيح");
}

$invoice_id = (int)$_GET['id'];

// جلب بيانات الفاتورة
try {
    $stmt = $pdo->prepare("SELECT * FROM invoices3 WHERE id = ?");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch();
    
    if (!$invoice) {
        die("الفاتورة غير موجودة");
    }
    
    // جلب تفاصيل الفاتورة
    $stmt = $pdo->prepare(" SELECT invoice_items.*, medicines.name 
                       FROM invoice_items 
                       JOIN medicines ON invoice_items.medicine_id = medicines.medicineid
                       WHERE invoice_items.invoice_id = :invoice_id");

    $stmt->execute([$invoice_id]);
    $items = $stmt->fetchAll();
    // // جلب تفاصيل الفاتورة
    // $stmt = $pdo->prepare("SELECT i.*, m.name FROM invoice_items i 
    //                       JOIN medicines m ON i.medicine_id = m.id
    //                       WHERE i.invoice_id = ?");
    // $stmt->execute([$invoice_id]);
    // $items = $stmt->fetchAll();
    
    // جلب قائمة الأدوية
    // $medicines = $pdo->parpare("SELECT medicineid, name, description, price, expiredate, quantity,price_sales,dateproduction FROM medicines")->fetchAll();
    $medicines = $pdo->query("SELECT id, name, selling_price, purchase_price FROM medicines")->fetchAll();
} catch (PDOException $e) {
    echo("حدث خطأ تقني: " . $e->getMessage());
}

// معالجة حفظ التعديلات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_invoice'])) {
    $customer_supplier = trim($_POST['customer_supplier']);
    $date = $_POST['date'];
    $total = 0;
    $items_to_update = [];
    
    // جمع البيانات الجديدة
    foreach ($_POST['item_id'] as $index => $item_id) {
        $med_id = (int)$_POST['medicine_id'][$index];
        $qty = (int)$_POST['quantity'][$index];
        
        if ($med_id && $qty > 0) {
            $stmt = $pdo->prepare("SELECT id, name, price_sales, price, quantity FROM medicines WHERE id = ?");
            $stmt->execute([$med_id]);
            $med = $stmt->fetch();
            
            if (!$med) continue;
            
            $unit_price = $invoice['type'] === 'sale' ? $med['selling_price'] : $med['purchase_price'];
            $total_price = $unit_price * $qty;
            $total += $total_price;
            
            $items_to_update[] = [
                'id' => $item_id,
                'medicine_id' => $med_id,
                'quantity' => $qty,
                'unit_price' => $unit_price,
                'total_price' => $total_price
            ];
        }
    }
    
    if (count($items_to_update) > 0) {
        try {
            // بدء المعاملة (Transaction)
            $pdo->beginTransaction();
            
            // 1. تحديث الفاتورة الأساسية
            $stmt = $pdo->prepare("UPDATE invoices3 SET customer_supplier_name = ?, total_amount = ?, date = ? 
                                  WHERE id = ?");
            $stmt->execute([
                $customer_supplier,
                $total,
                $date,
                $invoice_id
            ]);
            
            // 2. حذف التفاصيل القديمة
            $stmt = $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
            $stmt->execute([$invoice_id]);
            
            // 3. إضافة التفاصيل الجديدة
            foreach ($items_to_update as $item) {
                $stmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, medicine_id, quantity, unit_price, total_price) 
                                      VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $invoice_id,
                    $item['medicine_id'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['total_price']
                ]);
            }
            
            // 4. تحديث كمية الأدوية في المخزون
            // أولاً: استعادة الكمية الأصلية
            foreach ($items as $original_item) {
                if ($invoice['type'] === 'sale') {
                    $pdo->prepare("UPDATE medicines SET quantity = quantity + ? WHERE id = ?")
                        ->execute([$original_item['quantity'], $original_item['medicine_id']]);
                } else {
                    $pdo->prepare("UPDATE medicines SET quantity = quantity - ? WHERE id = ?")
                        ->execute([$original_item['quantity'], $original_item['medicine_id']]);
                }
            }
            
            // ثانياً: تطبيق الكميات الجديدة
            foreach ($items_to_update as $new_item) {
                if ($invoice['type'] === 'sale') {
                    $pdo->prepare("UPDATE medicines SET quantity = quantity - ? WHERE id = ?")
                        ->execute([$new_item['quantity'], $new_item['medicine_id']]);
                } else {
                    $pdo->prepare("UPDATE medicines SET quantity = quantity + ? WHERE id = ?")
                        ->execute([$new_item['quantity'], $new_item['medicine_id']]);
                }
            }
            
            // إتمام المعاملة
            $pdo->commit();
            
            // العودة إلى صفحة الفواتير مع رسالة نجاح
            header("Location: invoices.php?success=1");
            exit;
        } catch (PDOException $e) {
            // التراجع عن التغييرات في حالة الخطأ
            $pdo->rollBack();
            error_log("خطأ في تعديل الفاتورة: " . $e->getMessage());
            $error = "حدث خطأ أثناء تحديث الفاتورة";
        }
    } else {
        $error = "يرجى إضافة صنف واحد على الأقل للفاتورة";
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تعديل فاتورة</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>
<body>
    <div class="container">
        <h2 class="page-title">تعديل فاتورة <?= $invoice['type'] == 'sale' ? 'بيع' : 'شراء' ?></h2>
        <a href="invoices.php" class="btn btn-outline-primary">العودة إلى سجل الفواتير</a>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3>تعديل الفاتورة: <?= htmlspecialchars($invoice['invoice_number']) ?></h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="update_invoice" value="1">
                    
                    <div class="form-row" style="display: flex; gap: 15px; margin-bottom: 20px;">
                        <div class="form-group" style="flex: 2;">
                            <label>اسم العميل/المورد *</label>
                            <input type="text" name="customer_supplier" class="form-control" 
                                   value="<?= htmlspecialchars($invoice['customer_supplier_name']) ?>" required>
                        </div>
                        
                        <div class="form-group" style="flex: 1;">
                            <label>التاريخ *</label>
                            <input type="date" name="date" class="form-control" 
                                   value="<?= $invoice['date'] ?>" required>
                        </div>
                    </div>
                    
                    <h4>أصناف الفاتورة</h4>
                    <div id="items-container">
                        <?php foreach ($items as $index => $item): ?>
                        <div class="item-row" style="background: #f8fafc; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                            <input type="hidden" name="item_id[]" value="<?= $item['id'] ?>">
                            
                            <div class="form-row" style="display: flex; gap: 15px;">
                                <div class="form-group" style="flex: 2;">
                                    <select name="medicine_id[]" class="form-control" required>
                                        <option value="">اختر دواء</option>
                                        <?php foreach ($medicines as $m): ?>
                                            <option value="<?= $m['id'] ?>" 
                                                <?= $item['medicine_id'] == $m['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($m['name']) ?> - 
                                                سعر <?= $invoice['type'] == 'sale' ? 'بيع' : 'شراء' ?>: 
                                                <?= $invoice['type'] == 'sale' ? $m['selling_price'] : $m['purchase_price'] ?> ج.م
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group" style="flex: 1;">
                                    <input type="number" name="quantity[]" class="form-control" 
                                           value="<?= $item['quantity'] ?>" placeholder="الكمية" min="1" required>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <button type="button" onclick="addInvoiceItem()" class="btn btn-outline-primary" style="margin-bottom: 20px;">
                        <i class="fas fa-plus"></i> إضافة صنف آخر
                    </button>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee;">
                        <h4 style="margin: 0;">إجمالي الفاتورة: <span id="total-amount"><?= number_format($invoice['total_amount'], 2) ?></span> ج.م</h4>
                        <button type="submit" class="btn btn-primary" style="padding: 10px 25px; font-size: 1.1rem;">
                            <i class="fas fa-save"></i> حفظ التعديلات
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function addInvoiceItem() {
            const container = document.getElementById('items-container');
            const newRow = document.createElement('div');
            newRow.className = 'item-row';
            newRow.style = "background: #f8fafc; padding: 15px; border-radius: 6px; margin-bottom: 15px;";
            
            newRow.innerHTML = `
                <div class="form-row" style="display: flex; gap: 15px;">
                    <div class="form-group" style="flex: 2;">
                        <select name="medicine_id[]" class="form-control" required>
                            <option value="">اختر دواء</option>
                            <?php foreach ($medicines as $m): ?>
                                <option value="<?= $m['id'] ?>">
                                    <?= htmlspecialchars($m['name']) ?> - 
                                    سعر <?= $invoice['type'] == 'sale' ? 'بيع' : 'شراء' ?>: 
                                    <?= $invoice['type'] == 'sale' ? $m['selling_price'] : $m['purchase_price'] ?> ج.م
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group" style="flex: 1;">
                        <input type="number" name="quantity[]" class="form-control" placeholder="الكمية" min="1" required>
                        <button type="button" onclick="removeInvoiceItem(this)" class="btn btn-danger btn-sm" style="margin-top: 5px; width: 100%;">
                            <i class="fas fa-trash"></i> إزالة
                        </button>
                    </div>
                </div>
            `;
            
            container.appendChild(newRow);
            calculateTotal();
        }
        
        function removeInvoiceItem(button) {
            const row = button.closest('.item-row');
            row.remove();
            calculateTotal();
        }
        
        function calculateTotal() {
            let total = 0;
            const type = "<?= $invoice['type'] ?>";
            
            document.querySelectorAll('.item-row').forEach(row => {
                const select = row.querySelector('select');
                const quantityInput = row.querySelector('[name="quantity[]"]');
                
                if (select.value && quantityInput.value) {
                    const option = select.options[select.selectedIndex];
                    const priceText = option.textContent;
                    const priceMatch = priceText.match(/سعر (بيع|شراء): ([\d.]+)/);
                    
                    if (priceMatch) {
                        const price = parseFloat(priceMatch[2]);
                        const quantity = parseInt(quantityInput.value);
                        total += price * quantity;
                    }
                }
            });
            
            document.getElementById('total-amount').textContent = total.toFixed(2);
        }
        
        // تحديث إجمالي الفاتورة عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            calculateTotal();
            
            // إضافة مستمع للأحداث لتحديث الإجمالي تلقائياً
            document.querySelectorAll('select, [name="quantity[]"]').forEach(element => {
                element.addEventListener('change', calculateTotal);
            });
        });
    </script>
</body>
</html>
