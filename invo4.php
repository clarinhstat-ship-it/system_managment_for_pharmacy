<?php
 session_start();
 if (!isset($_SESSION['userid'])) {
     header("Location: index.php");
     exit;
}
require_once 'db.php';

// إضافة فاتورة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_invoice'])) {
    $type = $_POST['type'];
    $customer_supplier = $_POST['customer_supplier'];
    $date = $_POST['date'];
    $total = 0;
    $items = [];

    foreach ($_POST['medicine_id'] as $index => $med_id) {
        if ($med_id && $_POST['quantity'][$index] > 0) {
            $qty = (int)$_POST['quantity'][$index];
            $stmt = $pdo->prepare("SELECT medicineid, name, description, price, expiredate, quantity,price_sales,dateproduction  FROM medicines WHERE medicineid = ?");
            // $stmt = $pdo->prepare("SELECT name,quantity FROM medicines WHERE medicineid = ?");
            $stmt->execute([$med_id]);
            $med = $stmt->fetch();
            

            if (!$med || $med['quantity'] < $qty) {
                $error = "الكمية غير كافية للدواء: " . $med['name'];
                continue;
            }

            $unit_price = $type === 'sale' ? $med['price_sales'] : $med['purchase_price'];
            $total_price = $unit_price * $qty;
            $total += $total_price;

            $items[] = [
                'id' => $med_id,
                'quantity' => $qty,
                'unit_price' => $unit_price,
                'total_price' => $total_price
            ];

            // تحديث الكمية
            $new_qty = $type === 'sale' ? $med['quantity'] - $qty : $med['quantity'] + $qty;
            $pdo->prepare("UPDATE medicines SET quantity = ? WHERE medicineid = ?")->execute([$new_qty, $med_id]);
        }
    }

    if (count($items) > 0) {
        $invoice_number = 'INV-' . time();
        $stmt = $pdo->prepare("INSERT INTO invoices3 (invoice_number, type, customer_supplier_name, total_amount, date) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$invoice_number, $type, $customer_supplier, $total, $date]);
        $invoice_id = $pdo->lastInsertId();

        foreach ($items as $item) {
            $stmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, medicine_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$invoice_id, $item['id'], $item['quantity'], $item['unit_price'], $item['total_price']]);
        }

        $success = "تم إنشاء الفاتورة بنجاح: $invoice_number";
    }
}

