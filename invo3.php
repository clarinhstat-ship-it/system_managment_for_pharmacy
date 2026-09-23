<?php
 session_start();
 if (!isset($_SESSION['userid'])) {
     header("Location: index.php");
     exit;
}
$username_login=$_SESSION['name'];
// $user3=isset($_SESSION['name'];)

// require اتصال قاعدة البيانات (ملف يجب أن يُعرّف $pdo كـ PDO)
require_once 'db.php'; // تحميل إعداد الاتصال بقاعدة البيانات - يجب أن يُعرّف $pdo

// صفحة الطباعة (اسم الملف المستخدم لإظهار الفاتورة للطباعة)
$PRINT_PAGE = 'invoice_print.php'; // صفحة الطباعة  
$ITEMS_PER_PAGE = 18; //   تحجيم صفحات الطباعة 

// ---------------- دوال مساعدة ----------------
// دالة هروب XSS (اختصار e)
function e($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); } // حماية الإخراج من XSS

// دالة تحويل عدد صحيح إلى كلمات عربية (تعمل للأعداد الصحيحة)
function numberToArabicWords($number) {
    // تعريف مصفوفات الأرقام
    $units = ['', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة'];
    $tens = ['', 'عشرة', 'عشرون', 'ثلاثون', 'أربعون', 'خمسون', 'ستون', 'سبعون', 'ثمانون', 'تسعون'];
    $teens = [11=>'أحد عشر',12=>'اثنا عشر',13=>'ثلاثة عشر',14=>'أربعة عشر',15=>'خمسة عشر',16=>'ستة عشر',17=>'سبعة عشر',18=>'ثمانية عشر',19=>'تسعة عشر'];
    $hundreds = ['', 'مائة', 'مئتان', 'ثلاثمائة', 'أربعمائة', 'خمسمائة', 'ستمائة', 'سبعمائة', 'ثمانمائة', 'تسعمائة'];

    $number = (int)$number; // التأكد من أن الرقم عدد صحيح
    if ($number === 0) return 'صفر'; // حالة الصفر
    if ($number < 0) return 'سالب ' . numberToArabicWords(abs($number)); // أعداد سالبة
    $parts = [];

    // الملايين
    if ($number >= 1000000) {
        $millions = floor($number / 1000000);
        $parts[] = numberToArabicWords($millions) . ' مليون';
        $number %= 1000000;
    }
    // الآلاف
    if ($number >= 1000) {
        $thousands = floor($number / 1000);
        if ($thousands == 1) $parts[] = 'ألف';
        elseif ($thousands == 2) $parts[] = 'ألفان';
        elseif ($thousands > 2 && $thousands < 11) $parts[] = numberToArabicWords($thousands) . ' آلاف';
        else $parts[] = numberToArabicWords($thousands) . ' ألف';
        $number %= 1000;
    }
    // المئات
    if ($number >= 100) {
        $h = floor($number / 100);
        $parts[] = $hundreds[$h];
        $number %= 100;
    }
    // من 11 إلى 19
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
    return implode(' و', array_filter($parts)); // إعادة النص النهائي
}

// ---------------- جلب بيانات للمساعدة ----------------
try {
    // جلب الأصناف من جدول medicines لملء datalist بالواجهة
    $medicinesStmt = $pdo->query("SELECT medicineid, name, price_sales, quantity FROM medicines");
    $medicines = $medicinesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $ex) {
    // لو فشل الاستعلام نُبقي المصفوفة فارغة لكن نُسجل الخطأ
    error_log("Medicines Fetch Error: " . $ex->getMessage());
    $medicines = [];
}
try {
    // جلب قائمة العملاء لملء datalist بالواجهة
    $customersStmt = $pdo->query("SELECT id, name FROM customers");
    $customers = $customersStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $ex) {
    error_log("Customers Fetch Error: " . $ex->getMessage());
    $customers = [];
}

// ---------------- التحقق من وجود أعمدة اختيارية ----------------
// دالة تتحقق من وجود عمود في جدول (تُعيد true/false)
function columnExists($pdo, $table, $column) {
    try {
        // نستخدم SHOW COLUMNS مع prepared statement لحماية من حقن بسيط
        $st = $pdo->prepare("SHOW COLUMNS FROM $table LIKE ?");
        $st->execute([$column]);
        return (bool)$st->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $ex) {
        error_log("columnExists Error ({$table}.{$column}): " . $ex->getMessage());
        return false;
    }
}

// التحقق وإضافة عمود description إن لم يكن موجوداً (محاولة مرنة)
$items_has_description = columnExists($pdo, 'invoice_items', 'description');
if (!$items_has_description) {
    try {
        $pdo->exec("ALTER TABLE invoice_items ADD COLUMN description TEXT NULL");
        $items_has_description = true;
    } catch (Exception $ex) {
        // إن فشل الإضافة نتابع بدون هذا العمود لكن نسجل الخطأ
        error_log("Add description column failed: " . $ex->getMessage());
        $items_has_description = false;
    }
}

// التحقق من وجود عمود total_words في جدول الفواتير
$invoices_has_total_words = columnExists($pdo, 'invoices3', 'total_words');

// ---------------- قيم عرض افتراضية ----------------
$success = null; // رسالة نجاح
$error = null;   // رسالة خطأ

// قراءة قيم من POST أو افتراضات
$invoice_title = $_POST['invoice_title'] ?? 'فاتورة بيع'; // عنوان الفاتورة
$customer_name = $_POST['customer_name'] ?? ''; // اسم العميل
$matched_customer_id = !empty($_POST['matched_customer_id']) ? (int)$_POST['matched_customer_id'] : null; // id العميل إن وجد
$date = $_POST['date'] ?? date('Y-m-d'); // تاريخ الفاتورة

// ---------------- عملية الحفظ ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_invoice'])) {
    $action = $_POST['action'] ?? 'save'; // نعرف هل المستخدم اختار حفظ فقط أم حفظ+طباعة

    // قراءة مصفوفات البنود المرسلة من الفورم
    $names = $_POST['item_name'] ?? [];
    $linked_ids = $_POST['item_medicine_id'] ?? [];
    $quantities = $_POST['item_qty'] ?? [];
    $unit_prices = $_POST['item_price'] ?? [];
    $descs = $_POST['item_desc'] ?? [];

    $items = []; // سنخزن البنود الصالحة هنا
    $total = 0.0; // إجمالي الفاتورة
    $hasError = false; // علامة وجود خطأ

    // التحقق من كل صف بند
    foreach ($names as $i => $rawName) {
        $name = trim($rawName);
        if ($name === '') continue; // تجاهل صفوف فارغة

        $qty = isset($quantities[$i]) ? (float)$quantities[$i] : 0;
        $price = isset($unit_prices[$i]) ? (float)$unit_prices[$i] : 0;
        $med_id = !empty($linked_ids[$i]) ? (int)$linked_ids[$i] : null;
        $desc = isset($descs[$i]) ? trim($descs[$i]) : '';

        // تحقق من صحة الكمية والسعر
        if ($qty <= 0 || $price < 0) {
            $error = "الكمية أو السعر غير صالح للصنف: " . e($name);
            $hasError = true;
            break;
        }

        // لو هناك ربط لدواء تحقق من توفره وكمية المخزون
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
            // لو السعر لم يحدد أو صفر نستخدم سعر المبيع من جدول الأدوية
            if ($price <= 0) $price = (float)$med['price_sales'];
        }

        $line_total = $price * $qty; // حساب الإجمالي لكل سطر
        $total += $line_total; // إضافة للسعر الكلي

        // إضافة البنود لمصفوفة الإدخال لاحقاً
        $items[] = [
            'name' => $name,
            'medicine_id' => $med_id ?: null,
            'qty' => $qty,
            'price' => $price,
            'line_total' => $line_total,
            'desc' => $desc
        ];
    }

    // إن لم يُدخل أي صنف نضع خطأ
    if (!$hasError && count($items) === 0) {
        $error = "لم تقم بإدخال أي أصناف في الفاتورة.";
        $hasError = true;
    }

    // إذا كل شيء تمام نبدأ محاولة الحفظ داخل معاملة (Transaction)
    if (!$hasError) {
        try {
            $pdo->beginTransaction(); // بدء المعاملة

            // تحويل الإجمالي إلى كلمات (نأخذ الجزء الصحيح فقط هنا)
            $total_words = numberToArabicWords((int)$total);

            // ملاحظة مهمة: استخدمنا اقتباس أعمدة SQL \` حول أسماء الأعمدة - هذا يمنع مشاكل
            // لو كان اسم العمود محجوزاً مثل type في بعض قواعد البيانات.
            if ($invoices_has_total_words) {
                $stmtInv = $pdo->prepare("INSERT INTO invoices3 (invoice_number, type, customer_supplier_name, total_amount, total_words, date) VALUES (?, ?, ?, ?, ?, ?)");
                $stmtInv->execute(['TEMP', $invoice_title, $customer_name, $total, $total_words, $date]);
            } else {
                $stmtInv = $pdo->prepare("INSERT INTO invoices3 (invoice_number, type, customer_supplier_name, total_amount, date) VALUES (?, ?, ?, ?, ?)");
                $stmtInv->execute(['TEMP', $invoice_title, $customer_name, $total, $date]);
            }

            $invoice_id = (int)$pdo->lastInsertId(); // رقم السطر (id) في جدول الفواتير

            // رقم الفاتورة النهائي بحسب التاريخ ومعرّف السجل
            $invoice_number_final = 'INV-' . date('Ymd') . '-' . str_pad($invoice_id, 6, '0', STR_PAD_LEFT);

            // تحديث الحقل invoice_number برقم الفاتورة النهائي
            $updInv = $pdo->prepare("UPDATE invoices3 SET invoice_number = ? WHERE id = ?");
            $updInv->execute([$invoice_number_final, $invoice_id]);

            // إعداد جملة الإدخال للبنود - نستخدم prepared statement مرّة واحدة
            if ($items_has_description) {
                $insertItem = $pdo->prepare("INSERT INTO invoice_items (invoice_id, medicine_id, item_name, description, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?, ?)");
            } else {
                $insertItem = $pdo->prepare("INSERT INTO invoice_items (invoice_id, medicine_id, item_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)");
            }

            // إعداد تحديث المخزون كـ prepared statement (إعادة استخدامه يحسّن الأداء)
            $updMed = $pdo->prepare("UPDATE medicines SET quantity = quantity - ? WHERE medicineid = ?");

            // إدخال كل بند وتحديث مخزون الدواء إن كان مرتبطاً
            foreach ($items as $it) {
                if ($items_has_description) {
                    $insertItem->execute([$invoice_id, $it['medicine_id'], $it['name'], $it['desc'], $it['qty'], $it['price'], $it['line_total']]);
                } else {
                    $insertItem->execute([$invoice_id, $it['medicine_id'], $it['name'], $it['qty'], $it['price'], $it['line_total']]);
                }

                if (!empty($it['medicine_id'])) {
                    $updMed->execute([$it['qty'], $it['medicine_id']]);
                }
            }

            // تسجيل حركة العميل لو كان موجوداً (محاولة ثانوية نتجاوز أي خطأ بها)
            if ($matched_customer_id) {
                try {
                    $ct = $pdo->prepare("INSERT INTO customer_transactions (customer_id, invoice_id, transaction_type, amount, description) VALUES (?, ?, 'sale', ?, ?)");
                    $ct->execute([$matched_customer_id, $invoice_id, $total, "فاتورة: " . $invoice_number_final]);

                    if (columnExists($pdo, 'customer_accounts', 'balance')) {
                        $ca = $pdo->prepare("UPDATE customer_accounts SET balance = balance + ? WHERE customer_id = ?");
                        $ca->execute([$total, $matched_customer_id]);
                    }
                } catch (Exception $ex) {
                    // تجاوُز أي خطأ ثانوي وتسجيله فقط
                    error_log("Customer transaction error: " . $ex->getMessage());
                }
            }

            $pdo->commit(); // إنهاء المعاملة بنجاح

            // إعداد رسائل واجهة المستخدم بعد الحفظ
            $success = "تم إنشاء الفاتورة بنجاح (رقم الفاتورة: {$invoice_number_final})";
            $display_invoice_number = $invoice_number_final;
            $display_operation_number = $invoice_id;

            // لو اختار المستخدم حفظ + طباعة نعيد توجيه إلى صفحة الطباعة مع id العملية
            if ($action === 'save_print') {
                // استخدمنا Location ثم خروج من السكربت - تأكد أن هذا السطر يتم قبل أي إخراج HTML.
                header("Location: {$PRINT_PAGE}?id=" . $invoice_id);
                exit;
            }

        } catch (Exception $ex) {
            // تراجع عن المعاملة وتسجيل الخطأ بدقة ثم عرض رسالة عامة للمستخدم
            $pdo->rollBack();
            error_log("Invoice Save Error: " . $ex->getMessage()); // سجل رسالة الخطأ الكاملة للبحث لاحقاً
            // عرض رسالة مبسطة تفيد بوجود خطأ أثناء الحفظ (يمكنك مراجعة سجل الأخطاء لمزيد من التفاصيل)
            $error = "حدث خطأ أثناء حفظ الفاتورة — تأكد من اتصال قاعدة البيانات وصلاحيات الجداول.". $ex->getMessage();
        }
    }
}

