<?php
// ==============================================================================
// 1. بدء الجلسة والمصادقة والتحقق من الصلاحيات
// ==============================================================================

// تضمين ملف المصادقة auth.php للحماية والاتصال بقاعدة البيانات PDO
require_once 'auth.php'; // حماية الصفحة للجميع (admin, manager, user)

$message = ''; // متغير لتخزين رسائل النجاح
$error = '';   // متغير لتخزين رسائل الخطأ
// ==============================================================================
// 2. معالجة حفظ الفاتورة الجديدة عند إرسال النموذج (POST)
// ==============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_invoice'])) {
    // قراءة البيانات المرسلة من واجهة البيع
    $customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null; // كود العميل
    $customer_supplier_name = trim($_POST['customer_name'] ?? 'عميل نقدي'); // اسم العميل
    $type = trim($_POST['type'] ?? 'sale'); // نوع الفاتورة (بيع sale أو شراء purchase)
    $discount = (float)($_POST['discount'] ?? 0); // الخصم
    $tax = (float)($_POST['tax'] ?? 0); // الضريبة
    
    // جلب الأصناف المباعة (تصل كـ مصفوفة JSON أو مصفوفة POST)
    $items = isset($_POST['items']) ? json_decode($_POST['items'], true) : [];

    if (empty($items) || !is_array($items)) {
        $error = "❌ لا يمكن حفظ فاتورة فارغة. يرجى إضافة أصناف أولاً.";
    } else {
        try {
            // بدء معاملة حركات قاعدة البيانات (Transaction) لضمان حفظ الفاتورة وتحديث المخزن معاً بأمان تام
            $pdo->beginTransaction();

            $subtotal = 0; // مجموع المنتجات قبل الخصم والضريبة

            // 1. حساب المجموع وحظر الصرف إذا كانت الكمية المتوفرة لا تكفي
            foreach ($items as $item) {
                $medId = (int)$item['medicine_id'];
                $qty = (int)$item['quantity'];
                $unitPrice = (float)$item['price'];

                // فحص كمية الدواء المتوفرة في قاعدة البيانات لضمان عدم حدوث بيع بالسالب
                $checkStmt = $pdo->prepare("SELECT name, quantity FROM medicines WHERE medicineid = :id FOR UPDATE");
                $checkStmt->execute([':id' => $medId]);
                $medData = $checkStmt->fetch();

                if (!$medData || $medData['quantity'] < $qty) {
                    throw new Exception("الكمية المتوفرة من الدواء (" . ($medData['name'] ?? 'غير معروف') . ") لا تكفي للطلب.");
                }

                $subtotal += ($qty * $unitPrice); // المجموع الجزئي
            }

            // 2. حساب الإجمالي الصافي النهائي بعد تطبيق الخصم والضريبة
            $netTotal = ($subtotal - $discount) + $tax;
            if ($netTotal < 0) $netTotal = 0;

            // 3. إدراج الفاتورة في جدول الفواتير الرئيسي (invoices3)
            $invStmt = $pdo->prepare("INSERT INTO invoices3 (customer_id, customer_supplier_name, type, total_amount, discount, tax, net_total, user_id) 
                                      VALUES (:cid, :cname, :type, :subtotal, :discount, :tax, :nettotal, :uid)");
            $invStmt->execute([
                ':cid' => $customer_id,
                ':cname' => $customer_supplier_name,
                ':type' => $type,
                ':subtotal' => $subtotal,
                ':discount' => $discount,
                ':tax' => $tax,
                ':nettotal' => $netTotal,
                ':uid' => $_SESSION['userid']
            ]);

            $invoice_id = $pdo->lastInsertId(); // جلب رقم الفاتورة المنشأة حديثاً

            // 4. إدراج عناصر الفاتورة وتخصيم الكميات من جدول الأدوية (medicines)
            $itemStmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, medicine_id, quantity, price, total) VALUES (:iid, :mid, :qty, :price, :total)");
            $updateStockStmt = $pdo->prepare("UPDATE medicines SET quantity = quantity - :qty WHERE medicineid = :mid");

            foreach ($items as $item) {
                $medId = (int)$item['medicine_id'];
                $qty = (int)$item['quantity'];
                $unitPrice = (float)$item['price'];
                $itemTotal = $qty * $unitPrice;

                // إدراج عنصر الفاتورة
                $itemStmt->execute([
                    ':iid' => $invoice_id,
                    ':mid' => $medId,
                    ':qty' => $qty,
                    ':price' => $unitPrice,
                    ':total' => $itemTotal
                ]);

                // تخصيم الكمية المباعة من مخزون الدواء
                $updateStockStmt->execute([
                    ':qty' => $qty,
                    ':mid' => $medId
                ]);
            }

            // 5. حفظ عملية البيع في سجل النشاطات والأمان
            $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_name, action, details) VALUES (:uname, 'إنشاء فاتورة بيع', :details)");
            $logStmt->execute([
                ':uname' => $_SESSION['name'],
                ':details' => "تم إنشاء فاتورة رقم: $invoice_id بمبلغ صافي: $netTotal للعميل: $customer_supplier_name"
            ]);

            // اعتماد وتطبيق كل التغييرات في قاعدة البيانات دفعة واحدة بنجاح
            $pdo->commit();

            // التوجيه لصفحة طباعة الفاتورة الحرارية والعادية
            header("Location: invoice_print.php?id=" . $invoice_id);
            exit;

        } catch (Exception $e) {
            // التراجع عن كل التغييرات في حالة حدوث أي مشكلة أو نقص بالكمية
            $pdo->rollBack();
            $error = "❌ فشل حفظ الفاتورة: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }
}

