<?php
// ==============================================================================
// 1. بدء الجلسة والمصادقة والتحقق من الصلاحيات
// ==============================================================================

// تضمين ملف المصادقة الذي يفحص الجلسة والاتصال بقاعدة البيانات $pdo
require_once 'auth.php'; // حماية الصفحة

// التحقق من صلاحية المستخدم (admin أو manager) لتعديل بيانات الدواء
if (!checkRole(['admin', 'manager'])) {
    die("❌ ليس لديك صلاحية لتعديل الأدوية."); // منع الوصول في حال كان user
}

$message = ''; // متغير لرسائل النجاح
$error = '';   // متغير لرسائل الخطأ

// استقبال معرف الدواء المطلوب تعديله من رابط GET أو من نموذج POST
$medicineid = isset($_REQUEST['medicineid']) ? (int)$_REQUEST['medicineid'] : (isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0);

// التحقق من وجود معرف دواء صالح
if ($medicineid <= 0) {
    header("Location: medicines.php"); // التوجيه لصفحة الأدوية في حال عدم تحديد رقم الدواء
    exit;
}

// ==============================================================================
// 2. معالجة طلب التعديل وإعادة الحفظ (POST)
// ==============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_medicine'])) {
    // تصفية وقراءة البيانات المعدلة
    $barcode = trim($_POST['barcode'] ?? ''); // الباركود المعدل
    $name = trim($_POST['name'] ?? '');       // اسم الدواء
    $description = trim($_POST['description'] ?? ''); // الوصف
    $supplierid = !empty($_POST['supplierid']) ? (int)$_POST['supplierid'] : null; // المورد
    $price = (float)($_POST['price'] ?? 0); // سعر الشراء
    $price_sales = (float)($_POST['price_sales'] ?? 0); // سعر البيع
    $dateproduction = !empty($_POST['dateproduction']) ? $_POST['dateproduction'] : null; // تاريخ الإنتاج
    $expiredate = !empty($_POST['expiredate']) ? $_POST['expiredate'] : null; // تاريخ الانتهاء
    $quantity = (int)($_POST['quantity'] ?? 0); // الكمية المعدلة

    // التحقق من الحقول الإجبارية
    if (empty($name) || $price_sales <= 0 || $quantity < 0) {
        $error = "❌ يرجى ملء البيانات الأساسية بشكل صحيح.";
    } else {
        try {
            // استعلام تحديث مجهز وآمن من ثغرات SQL Injection
            $sql = "UPDATE medicines SET 
                    barcode = :barcode,
                    name = :name,
                    description = :description,
                    supplierid = :supplierid,
                    price = :price,
                    price_sales = :price_sales,
                    dateproduction = :dateproduction,
                    expiredate = :expiredate,
                    quantity = :quantity
                    WHERE medicineid = :medicineid";
            $stmt = $pdo->prepare($sql); // تجهيز الاستعلام
            
            // تنفيذ التحديث بحقن البيانات الآمنة
            $stmt->execute([
                ':barcode' => !empty($barcode) ? $barcode : null,
                ':name' => $name,
                ':description' => $description,
                ':supplierid' => $supplierid,
                ':price' => $price,
                ':price_sales' => $price_sales,
                ':dateproduction' => $dateproduction,
                ':expiredate' => $expiredate,
                ':quantity' => $quantity,
                ':medicineid' => $medicineid
            ]);

            // تسجيل العملية في سجل الأمان
            $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_name, action, details) VALUES (:uname, 'تعديل دواء', :details)");
            $logStmt->execute([
                ':uname' => $_SESSION['name'],
                ':details' => "تم تعديل بيانات الدواء رقم: $medicineid ($name)"
            ]);

            $message = "✅ تم تحديث بيانات الدواء بنجاح!"; // رسالة النجاح
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "❌ رمز الباركود مستخدم بالفعل بدواء آخر.";
            } else {
                $error = "❌ خطأ أثناء تحديث البيانات: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            }
        }
    }
}

// ==============================================================================
// 3. جلب بيانات الدواء الحالية لتعبئتها في النموذج
// ==============================================================================

