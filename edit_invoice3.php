<?php
// 1) بدء الجلسة للتحقق من الدخول
session_start(); // (1) بدء الجلسة

// 2) التحقق من وجود معرّف المستخدم في الجلسة وإعادة توجيه غير المسجلين
if (!isset($_SESSION['userid'])) { // (2) تحقق من تسجيل الدخول
    header("Location: login.php"); // (3) إعادة توجيه إلى صفحة الدخول
    exit; // (4) إيقاف التنفيذ بعد التحويل
}

// 3) تضمين ملف الاتصال بقاعدة البيانات (PDO)
require_once 'db.php'; // (5) تحميل إعدادات PDO و$pdo

// 4) التحقق من وجود معرف الفاتورة في الرابط وصحته
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) { // (6) تحقق من صحة المعرف في GET
    echo "معرف الفاتورة غير صحيح"; // (7) إظهار رسالة واضحة بدل die
    exit; // (8) إيقاف التنفيذ
}

// 5) تحويل معرف الفاتورة إلى عدد صحيح آمن
$invoice_id = (int) $_GET['id']; // (9) تحويل إلى int

// 6) نستخدم try/catch للتعامل مع أخطاء PDO
try { // (10) بداية الكتلة المحمية
    // 6.1) جلب بيانات الفاتورة من جدول invoices3
    $stmt = $pdo->prepare("SELECT * FROM invoices3 WHERE id = ?"); // (11) تحضير استعلام الفاتورة
    if (!$stmt->execute([$invoice_id])) { // (12) تنفيذ مع فحص الخطأ
        $err = $stmt->errorInfo(); // (13) جلب تفاصيل الخطأ
        throw new Exception("خطأ في تنفيذ استعلام الفاتورة: " . $err[2]); // (14) رمي استثناء مع نص واضح
    }
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC); // (15) جلب صف الفاتورة كمصفوفة اسمية

    // 6.2) التحقق من وجود الفاتورة
    if (!$invoice) { // (16) إذا لم توجد الفاتورة
        echo "الفاتورة غير موجودة"; // (17) ابلاغ المستخدم
        exit; // (18) إيقاف تنفيذ الصفحة
    }

    // 6.3) جلب تفاصيل الفاتورة (invoice_items) مع اسم الدواء من جدول medicines
    // ملاحظة: المفتاح الأساسي في جدول medicines حسب صورك هو medicineid -> نستخدمه هنا
    $stmt = $pdo->prepare(" SELECT invoice_items.id, invoice_items.invoice_id, invoice_items.medicine_id, 
               invoice_items.quantity, invoice_items.unit_price, invoice_items.total_price,
               medicines.medicineid AS med_medicineid, medicines.name AS medicine_name
        FROM invoice_items
        JOIN medicines ON invoice_items.medicine_id = medicines.medicineid
        WHERE invoice_items.invoice_id = ?
        ORDER BY invoice_items.id
    "); // (19) تحضير استعلام العناصر بدون alias غامض

    if (!$stmt->execute([$invoice_id])) { // (20) تنفيذ الاستعلام مع فحص الخطأ
        $err = $stmt->errorInfo(); // (21) جلب تفاصيل الخطأ
        throw new Exception("خطأ في استعلام تفاصيل الفاتورة: " . $err[2]); // (22) رمي استثناء
    }
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC); // (23) جلب كل العناصر كمصفوفة اسمية

    // 6.4) إذا ما في عناصر للفواتير نعرض رسالة واضحة
    if (empty($items)) { // (24) تحقق من وجود عناصر
        echo "⚠ لا توجد عناصر لهذه الفاتورة."; // (25) إخطار المستخدم
        // لا نخرج بالقوة هنا لأن قد يكون المستخدم يريد تعديل وإضافة أصناف؛ لكن حسب سير عملك يمكن الخروج
        // exit; // (26) إن أردت الإنهاء قم بإلغاء تعليق هذا السطر
    }

    // 6.5) جلب قائمة الأدوية لاستخدامها في القوائم (نستخدم medicineid كـ id في الواجهة)
    $medicines = $pdo->query("SELECT medicineid AS id, name, price_sales, price 
        FROM medicines
    ")->fetchAll(PDO::FETCH_ASSOC); // (27) جلب قائمة الأدوية كمصفوفة اسمية

    // 6.6) نجهز HTML الخاص بخيارات <option> بحيث نستخدمه داخل جافاسكربت لاحقًا
    $medicineOptionsHtml = ''; // (28) متغير لحفظ خيارات الـ select كـ HTML
    foreach ($medicines as $m) { // (29) بناء السلسلة
        $priceText = 'سعر ' . ($invoice['type'] === 'sale' ? 'بيع' : 'شراء') . ': ' . 
                     number_format($m['selling_price'] ?? $m['purchase_price'], 2); // (30) صياغة نص السعر
        $medicineOptionsHtml .= '<option value="' . htmlspecialchars($m['id']) . '">' .
                                htmlspecialchars($m['name']) . ' - ' . $priceText .
                                '</option>'; // (31) إضافة خيار
    }
} catch (Exception $e) { // (32) التقاط الاستثناءات
    // 6.7) تسجيل الخطأ للمطور وإظهار رسالة للمستخدم
    error_log("خطأ تقني: " . $e->getMessage()); // (33) تسجيل في لوج السيرفر
    echo "حدث خطأ تقني: " . htmlspecialchars($e->getMessage()); // (34) إظهار رسالة واضحة محتوية على سبب الخطأ
    exit; // (35) إيقاف التنفيذ
}