// ---------------- تقدير رقم مبدئي للعرض ----------------
try {
    // نحسب التوقع للمعرّف التالي ليُعرض للمستخدم قبل الحفظ
    $nextRow = $pdo->query("SELECT COALESCE(MAX(id),0)+1 AS next_id FROM invoices3")->fetch(PDO::FETCH_ASSOC);
    $next_id_estimate = (int)($nextRow['next_id'] ?? 1);
} catch (Exception $ex) {
    error_log("Next ID estimate error: " . $ex->getMessage());
    $next_id_estimate = 1;
}
$provisional_invoice_number = 'INV-' . date('Ymd') . '-' . str_pad($next_id_estimate, 6, '0', STR_PAD_LEFT);
$provisional_operation_number = $next_id_estimate;

if (!isset($display_invoice_number)) $display_invoice_number = $provisional_invoice_number;
if (!isset($display_operation_number)) $display_operation_number = $provisional_operation_number;

// -// ------------------------------ بيانات المحل ----------------------------------
$company = [
    'name_ar' => 'صيدلية الشرية المركزية للأدوية',      // اسم عربي
    'name_en' => 'pharmacy AL SHARIH Stores for Medicines', // اسم إنجليزي
    'phones'  => ['779550018','778199909','773743725'], // أرقام الهواتف
    'fax'     => '01360027',                             // فاكس
    "address"     =>  ' صنعاء-بني حشيش-الشرية ',       // العنوان
    'logo'    => 'abd.jpg',                              // مسار الشعار (إن وجد)
];

