<?php
// ==============================================================================
// 1. بداية الجلسة والتحقق من الصلاحيات والمصادقة
// ==============================================================================

// تضمين ملف المصادقة auth.php الذي يبدأ الجلسة ويتحقق من تسجيل الدخول وقاعدة البيانات
require_once 'auth.php'; // حماية الصفحة وتجهيز الكائن $pdo ووسوم الجلسة

// جلب دور واسم المستخدم الحالي من الجلسة لتحديد الواجهة المناسبة له
$currentRole = $_SESSION['role'] ?? 'user'; // دور المستخدم (admin, manager, user)
$currentName = $_SESSION['name'] ?? 'مستخدم'; // اسم المستخدم

// ==============================================================================
// 2. جلب الإحصائيات والتنبيهات الذكية للنظام
// ==============================================================================

try {
    // 1. استعلام لحساب عدد الأدوية الإجمالي في الصيدلية
    $stmtMed = $pdo->query("SELECT COUNT(*) as count FROM medicines"); // تجهيز وتنفيذ استعلام العد
    $med_count = $stmtMed->fetch()['count'] ?? 0; // جلب عدد الأدوية

    // 2. استعلام لحساب إجمالي المبيعات من جدول الفواتير الرئيسي invoices3
    $stmtSales = $pdo->query("SELECT SUM(total_amount) as total FROM invoices3 WHERE type = 'sale'"); // استعلام الإجمالي
    $sales_total = $stmtSales->fetch()['total'] ?? 0; // جلب المجموع الإجمالي

    // 3. استعلام جلب الأدوية القريبة من الانتهاء (تاريخ الانتهاء خلال 60 يوماً القادمة) أو المنتهية بالفعل
    $stmtExpiry = $pdo->query("SELECT COUNT(*) as count FROM medicines WHERE expiredate IS NOT NULL AND expiredate <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)"); // استعلام الصلاحية
    $near_expiry_count = $stmtExpiry->fetch()['count'] ?? 0; // عدد الأدوية القريبة من الانتهاء

    // 4. استعلام جلب الأدوية التي يقل رصيدها عن الحد الأدنى (أقل من أو يساوي 5 قطع)
    $stmtStock = $pdo->query("SELECT COUNT(*) as count FROM medicines WHERE quantity <= 5"); // استعلام النواقص
    $low_stock_count = $stmtStock->fetch()['count'] ?? 0; // عدد النواقص

} catch (PDOException $e) {
    // في حال حدوث أي خطأ بالاستعلامات، يتم تعيين القيم الافتراضية كـ 0 لتجنب إيقاف الشاشة
    $med_count = 0; // عدد أدوية صفر
    $sales_total = 0; // مبيعات صفر
    $near_expiry_count = 0; // قريبة الانتهاء صفر
    $low_stock_count = 0; // نواقص صفر
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8"> <!-- ضبط الترميز العربي -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- التجاوب مع الأجهزة -->
    <title>لوحة التحكم - صيدلية الشرية المركزية</title> <!-- عنوان الصفحة -->

    <!-- تضمين Bootstrap CSS للتصميم العربي المتجاوب -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- تضمين أيقونات FontAwesome للأزرار والبطاقات -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* التنسيقات العامة للوحة التحكم */
        body {
            background: #f4f7f6; /* خلفية ناعمة مريحة */
            font-family: 'Cairo', 'Segoe UI', Tahoma, sans-serif; /* الخط العربي القياسي */
            direction: rtl; /* اتجاه الكتابة من اليمين لليسار */
            min-height: 100vh; /* الارتفاع الكامل */
        }

        /* شريط الملاحة العلوية */
        .navbar-custom {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); /* تدرج كحلي أنيق */
            box-shadow: 0 4px 12px rgba(0,0,0,0.15); /* ظل رمادي ناعم */
        }

        /* بطاقة الترحيب والخيارات الرئيسية */
        .main-card {
            background: #ffffff; /* خلفية بيضاء */
            border-radius: 16px; /* حواف دائرية */
            box-shadow: 0 8px 24px rgba(0,0,0,0.06); /* ظل البطاقة */
            padding: 30px; /* الحشوة الداخلية */
            margin-top: 25px; /* الهامش العلوي */
        }

        /* أزرار الخدمات والوظائف */
        .action-btn {
            padding: 14px 20px; /* الحشوة */
            font-weight: 600; /* خط عريض */
            border-radius: 12px; /* حواف ناعمة */
            display: flex; /* تفعيل flexbox */
            align-items: center; /* محاذاة عناصر الزر رأسياً */
            justify-content: center; /* محاذاة عناصر الزر أفقياً */
            gap: 10px; /* مسافة بين الأيقونة والنص */
            box-shadow: 0 4px 10px rgba(0,0,0,0.05); /* ظل الزر */
            transition: all 0.25s ease; /* حركة سلسة عند التحويم */
            text-decoration: none; /* إزالة الخط أسفل الرابط */
        }

        /* تأثير رفع الزر عند تحويم الماوس */
        .action-btn:hover {
            transform: translateY(-3px); /* رفع الزر للأعلى */
            box-shadow: 0 6px 15px rgba(0,0,0,0.12); /* تكثيف الظل */
        }

        /* بطاقات الإحصائيات السريعة */
        .stat-card {
            border: none; /* إلغاء الحدود */
            border-radius: 14px; /* حواف دائرية */
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); /* ظل ناعم */
            transition: transform 0.2s; /* تأثير حركة */
        }
        
        .stat-card:hover {
            transform: scale(1.02); /* تكبير طفيف عند التحويم */
        }
    </style>
