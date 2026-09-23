<?php
// ========================== invoice_template_full.php ==========================
// نسخة مُحسّنة مع تعليقات عربية تفصيلية + إصلاحات حسب طلبك
// ==============================================================================

// ------------------------------ إعداد عام -------------------------------------
session_start();                                        // بدء جلسة لتتبع المستخدم
require_once 'db.php';                                  // استدعاء الاتصال بقاعدة البيانات — يجب أن يعرّف $pdo (كائن PDO)

// ----------------- التحقق من تسجيل الدخول وجلب بيانات المستخدم ---------------
if (!isset($_SESSION['userid'])) {                     // إن لم يكن المستخدم مسجّل دخول
    header('Location: login.php');                      // إعادة توجيه لصفحة الدخول
    exit;                                               // إيقاف التنفيذ
}
$current_user_id = (int)$_SESSION['userid'];           // تحويل معرف المستخدم إلى رقم صحيح للحماية
$usr = $pdo->prepare(                                   // تجهيز استعلام لجلب بيانات المستخدم من جدول users
    "SELECT userid, name, username FROM users WHERE userid = ?"
);
$usr->execute([$current_user_id]);                      // تنفيذ الاستعلام بالمعرّف الحالي
$current_user = $usr->fetch(PDO::FETCH_ASSOC);          // جلب صفّ المستخدم كـ مصفوفة ترابطية
$entered_by_default =                                   // الاسم المعروض في الفاتورة (قابل للتعديل من الواجهة)
    $current_user['name']                               
        ?? $current_user['username']                    
        ?? 'غير معروف';                                

// --------------------------- التحقق من معرف العميل ----------------------------
if (!isset($_GET['customer_id']) || !is_numeric($_GET['customer_id'])) { 
    die('معرف العميل غير صحيح');                       // إيقاف بتنبيه إذا لم يصل المعرف بشكل صحيح
}
$customer_id = (int)$_GET['customer_id'];               // تثبيت نوع البيانات

// ------------------------------ جلب بيانات العميل -----------------------------
$cs = $pdo->prepare(                                    // تجهيز استعلام لجلب بيانات العميل
    "SELECT id, name, phone, address FROM customers WHERE id = ?"
);
$cs->execute([$customer_id]);                           // تنفيذ بالمعرف القادم من الرابط
$customer = $cs->fetch(PDO::FETCH_ASSOC);               // الحصول على بيانات العميل
if (!$customer) die('العميل غير موجود');               // إيقاف إذا لم يوجد عميل بهذا المعرف

// ---------------------------- رصيد العميل الحالي ------------------------------
$balStmt = $pdo->prepare(                               // تجهيز استعلام لجلب الرصيد من جدول حسابات العملاء
    "SELECT balance FROM customer_accounts WHERE customer_id = ?"
);
$balStmt->execute([$customer_id]);                      // تنفيذ الاستعلام
$balanceRow = $balStmt->fetch(PDO::FETCH_ASSOC);        // جلب صف الرصيد
$customer_balance = $balanceRow ? (float)$balanceRow['balance'] : 0.0; // تحويل إلى رقم أو صفر

// ------------------------- جلب الأصناف (الأدوية) ------------------------------
$medStmt = $pdo->prepare("SELECT * FROM medicines ORDER BY name"); // أصناف مرتبة بالاسم
$medStmt->execute();                                     // تنفيذ
$medicines = $medStmt->fetchAll(PDO::FETCH_ASSOC);       // جلب كل الأدوية

// --------- تقدير رقم الفاتورة/العملية التالي قبل الحفظ (للعرض فقط) ----------
// ملاحظة: نستعمل MAX(id)+1 بدلاً من AUTO_INCREMENT لضمان ظهور رقم متغيّر على الشاشة
$nextRow = $pdo->query("SELECT COALESCE(MAX(id),0)+1 AS next_id FROM invoices3")->fetch(PDO::FETCH_ASSOC);
$next_id_estimate = (int)($nextRow['next_id'] ?? 1);     // إذا لا يوجد فواتير يرجع 1
$provisional_invoice_number = 'INV-' . $next_id_estimate; // رقم فاتورة مبدئي للعرض قبل الحفظ
$provisional_operation_number = $next_id_estimate;        // رقم عملية مبدئي (رقمي متسلسل)

// ------------------------------ بيانات المحل ----------------------------------
$company = [
    'name_ar' => 'صيدلية الشرية المركزية للأدوية',      // اسم عربي
    'name_en' => 'pharmacy AL SHARIH Stores for Medicines', // اسم إنجليزي
    'phones'  => ['779550018','778199909','773743725'], // أرقام الهواتف
    'fax'     => '01360027',                             // فاكس
    'logo'    => 'abd.jpg',                              // مسار الشعار
];

