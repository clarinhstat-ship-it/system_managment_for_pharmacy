<?php
// ==============================================================================
// 1. بدء الجلسة والتحقق من تسجيل الدخول والمصادقة
// ==============================================================================

// تضمين ملف المصادقة الذي يفحص الجلسة ويتصل بقاعدة البيانات $pdo
require_once 'auth.php'; // حماية الصفحة

// جلب دور المستخدم الحالي من الجلسة (admin, manager, user)
$userRole = $_SESSION['role'] ?? 'user'; // دور المستخدم الحالي

// ==============================================================================
// 2. استقبال شروط البحث وجلب الأدوية مع أسماء الموردين بـ SQL JOIN الصحيح
// ==============================================================================

$search = trim($_GET['search'] ?? ''); // جلب كلمة البحث إن وجدت وتصفيتها من المسافات

try {
    if (!empty($search)) {
        // استعلام البحث المجهز الآمن بالاسم أو الباركود لمنع SQL Injection
        $sql = "SELECT medicines.*, suppliers.name AS supplier_name 
                FROM medicines 
                LEFT JOIN suppliers ON medicines.supplierid = suppliers.supplierid 
                WHERE medicines.name LIKE :search OR medicines.barcode LIKE :search 
                ORDER BY medicines.medicineid DESC"; // الترتيب تنازلياً حسب الأحدث
        $stmt = $pdo->prepare($sql); // تحضير الاستعلام
        $stmt->execute([':search' => '%' . $search . '%']); // تنفيذ الاستعلام بحقن نص البحث
    } else {
        // استعلام جلب كافة الأدوية المعتاد مع اسم المورد عبر LEFT JOIN
        $sql = "SELECT medicines.*, suppliers.name AS supplier_name 
                FROM medicines 
                LEFT JOIN suppliers ON medicines.supplierid = suppliers.supplierid 
                ORDER BY medicines.medicineid DESC"; // ترتيب حسب الأحدث
        $stmt = $pdo->query($sql); // تنفيذ الاستعلام
    }
    
    $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC); // جلب كافة النتائج كـ مصفوفة ترابطية
} catch (PDOException $e) {
    // في حالة حدوث خطأ استعلام، يتم طباعة الرسالة بأمان
    die("❌ خطأ في جلب بيانات الأدوية: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8"> <!-- ترميز اللغة العربية -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- التجاوب مع الهواتف -->
    <title>إدارة قائمة الأدوية - صيدلية الشرية</title> <!-- عنوان الصفحة -->

    <!-- تضمين مكتبة Bootstrap لتنسيق الصفحة -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- تضمين أيقونات FontAwesome للأزرار -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* التنسيق العام لصفحة الأدوية */
        body {
            font-family: 'Cairo', sans-serif; /* الخط العربي */
            background-color: #f7f9fc; /* خلفية ناعمة */
            direction: rtl; /* الاتجاه من اليمين للشمال */
            padding-bottom: 40px; /* حشوة سفلية */
        }

        /* حاوية البيانات البيضاء */
        .box-container {
            background-color: #ffffff; /* لون خلفية الصندوق */
            border-radius: 15px; /* حواف دائرية */
            padding: 25px; /* حشوة داخلية */
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); /* ظل رمادي خفيف */
            margin-top: 30px; /* هامش علوي */
        }

        /* تنسيق جدول البيانات */
        .table th, .table td {
            vertical-align: middle; /* محاذاة النص في منتصف الخلية رأسياً */
        }

        /* تمييز الأدوية منتهية الصلاحية باللون الأحمر */
        .expired-row {
            background-color: #ffe6e6 !important; /* خلفية حمراء ناعمة */
        }

        /* تمييز الأدوية قريبة النفاد باللون الأصفر */
        .low-stock-row {
            background-color: #fff9e6 !important; /* خلفية صفراء ناعمة */
        }
    </style>
</head>
<body>

<div class="container">
    <div class="box-container">
        
        <!-- رأس الصفحة وأزرار التحكم الرئيسية -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h3 class="fw-bold text-primary mb-0">
                <i class="fa-solid fa-pills me-2"></i> قائمة الأدوية والمخزون
            </h3>
            <div>
                <!-- زر العودة للصفحة الرئيسية -->
                <a href="index.php" class="btn btn-outline-secondary me-2">
                    <i class="fa-solid fa-arrow-right me-1"></i> الرئيسية
                </a>
                
                <!-- زر إضافة دواء جديد متاح للمدراء والمشرفين (admin / manager) -->
                <?php if ($userRole === 'admin' || $userRole === 'manager'): ?>
                    <a href="add.php" class="btn btn-success">
                        <i class="fa-solid fa-plus me-1"></i> إضافة دواء جديد
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- نموذج البحث المباشر باسم الدواء أو الباركود -->
        <form method="get" action="medicines.php" class="row g-2 mb-4">
            <div class="col-md-10">
                <!-- حقل الإدخال للبحث السريع بالاسم أو الباركود -->
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass text-secondary"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="ابحث باسم الدواء أو كود الباركود..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>
            <div class="col-md-2">
                <!-- زر تنفيذ البحث -->
                <button type="submit" class="btn btn-primary w-100">بحث</button>
            </div>
        </form>

        <!-- جدول عرض بيانات الأدوية -->
        <div class="table-responsive">
            <table class="table table-hover table-bordered text-center align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>الرقم</th>
                        <th>الباركود 🏷️</th>
                        <th>اسم الدواء 💊</th>
                        <th>الوصف 📝</th>
                        <th>المورد 🚚</th>
                        <th>سعر الشراء</th>
                        <th>سعر البيع</th>
                        <th>الانتاج 📅</th>
                        <th>الانتهاء ⏳</th>
                        <th>الكمية 📦</th>
                        <!-- عمود الإجراءات متاح لـ admin و manager -->
                        <?php if ($userRole === 'admin' || $userRole === 'manager'): ?>
                            <th>الإجراءات ⚙️</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($medicines) > 0): ?>
                        <?php foreach ($medicines as $medicine): ?>
                            <?php
                            // فحص تاريخ انتهاء الصلاحية لتمييز الصفوف المنتهية
                            $isExpired = false; // افتراض غير منتهي
                            if (!empty($medicine['expiredate']) && strtotime($medicine['expiredate']) <= time()) {
                                $isExpired = true; // الدواء منتهي الصلاحية
                            }

                            // فحص الكمية لتمييز النواقص
                            $isLowStock = ($medicine['quantity'] <= 5); // كمية قليلة جداً
                            
                            // تحديد صنف التنسيق الخاص بالصف
                            $rowClass = ''; // صنف عادي
                            if ($isExpired) {
                                $rowClass = 'expired-row'; // صنف منتهي
                            } elseif ($isLowStock) {
                                $rowClass = 'low-stock-row'; // صنف كمية قليلة
                            }
                            ?>
                            <tr class="<?= $rowClass ?>">
                                <td><?= htmlspecialchars($medicine['medicineid']) ?></td>
                                <!-- عرض الباركود أو علامة عدم وجوده -->
                                <td>
                                    <?php if (!empty($medicine['barcode'])): ?>
                                        <span class="badge bg-secondary font-monospace"><?= htmlspecialchars($medicine['barcode']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold"><?= htmlspecialchars($medicine['name']) ?></td>
                                <td class="small text-secondary"><?= htmlspecialchars($medicine['description'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($medicine['supplier_name'] ?? $medicine['supplierid'] ?? '-') ?></td>
                                <td><?= number_format($medicine['price'], 2) ?> ر.س</td>
                                <td class="fw-bold text-success"><?= number_format($medicine['price_sales'], 2) ?> ر.س</td>
                                <td class="small"><?= htmlspecialchars($medicine['dateproduction'] ?? '-') ?></td>
                                
                                <!-- عرض تاريخ الانتهاء مع شارة حمراء إذا كان منتهياً -->
                                <td>
                                    <?php if ($isExpired): ?>
                                        <span class="badge bg-danger">منتهي (<?= htmlspecialchars($medicine['expiredate']) ?>)</span>
                                    <?php else: ?>
                                        <?= htmlspecialchars($medicine['expiredate'] ?? '-') ?>
                                    <?php endif; ?>
                                </td>

                                <!-- عرض الكمية المتوفرة مع شارة تحذير إذا كانت منخفضة -->
                                <td>
                                    <?php if ($isLowStock): ?>
                                        <span class="badge bg-warning text-dark fw-bold"><?= htmlspecialchars($medicine['quantity']) ?> (منخفض)</span>
                                    <?php else: ?>
                                        <span class="badge bg-success"><?= htmlspecialchars($medicine['quantity']) ?></span>
                                    <?php endif; ?>
                                </td>

                                <!-- أزرار الإجراءات المباشرة التفاعلية لكل صف -->
                                <?php if ($userRole === 'admin' || $userRole === 'manager'): ?>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <!-- زر التعديل المباشر لهذا الدواء -->
                                            <a href="edept.php?medicineid=<?= $medicine['medicineid'] ?>" class="btn btn-warning" title="تعديل بيانات الدواء">
                                                <i class="fa-solid fa-pen-to-square"></i> تعديل
                                            </a>
                                            <!-- زر الحذف المباشر المتاح فقط لمدير النظام (admin) -->
                                            <?php if ($userRole === 'admin'): ?>
                                                <a href="delete_medicine.php?medicineid=<?= $medicine['medicineid'] ?>" class="btn btn-danger" onclick="return confirm('هل أنت تأكد من رغبتك في حذف هذا الدواء نهائياً؟');" title="حذف الدواء">
                                                    <i class="fa-solid fa-trash"></i> حذف
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <!-- رسالة تنبيه عدم وجود نتائج -->
                            <td colspan="11" class="text-center text-muted py-4">
                                <i class="fa-solid fa-box-open fs-2 d-block mb-2"></i>
                                لا توجد أدوية مسجلة حالياً تطابق معايير البحث.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- تضمين مكتبة جافاسكريبت المساعدة لـ Bootstrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
