<?php
// ==============================================================================
// 1. بدء الجلسة والتحقق من الصلاحية
// ==============================================================================

// تضمين ملف المصادقة الذي يضمن بدء الجلسة والاتصال بقاعدة البيانات $pdo
require_once 'auth.php'; // حماية الصفحة

// التحقق من أن المستخدم يمتلك صلاحية admin أو manager لإضافة أدوية جديدة
if (!checkRole(['admin', 'manager'])) {
    die("❌ ليس لديك صلاحية للوصول لهذه الصفحة."); // منع الوصول في حال كان user
}

$message = ''; // متغير لتخزين رسائل النجاح
$error = '';   // متغير لتخزين رسائل الخطأ

// ==============================================================================
// 2. معالجة إرسال النموذج وحفظ الدواء الجديد
// ==============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_medicine'])) {
    // تصفية وقراءة البيانات المدخلة من النموذج
    $barcode = trim($_POST['barcode'] ?? ''); // كود الباركود الفريد
    $name = trim($_POST['name'] ?? '');       // اسم الدواء
    $description = trim($_POST['description'] ?? ''); // وصف الدواء
    $supplierid = !empty($_POST['supplierid']) ? (int)$_POST['supplierid'] : null; // كود المورد
    $price = (float)($_POST['price'] ?? 0); // سعر الشراء
    $price_sales = (float)($_POST['price_sales'] ?? 0); // سعر البيع
    $dateproduction = !empty($_POST['dateproduction']) ? $_POST['dateproduction'] : null; // تاريخ الانتاج
    $expiredate = !empty($_POST['expiredate']) ? $_POST['expiredate'] : null; // تاريخ الانتهاء
    $quantity = (int)($_POST['quantity'] ?? 0); // الكمية الأولية

    // التحقق من الحقول الإجبارية
    if (empty($name) || $price_sales <= 0 || $quantity < 0) {
        $error = "❌ يرجى ملء اسم الدواء وسعر البيع والكمية بشكل صحيح.";
    } else {
        try {
            // تحضير استعلام الإدراج المجهز الآمن لمنع أي ثغرة SQL Injection
            $sql = "INSERT INTO medicines (barcode, name, description, supplierid, price, price_sales, dateproduction, expiredate, quantity) 
                    VALUES (:barcode, :name, :description, :supplierid, :price, :price_sales, :dateproduction, :expiredate, :quantity)";
            $stmt = $pdo->prepare($sql); // تجهيز الاستعلام
            
            // تنفيذ الاستعلام الآمن بحقن البيانات المفلترة
            $stmt->execute([
                ':barcode' => !empty($barcode) ? $barcode : null,
                ':name' => $name,
                ':description' => $description,
                ':supplierid' => $supplierid,
                ':price' => $price,
                ':price_sales' => $price_sales,
                ':dateproduction' => $dateproduction,
                ':expiredate' => $expiredate,
                ':quantity' => $quantity
            ]);

            // تسجيل العملية في سجل النشاطات والأمان
            $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_name, action, details) VALUES (:uname, 'إضافة دواء', :details)");
            $logStmt->execute([
                ':uname' => $_SESSION['name'],
                ':details' => "تم إضافة دواء جديد: $name بـ كمية: $quantity وسعر بيع: $price_sales"
            ]);

            $message = "✅ تم إضافة الدواء بنجاح!"; // رسالة النجاح
        } catch (PDOException $e) {
            // في حالة تكرار الباركود أو وجود خطأ ببيانات الجدول
            if ($e->getCode() == 23000) {
                $error = "❌ رمز الباركود أدخلته مستخدم مسبقاً لدواء آخر.";
            } else {
                $error = "❌ حدث خطأ أثناء إضافة الدواء: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            }
        }
    }
}