</head>
<body>

<!-- شريط الملاحة العلوي Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom px-4">
    <div class="container-fluid">
        <!-- اسم الصيدلية وشعارها -->
        <a class="navbar-brand fw-bold fs-4" href="index.php">
            <i class="fa-solid fa-prescription-bottle-medical me-2"></i> صيدلية الشرية المركزية
        </a>
        
        <!-- قسم بيانات المستخدم الحالي وزر الخروج -->
        <div class="d-flex align-items-center gap-3 text-white">
            <span class="fs-6">
                <i class="fa-solid fa-user-circle me-1"></i> مرحباً، <strong><?= htmlspecialchars($currentName, ENT_QUOTES, 'UTF-8') ?></strong>
                <span class="badge bg-info text-dark me-2"><?= htmlspecialchars($currentRole, ENT_QUOTES, 'UTF-8') ?></span>
            </span>
            <!-- زر تغيير كلمة المرور للمستخدم الحالي -->
            <a href="change_password.php" class="btn btn-outline-light btn-sm rounded-pill" title="تغيير كلمة المرور">
                <i class="fa-solid fa-key"></i> كلمة المرور
            </a>
            <!-- زر تسجيل الخروج Lugout -->
            <a href="lugout.php" class="btn btn-danger btn-sm rounded-pill px-3">
                <i class="fa-solid fa-right-from-bracket me-1"></i> خروج
            </a>
        </div>
    </div>
</nav>