// $medicines = $pdo->prepare("SELECT name, description,price_sales, expiredate FROM medicines")->fetchAll();
$medicines = $pdo->query("SELECT*FROM medicines")->fetchAll();

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إنشاء فاتورة</title>
    <link rel="stylesheet" href="style.css">
    <script src="script.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">

    <style>
        /* body { direction: rtl; font-family: 'Cairo', sans-serif; background-color: #f9f9f9; }
        .form-box { max-width: 900px; margin: 40px auto; padding: 30px; background: white; border-radius: 12px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        */
       
        body { font-family: 'Tahoma'; direction: rtl; background: #f7f7f7; padding: 20px; }
        h2 { color: #333; }
        table { border-collapse: collapse; width: 100%; background: #fff; box-shadow: 0 0 10px #ccc; }
        th, td { padding: 10px; border: 1px solid #aaa; text-align: center; }
        form { margin-bottom: 20px; background: #fff; padding: 10px; border: 1px solid #ddd; }
        input[type=text], input[type=number] { padding: 6px; margin: 5px; width: 180px; }
        button { padding: 8px 12px; background: #28a745; color: white; border: none; cursor: pointer; }
        a { color: red; text-decoration: none; }


    
    </style>
</head>
<body>
    <div class="container">
        <h1 style="align: center"> صيدلة الشرية المركزية</h1>
        <h2>إنشاء فاتورة</h2>
        <!-- <a href="index.php">العودة للوحة التحكم</a> -->
        <a href="index.php" class="btn btn-secondary">رجوع إلى الرئيسية</a>
        <a href="invoice_all.php" class="btn btn-secondary">قائمةالفواتير</a>
        <a href="customers.php" class="btn btn-secondary">قائمة العملاء</a>


        <?php if (isset($success)): ?>
            <p class="success"><?php echo $success; ?></p>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>

        <form method="POST" id="invoiceForm">
            <select name="type" onchange="updateForm()" required>
                <option value="">نوع الفاتورة</option>
                <option value="purchase">شراء من مورد</option>
                <option value="sale">بيع لعميل</option>
            </select>

            <input type="text" name="customer_supplier" placeholder="اسم العميل/المورد" required>
            <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>

            <div id="items">
                <h3>الأصناف</h3>
                <!-- <div class="item-row">
                    <select name="medicine_id[]" required>
                        <option value="">اختر دواء</option>
                        <?php// foreach ($medicines as $m): ?>
                            <option value="<?php// echo $m['medicineid']; ?>" data-price="<?php// echo $m['price_sales']; ?>">
                                <?php// echo $m['name']; ?> - <?php //echo $m['price_sales']; ?> ج.م
                            </option>
                        <?php //endforeach; ?>
                    </select>
                    <input type="number" name="quantity[]" placeholder="الكمية" min="1" required>
                </div> -->
                <div class="item-row">
  <select name="medicine_id[]" required>
    <option value="">اختر دواء</option>
    <?php foreach ($medicines as $m): ?>
      <option 
        value="<?= htmlspecialchars($m['medicineid']) ?>"
        data-price="<?= htmlspecialchars($m['price_sales']) ?>" 
        data-label="<?= htmlspecialchars($m['name']) ?>"
      >
        <?= htmlspecialchars($m['name']) ?> - <?= htmlspecialchars($m['price_sales']) ?> ج.م
      </option>
    <?php endforeach; ?>
  </select>
  <input type="number" name="quantity[]" placeholder="الكمية" min="1" required>
  <!-- <input type="number" name="unit_price[]" placeholder="السعر" min="100" required> -->
</div>
            </div>

            <button type="button" onclick="addItem()">+ إضافة صنف</button>
            <button type="submit" name="create_invoice">إنشاء الفاتورة</button>
        </form>
    </div>
    <!-- جزء لعرض الفواتير المحفوظة -->
<h3 style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee;">سجل الفواتير</h3>

<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>رقم الفاتورة</th>
                <th>النوع</th>
                <th>العميل/المورد</th>
                <th>التاريخ</th>
                <th>المبلغ</th>
                <th>الإجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // جلب جميع الفواتير من قاعدة البيانات
            $stmt = $pdo->prepare("SELECT * FROM invoices3 ORDER BY date DESC, id DESC");
            $stmt->execute();
            $invoices = $stmt->fetchAll();
            if($invoices){echo "required all invoices";}
            else {echo "not invoices";}
            if (count($invoices) > 0):
                foreach ($invoices as $inv): ?>
                <tr>
                    <td><?= htmlspecialchars($inv['invoice_number']) ?></td>
                    <td><?= $inv['type'] == 'purchase' ? 'شراء' : 'بيع' ?></td>
                    <td><?= htmlspecialchars($inv['customer_supplier_name']) ?></td>
                    <td><?= htmlspecialchars($inv['date']) ?></td>
                    <td><?= number_format($inv['total_amount'], 2) ?> ج.م</td>
                    <td>
                        <a href="invoice_print.php?id=<?= $inv['id'] ?>" class="btn btn-primary btn-sm" target="_blank">
                            <i class="fas fa-print"></i> طباعة
                        </a>
                        <a href="invoice_items.php?id=<?= $inv['id'] ?>" class="btn btn-info btn-sm" style="margin-left: 5px;">
                      <i class="fas fa-eye"></i> تفاصيل
                      </a>

                        <a href="edit_invoice.php?id=<?= $inv['id'] ?>" class="btn btn-warning btn-sm" style="margin-right: 5px;">
                                                    <i class="fas fa-edit"></i> تعديل
                                                </a>
                    </td>
                </tr>
                <?php endforeach;
            else: ?>
                <tr>
                    <td colspan="6" class="text-center">لا توجد فواتير محفوظة</td>
                </tr>
            <?php endif; ?>
        </tbody>
     </table>
</div>
</body>
</html>
