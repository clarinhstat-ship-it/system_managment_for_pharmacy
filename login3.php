
 <?php
// login.php
session_start();

// --- تصحيح: إذا المستخدم أرسل الفورم (POST) نقبل الاسم ونعطيه دوراً ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // نأخذ الاسم من الفورم
    $username = trim($_POST['name'] ?? '');

    // مثال بسيط لتعيين الأدوار (في الواقع هذه البيانات تأتي من قاعدة بيانات)
    // هنا نحدد الأدوار يدوياً حسب الاسم (أنت تقدر تغيرها أو تربطها بقاعدة بيانات)
    $roles_map = [
        'abdalslam' => 'superadmin',   // عبدالسلام = كل الصلاحيات
        'saif'      => 'admin',        // سيف = مدير
        'maram'     => 'accountant',   // مرام = محاسبة (فواتير/تعديل)
        'client1'   => 'client'        // زبون مثلاً
    ];
     if (has_permission('view_invoices', $role, $permissions)):
    // لو الاسم موجود في الخريطة نحط الدور المحدد، وإلا نعطيه دور 'guest'
    $role = $roles_map[strtolower($username)] ?? 'guest';

    // حفظ في الجلسة
    $_SESSION['user'] = $username;
    $_SESSION['role'] = $role;
    $_SESSION['logged_in'] = true;

    // تروح لصفحة dashboard بعد تسجيل الدخول
    header('Location:dashbord.php');
     endif;
};
$f;
?>

<!DOCTYPE html>
<html lang="ar">
<head><meta charset="utf-8"><title>صفحة الدخول</title></head>
<body>
  <h2>تسجيل دخول بسيط</h2>
  <form method="post" action="login.php">
    <label>الاسم: <input type="text" name="name" required></label><br><br>
    <button type="submit">دخول</button>
  </form>

  <p>أمثلة أسماء للتجربة: abdalslam ، saif ، maram ، client1</p>
</body>
</html>