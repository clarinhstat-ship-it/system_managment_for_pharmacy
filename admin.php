
<?php
session_start(); // بدء الجلسة لتخزين بيانات المدير لاحقًا

require_once 'db.php'; // الاتصال بقاعدة البيانات

// إذا تم إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // استعلام لجلب بيانات المدير بناءً على اسم المستخدم
    $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = :username");
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    // تحقق من أن المستخدم موجود وكلمة المرور صحيحة
    if ($admin && password_verify($password, $admin['password'])) {
        // حفظ بيانات المدير في الجلسة
        $_SESSION['adminid'] = $admin['adminid'];
        $_SESSION['adminname'] = $admin['name'];

        // توجيه إلى الصفحة الرئيسية
        header("Location:index.php");
        exit;
    } else {
        $error = "اسم المستخدم أو كلمة المرور غير صحيحة!";
    }
}
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>تسجيل دخول المدير</title>

    <!-- استدعاء Bootstrap للتصميم -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        /* تنسيق الصفحة بالكامل */
        body {
            background-color: #f0f0f0;
            font-family: 'Cairo', sans-serif;
            direction: rtl;
        }

        .login-box {
            background-color: white;
            border-radius: 15px;
            padding: 30px;
            margin: 100px auto;
            max-width: 450px;
            box-shadow: 0 0 15px rgba(0,0,0,0.2);
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #333;
              pack: acos;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="login-box">
        <h2>تسجيل دخول المدير</h2>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger text-center">
                <?= $error ?>
            </div>
        <?php endif; ?>
        <!-- نموذج تسجيل الدخول -->
        <form method="post">
            <div class="mb-3">
                <label class="form-label">اسم المستخدم:</label>
                <input type="text" name="username" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">كلمة المرور:</label>
                <input type="password" name="password" class="form-control" required>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">دخول</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>