try {
    $stmt = $pdo->prepare("SELECT * FROM medicines WHERE medicineid = :id LIMIT 1");
    $stmt->execute([':id' => $medicineid]);
    $medicine = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$medicine) {
        die("❌ الدواء المطلوب غير موجود بالسيستم.");
    }
} catch (PDOException $e) {
    die("❌ خطأ في جلب بيانات الدواء: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

// جلب الموردين
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل دواء - صيدلية الشرية</title>

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
        
        <!-- رأس النموذج -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold text-warning text-dark mb-0">
                <i class="fa-solid fa-pen-to-square me-2"></i> تعديل بيانات الدواء (رقم: <?= $medicine['medicineid'] ?>)
            </h3>
            <a href="medicines.php" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-right me-1"></i> العودة للأدوية
            </a>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success rounded-3 mb-3"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger rounded-3 mb-3"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- نموذج التعديل -->
        <form method="post" action="edept.php?medicineid=<?= $medicine['medicineid'] ?>" class="row g-3">
            
            <!-- الباركود -->
            <div class="col-md-6">
                <label for="barcode" class="form-label fw-bold">🏷️ كود الباركود:</label>
                <input type="text" name="barcode" id="barcode" class="form-control font-monospace" value="<?= htmlspecialchars($medicine['barcode'] ?? '') ?>">
            </div>

            <!-- اسم الدواء -->
            <div class="col-md-6">
                <label for="name" class="form-label fw-bold">💊 اسم الدواء <span class="text-danger">*</span>:</label>
                <input type="text" name="name" id="name" class="form-control" required value="<?= htmlspecialchars($medicine['name']) ?>">
            </div>

            <!-- الوصف -->
            <div class="col-12">
                <label for="description" class="form-label fw-bold">📝 الوصف والتفاصيل:</label>
                <textarea name="description" id="description" class="form-control" rows="2"><?= htmlspecialchars($medicine['description'] ?? '') ?></textarea>
            </div>

            <!-- المورد -->
            <div class="col-md-6">
                <label for="supplierid" class="form-label fw-bold">🚚 المورد:</label>
                <select name="supplierid" id="supplierid" class="form-select">
                    <option value="">-- اختر المورد --</option>
                    <?php foreach ($suppliers as $sup): ?>
                        <option value="<?= $sup['supplierid'] ?>" <?= ($medicine['supplierid'] == $sup['supplierid']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sup['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- الكمية -->
            <div class="col-md-6">
                <label for="quantity" class="form-label fw-bold">📦 الكمية بالمخزن <span class="text-danger">*</span>:</label>
                <input type="number" name="quantity" id="quantity" class="form-control" min="0" required value="<?= (int)$medicine['quantity'] ?>">
            </div>

            <!-- سعر الشراء -->
            <div class="col-md-6">
                <label for="price" class="form-label fw-bold">💵 سعر الشراء:</label>
                <input type="number" step="0.01" name="price" id="price" class="form-control" min="0" value="<?= (float)$medicine['price'] ?>">
            </div>

            <!-- سعر البيع -->
            <div class="col-md-6">
                <label for="price_sales" class="form-label fw-bold">💰 سعر البيع <span class="text-danger">*</span>:</label>
                <input type="number" step="0.01" name="price_sales" id="price_sales" class="form-control" min="0.01" required value="<?= (float)$medicine['price_sales'] ?>">
            </div>

            <!-- تاريخ الإنتاج -->
            <div class="col-md-6">
                <label for="dateproduction" class="form-label fw-bold">📅 تاريخ الإنتاج:</label>
                <input type="date" name="dateproduction" id="dateproduction" class="form-control" value="<?= htmlspecialchars($medicine['dateproduction'] ?? '') ?>">
            </div>

            <!-- تاريخ الانتهاء -->
            <div class="col-md-6">
                <label for="expiredate" class="form-label fw-bold">⏳ تاريخ الانتهاء:</label>
                <input type="date" name="expiredate" id="expiredate" class="form-control" value="<?= htmlspecialchars($medicine['expiredate'] ?? '') ?>">
            </div>

            <!-- أزرار التحديث -->
            <div class="col-12 text-center mt-4">
                <button type="submit" name="update_medicine" class="btn btn-warning px-5 py-2 fw-bold text-dark">
                    <i class="fa-solid fa-floppy-disk me-1"></i> حفظ التعديلات
                </button>
                <a href="medicines.php" class="btn btn-secondary px-4 py-2 me-2">إلغاء</a>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