// 7) الآن ننتقل لمعالجة POST عند حفظ التعديلات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_invoice'])) { // (36) تحقق من POST وحقل update_invoice
    // 7.1) جلب القيم من الفورم بأمان
    $customer_supplier = trim($_POST['customer_supplier'] ?? ''); // (37) اسم العميل/المورد
    $date = $_POST['date'] ?? date('Y-m-d'); // (38) تاريخ الفاتورة
    $total = 0; // (39) إجمالي جديد سيُحسب
    $items_to_update = []; // (40) مصفوفة لتجميع العناصر المعدلة

    // 7.2) تحقق من وجود عناصر مرسلة
    $posted_item_ids = $_POST['item_id'] ?? []; // (41) مصفوفة المعرفات القديمة
    $posted_medicine_ids = $_POST['medicine_id'] ?? []; // (42) مصفوفة معرفات الأدوية
    $posted_quantities = $_POST['quantity'] ?? []; // (43) مصفوفة الكميات

    // 7.3) تجميع عناصر التحديث بالقراءة الآمنة للمصفوفات
    for ($index = 0; $index < count($posted_medicine_ids); $index++) { // (44) حلقة على العناصر المرسلة
        $item_id = $posted_item_ids[$index] ?? null; // (45) id الخاص بصف العنصر إن وُجد
        $med_id = (int) ($posted_medicine_ids[$index] ?? 0); // (46) معرف الدواء كم-int
        $qty = (int) ($posted_quantities[$index] ?? 0); // (47) الكمية كم-int

        if ($med_id > 0 && $qty > 0) { // (48) تجاهل العناصر الفارغة
            // 7.3.1) جلب بيانات الدواء الحالي من جدول medicines
            $stmt = $pdo->prepare("SELECT medicineid AS id, name, price_sales, price, quantity FROM medicines WHERE medicineid = ?"); // (49) تحضير استعلام الدواء
            $stmt->execute([$med_id]); // (50) تنفيذ
            $med = $stmt->fetch(PDO::FETCH_ASSOC); // (51) جلب الدواء

            if (!$med) continue; // (52) إذا الدواء غير موجود نتخطى هذا العنصر

            // 7.3.2) تحديد سعر الوحدة حسب نوع الفاتورة (بيع/شراء)
            $unit_price = ($invoice['type'] === 'sale') ? $med['selling_price'] : $med['purchase_price']; // (53) اختيار السعر الصحيح
            $total_price = $unit_price * $qty; // (54) حساب الإجمالي لهذا الصنف
            $total += $total_price; // (55) إضافة إلى المجموع الكلي

            // 7.3.3) إضافة العنصر إلى مصفوفة التحديث
            $items_to_update[] = [ // (56) تجميع البيانات
                'id' => $item_id,
                'medicine_id' => $med_id,
                'quantity' => $qty,
                'unit_price' => $unit_price,
                'total_price' => $total_price
            ]; // (57) نهاية تعريف العنصر
        } // (58) نهاية شرط med_id && qty
    } // (59) نهاية حلقة العناصر

    // 7.4) تحقق من وجود عناصر صالحة للتحديث
    if (count($items_to_update) > 0) { // (60) إذا يوجد عناصر للتحديث
        try { // (61) بدء محاولة تحديث القاعدة داخل معاملة
            $pdo->beginTransaction(); // (62) بدء المعاملة

            // 7.4.1) تحديث بيانات الفاتورة الأساسية
            $stmt = $pdo->prepare("UPDATE invoices3 SET customer_supplier_name = ?, total_amount = ?, date = ? WHERE id = ?"); // (63) تحضير تحديث الفاتورة
            $stmt->execute([$customer_supplier, $total, $date, $invoice_id]); // (64) تنفيذ

            // 7.4.2) حذف تفاصيل الفاتورة القديمة لإعادة إدخال الجديدة (طريقة آمنة)
            $stmt = $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = ?"); // (65) حذف القديم
            $stmt->execute([$invoice_id]); // (66) تنفيذ الحذف

            // 7.4.3) إدخال التفاصيل الجديدة
            $insertStmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, medicine_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)"); // (67) تحضير الإدراج
            foreach ($items_to_update as $it) { // (68) حلقة لإدخال كل عنصر جديد
                $insertStmt->execute([
                    $invoice_id,
                    $it['medicine_id'],
                    $it['quantity'],
                    $it['unit_price'],
                    $it['total_price']
                ]); // (69) تنفيذ إدراج العنصر
            } // (70) نهاية حلقة الإدراج

            // 7.4.4) تحديث كمية الأدوية في المخزون
            // أولاً: إعادة الكميات الأصلية (استرجاع التغييرات السابقة)
            foreach ($items as $original_item) { // (71) حلقة على العناصر الأصلية قبل التعديل
                // original_item يحتوي على keys: medicine_id و quantity
                if ($invoice['type'] === 'sale') { // (72) استرجاع حالة البيع
                    $pdo->prepare("UPDATE medicines SET quantity = quantity + ? WHERE medicineid = ?")
                        ->execute([$original_item['quantity'], $original_item['medicine_id']]); // (73) زيادة المخزون
                } else { // (74) حالة شراء
                    $pdo->prepare("UPDATE medicines SET quantity = quantity - ? WHERE medicineid = ?")
                        ->execute([$original_item['quantity'], $original_item['medicine_id']]); // (75) إنقاص المخزون
                }
            } // (76) نهاية استعادة الكميات

            // ثانياً: تطبيق الكميات الجديدة من $items_to_update
            foreach ($items_to_update as $new_item) { // (77) حلقة على العناصر الجديدة
                if ($invoice['type'] === 'sale') { // (78) في حال كانت فاتورة بيع
                    $pdo->prepare("UPDATE medicines SET quantity = quantity - ? WHERE medicineid = ?")
                        ->execute([$new_item['quantity'], $new_item['medicine_id']]); // (79) إنقاص المخزون
                } else { // (80) في حال كانت فاتورة شراء
                    $pdo->prepare("UPDATE medicines SET quantity = quantity + ? WHERE medicineid = ?")
                        ->execute([$new_item['quantity'], $new_item['medicine_id']]); // (81) زيادة المخزون
                }
            } // (82) نهاية تطبيق الكميات الجديدة

            $pdo->commit(); // (83) إنهاء المعاملة بنجاح

            // 7.4.5) إعادة توجيه إلى صفحة الفواتير مع إشارة نجاح
            header("Location: invoce.php?success=1"); // (84) إعادة توجيه
            exit; // (85) إيقاف التنفيذ بعد التحويل
        } catch (PDOException $e) { // (86) في حال فشل أي خطوة ضمن المعاملة
            $pdo->rollBack(); // (87) التراجع عن التغييرات
            error_log("خطأ في تعديل الفاتورة: " . $e->getMessage()); // (88) تسجيل الخطأ
            $error = "حدث خطأ أثناء تحديث الفاتورة: " . $e->getMessage(); // (89) رسالة للعرض في الواجهة
        }
    } else { // (90) إذا لم توجد عناصر صالحة
        $error = "يرجى إضافة صنف واحد على الأقل للفاتورة"; // (91) تحذير للمستخدم
    }
} // (92) نهاية معالجة POST
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8"> <!-- (93) تعيين ترميز الصفحة -->
    <title>تعديل فاتورة</title> <!-- (94) عنوان الصفحة -->
    <link rel="stylesheet" href="style.css?v=<?= time() ?>"> <!-- (95) تحميل CSS -->