// --------------- تحديد ما سيُعرض قبل/بعد الحفظ من أرقام ----------------------
// قراءة عرض أرقام من الـ GET إن وجدت وإلا نعرض التنبؤ
$display_invoice_number   = isset($_GET['inv'])    ? htmlspecialchars($_GET['inv'])    : $provisional_invoice_number;
$display_operation_number = isset($_GET['inv_id']) ? (int)$_GET['inv_id']              : $provisional_operation_number;

?><!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>إنشاء فاتورة</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
 
</head>
<body>
    <div class="paper">
        <!-- رأس الفاتورة مبني بواسطة DIVs بدل استخدام جدول مشوّه -->
        <div class="head-box">
            <div class="head-right">
                <div class="company-ar"><h3><b><?= e($company['name_ar']) ?></b></h3></div> <!-- اسم الصيدلية عربي -->
                <div class="phones small-muted">
                  <h5>  <?= e($company['address']) ?><br></h5>
                    هاتف: <?= e($company['phones'][0] ?? '-') ?> &nbsp;
                    هاتف: <?= e($company['phones'][1] ?? '-') ?> &nbsp;
                    هاتف: <?= e($company['phones'][2] ?? '-') ?> &nbsp;
                    فاكس: <?= e($company['fax']) ?>
                </div> تاريخ الفاتورة/<?= date('Y-m-d H:i:s') ?>
            </div>
            <div class="head-center">
                <!-- شعار (إن أردت عرضه) -->
                <?php if (!empty($company['logo'])): ?>
                    <img src="<?= e($company['logo']) ?>" alt="logo" style="max-width:120px; height:auto;">
                <?php endif; ?>
            </div>
            <div class="head-left">
                <div class="company-en"><?= e($company['name_en']) ?></div>
                <div class="contact-info small-muted">
                    tel: <?= e($company['phones'][0] ?? '-') ?>,
                    tel: <?= e($company['phones'][1] ?? '-') ?>,<hR>
                    tel: <?= e($company['phones'][2] ?? '-') ?>,
                    fax: <?= e($company['fax']) ?>
                </div>

                <div style="margin-top:10px;">
                    <div class="small-muted">رقم الفاتورة: <strong id="invoice-number-display"><?= e($display_invoice_number) ?></strong></div>
                    <div class="small-muted">رقم العملية: <strong id="operation-number-display"><?= e($display_operation_number) ?></strong></div>
                    <div class="small-muted no-print">Page 1 of 1</div>
                    
                </div>
                            اسم المستخدم: <strong id="footer-entered-by"><?php echo htmlspecialchars($username_login); ?></strong>

            </div>
        </div>

    <!-- عنوان الفاتورة -->
    <div style="text-align:center; margin-top:12px;"> 
        <input type="text" id="invoice_title_display" name="invoice_title_front" value="<?= e($_POST['invoice_title'] ?? 'فاتورة بيع أجل') ?>" style="font-size:18px; font-weight:800; text-align:center; border:0; width:60%;" />
    
    </div>

    <!-- رسائل نجاح/خطأ -->
    <?php if ($success): ?>
        <div class="alert alert-success mt-3"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger mt-3"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- النموذج الرئيسي -->
    <form method="POST" id="invoiceForm">
        <input type="hidden" name="action" id="formAction" value="save">
        <input type="hidden" name="create_invoice" value="1"> <!-- لتأكيد الإرسال -->
        <div class="client-box">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">نوع الفاتورة</label> 
                    <input type="text" name="invoice_title" class="form-control" value="<?= e($invoice_title) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">اسم العميل</label>
                    <input list="customersList" name="customer_name" id="customer_name" class="form-control" placeholder=" اسم العميل " value="<?= e($customer_name) ?>">
                    <datalist id="customersList">
                        <?php foreach ($customers as $c): ?>
                            <option data-id="<?= (int)$c['id'] ?>" value="<?= e($c['name']) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <input type="hidden" name="matched_customer_id" id="matched_customer_id" value="<?= e($matched_customer_id ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">التاريخ</label>
                    <input type="date" name="date" class="form-control" value="<?= e($date) ?>">
                </div>
            </div>
        </div>

        <!-- جدول الأصناف -->
        <table class="items" id="itemsTable">
            <thead>
                <tr>
                    <th class="index-cell">م</th>
                    <th>البيان / اسم الدواء</th>
                    <th style="width:90px;">الكمية</th>
                    <th style="width:120px;">سعر الوحدة</th>
                    <th style="width:120px;">المتوفرة</th>
                    <th>ملاحظة / وصف</th>
                    <th class="no-print" style="width:90px;">حذف</th>
                    <th class="no-print" style="width:90px;">حذف</th>
                </tr>
            </thead>
            <tbody id="itemsTbody">
                <tr class="item-row">
                    <td class="index index-cell">1</td>
                    <td>
                        <input list="medsList" name="item_name[]" class="form-control item-name" placeholder="اكتب اسم الدواء أو اختر">
                        <datalist id="medsList">
                            <?php foreach ($medicines as $m): ?>
                                <option data-id="<?= (int)$m['medicineid'] ?>" data-price="<?= e($m['price_sales']) ?>" data-qty="<?= (int)$m['quantity'] ?>" value="<?= e($m['name']) ?>"  data-prv="<?=(int)$m['quantity'];?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <input type="hidden" name="item_medicine_id[]" class="item-med-id">
                    </td>
                    <td><input type="number" name="item_qty[]" class="form-control item-qty" min="1" value="1"></td>
                    <td><input type="number" step="0.01" name="item_price[]" class="form-control item-price" placeholder="0.00"></td>
                    <td><textarea name="item_desc[]" class="form-control item-desc" rows="1" placeholder="ملاحظة للصنف"></textarea></td>
                    <td class="no-print text-center">
                        <button type="button" class="btn btn-danger btn-sm remove-item-btn" onclick="removeItemRow(this)"><i class="fa fa-trash"></i></button>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- أزرار إضافة واسترجاع وإعادة ضبط -->
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div>
                <button type="button" class="btn btn-outline" onclick="addItemRow()"><i class="fa fa-plus"></i> إضافة صنف</button>
                <button type="button" class="btn btn-outline" onclick="resetForm()"><i class="fa fa-refresh"></i> إعادة ضبط</button>
                <button type="button" class="btn btn-outline" onclick="undoRemove()"><i class="fa fa-undo"></i> استرجاع آخر حذف</button>
            </div>

            <div class="actions no-print">
                <button type="button" class="btn btn-secondary" onclick="submitForm('save_print')"><i class="fa fa-print"></i> حفظ وطباعه</button>
                <button type="submit" name="create_invoice" class="btn btn-primary" onclick="submitForm('save')"><i class="fa fa-save"></i> حفظ الفاتورة</button>
            </div>
        </div>

        <!-- المجموع والاحرف -->
        <div class="totals">
            <div class="box">
                <h5 class="m-0">الإجمالي بالأرقام: <span id="grandTotal">0.00</span> </h5>
               <i> <div class="small-muted mt-1">بالأحرف: <span id="grandTotalWords">صفر</span></div></i>
            </div>
            <div class="box">
                <label class="form-label small-muted">ملاحظات الفاتورة</label>
                <textarea name="note" class="form-control" rows="2"><?= e($_POST['note'] ?? '') ?></textarea>
                <div style="color:#c00; font-weight:700; margin-top:10px;"><h6><b>ملاحظة: الأدوية لا تُرد بعد خروجها من المحل.</b></h6></div>
            </div>
        </div>

    </form>

    <!-- رسائل إضافية -->
    <?php if ($error): ?>
        <div style="color:#c00; font-weight:700; margin-top:10px;"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['success'])): ?>
        <div style="color:#0a7b0a; font-weight:700; margin-top:10px;">تم حفظ الفاتورة بنجاح — رقم الفاتورة: <?= e($_GET['inv'] ?? '') ?></div>
    <?php endif; ?>

    <div class="footer">
        تم إدخال الفاتورة بواسطة: <strong id="footer-entered-by"><?php echo htmlspecialchars($username_login); ?></strong>
        &nbsp; | تاريخ/وقت الإدخال: <?= date('Y-m-d H:i:s') ?>
    </div>
    </div>

