<?php
// بدء الجلسة والتحقق من تسجيل الدخول
session_start(); // بدء الجلسة (مطلوب لاستعمال $_SESSION)

// إن لم يكن المستخدم مسجلًا أعد توجيهه لصفحة الدخول
if (!isset($_SESSION['userid'])) {
    header("Location: login.php");
    exit;
}

// تضمين ملف الاتصال بقاعدة البيانات - يجب أن يُعرّف $pdo ككائن PDO
require_once 'db.php'; // تأكد أن db.php يعرّف $pdo (PDO) بشكل صحيح

// التحقق من معرف الفاتورة الوارد عبر GET
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "معرف الفاتورة غير صحيح.";
    exit;
}
$invoice_id = (int) $_GET['id']; // تحويل آمن لـ int

// كتلة try/catch للتعامل مع أخطاء PDO
try {
    // جلب بيانات الفاتورة الأساسية من جدول invoices3
    $stmt = $pdo->prepare("SELECT * FROM invoices3 WHERE id = ?");
    if (!$stmt->execute([$invoice_id])) {
        $err = $stmt->errorInfo();
        throw new Exception("خطأ في تنفيذ استعلام الفاتورة: " . ($err[2] ?? 'غير معروف'));
    }
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC); // صف الفاتورة كمصفوفة اسمية

    if (!$invoice) {
        echo "الفاتورة غير موجودة.";
        exit;
    }

    // جلب عناصر الفاتورة (invoice_items) مع بيانات الدواء الأساسية من table medicines
    // نرجع الأعمدة الهامة: id, invoice_id, medicine_id, quantity, unit_price, total_price
    // ونعيد من medicines الحقول: medicineid,name,selling_price,purchase_price,quantity
    $stmt = $pdo->prepare("SELECT invoice_items.id, invoice_items.invoice_id,
     invoice_items.medicine_id, invoice_items.quantity,
      invoice_items.unit_price, invoice_items.total_price,
               m.medicineid AS med_medicineid, m.name AS medicine_name,
              /* m.price_sales AS price_sales, m.price AS price*/
               m.price_sales AS price_sales, m.price AS price
        FROM invoice_items invoice_items
        JOIN medicines m ON invoice_items.medicine_id = m.medicineid
        WHERE invoice_items.invoice_id = ?
        ORDER BY invoice_items.id
    ");
    if (!$stmt->execute([$invoice_id])) {
        $err = $stmt->errorInfo();
        throw new Exception("خطأ في استعلام تفاصيل الفاتورة: " . ($err[2] ?? 'غير معروف'));
    }
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC); // جميع عناصر الفاتورة كمصفوفة اسمية

    // جلب قائمة الأدوية لاستخدامها في select (سوف نستخدم selling_price و purchase_price)
    // *تأكد أن أسماء الأعمدة هنا تطابق جدولك* (selling_price, purchase_price)
    $medStmt = $pdo->query("SELECT medicineid AS id, name, price_sales, price, quantity FROM medicines");
    $medicines = $medStmt->fetchAll(PDO::FETCH_ASSOC);

    // بناء HTML لخيارات الـ <option> في PHP ليتم تمريره بأمان لجافاسكربت
    $medicineOptionsHtml = '';
    // نوع الفاتورة الحالي (sale أو purchase) — عرض افتراضي إن لم يكن المعطى في DB
    $invoice_type = $invoice['type'] ?? 'sale';
    foreach ($medicines as $m) {
        // تأكد من توفر قيم الأسعار — إذا لم تتوفر استخدم 0 كقيمة افتراضية لتجنب Notices
        $sell = isset($m['price_sales']) ? number_format((float)$m['price_sales'], 2) : number_format(0,2);
        $buy  = isset($m['price']) ? number_format((float)$m['price'], 2) : number_format(0,2);
        // نص السعر لعرضه في الخيار (نضع الرقم في نهاية النص لسهولة الاستخراج بالـ JS إن احتجنا)
        $priceText = ($invoice_type === 'sale') ? $sell : $buy;
        // نص الخيار: نعرض الاسم ثم السعر (بدون كتابة HTML خطير)
        $medicineOptionsHtml .= '<option data-sell="'.htmlspecialchars($sell).'" data-buy="'.htmlspecialchars($buy).'" value="' . htmlspecialchars($m['id']) . '">' .
                                htmlspecialchars($m['name']) . ' - سعر ' . ($invoice_type === 'sale' ? 'بيع' : 'شراء') . ': ' . $priceText .
                                '</option>';
    }

} catch (Exception $e) {
    // سجل الخطأ للمطور وأعط المستخدم رسالة عامة (لا تكشف معلومات حساسة)
    error_log("خطأ تقني في edit_invoice.php: " . $e->getMessage());
    echo "حدث خطأ تقني. الرجاء المحاولة لاحقاً.".$e->getMessage();
    exit;
}

