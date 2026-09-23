<?php
// invoicessss.php
// نسخة محسّنة من صفحة إنشاء الفاتورة تشمل: ملاحظات لكل صنف، تحويل المبالغ إلى كلمات، حفظ + طباعة، تنسيق طباعة مع عد الصفحات.
// ----------------------------------------------------------
// ملاحظة: هذا الملف يفترض وجود ملف db.php يُعرف $pdo (اتصال PDO). لا تغيّر اسم db.php إلا إذا اسم ملف الاتصال مختلف.
// ضع شعارك باسم "logo.png" بنفس المجلد إن أردت ظهور الشعار في أعلى الفاتورة.
// ----------------------------------------------------------

require_once 'db.php'; // جلب إعداد الاتصال بقاعدة البيانات (يجب أن يُعرّف $pdo).

// -----------------------------
// دالة لتحويل رقم إلى كلمات عربية (جزء من جانب الخادم).
// تقوم بتحويل الجزء الصحيح فقط. ترجع سلسلة باللغة العربية.
// هذه دالة كافية للمبالغ الاعتيادية (آلاف، ملايين).
// -----------------------------
function numberToArabicWords($number) {
    // نتعامل فقط مع الأرقام الموجبة وصفر
    $number = strval((int) round($number)); // نتعامل بالجزء الصحيح فقط
    if ($number == '0') {
        return 'صفر';
    }

    // كلمات الوحدات والعشرات والمئات
    $ones = ['', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة'];
    $teens = [10=>'عشرة',11=>'أحد عشر',12=>'اثنا عشر',13=>'ثلاثة عشر',14=>'أربعة عشر',15=>'خمسة عشر',16=>'ستة عشر',17=>'سبعة عشر',18=>'ثمانية عشر',19=>'تسعة عشر'];
    $tens = ['', '', 'عشرون', 'ثلاثون', 'أربعون', 'خمسون', 'ستون', 'سبعون', 'ثمانون', 'تسعون'];
    $hundreds = ['', 'مائة', 'مئتان', 'ثلاثمائة', 'أربعمائة', 'خمسمائة', 'ستمائة', 'سبعمائة', 'ثمانمائة', 'تسعمائة'];

    // أجزاء الألوف
    $bigUnits = [
        ['', '', ''],
        ['ألف', 'ألفان', 'آلاف'],
        ['مليون', 'مليونان', 'ملايين'],
        ['مليار', 'ملياران', 'مليارات'],
    ];

    // تفكيك الرقم إلى مجموعات من 3 خانات من اليمين
    $groups = [];
    while (strlen($number) > 0) {
        $groups[] = (int)substr($number, -3);
        $number = substr($number, 0, -3);
    }

    $parts = [];

    // تحويل كل مجموعة (0..999)
    foreach ($groups as $idx => $group) {
        if ($group == 0) continue;
        $g = $group;
        $h = (int)($g / 100);
        $t = (int)(($g % 100) / 10);
        $u = $g % 10;

        $segmentParts = [];

        // المئات
        if ($h > 0) {
            $segmentParts[] = $hundreds[$h];
        }

        // العشرات والوحدات
        if ($t == 1) {
            $teenKey = 10 + $u;
            $segmentParts[] = $teens[$teenKey];
        } else {
            if ($u > 0) {
                $segmentParts[] = $ones[$u];
            }
            if ($t > 0) {
                $segmentParts[] = $tens[$t];
            }
        }

        $segment = implode(' و ', array_reverse($segmentParts)); // نجمع الأجزاء داخل الثلاث خانات

        // نضيف لفظ الفئات (ألف، مليون، ...)
        if ($idx > 0) {
            // idx==1 => آلاف, idx==2 => ملايين ...
            $unitForms = $bigUnits[$idx] ?? ['', '', ''];
            // تصريف بسيط للأرقام: 1 -> singular, 2 -> dual, 3+ -> plural
            if ($g == 1) {
                $segment = $unitForms[0]; // 'ألف'
            } elseif ($g == 2) {
                $segment = $unitForms[1]; // 'ألفان'
            } else {
                // إذا أكبر من 2: نضع صيغة الجمع بعد العدد
                $segment .= ' ' . ($unitForms[2] ?: '');
            }
        }

        $parts[] = $segment;
    }

    // نركّب الجملة من مجموع المجموعات
    $words = implode(' و ', array_reverse($parts));
    // بعض التنقيحات البسيطة
    $words = preg_replace('/\s+/', ' ', trim($words));
    return $words;
}

// -----------------------------
// دعم استجابة سريعة لطلب AJAX: تحويل رقم إلى كلمات (تستخدمه JS لعرض الكلمات دون إعادة تحميل الصفحة).
// إذا استلمنا ?action=words&num=xxx سنعيد JSON يحتوي على المفتاح 'words'.
// -----------------------------
if (isset($_GET['action']) && $_GET['action'] === 'words' && isset($_GET['num'])) {
    header('Content-Type: application/json; charset=utf-8');
    $num = floatval(str_replace(',', '.', $_GET['num']));
    $intPart = floor($num); // جزء صحيح فقط
    $words = numberToArabicWords($intPart);
    echo json_encode(['words' => $words]);
    exit;
}

// -----------------------------
// معالجة POST لحفظ الفاتورة (زر حفظ أو حفظ+طباعة).
// هذا الجزء يتصرف كما يلي:
// - يجمع الأصناف من الحقول: medicine_id[], quantity[], unit_price[], note[]
// - يتأكد من وجود أصناف صالحة، يحسب الإجمالي، يحدث الكميات في جدول medicines (بيع ينقص، شراء يزيد).
// - يُدخل سجل الفاتورة في invoices3 ثم يدخل الأصناف في invoice_items.
// - يدعم وجود/عدم وجود عمود notes في جدول الفواتير وعمود note في جدول العناصر (يتحقق ويستخدم أو يتخطى).
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['create_invoice']) || isset($_POST['create_and_print']))) {

    // جلب المدخلات الأساسية من النموذج
    $type = isset($_POST['type']) ? $_POST['type'] : '';
    $customer_supplier = isset($_POST['customer_supplier']) ? trim($_POST['customer_supplier']) : '';
    $date = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d');
    $invoice_note = isset($_POST['invoice_note']) ? trim($_POST['invoice_note']) : '';

    $total = 0.0; // المجموع الكلي الرقمي
    $items = []; // مصفوفة الأصناف لتخزينها مؤقتًا قبل الإدراج

    // إذا لم يتم تمرير medicine_id[] لا نفعل شيء
    if (isset($_POST['medicine_id']) && is_array($_POST['medicine_id'])) {
        foreach ($_POST['medicine_id'] as $index => $med_id_raw) {
            $med_id = trim($med_id_raw);
            $qty = isset($_POST['quantity'][$index]) ? (int)$_POST['quantity'][$index] : 0;
            // إذا لم يُختَر دواء أو الكمية صفر نتخطى
            if ($med_id === '' || $qty <= 0) {
                continue;
            }

            // جلب بيانات الدواء من DB
            $stmt = $pdo->prepare("SELECT medicineid, name, description, price, expiredate, quantity, price_sales, dateproduction FROM medicines WHERE medicineid = ?");
            $stmt->execute([$med_id]);
            $med = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$med) {
                // لو الدواء غير موجود في DB نضع رسالة خطأ وننتقل
                $error = "الدواء برقم #{$med_id} غير موجود في قاعدة البيانات.";
                continue;
            }

            // إذا كانت العملية بيع (sale) وتوجد كمية غير كافية
            if ($type === 'sale' && isset($med['quantity']) && $med['quantity'] < $qty) {
                $error = "الكمية غير كافية للدواء: " . $med['name'];
                continue;
            }

            // سعر الوحدة: إن أدخل المستخدم سعرًا يدوياً نأخذ منه، وإلا نستخدم سعر البيع/الشراء من DB
            $submitted_price = isset($_POST['unit_price'][$index]) ? floatval(str_replace(',', '.', $_POST['unit_price'][$index])) : 0;
            if ($submitted_price > 0) {
                $unit_price = $submitted_price;
            } else {
                // إذا نوع العملية بيع نستخدم price_sales وإلا نستخدم price أو price_sales كبديل
                if ($type === 'sale') {
                    $unit_price = isset($med['price_sales']) && $med['price_sales'] !== '' ? floatval($med['price_sales']) : floatval($med['price']);
                } else {
                    $unit_price = isset($med['price']) && $med['price'] !== '' ? floatval($med['price']) : (isset($med['price_sales']) ? floatval($med['price_sales']) : 0);
                }
            }

            $total_price = $unit_price * $qty;
            $total += $total_price;

            $item_note = isset($_POST['note'][$index]) ? trim($_POST['note'][$index]) : '';

            // نضيف العنصر لمصفوفة الإدراج لاحقًا
            $items[] = [
                'id' => $med_id,
                'quantity' => $qty,
                'unit_price' => $unit_price,
                'total_price' => $total_price,
                'note' => $item_note,
                'name' => $med['name'],
            ];
        }
    }

    // إذا وجدنا على الأقل صنف واحد صالح نقوم بعملية الحفظ
    if (count($items) > 0) {
        // إنشاؤ رقم فاتورة فريد
        $invoice_number = 'INV-' . time();

        // نتحقق إذا كان جدول الفواتير يحتوي عمود 'notes' لندخله إن وُجد
        $colStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices3' AND COLUMN_NAME = 'notes'");
        $colStmt->execute();
        $has_notes_col = $colStmt->fetchColumn() > 0;

        // نجهز SQL للإدراج بحسب وجود عمود الملاحظات
        if ($has_notes_col) {
            $insertInvoiceSql = "INSERT INTO invoices3 (invoice_number, type, customer_supplier_name, total_amount, date, notes) VALUES (?, ?, ?, ?, ?, ?)";
            $insertInvoiceStmt = $pdo->prepare($insertInvoiceSql);
            $insertInvoiceStmt->execute([$invoice_number, $type, $customer_supplier, $total, $date, $invoice_note]);
        } else {
            $insertInvoiceSql = "INSERT INTO invoices3 (invoice_number, type, customer_supplier_name, total_amount, date) VALUES (?, ?, ?, ?, ?)";
            $insertInvoiceStmt = $pdo->prepare($insertInvoiceSql);
            $insertInvoiceStmt->execute([$invoice_number, $type, $customer_supplier, $total, $date]);
        }

        // نأخذ id الفاتورة التي أُنشئت
        $invoice_id = $pdo->lastInsertId();

        // نتحقق إذا كان invoice_items يحتوي عمود 'note'
        $colStmt2 = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoice_items' AND COLUMN_NAME = 'note'");
        $colStmt2->execute();
        $items_has_note = $colStmt2->fetchColumn() > 0;

        // إدراج كل صنف في جدول invoice_items
        foreach ($items as $it) {
            if ($items_has_note) {
                $stmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, medicine_id, quantity, unit_price, total_price, note) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$invoice_id, $it['id'], $it['quantity'], $it['unit_price'], $it['total_price'], $it['note']]);
            } else {
                // لو لم يكن عمود note متاحًا ندرج بدون الملاحظة
                $stmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, medicine_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$invoice_id, $it['id'], $it['quantity'], $it['unit_price'], $it['total_price']]);
            }

            // تحديث كمية الدواء في جدول medicines:
            // إذا كانت عملية بيع (sale) ننقص الكمية، وإذا كانت شراء (purchase) نزيدها.
            $medStmt = $pdo->prepare("SELECT quantity FROM medicines WHERE medicineid = ?");
            $medStmt->execute([$it['id']]);
            $currentQty = $medStmt->fetchColumn();
            if ($currentQty === false) $currentQty = 0;
            if ($type === 'sale') {
                $newQty = $currentQty - $it['quantity'];
            } else {
                $newQty = $currentQty + $it['quantity'];
            }
            // نقوم بالتحديث النهائي للمخزون
            $pdo->prepare("UPDATE medicines SET quantity = ? WHERE medicineid = ?")->execute([$newQty, $it['id']]);
        }

        // رسالة نجاح تعرض للمستخدم
        $success = "تم إنشاء الفاتورة بنجاح: $invoice_number";

        // إذا ضغط المستخدم زر 'حفظ + طباعة' نفتح نافذة طباعة تلقائيًا إلى رابط الطباعة الخاص بهذه الفاتورة
        if (isset($_POST['create_and_print'])) {
            // رابط طباعة داخل نفس الملف:
            $print_url = htmlspecialchars($_SERVER['PHP_SELF']) . '?print_id=' . $invoice_id;
            // نُنشئ كود جافاسكربت لفتْح نافذة طباعة جديدة بعد انتهاء عملية الحفظ
            $openPrintScript = "<script>
                // فتح علامة تبويب جديدة للطباعة - سيستلمها المتصفح
                window.open('$print_url', '_blank');
            </script>";
        }
    } else {
        $error = "لا يوجد أصناف صحيحة للحفظ. تأكد من اختيار دواء وإدخال كمية صحيحة.";
    }
} // نهاية معالجة POST