// جلب قائمة الموردين لتعبئة القائمة المنسدلة اختيارياً
try {
    $suppliersStmt = $pdo->query("SELECT supplierid, name FROM suppliers ORDER BY name ASC");
    $suppliers = $suppliersStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $ex) {
    $suppliers = [];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8"> <!-- ترميز اللغة العربية -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- التجاوب مع الأجهزة -->
    <title>إضافة دواء جديد - صيدلية الشرية</title> <!-- عنوان الصفحة -->

    <!-- تضمين Bootstrap والتنسيقات -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background-color: #f7f9fc;
            direction: rtl;
            padding-bottom: 40px;
        }

        .form-card {
            background-color: #ffffff;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            max-width: 800px;
            margin: 30px auto;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="form-card">
        
        <!-- رأس النموذج والأزرار -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold text-primary mb-0">
                <i class="fa-solid fa-square-plus me-2"></i> إضافة دواء جديد للمخزون
            </h3>
            <a href="medicines.php" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-right me-1"></i> العودة للأدوية
            </a>
        </div>

        <!-- إظهار رسائل النجاح أو الخطأ -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-success rounded-3 mb-3"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger rounded-3 mb-3"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- نموذج إدخال بيانات الدواء -->
        <form method="post" action="add.php" class="row g-3">
            
            <!-- حقل رمز الباركود مع إمكانية توليد باركود عشوائي -->
            <div class="col-md-6">
                <label for="barcode" class="form-label fw-bold">🏷️ كود الباركود (يمكن مسحه بالماسح الضوئي):</label>
                <div class="input-group">
                    <input type="text" name="barcode" id="barcode" class="form-control font-monospace" placeholder="امسح الباركود أو اكتبه هنا..." autofocus>
                    <button type="button" class="btn btn-outline-secondary" onclick="generateRandomBarcode()" title="توليد باركود تلقائي">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> توليد
                    </button>
                </div>
            </div>

            <!-- اسم الدواء -->
            <div class="col-md-6">
                <label for="name" class="form-label fw-bold">💊 اسم الدواء <span class="text-danger">*</span>:</label>
                <input type="text" name="name" id="name" class="form-control" required placeholder="مثال: بندول إكسترا 500 ملجم">
            </div>

            <!-- الوصف والملاحظات -->
            <div class="col-12">
                <label for="description" class="form-label fw-bold">📝 الوصف والتفاصيل:</label>
                <textarea name="description" id="description" class="form-control" rows="2" placeholder="أدخل تفاصيل الدواء أو دواعي الاستعمال..."></textarea>
            </div>

            <!-- اختيار المورد -->
            <div class="col-md-6">
                <label for="supplierid" class="form-label fw-bold">🚚 المورد (اختياري):</label>
                <select name="supplierid" id="supplierid" class="form-select">
                    <option value="">-- اختر المورد --</option>
                    <?php foreach ($suppliers as $sup): ?>
                        <option value="<?= $sup['supplierid'] ?>"><?= htmlspecialchars($sup['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- الكمية الأولية -->
            <div class="col-md-6">
                <label for="quantity" class="form-label fw-bold">📦 الكمية المتوفرة بالمخزن <span class="text-danger">*</span>:</label>
                <input type="number" name="quantity" id="quantity" class="form-control" value="10" min="0" required>
            </div>

            <!-- سعر الشراء -->
            <div class="col-md-6">
                <label for="price" class="form-label fw-bold">💵 سعر الشراء (من المورد):</label>
                <input type="number" step="0.01" name="price" id="price" class="form-control" value="0.00" min="0">
            </div>

            <!-- سعر البيع -->
            <div class="col-md-6">
                <label for="price_sales" class="form-label fw-bold">💰 سعر البيع للجمهور <span class="text-danger">*</span>:</label>
                <input type="number" step="0.01" name="price_sales" id="price_sales" class="form-control" value="0.00" min="0.01" required>
            </div>

            <!-- تاريخ الإنتاج -->
            <div class="col-md-6">
                <label for="dateproduction" class="form-label fw-bold">📅 تاريخ الإنتاج:</label>
                <input type="date" name="dateproduction" id="dateproduction" class="form-control">
            </div>

            <!-- تاريخ انتهاء الصلاحية -->
            <div class="col-md-6">
                <label for="expiredate" class="form-label fw-bold">⏳ تاريخ انتهاء الصلاحية:</label>
                <input type="date" name="expiredate" id="expiredate" class="form-control">
            </div>

            <!-- أزرار الإرسال والإلغاء -->
            <div class="col-12 text-center mt-4">
                <button type="submit" name="add_medicine" class="btn btn-success px-5 py-2 fw-bold">
                    <i class="fa-solid fa-check me-1"></i> حفظ الدواء
                </button>
                <a href="medicines.php" class="btn btn-secondary px-4 py-2 me-2">إلغاء</a>
            </div>
        </form>
    </div>
</div>

<script>
// دالة لإنشاء كود باركود عشوائي للسهولة والاختبار عند عدم توفر باركود مطبوع
function generateRandomBarcode() {
    // توليد رقم عشوائي مكون من 12 خانة
    const randomCode = '629' + Math.floor(100000003 + Math.random() * 900000000);
    document.getElementById('barcode').value = randomCode; // كتابة الكود في حقل الإدخال
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