// -------------------------- معالجة الحفظ (إن وُجد POST) -----------------------
$error = null;                                          // متغير لتجميع أي خطأ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_sale'])) { // عند الضغط "حفظ الفاتورة"

    // ----------------------- قراءة قيم النموذج المرسلة ------------------------
    $date = $_POST['date'] ?? date('Y-m-d');            // تاريخ الفاتورة
    $invoice_title = trim($_POST['invoice_title'] ?? 'فاتورة بيع أجل'); // عنوان الفاتورة
    $note = trim($_POST['note'] ?? '');                  // الملاحظات
    $paid = isset($_POST['paid']) ? (float)$_POST['paid'] : 0.0; // المدفوع الآن
    $entered_by_name = trim($_POST['entered_by_name'] ?? $entered_by_default); // اسم المستخدم المعروض (قابل للتعديل من الواجهة)

    // مصفوفات الأصناف
    $ids   = $_POST['medicine_id'] ?? [];               // معرفات الأدوية
    $qtys  = $_POST['quantity'] ?? [];                  // الكميات
    $units = $_POST['unit'] ?? [];                      // الوحدات
    $expiry_dates = $_POST['expiredate'] ?? [];        // تواريخ الانتهاء

    $total = 0.0;                                       // إجمالي الفاتورة
    $items = [];                                        // عناصر الفاتورة (للحفظ لاحقاً)

    // ----------------------- التحقق وتجهيز عناصر الفاتورة ---------------------
    for ($i = 0; $i < count($ids); $i++) {              // المرور على كل سطر
        $mid = (int)$ids[$i];                           // معرف الدواء
        $q   = isset($qtys[$i]) ? (int)$qtys[$i] : 0;   // الكمية
        $u   = trim($units[$i] ?? 'باكت');              // الوحدة (افتراضي باكت)
        $exp = trim($expiry_dates[$i] ?? '');           // تاريخ الانتهاء إن وُجد

        if ($mid > 0 && $q > 0) {                       // شرط وجود صنف + كمية صحيحة
            $s = $pdo->prepare(                         // جلب بيانات الدواء (السعر/المخزون)
                "SELECT medicineid, name, price_sales, quantity 
                 FROM medicines 
                 WHERE medicineid = ?"
            );
            $s->execute([$mid]);                        // تنفيذ
            $m = $s->fetch(PDO::FETCH_ASSOC);           // صف الدواء
            if (!$m) { $error = 'الدواء غير موجود'; break; } // تحقق من الوجود
            if ($m['quantity'] < $q) {                  // التأكد من المخزون
                $error = 'الكمية غير كافية للدواء: ' . $m['name']; 
                break;
            }

            $unit_price = (float)$m['price_sales'];     // سعر الوحدة
            $line_total = $unit_price * $q;             // إجمالي السطر = السعر × الكمية
            $total += $line_total;                      // إضافة إلى الإجمالي العام

            $items[] = [                                // تخزين السطر لمرحلة الإدخال
                'medicine_id' => $m['medicineid'],
                'name'        => $m['name'],
                'quantity'    => $q,
                'unit'        => $u,
                'unit_price'  => $unit_price,
                'line_total'  => $line_total,
                'row_date'    => $exp ?: $date,         // إن لم يُدخل تاريخ انتهاء نضع تاريخ الفاتورة
            ];
        }
    }

    // ------------------------------ تنفيذ الحفظ --------------------------------
    if (empty($error) && count($items) > 0) {
        try {
            $pdo->beginTransaction();                   // بدء معاملة (Transaction)

            // ندخل الفاتورة برقم مؤقت ثم نحدّثه بعد الحصول على id الحقيقي
            $temp_inv = 'TEMP';                         
            $ins = $pdo->prepare(
                "INSERT INTO invoices3 
                 (invoice_number, type, customer_id, total_amount, date, title, note, paid, created_by) 
                 VALUES (?, 'sale', ?, ?, ?, ?, ?, ?, ?)"
            );
            $ins->execute([$temp_inv, $customer_id, $total, $date, $invoice_title, $note, $paid, $current_user_id]);

            $invoice_id = (int)$pdo->lastInsertId();    // هذا هو الرقم الحقيقي المتسلسل والفريد

            // تكوين رقم الفاتورة النهائي بصيغة INV-{id}
            $final_invoice_number = 'INV-' . $invoice_id;

            // تحديث حقل رقم الفاتورة
            $updNum = $pdo->prepare("UPDATE invoices3 SET invoice_number = ? WHERE id = ?");
            $updNum->execute([$final_invoice_number, $invoice_id]);

            // إدخال تفاصيل الأصناف + تحديث المخزون
            $insertItem = $pdo->prepare(
                "INSERT INTO invoice_items 
                 (invoice_id, medicine_id, quantity, unit, unit_price, total_price, row_date) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $updateQty = $pdo->prepare(                 // إصلاح العمود إلى medicineid (بدلاً من id)
                "UPDATE medicines SET quantity = quantity - ? WHERE medicineid = ?"
            );

            foreach ($items as $it) {                   // إدخال كل سطر وتخفيض المخزون
                $insertItem->execute([$invoice_id, $it['medicine_id'], $it['quantity'], $it['unit'], $it['unit_price'], $it['line_total'], $it['row_date']]);
                $updateQty->execute([$it['quantity'], $it['medicine_id']]);
            }

            // تسجيل معاملة في حركة حساب العميل
            $tx = $pdo->prepare(
                "INSERT INTO customer_transactions 
                 (customer_id, invoice_id, transaction_type, amount, description) 
                 VALUES (?, ?, 'sale', ?, ?)"
            );
            $tx->execute([$customer_id, $invoice_id, $total, 'فاتورة مبيعات رقم ' . $final_invoice_number]);

            // تحديث رصيد العميل (المتبقي = الإجمالي - المدفوع)
            $upd = $pdo->prepare("UPDATE customer_accounts SET balance = balance + ? WHERE customer_id = ?");
            $upd->execute([$total - $paid, $customer_id]);

            $pdo->commit();                             // اعتماد المعاملة

            // إعادة توجيه لإظهار الأرقام النهائية للفاتورة/العملية
            header('Location: invoice_template_full.php?customer_id=' . $customer_id . '&success=1&inv=' . urlencode($final_invoice_number) . '&inv_id=' . $invoice_id);
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();                           // إرجاع المعاملة عند الخطأ
            error_log('خطأ في حفظ الفاتورة: ' . $e->getMessage()); // تسجيل الخطأ للسجل
            $error = 'حدث خطأ أثناء حفظ الفاتورة';      // رسالة للمستخدم
        }
    } elseif (count($items) === 0) {                     // في حال لم تُضف أي أصناف
        $error = 'الرجاء إضافة صنف واحد على الأقل';
    }
}