// -----------------------------
// جلب قائمة الأدوية لعرضها في select (ستُستعمل لبناء صفوف الأصناف الموجودة في النموذج).
// -----------------------------
$medicines = $pdo->query("SELECT * FROM medicines ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// -----------------------------
// حالة: عرض صفحة الطباعة (عند ?print_id=123) -> نبني واجهة الطباعة الجميلة.
// هذه الفقرة تعرض نسخة قابلة للطباعة من الفاتورة المحفوظة مع عد الصفحات في الرأس (اعتماد المتصفح).
// -----------------------------
if (isset($_GET['print_id']) && is_numeric($_GET['print_id'])) {
    $pid = (int)$_GET['print_id'];
    // جلب الفاتورة
    $stmt = $pdo->prepare("SELECT * FROM invoices3 WHERE id = ?");
    $stmt->execute([$pid]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$invoice) {
        echo "فاتورة غير موجودة للطباعة.";
        exit;
    }
    // جلب عناصر الفاتورة
    $stmt = $pdo->prepare("SELECT ii.*, m.name as med_name FROM invoice_items ii LEFT JOIN medicines m ON m.medicineid = ii.medicine_id WHERE ii.invoice_id = ?");
    $stmt->execute([$pid]);
    $itemsForPrint = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // تابع لتحويل المبلغ إلى كلمات لاستخدامه داخل الطباعة
    $totalWords = numberToArabicWords($invoice['total_amount']);

    // نبدأ إخراج نسخة الطباعة (HTML بسيط مع CSS للطباعة)
    ?>
    <!DOCTYPE html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <title>طباعة فاتورة - <?= htmlspecialchars($invoice['invoice_number']) ?></title>
        <style>
            /* تنسيقات الطباعة */
            @media print {
                @page { margin: 20mm; }
                body { font-family: Tahoma, Arial; font-size: 14px; }
                .no-print { display: none !important; }
            }
            body { direction: rtl; font-family: Tahoma, Arial; margin: 0; padding: 20px; }
            .invoice-box { width: 100%; border: 1px solid #ddd; padding: 20px; box-sizing: border-box; }
            header.invoice-header { text-align: center; margin-bottom: 10px; position: relative; }
            header.invoice-header img { max-height: 60px; float: left; }
            .shop-info { text-align: center; }
            .invoice-type { font-size: 20px; font-weight: bold; margin: 6px 0; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th, td { border: 1px solid #ccc; padding: 8px; text-align: center; }
            .totals { margin-top: 12px; text-align: left; }
            /* محاولة لإظهار عد الصفحات في رأس كل صفحة */
            .page-num { position: fixed; top: 5px; left: 10px; font-size: 12px; }
            /* استخدام عداد الصفحات عبر CSS (قد يعمل في المتصفحات الحديثة عند الطباعة) */
            .page-num:after { content: "صفحة " counter(page) " من " counter(pages); }
        </style>
    </head>
    <body>
        <div class="invoice-box">
            <header class="invoice-header">
                <div style="display:flex; justify-content: space-between; align-items: center;">
                    <div style="text-align:left;">
                        <!-- شعار المحل -->
                        <img src="abd.png" alt="شعار المحل" onerror="this.style.display='none'">
                    </div>
                    <div class="shop-info">
                        <!-- اسم المحل والعنوان والهاتف -->
                        <div style="font-size:18px; font-weight:bold;">صيدلة الشرية المركزية</div>
                        <div>  صنعاء-بني حشيش- الشرية</div>
                                   <div>هاتف: 01630027 - 778199909-773743725</div>
                        <div class="invoice-type"><?= ($invoice['type'] == 'sale') ? 'فاتورة بيع' : 'فاتورة شراء' ?></div>
                    </div>
                    <div style="text-align:right;">
                        <!-- بيانات الفاتورة العامة -->
                        <div>رقم الفاتورة: <strong><?= htmlspecialchars($invoice['invoice_number']) ?></strong></div>
                        <div>التاريخ: <strong><?= htmlspecialchars($invoice['date']) ?></strong></div>
                        <div>اسم العميل/المورد: <strong><?= htmlspecialchars($invoice['customer_supplier_name']) ?></strong></div>
                    </div>
                </div>
            </header>

            <div class="page-num"></div>

            <table>
                <thead>
                    <tr>
                        <th>م</th>
                        <th>اسم الصنف</th>
                        <th>الكمية</th>
                        <th>سعر الوحدة</th>
                        <th>المجموع</th>
                        <th>ملاحظة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i=1; foreach($itemsForPrint as $it): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($it['med_name']) ?></td>
                            <td><?= htmlspecialchars($it['quantity']) ?></td>
                            <td><?= number_format($it['unit_price'],2) ?></td>
                            <td><?= number_format($it['total_price'],2) ?></td>
                            <td><?= isset($it['note']) ? htmlspecialchars($it['note']) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="totals">
                <div><strong>الإجمالي بالأرقام:</strong> <?= number_format($invoice['total_amount'], 2) ?> ج.م</div>
                <div><strong>الإجمالي كتابة:</strong> <?= htmlspecialchars($totalWords) ?> </div>
                <?php if (!empty($invoice['notes'])): ?>
                    <div style="margin-top:8px;"><strong>ملاحظات:</strong> <?= htmlspecialchars($invoice['notes']) ?></div>
                <?php endif; ?>
            </div>

        </div>

        <script>
            // بعد فتح النافذة نطلب من المتصفح تنفيذ أمر الطباعة تلقائيًا
            window.onload = function() {
                // أمر الطباعة؛ يُمكن للمستخدم إلغاؤه أو استعراضه
                window.print();
            };
        </script>
    </body>
    </html>
    <?php
    exit;
} // نهاية قسم الطباعة


// -----------------------------
// الآن نعرض واجهة إنشاء الفاتورة والتعامل مع المستخدم (الصفحة الرئيسية).
// -----------------------------
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إنشاء فاتورة - صيدلة الشرية المركزية</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- رابط Bootstrap CDN بسيط للتنسيق -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <style>
        body { direction: rtl; font-family: Tahoma, Arial; background: #f4f6f9; padding: 20px; }
        .container { max-width: 1100px; margin: auto; }
        .card { padding: 18px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); background: #fff; }
        header.topbar { display:flex; justify-content: space-between; align-items:center; margin-bottom: 12px; }
        header.topbar img { max-height: 60px; }
        header.topbar .shop { text-align:center; flex:1; }
        .controls { text-align: left; } /* الأزرار على اليمين كما طلبت (لكن الصفحة RTL) سنجعلها على يمين الcontainer */
        .items { margin-top: 12px; }
        .item-row { display:flex; gap:8px; align-items:center; margin-bottom:8px; }
        .item-row select, .item-row input { padding:6px; border:1px solid #ccc; border-radius:6px; }
        .item-row textarea { padding:6px; border:1px solid #ccc; border-radius:6px; resize:vertical; }
        .btn-add { margin-top:10px; display:block; }
        .totals-area { display:flex; justify-content: space-between; align-items:center; margin-top:15px; }
        .totals-area .words { font-weight:bold; }
        .btn-save { margin-left:8px; }
        .success { background:#e6ffed; padding:8px; border-radius:6px; color:#0a6a2b; }
        .error { background:#ffe6e6; padding:8px; border-radius:6px; color:#9b1c1c; }
        /* تنسيقات الطباعة داخل النافذة الرئيسية (ليست نسخة الطباعة) */
        @media print {
            body * { visibility: hidden; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">

            <!-- رأس الفاتورة: شعار واسم المحل والعنوان -->
            <header class="topbar">
                <div style="width:120px; text-align:left;">
                    <img src="abd.jpg" alt="شعار المحل" onerror="this.style.display='none'">
                </div>
                <div class="shop">
                    <div style="font-size:20px; font-weight:bold;">صيدلة الشرية المركزية</div>
                    <div>العنوان: صنعاء -بني حشيش- الشرية  - </div>
                    <div>هاتف: 01360027 - 778199909-773743725</div>
                </div>
                <div class="controls">
                    <!-- الأزرار في أعلى اليمين كما طلبت -->
                    <a href="index.php" class="btn btn-secondary btn-sm">الرئيسية</a>
                    <a href="invoice_all.php" class="btn btn-secondary btn-sm">قائمة الفواتير</a>
                    <a href="customers.php" class="btn btn-secondary btn-sm">العملاء</a>
                </div>
            </header>

            <h2 style="text-align:center; margin-bottom:10px;">نوع الفاتورة</h2>

            <!-- عرض رسائل النجاح/الخطأ -->
            <?php if (isset($success)): ?>
                <div class="success mb-2"><?= $success ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="error mb-2"><?= $error ?></div>
            <?php endif; ?>
            <?php if (isset($openPrintScript)) { echo $openPrintScript; } ?>

            <!-- نموذج الفاتورة -->
            <form method="POST" id="invoiceForm">
                <div class="row g-2">
                    <div class="col-md-4">
                        <!-- اختيار نوع الفاتورة -->
                        <label>نوع الفاتورة</label>
                        <select name="type" id="typeSelect" class="form-control" required>
                            <option value="">اختر نوع الفاتورة</option>
                            <option value="purchase">شراء من مورد</option>
                            <option value="sale">بيع لعميل</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <!-- اسم العميل/المورد -->
                        <label>اسم العميل/المورد</label>
                        <input type="text" name="customer_supplier" class="form-control" placeholder="اكتب اسم العميل/المورد" required>
                        <small class="text-muted">لو اخترت اسم من قائمة العملاء سيؤثر على حسابه، وإلا سيسجل كاسم فقط.</small>
                    </div>
                    <div class="col-md-4">
                        <!-- التاريخ -->
                        <label>التاريخ</label>
                        <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <!-- الأصناف -->
                <div id="items" class="items">
                    <h4 style="margin-top:12px;">الأصناف</h4>

                    <!-- بداية صف عنصر افتراضي واحد -->
                    <div class="item-row" data-index="0">
                        <!-- حقل اختيار الدواء -->
                        <select name="medicine_id[]" class="form-select form-select-sm" onchange="onMedicineChange(this)" required>
                            <option value="">اختر دواء</option>
                            <?php foreach ($medicines as $m): ?>
                                <option value="<?= htmlspecialchars($m['medicineid']) ?>"
                                        data-price="<?= htmlspecialchars($m['price_sales'] ?? $m['price'] ?? 0) ?>"
                                        data-name="<?= htmlspecialchars($m['name']) ?>"
                                >
                                    <?= htmlspecialchars($m['name']) ?> - <?= htmlspecialchars($m['price_sales'] ?? $m['price'] ?? 0) ?> ج.م
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- حقل الكمية -->
                        <input type="number" name="quantity[]" class="form-control form-control-sm" placeholder="الكمية" min="1" value="1" oninput="recalcTotals()" required>

                        <!-- حقل سعر الوحدة (يمكن تعديله يدوياً) -->
                        <input type="number" name="unit_price[]" class="form-control form-control-sm" placeholder="سعر الوحدة" min="0" step="0.01" oninput="recalcTotals()" required>

                        <!-- حقل ملاحظة للصنف -->
                        <textarea name="note[]" rows="1" class="form-control form-control-sm" placeholder="ملاحظة/وصف للدواء (اختياري)"></textarea>

                        <!-- زر حذف هذا الصنف -->
                        <button type="button" class="btn btn-danger btn-sm" onclick="removeItem(this)" title="حذف الصنف">حذف</button>
                    </div>
                    <!-- نهاية صف عنصر افتراضي واحد -->

                    <!-- زر إضافة صنف: يقع تحت قائمة الأصناف كما طلبت -->
                    <button type="button" class="btn btn-primary btn-sm btn-add" onclick="addItem()">+ إضافة صنف</button>
                </div>

                <!-- حقل ملاحظة عام للفاتورة -->
                <div style="margin-top:12px;">
                    <label>ملاحظة (اختياري)</label>
                    <textarea name="invoice_note" class="form-control" rows="2" placeholder="اكتب ملاحظة عامة عن الفاتورة"></textarea>
                </div>

                <!-- الجزء الخاص بالمجاميع: الأرقام على اليسار والكلمات على اليمين -->
                <div class="totals-area">
                    <div>
                        <button type="submit" name="create_invoice" class="btn btn-success btn-save">حفظ الفاتورة</button>
                        <button type="submit" name="create_and_print" class="btn btn-outline-primary btn-save">حفظ و طباعة</button>
                    </div>
                    <div style="text-align:left;">
                        <div><strong>الإجمالي بالأرقام:</strong> <span id="totalNumber">0.00</span> ج.م</div>
                        <div class="words"><strong>الإجمالي كتابة:</strong> <span id="totalWords">صفر</span></div>
                    </div>
                </div>

            </form>

            <!-- سجل الفواتير المحفوظة (جزءك القديم) -->
            <h3 style="margin-top: 20px;">سجل الفواتير</h3>
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
                    <tbody id="invoicesList">
                        <?php
                        // جلب الفواتير للعرض
                        $stmt = $pdo->prepare("SELECT * FROM invoices3 ORDER BY date DESC, id DESC LIMIT 50");
                        $stmt->execute();
                        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        if ($invoices && count($invoices)>0):
                            foreach ($invoices as $inv):
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($inv['invoice_number']) ?></td>
                            <td><?= ($inv['type'] == 'purchase') ? 'شراء' : 'بيع' ?></td>
                            <td><?= htmlspecialchars($inv['customer_supplier_name']) ?></td>
                            <td><?= htmlspecialchars($inv['date']) ?></td>
                            <td><?= number_format($inv['total_amount'],2) ?> ج.م</td>
                            <td>
                                <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) . '?print_id=' . $inv['id'] ?>" target="_blank" class="btn btn-primary btn-sm">طباعة</a>
                                <a href="invoice_items.php?id=<?= $inv['id'] ?>" class="btn btn-info btn-sm">تفاصيل</a>
                                <a href="edit_invoice.php?id=<?= $inv['id'] ?>" class="btn btn-warning btn-sm">تعديل</a>
                            </td>
                        </tr>
                        <?php
                            endforeach;
                        else:
                        ?>
                        <tr><td colspan="6" class="text-center">لا توجد فواتير محفوظة</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div> <!-- نهاية الكارد -->
    </div> <!-- نهاية الكونتينر -->

    <!-- السكربتات: إضافة/حذف عناصر وحساب الإجمالي واستدعاء تحويل الرقم إلى كلمات من الخادم -->
    <script>
        // ملاحظة: نستخدم وظائف بسيطة لإدارة صفوف الأصناف وحساب الإجمالي.
        // عند تغيير الكمية أو السعر يعاد حساب الإجمالي تلقائيًا.
        // للحصول على النص العربي للكلمات نرسل طلب GET إلى نفس الصفحة مع ?action=words&num=...

        // دالة تضيف صف صنف جديد أسفل الأصناف
        function addItem() {
            const itemsDiv = document.getElementById('items');
            // العثور على آخر صف عنصر للحصول على مؤشر فريد (غير ضروري ولكنه يساعد)
            const rows = itemsDiv.querySelectorAll('.item-row');
            const newIndex = rows.length; // فهرس جديد
            // ننشىء عنصر DOM جديد لصف العنصر
            const div = document.createElement('div');
            div.className = 'item-row';
            div.dataset.index = newIndex;

            // نُنشئ HTML داخلي للصف الجديد (مطابق للصف الافتراضي)
            // ملاحظة: نستعمل نفس قائمة الأدوية الموجودة في الصفحة الحالية
            const selectHtml = `<?php
                // توليد قائمة الخيارات كسلسلة واحدة لإعادة استخدامها في JS
                ob_start();
            ?><?php foreach ($medicines as $m): ?>
<option value="<?= htmlspecialchars($m['medicineid']) ?>" data-price="<?= htmlspecialchars($m['price_sales'] ?? $m['price'] ?? 0) ?>" data-name="<?= htmlspecialchars($m['name']) ?>"><?= htmlspecialchars($m['name']) ?> - <?= htmlspecialchars($m['price_sales'] ?? $m['price'] ?? 0) ?> ج.م</option>
<?php endforeach; ?><?php $opts = trim(ob_get_clean()); echo '<select name="medicine_id[]" class="form-select form-select-sm" onchange="onMedicineChange(this)"><option value=\"\">اختر دواء</option>' . $opts . '</select>'; ?>`;

            div.innerHTML = selectHtml +
                '<input type="number" name="quantity[]" class="form-control form-control-sm" placeholder="الكمية" min="1" value="1" oninput="recalcTotals()" required>' +
                '<input type="number" name="unit_price[]" class="form-control form-control-sm" placeholder="سعر الوحدة" min="0" step="0.01" oninput="recalcTotals()" required>' +
                '<textarea name="note[]" rows="1" class="form-control form-control-sm" placeholder="ملاحظة/وصف للدواء (اختياري)"></textarea>' +
                '<button type="button" class="btn btn-danger btn-sm" onclick="removeItem(this)" title="حذف الصنف">حذف</button>';

            // إدراج قبل زر الإضافة
            const addBtn = itemsDiv.querySelector('.btn-add');
            itemsDiv.insertBefore(div, addBtn);

            // إعادة حساب الإجمالي بعد الإضافة
            recalcTotals();
        }

        // دالة لإزالة صف عنصر (زر الحذف يستعملها)
        function removeItem(btn) {
            const row = btn.closest('.item-row');
            if (!row) return;
            row.remove();
            recalcTotals();
        }

        // عند تغيير اختيار الدواء نضع السعر المبدئي في حقل السعر تلقائياً
        function onMedicineChange(sel) {
            const price = sel.options[sel.selectedIndex].dataset.price || 0;
            // نبحث الحقل المجاور لسعر الوحدة ونملؤه
            const row = sel.closest('.item-row');
            if (!row) return;
            const priceInput = row.querySelector('input[name="unit_price[]"]');
            if (priceInput) {
                priceInput.value = parseFloat(price).toFixed(2);
            }
            recalcTotals();
        }

        // دالة لحساب المجموع الإجمالي وتحديث العرض الرقمي والنصي
        function recalcTotals() {
            let total = 0.0;
            const rows = document.querySelectorAll('.item-row');
            rows.forEach(row => {
                const qtyEl = row.querySelector('input[name="quantity[]"]');
                const priceEl = row.querySelector('input[name="unit_price[]"]');
                let q = qtyEl ? parseFloat(qtyEl.value) : 0;
                let p = priceEl ? parseFloat(priceEl.value) : 0;
                if (isNaN(q) || q < 0) q = 0;
                if (isNaN(p) || p < 0) p = 0;
                total += q * p;
            });
            // عرض الإجمالي الرقمي
            document.getElementById('totalNumber').innerText = total.toFixed(2);
            // طلب تحويل الرقم إلى كلمات من الخادم (AJAX GET)
            fetch('?action=words&num=' + encodeURIComponent(total))
                .then(r => r.json())
                .then(data => {
                    if (data && data.words !== undefined) {
                        // إذا جاءت الكلمات فارغة نظهر "صفر"
                        document.getElementById('totalWords').innerText = data.words || 'صفر';
                    }
                })
                .catch(err => {
                    // في حال فشل الاتصال نظهر النص البديل
                    document.getElementById('totalWords').innerText = '...';
                    console.error('خطأ في تحويل الرقم إلى كلمات:', err);
                });
        }

        // نعيد حساب المجموع عند تحميل الصفحة لأول مرة
        window.addEventListener('load', function() {
            recalcTotals();
        });

    </script>
</body>
</html>
