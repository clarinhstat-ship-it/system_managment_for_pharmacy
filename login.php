<?php
// ==============================================================================
// 1. بدء الجلسة واستدعاء ملف قاعدة البيانات
// ==============================================================================

// التحقق من حالة الجلسة وبدء جلسة جديدة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start(); // بدء الجلسة لتخزين بيانات المستخدم عند تسجيل الدخول
}

// استدعاء ملف الاتصال بقاعدة البيانات PDO
require_once 'db.php'; // تضمين ملف الاتصال $pdo

// ==============================================================================
// 2. إعادة توجيه المستخدم إذا كان مسجل دخوله بالفعل
// ==============================================================================

// التحقق مما إذا كانت جلسة المستخدم موجودة بالفعل
if (isset($_SESSION['name']) && isset($_SESSION['role'])) {
    header("Location: index.php"); // التوجيه للصفحة الرئيسية تلقائياً
    exit; // إيقاف تنفيذ السكريبت
}

$error = ''; // متغير لتخزين نصوص أخطاء الدخول

// ==============================================================================
// 3. معالجة طلب تسجيل الدخول عند إرسال النموذج (POST)
// ==============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // تنظيف المدخلات وتصفيتها لمنع المسافات الزائدة
    $name = trim($_POST['name'] ?? ''); // جلب اسم المستخدم وتصفيته
    $password = trim($_POST['password'] ?? ''); // جلب كلمة المرور وتصفيتها

    // التحقق من أن المستخدم أدخل الاسم وكلمة المرور
    if (!empty($name) && !empty($password)) {
        // استعلام مجهز بالكامل لمنع ثغرات حقن SQL (SQL Injection)
        $sql = "SELECT * FROM users WHERE name = :name LIMIT 1"; // البحث عن المستخدم بالاسم فقط أولاً
        $stmt = $pdo->prepare($sql); // تحضير الاستعلام بواسطة PDO
        $stmt->execute([':name' => $name]); // تنفيذ الاستعلام بحقن اسم المستخدم بأمان
        $user = $stmt->fetch(PDO::FETCH_ASSOC); // جلب البيانات كمصفوفة ترابطية

        if ($user) {
            // فحص كلمة المرور بواسطة password_verify للتشفير الآمن أو الفحص المباشر للحسابات القديمة
            $passwordMatched = false; // متغير لحالة صحة كلمة المرور

            if (password_verify($password, $user['password'])) {
                // إذا كانت كلمة المرور مشفرة ومطابقة
                $passwordMatched = true; // تعيين صحة كلمة المرور
            } elseif ($password === $user['password']) {
                // إذا كانت كلمة المرور نصاً صريحاً من الحسابات القديمة
                $passwordMatched = true; // تعيين صحة كلمة المرور
                
                // ترقية التشفير تلقائياً وتحديث كلمة المرور في قاعدة البيانات لمنع استغلالها مستقبلاً
                $newHash = password_hash($password, PASSWORD_DEFAULT); // تشفير كلمة المرور
                $updateHashStmt = $pdo->prepare("UPDATE users SET password = :hash WHERE userid = :id"); // تحضير استعلام التحديث
                $updateHashStmt->execute([':hash' => $newHash, ':id' => $user['userid']]); // تنفيذ تحديث التشفير
            }

            if ($passwordMatched) {
                // تخزين بيانات المستخدم في الجلسة بنجاح
                $_SESSION['userid'] = $user['userid']; // كود المستخدم
                $_SESSION['name'] = $user['name'];     // اسم المستخدم
                $_SESSION['role'] = $user['role'];     // دور وصلاحية المستخدم (admin, manager, user)

                // تسجيل العملية في سجل الأمان (audit_logs)
                try {
                    $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_name, action, details) VALUES (:name, 'تسجيل دخول', 'قام المستخدم بتسجيل الدخول بنجاح')"); // تحضير استعلام السجل
                    $logStmt->execute([':name' => $user['name']]); // تنفيذ حفظ السجل
                } catch (Exception $e) {
                    // تجاهل خطأ السجل في حالة وجود مشكلة بسيطة لعدم تعطيل الدخول
                }

                header('Location: index.php'); // التوجيه للصفحة الرئيسية
                exit; // إيقاف التنفيذ
            } else {
                $error = "❌ اسم المستخدم أو كلمة المرور غير صحيحة"; // رسالة خطأ آمنة لا توضح سبب الفشل بدقة للحماية
            }
        } else {
            $error = "❌ اسم المستخدم أو كلمة المرور غير صحيحة"; // رسالة خطأ آمنة
        }
    } else {
        $error = "❌ يرجى كتابة اسم المستخدم وكلمة المرور"; // رسالة في حال نقص المدخلات
    }
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8"> <!-- ضبط ترميز الصفحة لدعم اللغة العربية -->
    <meta name="viewport" content="width=device-width, initial-scale=1"> <!-- دعم الهواتف والشاشات المختلفة -->
    <title>تسجيل الدخول - صيدلية الشرية المركزية</title> <!-- عنوان الصفحة -->
    
    <!-- تضمين مكتبة Bootstrap للتنسيقات الحديثة والجمالية -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        /* تنسيق خلفية الصفحة بتدرج ألوان احترافي ومريح للعين */
        body {
            font-family: 'Cairo', 'Segoe UI', Tahoma, sans-serif; /* نوع الخط العربي */
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); /* خلفية متدرجة كحلي */
            min-height: 100vh; /* ملء ارتفاع الشاشة */
            display: flex; /* تفعيل Flexbox للمركزة */
            justify-content: center; /* مركزة محتوى الصفحة أفقياً */
            align-items: center; /* مركزة محتوى الصفحة رأسياً */
            margin: 0; /* إلغاء الهوامش الخارجية */
        }
        
        /* بطاقة نموذج تسجيل الدخول */
        .login-card {
            background: #ffffff; /* خلفية بيضاء للبطاقة */
            padding: 40px 30px; /* الحشوة الداخلية */
            border-radius: 20px; /* حواف دائرية ناعمة */
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25); /* ظل مرتفع وعميق للبطاقة */
            width: 100%; /* العرض الكامل ضمن الحد الأقصى */
            max-width: 400px; /* الحد الأقصى لعرض البطاقة */
            text-align: center; /* مركزة النصوص داخل البطاقة */
        }
        
        /* عنوان البطاقة */
        .login-card h2 {
            color: #1e3c72; /* لون العنوان */
            font-weight: 700; /* سمك الخط */
            margin-bottom: 25px; /* المسافة السفلية */
        }
        
        /* تنسيق حقول الإدخال */
        .form-control {
            border-radius: 10px; /* حواف ناعمة للحقول */
            padding: 12px 15px; /* الحشوة الداخلية */
            font-size: 15px; /* حجم الخط */
            border: 1px solid #ced4da; /* إطار رمادي فاتح */
        }
        
        /* إبراز الحقل عند التركيز عليه */
        .form-control:focus {
            border-color: #2a5298; /* تغيير لون الإطار */
            box-shadow: 0 0 8px rgba(42, 82, 152, 0.4); /* إشعاع أزرق ناعم */
        }
        
        /* زر تسجيل الدخول */
        .btn-login {
            background: linear-gradient(to right, #1e3c72, #2a5298); /* تدرج لون الزر */
            border: none; /* إلغاء الإطار */
            color: #fff; /* لون النص */
            padding: 12px; /* الحشوة */
            border-radius: 10px; /* حواف ناعمة */
            font-weight: bold; /* خط عريض */
            font-size: 16px; /* حجم الخط */
            width: 100%; /* زر بعرض البطاقة كاملة */
            transition: all 0.3s ease; /* تأثير حركة سلسة */
        }
        
        /* تأثير تحويم الماوس فوق الزر */
        .btn-login:hover {
            opacity: 0.9; /* تغيير درجة الشفافية */
            transform: translateY(-2px); /* رفع الزر لأعلى قليلاً */
        }
    </style>
</head>
<body>

<!-- بطاقة تسجيل الدخول الرئيسية -->
<div class="login-card">
    <div class="mb-4">
        <h3 class="fw-bold text-primary">💊 صيدلية الشرية</h3>
        <p class="text-muted small">نظام إدارة الصيدلية المركزية</p>
    </div>
    
    <h2>🔑 تسجيل الدخول</h2>

    <!-- إظهار رسالة الخطأ في حالة وجود خطأ في الدخول -->
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger p-2 mb-3 text-center small rounded-3" role="alert">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <!-- نموذج إدخال البيانات -->
    <form method="post" action="login.php">
        <div class="mb-3 text-start">
            <label for="name" class="form-label text-secondary fw-semibold">👤 اسم المستخدم:</label>
            <!-- حقل إدخال اسم المستخدم نصي آمن دون كشف أسماء المستخدمين -->
            <input type="text" name="name" id="name" class="form-control" required placeholder="أدخل اسم المستخدم الخاص بك" autocomplete="username">
        </div>

        <div class="mb-4 text-start">
            <label for="password" class="form-label text-secondary fw-semibold">🔒 كلمة المرور:</label>
            <!-- حقل إدخال كلمة المرور -->
            <input type="password" name="password" id="password" class="form-control" required placeholder="أدخل كلمة المرور" autocomplete="current-password">
        </div>

        <!-- زر تقديم النموذج -->
        <button type="submit" name="login" class="btn btn-login shadow-sm">دخول للنظام</button>
    </form>
</div>

<!-- مكتبة جافاسكريبت المساعدة لـ Bootstrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