// ---------------- دوال تحويل الأرقام إلى كلمات عربية (للعرض والكتابة) --------
function numberToArabicWords($number) {                 // تحويل رقم عشري إلى نص عربي (ريال + فلس)
    $number = number_format((float)$number, 2, '.', ''); // تهيئة بدقتين عشريتين
    list($intPart, $fraction) = explode('.', $number);  // فصل الصحيح عن الكسري
    $intPart = (int)$intPart;                           // الجزء الصحيح
    $fraction = (int)$fraction;                         // الجزء الكسري (فلس)
    $intWords = $intPart === 0 ? 'صفر' : intToWordsArabic($intPart); // نص الجزء الصحيح
    $result = $intWords . ' ريال';                      // إضافة العملة
    if ($fraction > 0) {                                // إن وُجد كسور
        $result .= ' و ' . intToWordsArabic($fraction) . ' فلس';
    }
    $result .= ' فقط لا غير';                           // صيغة قانونية
    return $result;
}

function intToWordsArabic($num) {                       // تحويل أعداد صحيحة حتى المليارات
    $ones = ['', 'واحد','اثنان','ثلاثة','أربعة','خمسة','ستة','سبعة','ثمانية','تسعة','عشرة',
        'أحد عشر','اثنا عشر','ثلاثة عشر','أربعة عشر','خمسة عشر','ستة عشر','سبعة عشر','ثمانية عشر','تسعة عشر'];
    $tens = ['', '', 'عشرون','ثلاثون','أربعون','خمسون','ستون','سبعون','ثمانون','تسعون'];
    $hundreds = ['', 'مائة','مائتان','ثلاثمائة','أربعمائة','خمسمائة','ستمائة','سبعمائة','ثمانمائة','تسعمائة'];

    if ($num == 0) return 'صفر';                        // معالجة الصفر
    $parts = [];                                        // حاوية أجزاء النص

    $levels = [                                         // مستويات الآلاف/الملايين/المليارات
        ['value' => 1000000000, 'name' => 'مليار'],
        ['value' => 1000000,    'name' => 'مليون'],
        ['value' => 1000,       'name' => 'ألف'],
        ['value' => 1,          'name' => ''],
    ];

    foreach ($levels as $lvl) {                         // الدوران على المستويات
        $value = $lvl['value'];
        if ($num >= $value) {                           // إذا يوجد جزء من هذا المستوى
            $count = intdiv($num, $value);              // عدد الوحدات في هذا المستوى
            $num = $num % $value;                       // المتبقي
            $words = triadToArabic($count, $ones, $tens, $hundreds); // كتابة العدد
            if ($lvl['name'] != '') {                   // إضافة اسم المستوى
                if     ($count == 1) $parts[] = $lvl['name'];
                elseif ($count == 2) $parts[] = ($lvl['name'] == 'ألف' ? 'ألفان' : $lvl['name'] . 'ان');
                else   $parts[] = $words . ' ' . $lvl['name'] . 'ات';
            } else {
                $parts[] = $words;                      // الآحاد/العشرات/المئات
            }
        }
    }
    return implode(' و ', $parts);                       // تجميع النص النهائي
}