</head>
<body> <!-- (96) بداية جسم الصفحة -->
    <div class="container"> <!-- (97) حاوية المحتوى -->
        <h2 class="page-title">تعديل فاتورة <?= $invoice['type'] == 'sale' ? 'بيع' : 'شراء' ?></h2> <!-- (98) عنوان ثانوي يوضح نوع الفاتورة -->
        <a href="invoices.php" class="btn btn-outline-primary">العودة إلى سجل الفواتير</a> <!-- (99) رابط العودة -->

        <?php if (isset($error)): ?> <!-- (100) عرض رسالة الخطأ إن وُجدت -->
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="card"> <!-- (101) بداية بطاقة المحتوى -->
            <div class="card-header"> <!-- (102) عنوان البطاقة -->
                <h3>تعديل الفاتورة: <?= htmlspecialchars($invoice['invoice_number']) ?></h3> <!-- (103) عرض رقم الفاتورة -->
            </div>
            <div class="card-body"> <!-- (104) جسم البطاقة -->
                <form method="POST"> <!-- (105) بداية الفورم -->
                    <input type="hidden" name="update_invoice" value="1"> <!-- (106) علم لتعرف أن الفورم طُلب للتحديث -->

                    <div class="form-row" style="display: flex; gap: 15px; margin-bottom: 20px;"> <!-- (107) صف للحقلين -->
                        <div class="form-group" style="flex: 2;"> <!-- (108) حقل اسم العميل/المورد -->
                            <label>اسم العميل/المورد *</label> <!-- (109) تسمية الحقل -->
                            <input type="text" name="customer_supplier" class="form-control" 
                                   value="<?= htmlspecialchars($invoice['customer_supplier_name']) ?>" required> <!-- (110) القيمة الحالية -->
                        </div>

                        <div class="form-group" style="flex: 1;"> <!-- (111) حقل التاريخ -->
                            <label>التاريخ *</label> <!-- (112) تسمية -->
                            <input type="date" name="date" class="form-control" 
                                   value="<?= htmlspecialchars($invoice['date']) ?>" required> <!-- (113) القيمة الحالية -->
                        </div>
                    </div>

                    <h4>أصناف الفاتورة</h4> <!-- (114) عنوان قسم الأصناف -->
                    <div id="items-container"> <!-- (115) حاوية العناصر -->
                        <?php foreach ($items as $index => $item): ?> <!-- (116) حلقة لطباعة العناصر الحالية -->
                        <div class="item-row" style="background: #f8fafc; padding: 15px; border-radius: 6px; margin-bottom: 15px;"> <!-- (117) صف عنصر -->
                            <input type="hidden" name="item_id[]" value="<?= htmlspecialchars($item['id']) ?>"> <!-- (118) id العنصر -->

                            <div class="form-row" style="display: flex; gap: 15px;"> <!-- (119) ترتيب الحقول داخل العنصر -->
                                <div class="form-group" style="flex: 2;"> <!-- (120) اختيار الدواء -->
                                    <select name="medicine_id[]" class="form-control" required> <!-- (121) select للأدوية -->
                                        <option value="">اختر دواء</option> <!-- (122) خيار افتراضي -->
                                        <?php foreach ($medicines as $m): ?> <!-- (123) خيارات الأدوية من $medicines -->
                                            <option value="<?= htmlspecialchars($m['id']) ?>" 
                                                <?= ($item['medicine_id'] == $m['id']) ? 'selected' : '' ?>> <!-- (124) اختيار الدواء الحالي -->
                                                <?= htmlspecialchars($m['name']) ?> - سعر <?= $invoice['type'] == 'sale' ? 'بيع' : 'شراء' ?>: <?= number_format(($invoice['type']=='sale' ? $m['selling_price'] : $m['purchase_price']),2) ?> ج.م
                                            </option> <!-- (125) نهاية الخيار -->
                                        <?php endforeach; ?> <!-- (126) نهاية حلقة الأدوية -->
                                    </select> <!-- (127) نهاية select -->
                                </div>

                                <div class="form-group" style="flex: 1;"> <!-- (128) حقل الكمية -->
                                    <input type="number" name="quantity[]" class="form-control" 
                                           value="<?= htmlspecialchars($item['quantity']) ?>" placeholder="الكمية" min="1" required> <!-- (129) قيمة الكمية -->
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?> <!-- (130) نهاية حلقة العناصر -->
                    </div>

                    <button type="button" id="add-item-btn" class="btn btn-outline-primary" style="margin-bottom: 20px;"> <!-- (131) زر إضافة عنصر -->
                        <i class="fas fa-plus"></i> إضافة صنف آخر
                    </button>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee;"> <!-- (132) شريط الأسفل -->
                        <h4 style="margin: 0;">إجمالي الفاتورة: <span id="total-amount"><?= number_format($invoice['total_amount'], 2) ?></span> ج.م</h4> <!-- (133) عرض الإجمالي -->
                        <button type="submit" class="btn btn-primary" style="padding: 10px 25px; font-size: 1.1rem;"> <!-- (134) زر حفظ -->
                            <i class="fas fa-save"></i> حفظ التعديلات
                        </button>
                    </div>
                </form> <!-- (135) نهاية الفورم -->
            </div> <!-- (136) نهاية card-body -->
        </div> <!-- (137) نهاية البطاقة -->
    </div> <!-- (138) نهاية الحاوية -->

    <script>
    // 8) تخزين HTML خيارات الأدوية التي أنشأناها في PHP داخل متغير جافاسكربت
    const medicineOptionsHtml = <?= str_replace("","\\", $medicineOptionsHtml) ?>; // (139) خيارات الدواء جاهزة للاستخدام في إضافة صفوف جديدة

    // 9) إضافة مستمع على زر إضافة صنف
    document.getElementById('add-item-btn').addEventListener('click', function() { // (140) حدث النقر على زر الإضافة
        const container = document.getElementById('items-container'); // (141) حاوية العناصر
        const newRow = document.createElement('div'); // (142) إنشاء صف جديد
        newRow.className = 'item-row'; // (143) إضافة الصنف CSS
        newRow.style = "background: #f8fafc; padding: 15px; border-radius: 6px; margin-bottom: 15px;"; // (144) تنسيق

        // 10) نستخدم نفس بنية HTML الموجودة في PHP لكن مع medicineOptionsHtml
        newRow.innerHTML = `
            <div class="form-row" style="display: flex; gap: 15px;">
                <div class="form-group" style="flex: 2;">
                    <select name="medicine_id[]" class="form-control" required>
                        <option value="">اختر دواء</option>
                        ${medicineOptionsHtml}
                    </select>
                </div>
                <div class="form-group" style="flex: 1;">
                    <input type="number" name="quantity[]" class="form-control" placeholder="الكمية" min="1" required>
                    <button type="button" class="remove-btn btn btn-danger btn-sm" style="margin-top: 5px; width: 100%;">إزالة</button>
                </div>
            </div>
        `; // (145) نهاية innerHTML

        container.appendChild(newRow); // (146) إضافة الصف للحاوية
        attachRemoveListener(newRow); // (147) ربط حدث الإزالة
        recalcTotal(); // (148) إعادة حساب الإجمالي
    });

    // 11) دالة لربط زر الإزالة داخل صف محدد
    function attachRemoveListener(row) { // (149) تعريف الدالة
        const btn = row.querySelector('.remove-btn'); // (150) البحث عن الزر
        if (btn) { // (151) إن وُجد
            btn.addEventListener('click', function() { // (152) ربط الحدث
                row.remove(); // (153) إزالة الصف
                recalcTotal(); // (154) إعادة حساب الإجمالي
            });
        }
    }

    // 12) ربط أزرار الإزالة لعناصر محملة من السيرفر
    document.querySelectorAll('.item-row').forEach(function(r) { attachRemoveListener(r); }); // (155) ربط للأزرار الموجودة

    // 13) دالة لحساب الإجمالي من عناصر الـ DOM
    function recalcTotal() { // (156) تعريف الدالة
        let total = 0; // (157) متغير تجميعي
        const type = "<?= $invoice['type'] ?>"; // (158) نوع الفاتورة من PHP

        document.querySelectorAll('.item-row').forEach(row => { // (159) المرور على كل صف
            const select = row.querySelector('select[name="medicine_id[]"]'); // (160) الحصول على select
            const qtyInput = row.querySelector('input[name="quantity[]"]'); // (161) الحصول على input الكمية

            if (select && qtyInput && select.value && qtyInput.value) { // (162) تحقق من القيم
                // نحاول استخراج السعر من الـ option text أو من بيانات الأدوية المحفوظة في PHP
                const option = select.options[select.selectedIndex]; // (163) اختيار الخيار المحدد
                // سعر في نص الـ option بصيغة: "... سعر بيع: 123.00" — نستخدم تعبيراً منتظماً لاستخراج العدد
                const priceMatch = option.textContent.match(/([\d,.]+)\s*ج?\.?م?$/); // (164) محاولة استخراج الرقم (مرن)
                let price = 0; // (165) الافتراضي
                if (priceMatch) { // (166) إن وجد رقم
                    price = parseFloat(priceMatch[1].replace(/,/g, '')); // (167) تحويل للنقطة العشرية
                } else {
                    // بدلاً من ذلك نحاول أخذ السعر من الـ data attributes لو وُجدت (لم نضعها هنا لكن يمكن إضافتها لاحقاً)
                    price = 0;
                }
                const qty = parseInt(qtyInput.value) || 0; // (168) تحويل الكمية إلى عدد
                total += price * qty; // (169) حساب الإضافة
            }
        });

        document.getElementById('total-amount').textContent = total.toFixed(2); // (170) عرض الناتج بنقطتين عشريتين
    }

    // 14) إعادة حساب الإجمالي عند تحميل الصفحة وربط تغييرات الحقول
    document.addEventListener('DOMContentLoaded', function() { // (171) عند تحميل الـ DOM
        // ربط مستمعي التغيير لحقول الـ select والكمية داخل كل صف
        document.querySelectorAll('select[name="medicine_id[]"], input[name="quantity[]"]').forEach(function(el) { // (172) تحديد الحقول
            el.addEventListener('change', recalcTotal); // (173) عند التغيير نعيد الحساب
            el.addEventListener('input', recalcTotal); // (174) حدث الإدخال أيضاً
        });
        recalcTotal(); // (175) حساب مبدئي
    }); // (176) نهاية DOMContentLoaded
    </script>
</body>
</html>