// ==============================================================================
// 3. جلب قائمة الأدوية المتاحة وقائمة العملاء لتجهيز الواجهة
// ==============================================================================

try {
    // جلب الأدوية ذات الكمية أكبر من 0 للعرض في الكاشير
    $medsStmt = $pdo->query("SELECT medicineid, barcode, name, price_sales, quantity FROM medicines WHERE quantity > 0 ORDER BY name ASC");
    $availableMeds = $medsStmt->fetchAll(PDO::FETCH_ASSOC);

    // جلب العملاء
    $custStmt = $pdo->query("SELECT id, name, phone FROM customers ORDER BY name ASC");
    $customers = $custStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("❌ خطأ في جلب البيانات: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8"> <!-- ضبط الترميز لدعم العربية -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نقطة البيع (POS) - صيدلية الشرية</title>

    <!-- مكتبة Bootstrap وأيقونات FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background-color: #f4f6f9;
            direction: rtl;
            padding-bottom: 30px;
        }

        .pos-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: #fff;
            padding: 15px 25px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .pos-card {
            background-color: #ffffff;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .cart-table th, .cart-table td {
            vertical-align: middle;
        }

        /* تمييز حقل الباركود للاستجابة السريعة للماسح الضوئي */
        .barcode-field {
            background-color: #eef9ff;
            border: 2px solid #007bff;
            font-size: 1.1rem;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="container my-3">
    
    <!-- رأس شاشة البيع الكاشير -->
    <div class="pos-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h4 class="fw-bold mb-0">
            <i class="fa-solid fa-cash-register me-2"></i> نقطة البيع والشراء (POS)
        </h4>
        <div>
            <a href="invoice_all.php" class="btn btn-light btn-sm me-2">
                <i class="fa-solid fa-list me-1"></i> جميع الفواتير
            </a>
            <a href="index.php" class="btn btn-outline-light btn-sm">
                <i class="fa-solid fa-house me-1"></i> الرئيسية
            </a>
        </div>
    </div>

    <!-- عرض رسائل الخطأ -->
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger rounded-3 mb-3"><?= $error ?></div>
    <?php endif; ?>

    <div class="row g-3">
        
        <!-- القسم الأيمن: ماسح الباركود وإضافة الأصناف -->
        <div class="col-lg-5">
            <div class="pos-card">
                <h5 class="fw-bold text-primary mb-3">
                    <i class="fa-solid fa-barcode me-2"></i> إضافة الأصناف للسلة
                </h5>

                <!-- ============================================================================== -->
                <!-- 1. ماسح الباركود التلقائي (Hardware Barcode Reader Wedge Listener)               -->
                <!-- ============================================================================== -->
                <div class="mb-3">
                    <label for="barcode_input" class="form-label fw-bold">🏷️ قراءة الباركود (امسح الكود بالماسح الضوئي):</label>
                    <input type="text" id="barcode_input" class="form-control barcode-field" placeholder="وجه قارئ الباركود هنا..." autofocus autocomplete="off">
                    <small class="text-muted">يقوم قارئ الباركود بإدراج الدواء في السلة تلقائياً بمجرد مسحه.</small>
                </div>

                <hr>

                <!-- 2. إضافة يدوي بواسطة الاختيار من القائمة المنسدلة -->
                <div class="mb-3">
                    <label for="select_medicine" class="form-label fw-bold">💊 أو اختر الدواء من القائمة:</label>
                    <select id="select_medicine" class="form-select">
                        <option value="">-- اختر الدواء --</option>
                        <?php foreach ($availableMeds as $med): ?>
                            <option value="<?= $med['medicineid'] ?>" 
                                    data-barcode="<?= htmlspecialchars($med['barcode'] ?? '') ?>" 
                                    data-name="<?= htmlspecialchars($med['name']) ?>" 
                                    data-price="<?= $med['price_sales'] ?>" 
                                    data-stock="<?= $med['quantity'] ?>">
                                <?= htmlspecialchars($med['name']) ?> (السعر: <?= number_format($med['price_sales'], 2) ?> ر.س - المتوفر: <?= $med['quantity'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- زر الإضافة اليدوي -->
                <button type="button" class="btn btn-primary w-100 py-2 fw-bold" onclick="addSelectedMedicine()">
                    <i class="fa-solid fa-cart-plus me-1"></i> إضافة الدواء المحدد للسلة
                </button>
            </div>
        </div>

        <!-- القسم الأيسر: جدول سلة الفاتورة والإجمالي النهائي -->
        <div class="col-lg-7">
            <div class="pos-card">
                
                <!-- نموذج إرسال الفاتورة لقاعدة البيانات -->
                <form method="post" action="invoce.php" id="pos-form">
                    
                    <!-- بيانات العميل ونوع الفاتورة -->
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">👤 اسم العميل:</label>
                            <input type="text" name="customer_name" id="customer_name" class="form-control" value="عميل نقدي" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">📑 نوع الفاتورة:</label>
                            <select name="type" class="form-select">
                                <option value="sale">فاتورة بيع (مبيعات)</option>
                                <option value="purchase">فاتورة شراء (مشتريات)</option>
                            </select>
                        </div>
                    </div>

                    <!-- جدول محتويات سلة الشراء -->
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered cart-table text-center align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th>اسم الدواء</th>
                                    <th style="width: 110px;">الكمية</th>
                                    <th style="width: 100px;">السعر</th>
                                    <th>الإجمالي</th>
                                    <th>حذف</th>
                                </tr>
                            </thead>
                            <tbody id="cart-body">
                                <!-- سيتم إضافة الأسطر تلقائياً بواسطة جافاسكريبت هنا -->
                            </tbody>
                        </table>
                    </div>

                    <!-- ملخص الإجمالي والخصم والضريبة -->
                    <div class="bg-light p-3 rounded-3 mb-3 border">
                        <div class="d-flex justify-content-between mb-2">
                            <span>المجموع الفرعي:</span>
                            <span id="display-subtotal" class="fw-bold fs-5">0.00 ر.س</span>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold">الخصم (ر.س):</label>
                                <input type="number" step="0.01" name="discount" id="input-discount" class="form-control form-control-sm" value="0.00" min="0" oninput="calculateTotals()">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">الضريبة (ر.س):</label>
                                <input type="number" step="0.01" name="tax" id="input-tax" class="form-control form-control-sm" value="0.00" min="0" oninput="calculateTotals()">
                            </div>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between text-success">
                            <h5 class="fw-bold mb-0">المبلغ الصافي النهائي:</h5>
                            <h4 id="display-nettotal" class="fw-bold mb-0">0.00 ر.س</h4>
                        </div>
                    </div>

                    <!-- حقل مخفي يحتوي على بيانات عناصر السلة بصيغة JSON -->
                    <input type="hidden" name="items" id="items-json">

                    <!-- زر حفظ وطباعة الفاتورة -->
                    <button type="submit" name="save_invoice" class="btn btn-success w-100 py-3 fs-5 fw-bold shadow-sm">
                        <i class="fa-solid fa-print me-2"></i> حفظ واصدار الفاتورة للطباعة
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>


<script>
// تخزين مصفوفة الأدوية المتاحة من قاعدة البيانات في متغير JavaScript للبحث السريع
const medicinesData = <?= json_encode($availableMeds) ?>;

// سلة المنتجات المضافة للفاتورة الحالية
let cart = [];

// ==============================================================================
// شرح آلية عمل وتشفير قارئ الباركود (Hardware Barcode Scanner Functionality):
// جهاز قارئ الباركود يعمل كجهاز إدخال لوحة مفاتيح خفيفة (Keyboard Wedge Device).
// عند مسح أي كود باركود، يقضي الجهاز إرسال سلسلة الأرقام بسرعة فائقة متبوعة بمفتاح Enter (KeyCode 13).
// لذلك، نقوم بالتقاط حدث الضغط على مفتاح Enter في حقل الباركود، ونبحث فوراً عن الدواء المطابق للكود في المصفوفة،
// ثم نضيفه إلى السلة ونفرغ حقل الباركود ليكون جاهزاً للمسحة التالية خلال أجزاء من الثانية!
// ==============================================================================

const barcodeInput = document.getElementById('barcode_input');

// الاستماع لحدث الضغط على الأزرار في حقل الباركود
barcodeInput.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault(); // منع إعادة تحميل الصفحة عند ضغط Enter
        const barcodeVal = this.value.trim(); // جلب الكود الممسوح
        
        if (barcodeVal !== '') {
            findAndAddByBarcode(barcodeVal); // البحث والإضافة للسلة
            this.value = ''; // تفريغ حقل الباركود فوراً
        }
    }
});

// دالة البحث عن الدواء بواسطة كود الباركود وإضافته للسلة
function findAndAddByBarcode(barcode) {
    // البحث في مصفوفة البيانات عن دواء مطابق للباركود الممسوح
    const foundMed = medicinesData.find(m => m.barcode && m.barcode.trim() === barcode.trim());

    if (foundMed) {
        addToCart(foundMed.medicineid, foundMed.name, parseFloat(foundMed.price_sales), parseInt(foundMed.quantity));
    } else {
        alert('❌ لم يتم العثور على دواء بهذا الباركود (' + barcode + ') في قاعدة البيانات!');
    }
}

// دالة إضافة الدواء المحدد من القائمة المنسدلة
function addSelectedMedicine() {
    const select = document.getElementById('select_medicine');
    const selectedOption = select.options[select.selectedIndex];

    if (!selectedOption.value) {
        alert('يرجى اختيار دواء من القائمة أولاً!');
        return;
    }

    const medId = parseInt(selectedOption.value);
    const medName = selectedOption.getAttribute('data-name');
    const medPrice = parseFloat(selectedOption.getAttribute('data-price'));
    const medStock = parseInt(selectedOption.getAttribute('data-stock'));

    addToCart(medId, medName, medPrice, medStock);
    select.selectedIndex = 0; // إعادة ضبط القائمة المنسدلة
}

// دالة إضافة عنصر للسلة أو زيادة كميته إذا كان موجوداً مسبقاً
function addToCart(id, name, price, stock) {
    const existingIndex = cart.findIndex(item => item.medicine_id === id);

    if (existingIndex > -1) {
        // إذا كان العنصر موجوداً بالسلة، تزيد الكمية بشرط عدم تجاوز المتاح بالمخزن
        if (cart[existingIndex].quantity + 1 > stock) {
            alert('⚠️ رصيد المخزن لا يكفي لزيادة الكمية لهذا الدواء! المتوفر: ' + stock);
            return;
        }
        cart[existingIndex].quantity += 1;
    } else {
        // إضافة عنصر جديد
        if (1 > stock) {
            alert('⚠️ هذا الدواء غير متوفر بالمخزن حالياً!');
            return;
        }
        cart.push({
            medicine_id: id,
            name: name,
            price: price,
            quantity: 1,
            stock: stock
        });
    }

    renderCart(); // إعادة رسم السلة في الشاشة
}

// دالة رسم وتحديث سلة الشراء في شاشة HTML
function renderCart() {
    const cartBody = document.getElementById('cart-body');
    cartBody.innerHTML = ''; // تفريغ السلة القديمة

    if (cart.length === 0) {
        cartBody.innerHTML = `<tr><td colspan="5" class="text-muted py-3">السلة فارغة. قم بمسح باركود أو اختيار دواء لإضافته.</td></tr>`;
        calculateTotals();
        return;
    }

    cart.forEach((item, index) => {
        const itemTotal = (item.quantity * item.price).toFixed(2);

        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="fw-bold">${item.name}</td>
            <td>
                <input type="number" class="form-control form-control-sm text-center" value="${item.quantity}" min="1" max="${item.stock}" onchange="updateQuantity(${index}, this.value)">
            </td>
            <td>${item.price.toFixed(2)}</td>
            <td class="fw-bold text-primary">${itemTotal} ر.س</td>
            <td>
                <button type="button" class="btn btn-danger btn-sm" onclick="removeFromCart(${index})">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </td>
        `;
        cartBody.appendChild(row);
    });

    calculateTotals(); // تحديث المجاميع
}

// دالة تحديث الكمية من داخل الجدول
function updateQuantity(index, newQty) {
    const qty = parseInt(newQty);
    if (qty > cart[index].stock) {
        alert('⚠️ الكمية المطلوبة تزيد عن رصيد المخزن المتوفر (' + cart[index].stock + ')');
        cart[index].quantity = cart[index].stock;
    } else if (qty <= 0) {
        removeFromCart(index);
        return;
    } else {
        cart[index].quantity = qty;
    }
    renderCart();
}

// دالة حذف عنصر من السلة
function removeFromCart(index) {
    cart.splice(index, 1);
    renderCart();
}

// دالة حساب المجاميع والإجمالي الصافي وتعبئة الحقل المخفي
function calculateTotals() {
    let subtotal = 0;
    cart.forEach(item => {
        subtotal += (item.quantity * item.price);
    });

    const discount = parseFloat(document.getElementById('input-discount').value) || 0;
    const tax = parseFloat(document.getElementById('input-tax').value) || 0;

    let netTotal = (subtotal - discount) + tax;
    if (netTotal < 0) netTotal = 0;

    document.getElementById('display-subtotal').innerText = subtotal.toFixed(2) + ' ر.س';
    document.getElementById('display-nettotal').innerText = netTotal.toFixed(2) + ' ر.س';

    // تحويل مصفوفة السلة لـ JSON وتعبئتها في الحقل المخفي لإرسالها مع النموذج
    document.getElementById('items-json').value = JSON.stringify(cart);
}

// تشغيل رسم السلة الابتدائية عند تحميل الصفحة
renderCart();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