function triadToArabic($n, $ones, $tens, $hundreds) {   // تحويل جزء ثلاثي (حتى 999)
    $words = [];
    if ($n >= 100) {                                    // المئات
        $h = intdiv($n, 100);
        $words[] = $hundreds[$h];
        $n = $n % 100;
    }
    if ($n >= 20) {                                     // العشرات 20–90
        $t = intdiv($n, 10);
        $r = $n % 10;
        if ($r > 0) $words[] = $ones[$r] . ' و ' . $tens[$t];
        else         $words[] = $tens[$t];
    } elseif ($n > 0) {                                 // من 1 إلى 19
        $words[] = $ones[$n];
    }
    return implode(' و ', $words);
}

// --------------- تحديد ما سيُعرض قبل/بعد الحفظ من أرقام ----------------------
$display_invoice_number   = isset($_GET['inv'])    ? htmlspecialchars($_GET['inv'])    : $provisional_invoice_number; // إن وُجدت بعد الحفظ نعرضها
$display_operation_number = isset($_GET['inv_id']) ? (int)$_GET['inv_id']              : $provisional_operation_number; // رقم العملية (id)

// ==============================================================================
// =============================== الواجهة (HTML) ================================
// ==============================================================================

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">                                  <!-- ترميز -->
<meta name="viewport" content="width=device-width, initial-scale=1"> <!-- تجاوبية -->
<title>فاتورة - <?= htmlspecialchars($customer['name']) ?></title> <!-- عنوان الصفحة -->