// =====================
// معالجة حفظ التعديلات عند POST
// =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_invoice'])) {
    // جلب حقول الفاتورة بأمان (استخدام null-coalescing لتجنب Notices)
    $customer_supplier = trim($_POST['customer_supplier'] ?? '');
    $date = $_POST['date'] ?? date('Y-m-d');

    // جمع حقول الأصناف المرسلة (كلها كمصفوفات)
    $posted_item_ids = $_POST['item_id'] ?? [];
    $posted_medicine_ids = $_POST['medicine_id'] ?? [];
    $posted_quantities = $_POST['quantity'] ?? [];

    $total = 0.0; // إجمالي جديد سيتم حسابه
    $items_to_update = []; // مصفوفة لحفظ العناصر الصحيحة

    // حلقة على العناصر المرسلة — نستخدم طول medicine_ids (آمنة لأنها المرجعية)
    for ($i = 0; $i < count($posted_medicine_ids); $i++) {
        $item_id = $posted_item_ids[$i] ?? null;
        $med_id = (int) ($posted_medicine_ids[$i] ?? 0);
        $qty = (int) ($posted_quantities[$i] ?? 0);

        // نتجاهل العناصر ذات معرف دواء صفر أو كمية صفر
        if ($med_id <= 0 || $qty <= 0) continue;

        // جلب بيانات الدواء الحالي من DB (سعر الشراء/البيع والكمية)
        $stmt = $pdo->prepare("SELECT medicineid AS id, name, price_sales, price, quantity FROM medicines WHERE medicineid = ?");
        $stmt->execute([$med_id]);
        $med = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$med) continue; // إذا الدواء غير موجود نكمل للعنصر التالي

        // اختيار السعر حسب نوع الفاتورة (sale => selling_price ، else => purchase_price)
        // نستخدم null-coalescing لضمان وجود قيمة رقمية
        $unit_price = ($invoice_type === 'sale') ? ((float)($med['price_sales'] ?? 0.0)) : ((float)($med['price'] ?? 0.0));
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

    // إذا لم تتوفر عناصر صالحة نعطي رسالة خطأ
    if (count($items_to_update) === 0) {
        $error = "يرجى إضافة صنف واحد على الأقل مع كمية صحيحة.";
    } else {
        // تنفيذ التحديثات داخل معاملة
        try {
            $pdo->beginTransaction();

            // 1) تحديث بيانات الفاتورة الأساسية
            $updateInvoice = $pdo->prepare("UPDATE invoices3 SET customer_supplier_name = ?, total_amount = ?, date = ? WHERE id = ?");
            $updateInvoice->execute([$customer_supplier, $total, $date, $invoice_id]);

            // 2) حذف عناصر الفاتورة القديمة (نعيد إدخال العناصر المرسلة)
            $del = $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
            $del->execute([$invoice_id]);

            // 3) إدخال العناصر الجديدة
            $ins = $pdo->prepare("INSERT INTO invoice_items (invoice_id, medicine_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");
            foreach ($items_to_update as $it) {
                $ins->execute([$invoice_id, $it['medicine_id'], $it['quantity'], $it['unit_price'], $it['total_price']]);
            }

            // 4) تحديث مخزون الأدوية:
            //    - أولاً نعيد المخزون كما كان قبل التعديل (بناء على $items الأصلي الذي جلبناه عند بداية الصفحة)
            //    - ثم نطبّق تغييرات الكميات الجديدة (من $items_to_update)
            // ملاحظة: منطق الإضافة/الطرح يعتمد على نوع الفاتورة (sale => إنقاص عند البيع، شراء => زيادة عند الشراء)
            foreach ($items as $original_item) {
                $orig_med = $original_item['medicine_id'] ?? null;
                $orig_qty = (int) ($original_item['quantity'] ?? 0);
                if (!$orig_med || $orig_qty <= 0) continue;

                if ($invoice_type === 'sale') {
                    // لو كانت فاتورة بيع، الكمية الأصلية تم خصمها من المخزون سابقًا، لذا نعيدها
                    $pdo->prepare("UPDATE medicines SET quantity = quantity + ? WHERE medicineid = ?")
                        ->execute([$orig_qty, $orig_med]);
                } else {
                    // لو كانت فاتورة شراء، الكمية الأصلية أضيفت سابقًا، لذا نخصمها
                    $pdo->prepare("UPDATE medicines SET quantity = quantity - ? WHERE medicineid = ?")
                        ->execute([$orig_qty, $orig_med]);
                }
            }

            // الآن نطبّق الكميات الجديدة
            foreach ($items_to_update as $new_item) {
                $medid = $new_item['medicine_id'];
                $qty = $new_item['quantity'];
                if ($invoice_type === 'sale') {
                    // بيع => نخصم من المخزون
                    $pdo->prepare("UPDATE medicines SET quantity = quantity - ? WHERE medicineid = ?")
                        ->execute([$qty, $medid]);
                } else {
                    // شراء => نزيد المخزون
                    $pdo->prepare("UPDATE medicines SET quantity = quantity + ? WHERE medicineid = ?")
                        ->execute([$qty, $medid]);
                }
            }

            $pdo->commit();

            // إعادة التوجيه بعد الحفظ بنجاح (صححنا اسم الصفحة إلى invoices.php)
            header("Location: invoice_all.php?success=1");
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("خطأ في تعديل الفاتورة: " . $e->getMessage());
            $error = "حدث خطأ أثناء حفظ التعديلات. حاول مرة أخرى.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تعديل فاتورة</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <style>
        /* تنسيقات بسيطة مدمجة لتجنب اعتماد كامل على css خارجي */
        .container{ max-width:1000px; margin:20px auto; padding:15px; }
        .form-row{ display:flex; gap:15px; }
        .form-group{ flex:1; }
        .item-row{ background:#f8fafc; padding:15px; border-radius:6px; margin-bottom:15px; }
        .btn{ padding:8px 12px; cursor:pointer; }
        .btn-primary{ background:#007bff; color:#fff; border:none; }
        .btn-outline-primary{ border:1px solid #007bff; color:#007bff; background:transparent; }
        .btn-danger{ background:#dc3545; color:#fff; border:none; }
    </style>
</head>
<body>
<div class="container">
    <h2>تعديل فاتورة <?= htmlspecialchars($invoice_type === 'sale' ? 'بيع' : 'شراء') ?></h2>
    <a href="invoice_all.php" class="btn btn-outline-primary">العودة إلى سجل الفواتير</a>

    <?php if (isset($error)): ?>
        <div style="margin-top:10px; padding:10px; background:#ffe6e6; border:1px solid #ffcccc;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div style="margin-top:15px; padding:15px; border:1px solid #eee; border-radius:6px;">
        <h3>تعديل الفاتورة: <?= htmlspecialchars($invoice['invoice_number'] ?? '—') ?></h3>

        <form method="POST">
            <input type="hidden" name="update_invoice" value="1">

            <div class="form-row" style="margin-bottom:15px;">
                <div class="form-group" style="flex:2;">
                    <label>اسم العميل/المورد *</label>
                    <input type="text" name="customer_supplier" class="form-control" style="width:100%; padding:8px;"
                           value="<?= htmlspecialchars($invoice['customer_supplier_name'] ?? '') ?>" required>
                </div>
                <div class="form-group" style="flex:1;">
                    <label>التاريخ *</label>
                    <input type="date" name="date" class="form-control" style="width:100%; padding:8px;"
                           value="<?= htmlspecialchars($invoice['date'] ?? date('Y-m-d')) ?>" required>
                </div>
            </div>

            <h4>أصناف الفاتورة</h4>
            <div id="items-container">
                <?php if (!empty($items)): ?>
                    <?php foreach ($items as $itemIndex => $item): ?>
                        <div class="item-row">
                            <input type="hidden" name="item_id[]" value="<?= htmlspecialchars($item['id'] ?? '') ?>">
                            <div class="form-row">
                                <div class="form-group" style="flex:2;">
                                    <select name="medicine_id[]" class="form-control" style="width:100%; padding:8px;" required>
                                        <option value="">اختر دواء</option>
                                        <?php foreach ($medicines as $m): 
                                            // في كل خيار نضع بيانات السعر كـ data- attributes لتسهيل الوصول من جافاسكربت
                                            $sel = ($item['medicine_id'] == $m['id']) ? 'selected' : '';
                                            $sell = isset($m['price']) ? number_format((float)$m['price'],2) : number_format(0,2);
                                            $buy  = isset($m['price_sales']) ? number_format((float)$m['price_sales'],2) : number_format(0,2);
                                        ?>
                                            <option value="<?= htmlspecialchars($m['id']) ?>" <?= $sel ?>
                                                data-sell="<?= htmlspecialchars($sell) ?>" data-buy="<?= htmlspecialchars($buy) ?>">
                                                <?= htmlspecialchars($m['name']) ?> - سعر <?= $invoice_type === 'sale' ? 'بيع' : 'شراء' ?>: <?= $invoice_type === 'sale' ? $sell : $buy ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group" style="flex:1;">
                                    <input type="number" name="quantity[]" class="form-control" style="width:100%; padding:8px;"
                                           value="<?= htmlspecialchars($item['quantity'] ?? 1) ?>" min="1" required>
                                    <button type="button" class="remove-btn btn btn-danger" style="margin-top:6px; width:100%;">إزالة</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- إن لم توجد أصناف نعرض صفَاً واحداً فارغاً ليتم ملؤه -->
                    <div class="item-row">
                        <input type="hidden" name="item_id[]" value="">
                        <div class="form-row">
                            <div class="form-group" style="flex:2;">
                                <select name="medicine_id[]" class="form-control" style="width:100%; padding:8px;" required>
                                    <option value="">اختر دواء</option>
                                    <?php foreach ($medicines as $m): 
                                        $sell = isset($m['price']) ? number_format((float)$m['price'],2) : number_format(0,2);
                                        $buy  = isset($m['price_sales']) ? number_format((float)$m['price_sales'],2) : number_format(0,2);
                                    ?>
                                        <option value="<?= htmlspecialchars($m['id']) ?>" data-sell="<?= htmlspecialchars($sell) ?>" data-buy="<?= htmlspecialchars($buy) ?>">
                                            <?= htmlspecialchars($m['name']) ?> - سعر <?= $invoice_type === 'sale' ? 'بيع' : 'شراء' ?>: <?= $invoice_type === 'sale' ? $sell : $buy ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="flex:1;">
                                <input type="number" name="quantity[]" class="form-control" style="width:100%; padding:8px;" value="1" min="1" required>
                                <button type="button" class="remove-btn btn btn-danger" style="margin-top:6px; width:100%;">إزالة</button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <button type="button" id="add-item-btn" class="btn btn-outline-primary" style="margin-top:10px;">
                إضافة صنف آخر
            </button>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:18px; padding-top:12px; border-top:1px solid #eee;">
                <h4>إجمالي الفاتورة: <span id="total-amount"><?= number_format((float)($invoice['total_amount'] ?? 0), 2) ?></span> ج.م</h4>
                <button type="submit" class="btn btn-primary">حفظ التعديلات</button>
            </div>
        </form>
    </div>
</div>

<script>
// تمرير HTML خيارات الـ <option> بأمان من PHP إلى جافاسكربت باستخدام JSON
const medicineOptionsHtml = <?= json_encode($medicineOptionsHtml, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
const invoiceType = <?= json_encode($invoice_type) ?>;

// وظيفة لإضافة صف عنصر جديد في DOM
function addItemRow(htmlOptions = medicineOptionsHtml) {
    const container = document.getElementById('items-container');
    const newRow = document.createElement('div');
    newRow.className = 'item-row';
    newRow.innerHTML = `
        <input type="hidden" name="item_id[]" value="">
        <div class="form-row">
            <div class="form-group" style="flex:2;">
                <select name="medicine_id[]" class="form-control" style="width:100%; padding:8px;" required>
                    <option value="">اختر دواء</option>
                    ${htmlOptions}
                </select>
            </div>
            <div class="form-group" style="flex:1;">
                <input type="number" name="quantity[]" class="form-control" style="width:100%; padding:8px;" value="1" min="1" required>
                <button type="button" class="remove-btn btn btn-danger" style="margin-top:6px; width:100%;">إزالة</button>
            </div>
        </div>
    `;
    container.appendChild(newRow);
    attachRemoveListener(newRow);
    attachChangeListeners(newRow);
    recalcTotal();
}

// ربط حدث إزالة لمستقبل زر الإزالة داخل صف
function attachRemoveListener(row) {
    const btn = row.querySelector('.remove-btn');
    if (btn) {
        btn.addEventListener('click', function() {
            row.remove();
            recalcTotal();
        });
    }
}

// ربط مستمعي التغيير لحقول select و quantity داخل صف معين
function attachChangeListeners(row) {
    const select = row.querySelector('select[name="medicine_id[]"]');
    const qty = row.querySelector('input[name="quantity[]"]');
    if (select) select.addEventListener('change', recalcTotal);
    if (qty) qty.addEventListener('input', recalcTotal);
}

// ربط أزرار الإزالة والـ change لكل الصفوف المحمّلة (التي جاءت من السيرفر)
document.querySelectorAll('.item-row').forEach(function(r) {
    attachRemoveListener(r);
    attachChangeListeners(r);
});

// زر إضافة عنصر جديد
document.getElementById('add-item-btn').addEventListener('click', function() {
    addItemRow();
});

// دالة لحساب الإجمالي من حقول الـ DOM
function recalcTotal() {
    let total = 0;
    document.querySelectorAll('.item-row').forEach(function(row) {
        const select = row.querySelector('select[name="medicine_id[]"]');
        const qtyInput = row.querySelector('input[name="quantity[]"]');
        if (!select || !qtyInput) return;

        const selectedOption = select.options[select.selectedIndex];
        const qty = parseInt(qtyInput.value) || 0;
        if (!selectedOption || !selectedOption.value || qty <= 0) return;

        // نحاول الحصول على السعر من data attributes أولاً
        let price = 0;
        if (selectedOption.dataset) {
            if (invoiceType === 'sale' && selectedOption.dataset.sell) {
                price = parseFloat(selectedOption.dataset.sell.replace(/,/g,'')) || 0;
            } else if (invoiceType !== 'sale' && selectedOption.dataset.buy) {
                price = parseFloat(selectedOption.dataset.buy.replace(/,/g,'')) || 0;
            }
        }

        // إذا لم نجد price من data-attributes نحاول استخراج الرقم من نص الخيار (fallback)
        if (!price) {
            const txt = selectedOption.textContent || '';
            const m = txt.match(/([\d.,]+)\s*$/);
            if (m) price = parseFloat(m[1].replace(/,/g,'')) || 0;
        }

        total += price * qty;
    });

    document.getElementById('total-amount').textContent = total.toFixed(2);
}

// حساب أولي عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    recalcTotal();
});
</script>
</body>
</html>