<!-- ================= جافاسكربت ================= -->
<script>
    // Stack للاحتفاظ بآخر صف محذوف للاسترجاع
    let removedStack = [];

    // تحويل رقم إلى كلمات مبسطة (JS) لعرض لحظي
    function toArabicWords(number) {
        number = Math.floor(Number(number) || 0);
        if (number === 0) return 'صفر';
        const units = ['','واحد','اثنان','ثلاثة','أربعة','خمسة','ستة','سبعة','ثمانية','تسعة'];
        const tens = ['','عشرة','عشرون','ثلاثون','أربعون','خمسون','ستمائة','سبعون','ثمانون','تسعون']; // ملاحظة: تركيبة كلمات عشرات
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
        if (number >= 11 && number <= 19) parts.push(teens[number]);
        else {
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

    // حساب الإجمالي
    function recalcTotal() {
        let sum = 0;
        document.querySelectorAll('.item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('.item-qty')?.value || 0);
            const price = parseFloat(row.querySelector('.item-price')?.value || 0);
            if (!isNaN(qty) && !isNaN(price)) sum += qty * price;
        });
        document.getElementById('grandTotal').textContent = sum.toFixed(2);
        document.getElementById('grandTotalWords').textContent = toArabicWords(Math.floor(sum));
    }

    // تحديث الترقيم في العمود "م"
    function updateIndexes() {
        document.querySelectorAll('#itemsTbody .item-row').forEach((row, idx) => {
            const cell = row.querySelector('.index');
            if (cell) cell.textContent = idx + 1;
        });
    }

    // عند تغيير اسم الدواء نملأ ID والسعر الافتراضي إن وُجد
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

    // إضافة صف جديد
    function addItemRow() {
        const tbody = document.getElementById('itemsTbody');
        const template = tbody.querySelector('.item-row');
        const clone = template.cloneNode(true);
        // تهيئة القيم
        clone.querySelector('.item-name').value = '';
        clone.querySelector('.item-med-id').value = '';
        clone.querySelector('.item-qty').value = '1';
        clone.querySelector('.item-price').value = '';
        clone.querySelector('.item-desc').value = '';
        tbody.appendChild(clone);
        updateIndexes();
        recalcTotal();
    }

    // حذف صف مع حفظ نسخة للاسترجاع
    function removeItemRow(btn) {
        const row = btn.closest('.item-row');
        const tbody = document.getElementById('itemsTbody');
        if (!row) return;
        const rows = tbody.querySelectorAll('.item-row');
        if (rows.length > 1) {
            removedStack.push(row.outerHTML);
            row.remove();
            updateIndexes();
            recalcTotal();
        } else {
            // إعادة تهيئة الحقول بدل الحذف
            row.querySelector('.item-name').value = '';
            row.querySelector('.item-med-id').value = '';
            row.querySelector('.item-qty').value = '1';
            row.querySelector('.item-price').value = '';
            row.querySelector('.item-desc').value = '';
            recalcTotal();
        }
    }

    // استرجاع آخر صف محذوف
    function undoRemove() {
        if (removedStack.length === 0) {
            alert('لا يوجد عناصر محذوفة للاسترجاع');
            return;
        }
        const lastHtml = removedStack.pop();
        const tbody = document.getElementById('itemsTbody');
        tbody.insertAdjacentHTML('beforeend', lastHtml);
        updateIndexes();
        recalcTotal();
    }

    // إعادة ضبط النموذج
    function resetForm() {
        if (!confirm('هل تريد إعادة ضبط النموذج؟')) return;
        document.getElementById('invoiceForm').reset();
        // إعادة تهيئة أول صف وازالة باقي الصفوف
        document.querySelectorAll('#itemsTbody .item-row').forEach((r, i) => {
            if (i === 0) {
                r.querySelector('.item-name').value = '';
                r.querySelector('.item-med-id').value = '';
                r.querySelector('.item-qty').value = '1';
                r.querySelector('.item-price').value = '';
                r.querySelector('.item-desc').value = '';
            } else r.remove();
        });
        removedStack = [];
        updateIndexes();
        recalcTotal();
    }

    // ارسال النموذج مع تحديد الإجراء
    function submitForm(action) {
        document.getElementById('formAction').value = (action === 'save_print') ? 'save_print' : 'save';
        if (action === 'save_print') {
            // زر حفظ وطباعه يرسل النموذج برمجياً
            document.getElementById('invoiceForm').submit();
        } else {
            // زر حفظ هو submit فعليًا (لا حاجة لعمل إضافي)
        }
    }

    // تفويض الأحداث للتعامل مع الحقول الديناميكية
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('item-qty') || e.target.classList.contains('item-price')) recalcTotal();
        if (e.target.classList.contains('item-name')) handleNameChange(e.target);
        if (e.target.id === 'customer_name') {
            const val = e.target.value.trim();
            const list = document.getElementById('customersList');
            let matchedId = '';
            list.querySelectorAll('option').forEach(opt => { if (opt.value === val) matchedId = opt.dataset.id || ''; });
            document.getElementById('matched_customer_id').value = matchedId;
        }
        if (e.target.id === 'entered-by-input') {
            document.getElementById('entered-by-display').textContent = e.target.value;
            document.getElementById('footer-entered-by').textContent = e.target.value;
        }
    });

    // تهيئة عند التحميل
    document.addEventListener('DOMContentLoaded', function() {
        updateIndexes();
        recalcTotal();
    });
</script>
</body>
</html>