<style>
        /* ============================= تنسيقات عامة ============================== */
        body{font-family:'Tahoma','Arial',sans-serif;background:#f3f6fb;margin:0;padding:18px;} /* خلفية ومسافات عامة */
        .paper{max-width:980px;margin:10px auto;background:#fff;padding:18px;box-shadow:0 8px 26px rgba(0,0,0,0.06);} /* ورقة الفاتورة */

        /* رأس الفاتورة — ثلاث أعمدة */
        .head{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:3px solid #e9eef6;padding-bottom:14px;}
        .head .right,.head .center,.head .left{flex:1;}
        .head .right{text-align:right;}
        .head .center{text-align:center;}
        .head .left{text-align:left;}
        .company-ar{font-weight:900;color:#b31f1f;font-size:20px;}         /* اسم عربي */
        .company-en{font-weight:700;color:#1f4a85;}                         /* اسم إنجليزي */
        .contact-info{font-size:13px;color:#333;margin-top:8px;line-height:1.45;} /* معلومات اتصال */
        .phones{margin-top:8px;} .phones div{margin-bottom:4px;}            /* هواتف */
        .logo-center img{width:110px;height:auto;display:block;margin:0 auto;} /* الشعار */
        .invoice-number{font-size:13px;margin-top:8px;color:#333;}          /* رقم الفاتورة */
        .operation-number{font-size:12px;color:#666;margin-top:6px;}        /* رقم العملية */

        /* مربعات بيانات العميل */
        .meta{display:flex;justify-content:space-between;gap:10px;margin-top:12px;}
        .meta .box{padding:10px;border:1px solid #eef5ff;background:#fff;font-size:13px;}

        /* جدول الأصناف */
        table.items{width:100%;border-collapse:collapse;margin-top:12px;}
        table.items th,table.items td{border:1px solid #e6eef8;padding:10px;font-size:13px;vertical-align:middle;}
        table.items th{background:#f2f7ff;color:#1a3696;}

        /* أزرار */
        .actions{margin-top:10px;display:flex;gap:8px;}
        .btn{padding:8px 12px;border-radius:6px;text-decoration:none;cursor:pointer;border:none;}
        .btn-primary{background:#1f4a85;color:#fff;}
        .btn-outline{background:transparent;border:1px solid #1f4a85;color:#1f4a85;}
        .btn-danger{background:#c0392b;color:#fff;}
        .note{font-weight:800; font-size:18px; color:#1a3696; }
        /* منطقة الإجماليات */
        .totals{display:flex;justify-content:space-between;align-items:center;margin-top:12px;gap:12px;}
        .totals .left,.totals .center,.totals .right{flex:1;}
        .totals .center{text-align:center;}
        .totals .right{text-align:right;}
        .notes{margin-top:12px;font-size:13px;}
        .footer{text-align:center;margin-top:14px;font-size:13px;color:#666;}
        .warning{color:#b31f1f;font-weight:700;}

        /* عناصر تظهر فقط في الطباعة/الإظهار */
        .no-print{display:inline;}
        .print-only{display:none;}

        /* عند الطباعة نخفي عناصر التحكم ونُظهر عدّاد الصفحات */
        @media print{
        .no-print{display:none !important;}                   /* إخفاء العناصر غير المراد طباعتها */
        .print-only{display:inline !important;}               /* إظهار عناصر الطباعة فقط */
        .page-counter::after{                                  /* كتابة Page X of Y بواسطة عدّادات CSS */
            content: "Page " counter(page) " of " counter(pages);
        }
        }
  </style>
</head>
<body>

<div class="paper">
    <!-- ========================== رأس الفاتورة =========================== -->
    <div class="head">
        <div class="right">
            <div class="company-ar"><?= htmlspecialchars($company['name_ar']) ?></div> <!-- اسم عربي -->
            <div class="phones">                                                        <!-- أرقام الاتصال -->
                <div>هاتف: <?= htmlspecialchars($company['phones'][0] ?? '-') ?></div>
                <div>هاتف: <?= htmlspecialchars($company['phones'][1] ?? '-') ?></div>
                <div>هاتف: <?= htmlspecialchars($company['phones'][2] ?? '-') ?></div>
                <div>فاكس: <?= htmlspecialchars($company['fax']) ?></div>
            </div>
        </div>
        
        <div class="center logo-center">
            <img src="<?= htmlspecialchars($company['logo']) ?>" alt="logo">            <!-- شعار -->
            <div class="invoice-number">رقم الفاتورة: <strong id="invoice-number-display"><?= $display_invoice_number ?></strong></div> <!-- رقم الفاتورة (مبدئي أو نهائي) -->
            <div class="operation-number">رقم العملية: <strong id="operation-number-display"><?= htmlspecialchars($display_operation_number) ?></strong></div> <!-- رقم العملية -->
        </div>
        
        <div class="left">
            <div class="company-en"><?= htmlspecialchars($company['name_en']) ?></div>  <!-- اسم إنجليزي -->
            <div class="contact-info">
                <div class="phones">                                                        <!-- أرقام الاتصال -->
                </div>
            </div>
                <!-- ترقيم الصفحات: على الشاشة نظهر نصًا ثابتًا، وفي الطباعة نظهر عدّاد CSS -->
                <div>
                    <span class="no-print">Page 1 of 1</span> 
                    <span class="print-only page-counter"></span>
                </div>
                <!-- اسم المستخدم المدخل (قابل للتعديل على الشاشة) -->
                <div>
                    المستخدم المدخل: 
                    <strong id="entered-by-display"><?= htmlspecialchars($entered_by_default) ?></strong>
                     <!-- <input class="no-print" type="text" id="entered-by-input" value="<?= htmlspecialchars($entered_by_default) ?>" style="margin-right:8px;padding:2px 6px;">  -->
                      <a href="customers.php">رجوع</a>
                </div>

            </div>
        </div>
    </div>

    <!-- =================== عنوان الفاتورة قابل للتعديل =================== -->
    <div style="text-align:center; margin-top:12px;">
        <input type="text" id="invoice_title" name="invoice_title_front" 
               value="<?= htmlspecialchars($_POST['invoice_title'] ?? 'فاتورة بيع أجل') ?>" 
               style="font-size:18px; font-weight:800; text-align:center; border:0; width:60%;" />
    </div>

    <!-- ========================== بيانات العميل ========================== -->
    <div class="meta">
        <div class="box">
            العميل: <strong><?= htmlspecialchars($customer['name']) ?></strong>
            &nbsp; الهاتف: <?= htmlspecialchars($customer['phone'] ?? '-') ?>
        </div>
        <div class="box" style="text-align:left;">
            رقم العميل: <strong><?= htmlspecialchars($customer_id) ?></strong><br>
            العنوان: <?= htmlspecialchars($customer['address'] ?? '-') ?>
        </div>
    </div>

    <!-- ========================== نموذج الفاتورة ========================= -->
    <form method="POST" id="saleForm">
        <!-- حقول خفية ومساعدة -->
        <input type="hidden" name="customer_id" value="<?= $customer_id ?>">                               <!-- معرف العميل -->
        <input type="hidden" name="invoice_number_hidden" id="invoice_number_hidden" value="<?= htmlspecialchars($display_invoice_number) ?>"> <!-- رقم الفاتورة المعروض -->
        <input type="hidden" name="invoice_title" id="invoice_title_input" value="<?= htmlspecialchars($_POST['invoice_title'] ?? 'فاتورة بيع أجل') ?>"> <!-- العنوان الذي سنرسله -->
        <input type="hidden" name="entered_by_name" id="entered_by_name" value="<?= htmlspecialchars($entered_by_default) ?>"> <!-- سنحدّثه من حقل الإدخال -->
        
        <!-- تاريخ الفاتورة -->
        <div style="margin-top:10px; font-size:13px;">
            تاريخ الفاتورة: 
            <input type="date" name="date" value="<?= htmlspecialchars($_POST['date'] ?? date('Y-m-d')) ?>" />
        </div>

        <!-- جدول الأصناف -->
        <table class="items">
            <thead>
                <tr>
                    <th style="width:40px;">م</th>
                    <th style="width:120px;">تاريخ الانتهاء</th>
                    <th>اسم الصنف</th>
                    <th style="width:120px;">الوحدة</th>
                    <th style="width:90px;">الكمية</th>
                    <th style="width:120px;">سعر الوحدة</th>
                    <th style="width:120px;">الإجمالي</th>
                    <th class="no-print" style="width:80px;">حذف</th>
                </tr>
            </thead>
            <tbody id="items-tbody">
                <tr class="item-row">
                    <td class="index">1</td>                                         <!-- رقم تسلسلي للسطر -->
                    <td><input type="date" name="expiry_date[]" value="" class="expiry-field"></td> <!-- تاريخ انتهاء -->
                    <td>
                        <select name="medicine_id[]" onchange="onChangeRow(this)" required>        <!-- اختيار الصنف -->
                            <option value="">اختر دواء</option>
                            <?php foreach ($medicines as $m): ?>
                                <option value="<?= $m['medicineid'] ?>" 
                                        data-price="<?= $m['price_sales'] ?>" 
                                        data-unit="<?= htmlspecialchars($m['unit'] ?? 'باكت') ?>" 
                                        data-expiry="<?= htmlspecialchars($m['expiredate'] ?? '') ?>">
                                    <?= htmlspecialchars($m['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="text" name="unit[]" value="باكت" placeholder="الوحدة (قابلة للتعديل)"></td> <!-- اسم الوحدة -->
                    <td><input type="number" name="quantity[]" min="1" value="1" onchange="calculateAll()" required></td> <!-- الكمية -->
                    <td class="unit-price">0.00</td>                                 <!-- سعر الوحدة (يملأ تلقائيًا) -->
                    <td class="line-total">0.00</td>                                  <!-- إجمالي السطر -->
                    <td class="no-print"><button type="button" onclick="removeRow(this)" class="btn btn-danger">إزالة</button></td> <!-- زر حذف السطر -->
                </tr>
            </tbody>
        </table>

        <!-- أزرار التحكم -->
        <div class="actions no-print">
            <button type="button" class="btn btn-outline" onclick="addRow()">+ إضافة صنف</button>
            <button type="button" class="btn btn-outline" onclick="undoRemove()">استرجاع آخر حذف</button>
            <button type="button" class="btn btn-outline" onclick="document.getElementById('saleForm').reset(); calculateAll();">إعادة ضبط</button>
            <button type="submit" name="create_sale" value="1" class="btn btn-primary">حفظ الفاتورة</button>
            <button type="button" class="btn btn-primary" onclick="printInvoice()">طباعة</button>
        </div>

        <!-- الإجماليات -->
        <div class="totals">
            <div class="left">
                <!-- المطلوب: يكون نص فقط على اليمين — لذا نكتب "كتابة:" هنا ونعرض النص الكامل -->
                كتابة: <span id="total-in-words"><?= htmlspecialchars(numberToArabicWords(0)) ?></span>
            </div>
            <div class="center">
                <P>استلمت البضاعه كامله وسليمه  </p>
                <div style="font-weight:800; font-size:18px;"> وتصبح مدينونتي  </div>
                <div id="remaining-center" style="font-weight:700; font-size:16px; color:#b31f1f;"><?= number_format($customer_balance,2) ?> ج.م</div>
                <div style="margin-top:6px; color:#333;">حتى تاريخ هذا المستند واتعهد بدفعها عند الطلب </div>
            </div>
            <div class="right">
                <!-- يسار/يمين حسب RTL: هنا "الإجمالي رقمي" يظهر كرقم -->
                الإجمالي رقمي: <strong id="grand-total">0.00 ج.م</strong>
                <div style="margin-top:6px;">مدفوع الآن: <input type="number" name="paid" id="paid" value="0" min="0" onchange="calculateRemaining()"></div>
            </div>
        </div>

        <!-- ملاحظات -->
        <div class="notes">
            <label>ملاحظات:</label>
            <textarea name="note" rows="3" style="width:100%;" placeholder="اكتب ملاحظات الفاتورة هنا..."><?= htmlspecialchars($_POST['note'] ?? '') ?></textarea>
        </div>
        <div >
            <p class="note">ملاحظة هامة:
                
          الادوية لاترد بعد خروجها من المحل
                            </div>
    </form>

    <!-- رسائل نجاح/خطأ -->
    <?php if ($error): ?>
        <div style="color:#c00; font-weight:700; margin-top:10px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['success'])): ?>
        <div style="color:#0a7b0a; font-weight:700; margin-top:10px;">تم حفظ الفاتورة بنجاح — رقم الفاتورة: <?= htmlspecialchars($_GET['inv'] ?? '') ?></div>
    <?php endif; ?>

    <!-- تذييل -->
    <div class="footer">
        تم إدخال الفاتورة بواسطة: 
        <strong id="footer-entered-by"><?= htmlspecialchars($entered_by_default) ?></strong>
        &nbsp; | تاريخ/وقت الإدخال: <?= date('Y-m-d H:i:s') ?>
    </div>
</div>

<!-- ============================= سكربتات الواجهة ============================= -->
<script>
// مكدس لاسترجاع الصفوف المحذوفة
let removedStack = [];

// إضافة صف جديد بنسخ أول صف وإعادة تهيئته
function addRow(){
    const tbody = document.getElementById('items-tbody');
    const first = tbody.querySelector('tr').cloneNode(true); // نسخ الصف الأول
    first.querySelector('.index').textContent = tbody.rows.length + 1; // رقم تسلسلي
    first.querySelector('select').selectedIndex = 0;         // تصفير الاختيار
    first.querySelector('[name="quantity[]"]').value = 1;    // كمية = 1
    first.querySelector('[name="unit[]"]').value = 'باكت';   // وحدة افتراضية
    first.querySelector('.unit-price').textContent = '0.00'; // سعر وحدة
    first.querySelector('.line-total').textContent = '0.00'; // إجمالي سطر
    first.querySelector('.expiry-field').value = '';         // مسح تاريخ الانتهاء
    tbody.appendChild(first);                                 // إضافة الصف
    attachEvents();                                           // ربط الأحداث
    calculateAll();                                          // إعادة حساب الإجمالي
}

// حذف صف مع حفظ نسخة للاسترجاع
function removeRow(btn){
    const row = btn.closest('tr');                            // الصف المعني
    removedStack.push(row.cloneNode(true));                   // حفظ نسخة
    row.remove();                                             // الحذف
    reindex();                                                // إعادة ترقيم السطور
    calculateAll();                                           // إعادة الحساب
}

// استرجاع آخر صف محذوف
function undoRemove(){
    if (removedStack.length === 0) return alert('لا يوجد عناصر محذوفة للاسترجاع');
    const last = removedStack.pop();                          // آخر نسخة
    document.getElementById('items-tbody').appendChild(last); // إرجاعها للجدول
    attachEvents();                                           // إعادة ربط الأحداث
    calculateAll();                                           // إعادة الحساب
}

// ربط أحداث التغيير لكل عناصر الجدول
function attachEvents(){
    document.querySelectorAll('#items-tbody select').forEach(s => s.onchange = function(){ onChangeRow(this); });
    document.querySelectorAll('[name="quantity[]"]').forEach(i => i.onchange = calculateAll);
}

// إعادة ترقيم العمود "م"
function reindex(){
    document.querySelectorAll('#items-tbody .index').forEach((el, idx) => el.textContent = idx + 1);
}

// عند تغيير الصنف: تعبئة سعر الوحدة/الوحدة/تاريخ الانتهاء
function onChangeRow(select){
    const opt = select.options[select.selectedIndex];         // الخيار الحالي
    const price  = parseFloat(opt.dataset.price || 0);        // السعر من data-price
    const unit   = opt.dataset.unit || 'باكت';                // الوحدة الافتراضية
    const expiry = opt.dataset.expiry || '';                  // تاريخ الانتهاء إن وُجد
    const row = select.closest('tr');
    row.querySelector('.unit-price').textContent = price.toFixed(2); // عرض السعر
    const expiryInput = row.querySelector('.expiry-field');
    if (expiryInput) expiryInput.value = expiry;              // ملء تاريخ الانتهاء
    const unitInput = row.querySelector('input[name="unit[]"]');
    if (unitInput) unitInput.value = unit || 'باكت';          // ملء الوحدة
    calculateAll();                                           // إعادة الحساب
}

// حساب إجمالي الفاتورة + تحديث الكتابة النصية
function calculateAll(){
    let grand = 0;                                            // الإجمالي
    document.querySelectorAll('#items-tbody tr').forEach((row, idx) => {
        const price = parseFloat(row.querySelector('.unit-price').textContent || 0); // سعر
        const qty   = parseInt(row.querySelector('[name="quantity[]"]').value || 0); // كمية
        const line  = price * qty;                              // إجمالي السطر
        row.querySelector('.line-total').textContent = line.toFixed(2); // عرض إجمالي السطر
        row.querySelector('.index').textContent = idx + 1;      // تحديث رقم السطر
        grand += line;                                          // جمع
    });
    document.getElementById('grand-total').textContent = grand.toFixed(2) + ' ج.م'; // عرض الإجمالي رقمي
    document.getElementById('total-in-words').textContent = numberToArabicWordsJS(grand); // كتابة نصية عربية كاملة
    calculateRemaining();                                      // تحديث المتبقي
}

// حساب المتبقي = الإجمالي - المدفوع
function calculateRemaining(){
    const paid  = parseFloat(document.getElementById('paid').value || 0);
    const grand = parseFloat(document.getElementById('grand-total').textContent.replace(' ج.م','') || 0);
    const remaining = grand - paid;
    document.getElementById('remaining-center').textContent = remaining.toFixed(2) + ' ج.م';
}

// طباعة: قبل الطباعة نحدّث الحقول المخفية من المدخلات القابلة للتعديل
function printInvoice(){
    document.getElementById('invoice_title_input').value = document.getElementById('invoice_title').value; // العنوان
    document.getElementById('entered_by_name').value = document.getElementById('entered-by-input').value;  // المستخدم المدخل
    window.print();                                          // أمر الطباعة
}

// مزامنة اسم المستخدم المدخل (العرض العلوي + التذييل)
document.getElementById('entered-by-input').addEventListener('input', function(){
    document.getElementById('entered-by-display').textContent = this.value; // في رأس الفاتورة
    document.getElementById('footer-entered-by').textContent   = this.value; // في أسفل الفاتورة
});

// قبل إرسال النموذج (حفظ الفاتورة) نمرّر الاسم المدخل
document.getElementById('saleForm').addEventListener('submit', function(){
    document.getElementById('invoice_title_input').value = document.getElementById('invoice_title').value;
    document.getElementById('entered_by_name').value     = document.getElementById('entered-by-input').value;
});

// -------------------- تحويل رقم إلى كلمات عربية (JavaScript) ------------------
// ملاحظة: هذه نسخة متوافقة مع التي في PHP وتعمل للأرقام الكبيرة بشكل مبسّط.
function numberToArabicWordsJS(number){
    number = Number(number || 0).toFixed(2);                   // تثبيت منزلتين
    const parts = number.split('.');
    const intPart = parseInt(parts[0],10);
    const fraction = parseInt(parts[1],10);

    const wordsInt = intToWordsArabicJS(intPart) || 'صفر';     // كتابة الجزء الصحيح
    let result = wordsInt + ' ريال';                           // العملة
    if (fraction > 0){
        result += ' و ' + intToWordsArabicJS(fraction) + ' فلس';
    }
    return result + ' فقط لا غير';
}

// دوال مساعدة لكتابة الأعداد حتى المليارات
function intToWordsArabicJS(num){
    if (num === 0) return 'صفر';
    const ones = ['', 'واحد','اثنان','ثلاثة','أربعة','خمسة','ستة','سبعة','ثمانية','تسعة','عشرة',
        'أحد عشر','اثنا عشر','ثلاثة عشر','أربعة عشر','خمسة عشر','ستة عشر','سبعة عشر','ثمانية عشر','تسعة عشر'];
    const tens = ['', '', 'عشرون','ثلاثون','أربعون','خمسون','ستون','سبعون','ثمانون','تسعون'];
    const hundreds = ['', 'مائة','مائتان','ثلاثمائة','أربعمائة','خمسمائة','ستمائة','سبعمائة','ثمانمائة','تسعمائة'];

    let parts = [];
    const levels = [
        {value: 1000000000, name: 'مليار'},
        {value: 1000000,    name: 'مليون'},
        {value: 1000,       name: 'ألف'},
        {value: 1,          name: ''},
    ];

    for (const lvl of levels){
        const value = lvl.value;
        if (num >= value){
            const count = Math.floor(num / value);
            num = num % value;
            const words = triadToArabicJS(count, ones, tens, hundreds);
            if (lvl.name !== ''){
                if (count === 1) parts.push(lvl.name);
                else if (count === 2) parts.push(lvl.name === 'ألف' ? 'ألفان' : (lvl.name + 'ان'));
                else parts.push(words + ' ' + lvl.name + 'ات');
            } else {
                parts.push(words);
            }
        }
    }
    return parts.join(' و ');
}

function triadToArabicJS(n, ones, tens, hundreds){
    let out = [];
    if (n >= 100){
        const h = Math.floor(n/100);
        out.push(hundreds[h]);
        n = n % 100;
    }
    if (n >= 20){
        const t = Math.floor(n/10);
        const r = n % 10;
        if (r > 0) out.push(ones[r] + ' و ' + tens[t]);
        else       out.push(tens[t]);
    } else if (n > 0){
        out.push(ones[n]);
    }
    return out.join(' و ');
}

// تشغيل أولي عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function(){
    attachEvents();           // ربط الأحداث للصف الأول
    calculateAll();           // حساب إجمالي البداية = 0
});
</script>

</body>
</html>