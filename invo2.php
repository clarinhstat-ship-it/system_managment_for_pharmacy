<?php

// session_start();
// if (!isset($_SESSION['user_id'])) {
//     header("Location: index.php");
//     exit;
//}

/*************** 2) استدعاء اتصال قاعدة البيانات ***************/
require_once 'db.php'; // يجب أن يُعرّف $pdo كـ PDO

/*************** 3) إعدادات بسيطة ***************/
$PRINT_PAGE = 'invoice_print.php'; // صفحة الطباعة  
$ITEMS_PER_PAGE = 18; //   تحجيم صفحات الطباعة 

/*************** 4) دوال مساعدة ***************/

/**
 * دالة لتحويل الأعداد الصحيحة إلى كلمات بالعربية (نسخة شاملة لغاية الملايين)
 * ملاحظات: هذه دالة لتسهيل العرض — يمكن تحسينها لتعامل الكسور (فلس/قرش) لاحقًا.
 */
function numberToArabicWords($number) {
    $units = ['', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة'];
    $tens = ['', 'عشرة', 'عشرون', 'ثلاثون', 'أربعون', 'خمسون', 'ستون', 'سبعون', 'ثمانون', 'تسعون'];
    $teens = [11 => 'أحد عشر', 12 => 'اثنا عشر', 13 => 'ثلاثة عشر', 14 => 'أربعة عشر', 15 => 'خمسة عشر', 16 => 'ستة عشر', 17 => 'سبعة عشر', 18 => 'ثمانية عشر', 19 => 'تسعة عشر'];
    $hundreds = ['', 'مائة', 'مئتان', 'ثلاثمائة', 'أربعمائة', 'خمسمائة', 'ستمائة', 'سبعمائة', 'ثمانمائة', 'تسعمائة'];

    $number = (int)$number;
    if ($number === 0) return 'صفر';
    if ($number < 0) return 'سالب ' . numberToArabicWords(abs($number));

    $parts = [];

    if ($number >= 1000000) {
        $millions = floor($number / 1000000);
        $parts[] = numberToArabicWords($millions) . ' مليون' . ($millions > 1 ? '' : '');
        $number %= 1000000;
    }

    if ($number >= 1000) {
        $thousands = floor($number / 1000);
        if ($thousands == 1) $parts[] = 'ألف';
        elseif ($thousands == 2) $parts[] = 'ألفان';
        elseif ($thousands > 2 && $thousands < 11) $parts[] = numberToArabicWords($thousands) . ' آلاف';
        else $parts[] = numberToArabicWords($thousands) . ' ألف';
        $number %= 1000;
    }

    if ($number >= 100) {
        $h = floor($number / 100);
        $parts[] = $hundreds[$h];
        $number %= 100;
    }

    if ($number >= 11 && $number <= 19) {
        $parts[] = $teens[$number];
    } else {
        if ($number >= 20 || $number == 10) {
            $t = floor($number / 10);
            $u = $number % 10;
            if ($u > 0) $parts[] = $units[$u] . ' و' . $tens[$t];
            else $parts[] = $tens[$t];
        } else {
            if ($number > 0) $parts[] = $units[$number];
        }
    }

    return implode(' و', array_filter($parts));
}

/**
 * فلتر بسيط للطباعة الآمنة
 */
function e($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

/*************** 5) جلب بيانات للمساعدة (datalist) ***************/
// جلب قائمة الأدوية (medicineid, name, price_sales, quantity)
$medicinesStmt = $pdo->query("SELECT medicineid, name, price_sales, quantity FROM medicines");
$medicines = $medicinesStmt->fetchAll(PDO::FETCH_ASSOC);

// جلب قائمة العملاء (id, name)
$customersStmt = $pdo->query("SELECT id, name FROM customers");
$customers = $customersStmt->fetchAll(PDO::FETCH_ASSOC);

/*************** 6) فحص وجود أعمدة اختيارية في الجداول ***************/
// نتحقق إن كانت الجداول تحتوي على الأعمدة الاختيارية لتفادي أخطاء الإدخال
function columnExists($pdo, $table, $column) {
    try {
        $st = $pdo->prepare("SHOW COLUMNS FROM $table LIKE ?");
        $st->execute([$column]);
        return (bool)$st->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $ex) {
        return false;
    }
}

$invoices_has_total_words = columnExists($pdo, 'invoices3', 'total_words');
$items_has_description = columnExists($pdo, 'invoice_items', 'description');

/*************** 7) معالجة حفظ الفاتورة من النموذج ***************/
$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_invoice'])) {

    // قراءة الحقول العليا
    $invoice_title = trim($_POST['invoice_title'] ?? 'فاتورة بيع');
    $customer_name = trim($_POST['customer_name'] ?? '');
    $matched_customer_id = !empty($_POST['matched_customer_id']) ? (int)$_POST['matched_customer_id'] : null;
    $date = $_POST['date'] ?? date('Y-m-d');
    $action = $_POST['action'] ?? 'save'; // 'save' أو 'save_print' — يتم ضبطه من الجافاسكربت

    // قراءة مصفوفات الأصناف (أضفنا item_desc[] لحفظ وصف الصنف)
    $names = $_POST['item_name'] ?? [];
    $linked_ids = $_POST['item_medicine_id'] ?? [];
    $quantities = $_POST['item_qty'] ?? [];
    $unit_prices = $_POST['item_price'] ?? [];
    $descs = $_POST['item_desc'] ?? []; // قد تكون فارغة إذا لم يرسِل العميل شيئًا

    $items = [];
    $total = 0.0;
    $hasError = false;

    // التحقق من كل صنف
    foreach ($names as $i => $rawName) {
        $name = trim($rawName);
        if ($name === '') continue; // تجاهل الأسطر الفارغة

        $qty = isset($quantities[$i]) ? (float)$quantities[$i] : 0;
        $price = isset($unit_prices[$i]) ? (float)$unit_prices[$i] : 0.0;
        $med_id = !empty($linked_ids[$i]) ? (int)$linked_ids[$i] : null;
        $desc = isset($descs[$i]) ? trim($descs[$i]) : '';

        if ($qty <= 0 || $price < 0) {
            $error = "الكمية أو السعر غير صالحين للصنف: " . e($name);
            $hasError = true;
            break;
        }

        // إن كان الصنف مرتبطًا بدواء في قاعدة البيانات تحقق من المخزون
        if ($med_id) {
            $checkStmt = $pdo->prepare("SELECT name, quantity, price_sales FROM medicines WHERE medicineid = ?");
            $checkStmt->execute([$med_id]);
            $med = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if (!$med) {
                $error = "الدواء غير موجود في قاعدة البيانات للصنف: " . e($name);
                $hasError = true;
                break;
            }
            if ($med['quantity'] < $qty) {
                $error = "الكمية غير كافية للدواء: " . e($med['name']);
                $hasError = true;
                break;
            }
            // لو السعر المدخل صفر نأخذ السعر من قاعدة البيانات كتسهيل
            if ($price <= 0) $price = (float)$med['price_sales'];
        }

        $line_total = $price * $qty;
        $total += $line_total;

        $items[] = [
            'name' => $name,
            'medicine_id' => $med_id ?: null,
            'qty' => $qty,
            'price' => $price,
            'line_total' => $line_total,
            'desc' => $desc
        ];
    }

    if (!$hasError && count($items) > 0) {
        try {
            $pdo->beginTransaction();

            // رقم فاتورة فريد
            $invoice_number = 'INV-' . time();

            // حساب الكلمات الإجمالية
            $total_words = numberToArabicWords((int)$total);

            // تركيب استعلام إدخال رأس الفاتورة — مرن بحسب وجود عمود total_words
            if ($invoices_has_total_words) {
                $insertInv = $pdo->prepare(
                    "INSERT INTO invoices3 (invoice_number, type, customer_supplier_name, total_amount, total_words, date)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $insertInv->execute([$invoice_number, $invoice_title, $customer_name, $total, $total_words, $date]);
            } else {
                // إن لم يكن الحقل موجودًا ندرج الصف بدون total_words
                $insertInv = $pdo->prepare(
                    "INSERT INTO invoices3 (invoice_number, type, customer_supplier_name, total_amount, date)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $insertInv->execute([$invoice_number, $invoice_title, $customer_name, $total, $date]);
            }

            $invoice_id = (int)$pdo->lastInsertId();

            // تحضير استعلام إدخال البنود مع مراعاة وجود حقل description أو لا
            if ($items_has_description) {
                $insertItem = $pdo->prepare(
                    "INSERT INTO invoice_items (invoice_id, medicine_id, item_name, description, quantity, unit_price, total_price)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
            } else {
                // إن لم يوجد حقل description نحفظ بدون العمود لتجنب الخطأ
                $insertItem = $pdo->prepare(
                    "INSERT INTO invoice_items (invoice_id, medicine_id, item_name, quantity, unit_price, total_price)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
            }

            // إدخال البنود وتحديث المخزون إن لزم
            foreach ($items as $it) {
                if ($items_has_description) {
                    $insertItem->execute([
                        $invoice_id,
                        $it['medicine_id'],
                        $it['name'],
                        $it['desc'],
                        $it['qty'],
                        $it['price'],
                        $it['line_total']
                    ]);
                } else {
                    $insertItem->execute([
                        $invoice_id,
                        $it['medicine_id'],
                        $it['name'],
                        $it['qty'],
                        $it['price'],
                        $it['line_total']
                    ]);
                }

                // إن كان مرتبطًا بدواء حقيقي نحدّث المخزون
                if (!empty($it['medicine_id'])) {
                    $upd = $pdo->prepare("UPDATE medicines SET quantity = quantity - ? WHERE medicineid = ?");
                    $upd->execute([$it['qty'], $it['medicine_id']]);
                }
            }

            //  تسجيل حركة العميل إن تم اختيار عميل حقيقي
            if ($matched_customer_id) {
                $ct = $pdo->prepare(
                    "INSERT INTO customer_transactions (customer_id, invoice_id, transaction_type, amount, description)
                     VALUES (?, ?, 'sale', ?, ?)"
                );
                $ct->execute([$matched_customer_id, $invoice_id, $total, "فاتورة: " . $invoice_number]);

                // تحديث رصيد العميل إن كان جدول customer_accounts موجودًا (ملاحظة: قد تحتاج UPSERT)
                try {
                    $ca = $pdo->prepare("UPDATE customer_accounts SET balance = balance + ? WHERE customer_id = ?");
                    $ca->execute([$total, $matched_customer_id]);
                } catch (Exception $ex) {
                    // نتجاوز إن لم يكن الجدول/العمود موجودًا — لا نفشل الحفظ لهذا السبب
                }
            }

            $pdo->commit();

            $success = "تم إنشاء الفاتورة بنجاح: {$invoice_number}";

            // إن طلب المستخدم الحفظ+طباعة نعيد توجيه لصفحة الطباعة
            if ($action === 'save_print') {
                header("Location: " . $PRINT_PAGE . "?id=" . $invoice_id);
                exit;
            }

        } catch (Exception $ex) {
            $pdo->rollBack();
            error_log("Invoice Save Error: " . $ex->getMessage());
            $error = "حدث خطأ أثناء حفظ الفاتورة، حاول مجدداً.";
        }
    } elseif (!$hasError) {
        $error = "لم تقم بإدخال أي أصناف في الفاتورة.";
    }
}

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
    "address"     =>  ' صنعاء-بني حشيش-الشرية ',                             // فاكس
    'logo'    => 'abd.jpg',                              // مسار الشعار
];

// --------------- تحديد ما سيُعرض قبل/بعد الحفظ من أرقام ----------------------
$display_invoice_number   = isset($_GET['inv'])    ? htmlspecialchars($_GET['inv'])    : $provisional_invoice_number; // إن وُجدت بعد الحفظ نعرضها
$display_operation_number = isset($_GET['inv_id']) ? (int)$_GET['inv_id']              : $provisional_operation_number; // رقم العملية (id)

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>إنشاء فاتورة</title>
    <!-- Bootstrap -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
     
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
        .actions{margin-top:10px;display:flex;gap:8px;}
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
</style>
</head>
<body>
   <table border="1" >
      <!-- <legend></legend> -->
       <tr border="3">
    <div class="head">
        <div class="right">
            <div class="company-ar"><?= htmlspecialchars($company['name_ar']) ?></b></h4></div> <!-- اسم عربي -->
            <div class="phones">                                                        <!-- أرقام الاتصال -->
                <div><h4> <?= htmlspecialchars($company['address']) ?></div>
                هاتف: <?= htmlspecialchars($company['phones'][0] ?? '-') ?>
                هاتف: <?= htmlspecialchars($company['phones'][1] ?? '-') ?>
                <div>هاتف: <?= htmlspecialchars($company['phones'][2] ?? '-') ?>
                فاكس: <?= htmlspecialchars($company['fax']) ?></div>
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
       
                <!-- ترقيم الصفحات: على الشاشة نظهر نصًا ثابتًا، وفي الطباعة نظهر عدّاد CSS -->
                <div>
                    <span class="no-print">Page 1 of 1</span> 
                    <span class="print-only page-counter"></span>
                </div>
                <!-- اسم المستخدم المدخل (قابل للتعديل على الشاشة) -->
             <div class="phones"><h6>
              tel: <?= htmlspecialchars($company['phones'][0] ?? '-') ?>&nbsp;
              tel: <?= htmlspecialchars($company['phones'][1] ?? '-') ?><br><br>
              tel: <?= htmlspecialchars($company['phones'][2] ?? '-') ?>&nbsp;
              fax: <?= htmlspecialchars($company['fax']) ?>

            
            </div> <!-- اسم إنجليزي -->
            </div>
            <div>
                <strong id="entered-by-display"></strong>
                <input  type="text" id="entered-by-input" value="عبدالسلام" >
                : user 
                </div>
        </div>
    </div>
</tr>
<!-- </fieldset> -->
    <!-- =================== عنوان الفاتورة قابل للتعديل =================== -->
    <div style="text-align:center; margin-top:12px;">
        <input type="text" id="invoice_title" name="invoice_title_front" 
               value="<?= htmlspecialchars($_POST['invoice_title'] ?? 'فاتورة بيع أجل') ?>" 
               style="font-size:18px; font-weight:800; text-align:center; border:0; width:60%;" />
    </div></td>



    <!-- رسائل -->
    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>
   <tr>
    <!-- النموذج الرئيسي -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div><strong>بيانات الفاتورة</strong></div>
            <div class="text-muted">تصميم عبدالسلام القاسي</div>
        </div>
        <div class="card-body">
            <form method="POST" id="invoiceForm">
                <input type="hidden" name="action" id="formAction" value="save">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">نوع/عنوان الفاتورة</label>
                        <input type="text" name="invoice_title" class="form-control" value="<?= e($invoice_title ?? 'فاتورة بيع') ?>" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">اسم العميل</label>
                        <input list="customersList" name="customer_name" id="customer_name" class="form-control" placeholder="اكتب اسم العميل أو اختر من القائمة" value="<?= e($customer_name ?? '') ?>">
                        <datalist id="customersList">
                            <?php foreach ($customers as $c): ?>
                                <option data-id="<?= (int)$c['id'] ?>" value="<?= e($c['name']) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <input type="hidden" name="matched_customer_id" id="matched_customer_id" value="<?= e($matched_customer_id ?? '') ?>">
                        <div class="form-text">لو اخترته من القائمة، يتحسّب على حسابه. إن كتبت اسم جديد يُسجَّل فقط.</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">التاريخ</label>
                        <input type="date" name="date" class="form-control" value="<?= e($date ?? date('Y-m-d')) ?>" required>
                    </div>
                </div>
                            </tr>
                            
                <!-- الأصناف -->
                <div class="mb-2 d-flex justify-content-between align-items-center">
                    <h5 class="m-0">أصناف الفاتورة</h5>
                </div>
                <div><table>
                  <th style="width:40px;">م</th>
                   <td class="index">1</td>  
                   <!-- هذا الترقيم يجب ان يكون ترقيم للاصناف ولاكن لااعرف لماذا 
                   لماذا لايوضع امام كل صنف رقم ويعد تلقائي 
                   لايظهر لي الا حرف الميم ورقم 1 جنبه لاهوه يعد ولا هوة حتى منظم
                  
                  حتى قد اضفت الداله الخاصه به في جافا سكربيت ولا حتئ الرقم تحت الحرف -->
                            </table>  </div>

                

                <div id="itemsContainer" class="items-container">
                    <!-- سطر النموذج الافتراضي (سيُستنسخ) -->
                    <div class="item-row row g-2 align-items-end">
                        <div class="col-md-6 name-cell">
                            <label class="form-label">اسم الدواء </label>
                            <input list="medsList" name="item_name[]" class="form-control item-name" placeholder="اكتب اسم الدواء أو اختر من القائمة">
                            <datalist id="medsList">
                                <?php foreach ($medicines as $m): ?>
                                    <option data-id="<?= (int)$m['medicineid'] ?>" data-price="<?= e($m['price_sales']) ?>" data-qty="<?= (int)$m['quantity'] ?>" value="<?= e($m['name']) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                            <input type="hidden" name="item_medicine_id[]" class="item-med-id">
                        </div>

                        <div class="col-md-2 qty-cell">
                            <label class="form-label">الكمية</label>
                            <input type="number" name="item_qty[]" class="form-control item-qty" min="1" value="1">
                        </div>

                        <div class="col-md-2 price-cell">
                            <label class="form-label">سعر الوحدة</label>
                            <input type="number" step="0.01" name="item_price[]" class="form-control item-price" placeholder="0.00">
                        </div>

                        <div class="col-md-10 mt-2">
                            <label class="form-label">ملاحظة للصنف</label>
                            <textarea name="item_desc[]" class="form-control item-desc" rows="1" cols="1" placeholder="اكتب وصفًا أو ملاحظة   "></textarea>
                        </div>

                        <div class="col-md-2 text-start">
                            <button type="button" class="btn btn-outline-danger w-100 mt-2" onclick="removeItemRow(this)"><i class="fa fa-trash"></i> حذف</button>
                        </div>
                    </div>
                </div>
            
                <!-- زر إضافة صنف (يوضع أسفل الأصناف) -->
                <div class="mb-3">
                    <button type="button" class="btn btn-outline-primary" onclick="addItemRow()"><i class="fa fa-plus"></i> إضافة صنف</button>
                </div>
                <!-- أزرار التحكم -->
        <div class="actions no-print">
            <button type="button" class="btn btn-outline" onclick="undoRemove()">استرجاع آخر حذف</button>
            <button type="button" class="btn btn-outline" onclick="document.getElementById('saleForm').reset(); calculateAll();">إعادة ضبط</button>
        </div>

             <tr>
                <!-- المجموع والأزرار -->
                <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                    <div class="total-box">
                        
                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary" onclick="submitForm('save_print')"><i class="fa fa-print"></i> حفظ وطباعه</button>
                        <button type="submit" name="create_invoice" class="btn btn-primary" onclick="submitForm('save')"><i class="fa fa-save"></i> حفظ الفاتورة</button>
                    </div>
                </div>
                <!-- المجموع والأزرار -->
                <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                    <div class="total-box">
                        <div>
                            <h5 class="m-0">الإجمالي بالأرقام: <span id="grandTotal">0.00</span> ج.م</h5>
                            <div class="total-words mt-1"><strong>بالأحرف:</strong> <span id="grandTotalWords">صفر</span> ريال</div>
                        </div>
                    </div>


            </form>
        </div>
    </div>
</div>

<!-- سكربتات -->
<script>
    // دوال مساعدة للجافاسكربت
    // دالة لتحويل الأرقام الصحيحة إلى كلمات بالعربية (نسخة مبسطة تغطي حتى الملايين)
    function toArabicWords(number) {
        number = Math.floor(Number(number) || 0);
        if (number === 0) return 'صفر';
        const units = ['','واحد','اثنان','ثلاثة','أربعة','خمسة','ستة','سبعة','ثمانية','تسعة'];
        const tens = ['','عشرة','عشرون','ثلاثون','أربعون','خمسون','ستون','سبعون','ثمانون','تسعون'];
        const teens = {11:'أحد عشر',12:'اثنا عشر',13:'ثلاثة عشر',14:'أربعة عشر',15:'خمسة عشر',16:'ستة عشر',17:'سبعة عشر',18:'ثمانية عشر',19:'تسعة عشر'};
        const hundreds = ['','مائة','مئتان','ثلاثمائة','أربعمائة','خمسمائة','ستمائة','سبعمائة','ثمانمائة','تسعمائة'];

        let parts = [];
        if (number >= 1000000) {
            const m = Math.floor(number / 1000000);
            parts.push(toArabicWords(m) + ' مليون');
            number %= 1000000;
        }
        if (number >= 1000) {
            const th = Math.floor(number / 1000);
            if (th === 1) parts.push('ألف');
            else if (th === 2) parts.push('ألفان');
            else parts.push(toArabicWords(th) + ' ألف');
            number %= 1000;
        }
        if (number >= 100) {
            const h = Math.floor(number / 100);
            parts.push(hundreds[h]);
            number %= 100;
        }
        if (number >= 11 && number <= 19) {
            parts.push(teens[number]);
        } else {
            if (number >= 20 || number == 10) {
                const t = Math.floor(number / 10);
                const u = number % 10;
                if (u > 0) parts.push(units[u] + ' و' + tens[t]);
                else parts.push(tens[t]);
            } else {
                if (number > 0) parts.push(units[number]);
            }
        }
        return parts.join(' و');
    }

    // إعادة حساب الإجمالي من كل صف
    function recalcTotal() {
        let sum = 0;
        document.querySelectorAll('.item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('.item-qty')?.value || 0);
            const price = parseFloat(row.querySelector('.item-price')?.value || 0);
            if (!isNaN(qty) && !isNaN(price)) {
                sum += qty * price;
            }
        });
        document.getElementById('grandTotal').textContent = sum.toFixed(2);
        document.getElementById('grandTotalWords').textContent = toArabicWords(Math.floor(sum));
    }

    // معالجة تغيير اسم الصنف لمطابقة الداتاليست (وإظهار السعر الافتراضي إن لم يُدخل)
    function handleNameChange(input) {
        const row = input.closest('.item-row');
        const hiddenId = row.querySelector('.item-med-id');
        const priceInput = row.querySelector('.item-price');

        const val = input.value.trim();
        const list = document.getElementById('medsList');
        let matched = false;
        list.querySelectorAll('option').forEach(opt => {
            if (opt.value === val) {
                matched = true;
                hiddenId.value = opt.dataset.id || '';
                if (!priceInput.value || parseFloat(priceInput.value) <= 0) {
                    priceInput.value = opt.dataset.price || '';
                }
            }
        });
        if (!matched) hiddenId.value = '';
        recalcTotal();
    }

    // إضافة صف صنف جديد أسفل الموجودين
    function addItemRow() {
        const container = document.getElementById('itemsContainer');
        const template = container.querySelector('.item-row');
        const clone = template.cloneNode(true);
            first.querySelector('.index').textContent = tbody.rows.length + 1; // رقم تسلسلي


        // تفريغ الحقول بالنسخة
        clone.querySelector('.item-name').value = '';
        clone.querySelector('.item-med-id').value = '';
        clone.querySelector('.item-qty').value = '1';
        clone.querySelector('.item-price').value = '';
        clone.querySelector('.item-desc').value = '';

        // إعادة إرفاق الأحداث إن لزم
        container.appendChild(clone);
        recalcTotal();
    }

    // حذف صف صنف (لا نحذف آخر صف لترك إمكانية الإدخال)
    function removeItemRow(btn) {
        const row = btn.closest('.item-row');
        const container = document.getElementById('itemsContainer');
        if (container.querySelectorAll('.item-row').length > 1) {
            row.remove();
            recalcTotal();
        } else {
            // تفضيل إعادة تهيئة الحقول بدلاً من حذف آخر صف
            row.querySelector('.item-name').value = '';
            row.querySelector('.item-med-id').value = '';
            row.querySelector('.item-qty').value = '1';
            row.querySelector('.item-price').value = '';
            row.querySelector('.item-desc').value = '';
            recalcTotal();
        }
    }

    // دالة لإرسال النموذج مع ضبط نوع الإجراء (حفظ فقط أو حفظ+طباعة)
    function submitForm(action) {
        document.getElementById('formAction').value = (action === 'save_print') ? 'save_print' : 'save';
        // لو الضغط جاء من زر حفظ أو حفظ+طباعة نترك الإرسال الطبيعي يحدث (زر الحفظ لديه type=submit)
        if (action === 'save_print') {
            // نرسل النموذج برمجياً لأن الزر حفظ وطباعه ليس submit مباشرة
            document.getElementById('invoiceForm').submit();
        }
    }
///////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////
////// إضافة صف جديد بنسخ أول صف وإعادة تهيئته


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
//////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////
    // الاستماع للأحداث العامة — إعادة الحساب، والتعامل مع المطابقة في datalist
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('item-qty') || e.target.classList.contains('item-price')) {
            recalcTotal();
        }
        if (e.target.classList.contains('item-name')) {
            handleNameChange(e.target);
        }
        if (e.target.id === 'customer_name') {
            const val = e.target.value.trim();
            const list = document.getElementById('customersList');
            let matchedId = '';
            list.querySelectorAll('option').forEach(opt => {
                if (opt.value === val) matchedId = opt.dataset.id || '';
            });
            document.getElementById('matched_customer_id').value = matchedId;
        }
    });

    // حساب أولي عند التحميل
    document.addEventListener('DOMContentLoaded', function(){
        recalcTotal();
    });
</script>
        <!-- ملاحظات -->
        <div class="notes">
            <label>ملاحظات:</label>
            <textarea name="note" rows="1" style="width:100%;" placeholder="اكتب ملاحظات الفاتورة هنا..."><?= htmlspecialchars($_POST['note'] ?? '') ?></textarea>
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
        <strong id="footer-entered-by">abdlalslam</strong>
        &nbsp; | تاريخ/وقت الإدخال: <?= date('Y-m-d H:i:s') ?>
    </div>
</div>
    </tr>
</body>
</html>