<!-- المحتوى الرئيسي للوحة التحكم -->
<div class="container my-4">

    <!-- ============================================================================== -->
    <!-- شريط التنبيهات الذكية للنظام (Smart Alerts) -->
    <!-- ============================================================================== -->
    <?php if ($near_expiry_count > 0 || $low_stock_count > 0): ?>
        <div class="row g-3 mb-4">
            <!-- تنبيه الأدوية منتهية أو قريبة الانتهاء -->
            <?php if ($near_expiry_count > 0): ?>
                <div class="col-md-6">
                    <div class="alert alert-warning d-flex align-items-center shadow-sm rounded-3 mb-0" role="alert">
                        <i class="fa-solid fa-triangle-exclamation fs-3 me-3 text-warning"></i>
                        <div>
                            <strong class="d-block">تنبيه انتهاء الصلاحية!</strong>
                            هناك <strong><?= $near_expiry_count ?></strong> دواء منتهي أو ينتهي خلال 60 يوماً. 
                            <a href="reports.php" class="alert-link ms-2">عرض في التقارير &laquo;</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- تنبيه نواقص المخزون -->
            <?php if ($low_stock_count > 0): ?>
                <div class="col-md-6">
                    <div class="alert alert-danger d-flex align-items-center shadow-sm rounded-3 mb-0" role="alert">
                        <i class="fa-solid fa-boxes-packing fs-3 me-3 text-danger"></i>
                        <div>
                            <strong class="d-block">تنبيه نواقص المخزون!</strong>
                            هناك <strong><?= $low_stock_count ?></strong> صنف قارب على النفاد (الكمية &le; 5). 
                            <a href="medicines.php" class="alert-link ms-2">مراجعة المخزون &laquo;</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- ============================================================================== -->
    <!-- كروت الإحصائيات الرئيسية -->
    <!-- ============================================================================== -->
    <div class="row g-4 mb-4">
        <!-- كارت إجمالي الأدوية بالمخزن -->
        <div class="col-md-6 col-lg-6">
            <div class="card stat-card bg-primary text-white p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-white-50">📦 عدد أصناف الأدوية</h6>
                        <h2 class="fw-bold mb-0"><?= number_format($med_count) ?></h2>
                    </div>
                    <i class="fa-solid fa-pills fs-1 text-white-50"></i>
                </div>
            </div>
        </div>

        <!-- كارت إجمالي مبيعات النظام -->
        <div class="col-md-6 col-lg-6">
            <div class="card stat-card bg-success text-white p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-white-50">💰 إجمالي المبيعات المسجلة</h6>
                        <h2 class="fw-bold mb-0"><?= number_format($sales_total, 2) ?> ر.س</h2>
                    </div>
                    <i class="fa-solid fa-file-invoice-dollar fs-1 text-white-50"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================================== -->
    <!-- بطاقة الوظائف والخدمات حسب الصلاحية الإجبارية المستلمة (Permissions) -->
    <!-- ============================================================================== -->
    <div class="main-card">
        <h4 class="fw-bold mb-4 text-secondary">
            <i class="fa-solid fa-grip me-2"></i> القائمة الرئيسية والمهام المتاحة
        </h4>

        <div class="row g-3">
            <!-- ========================================================== -->
            <!-- 1. صلاحيات المستخدم العادي (role = user)                   -->
            <!-- ========================================================== -->
            <?php if ($currentRole === 'user'): ?>
                <div class="col-md-4">
                    <a href="invoice_all.php" class="btn btn-primary action-btn w-100">
                        <i class="fa-solid fa-receipt"></i> قائمة الفواتير
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="invoce.php" class="btn btn-success action-btn w-100">
                        <i class="fa-solid fa-cart-plus"></i> فاتورة بيع جديدة (POS)
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="reports.php" class="btn btn-info text-white action-btn w-100">
                        <i class="fa-solid fa-chart-line"></i> التقارير
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="customers.php" class="btn btn-secondary action-btn w-100">
                        <i class="fa-solid fa-users"></i> عرض العملاء
                    </a>
                </div>
            <?php endif; ?>

            <!-- ========================================================== -->
            <!-- 2. صلاحيات مدير الصيدلية (role = manager)                   -->
            <!-- ========================================================== -->
            <?php if ($currentRole === 'manager'): ?>
                <div class="col-md-4">
                    <a href="medicines.php" class="btn btn-primary action-btn w-100">
                        <i class="fa-solid fa-pills"></i> عرض وإدارة الأدوية
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="invoice_all.php" class="btn btn-primary action-btn w-100">
                        <i class="fa-solid fa-receipt"></i> الفواتير
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="invoce.php" class="btn btn-success action-btn w-100">
                        <i class="fa-solid fa-cart-plus"></i> فاتورة جديدة (POS)
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="reports.php" class="btn btn-info text-white action-btn w-100">
                        <i class="fa-solid fa-chart-pie"></i> التقارير المالية والطباعة
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="customers.php" class="btn btn-secondary action-btn w-100">
                        <i class="fa-solid fa-users"></i> عرض العملاء
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="view_employees.php" class="btn btn-dark action-btn w-100">
                        <i class="fa-solid fa-user-tie"></i> عرض الموظفين
                    </a>
                </div>
            <?php endif; ?>

            <!-- ========================================================== -->
            <!-- 3. صلاحيات مدير النظام الكاملة (role = admin)              -->
            <!-- ========================================================== -->
            <?php if ($currentRole === 'admin'): ?>
                <div class="col-md-4">
                    <a href="medicines.php" class="btn btn-primary action-btn w-100">
                        <i class="fa-solid fa-pills"></i> إدارة الأدوية والمخزون
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="invoce.php" class="btn btn-success action-btn w-100">
                        <i class="fa-solid fa-cart-plus"></i> نقطة البيع (POS)
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="invoice_all.php" class="btn btn-outline-primary action-btn w-100">
                        <i class="fa-solid fa-receipt"></i> قائمة الفواتير
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="reports.php" class="btn btn-info text-white action-btn w-100">
                        <i class="fa-solid fa-chart-pie"></i> التقارير الشاملة
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="customers.php" class="btn btn-secondary action-btn w-100">
                        <i class="fa-solid fa-users"></i> إدارة العملاء
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="suppliers.php" class="btn btn-warning text-dark action-btn w-100">
                        <i class="fa-solid fa-truck-field"></i> إدارة الموردين
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="viewuser.php" class="btn btn-dark action-btn w-100">
                        <i class="fa-solid fa-user-gear"></i> إدارة المستخدمين
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="view_employees.php" class="btn btn-secondary action-btn w-100">
                        <i class="fa-solid fa-user-tie"></i> إدارة الموظفين
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="returns.php" class="btn btn-danger action-btn w-100">
                        <i class="fa-solid fa-rotate-left"></i> المرتجعات
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="order_all.php" class="btn btn-outline-dark action-btn w-100">
                        <i class="fa-solid fa-truck-ramp-box"></i> طلبات الشراء
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="shipping.php" class="btn btn-outline-info action-btn w-100">
                        <i class="fa-solid fa-boxes-stacked"></i> الشحن والتوصيل
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="invontory.php" class="btn btn-outline-success action-btn w-100">
                        <i class="fa-solid fa-warehouse"></i> تفاصيل الجرد
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="backup.php" class="btn btn-outline-warning text-dark action-btn w-100">
                        <i class="fa-solid fa-database"></i> النسخ الاحتياطي
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- تضمين جافاسكريبت المساعد لـ Bootstrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
