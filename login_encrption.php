<?php
// session_start();
// require_once 'db.php';

// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     $username = trim($_POST['username']);
//     $password = trim($_POST['password']);

//     try {
//         $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = ?");
//         $stmt->execute([$username]);
//         $user = $stmt->fetch();

//         if ($user) {

//             if ($user['username'] === 'admin' && $password === '123') {
//                 $_SESSION['user_id'] = $user['id'];
//                 header("Location: index.php");
//                 exit;
//             } else {
//                 header("Location: index.php?error=اسم المستخدم أو كلمة المرور غير صحيحة");
//                 exit;
//             }
//         } else {
//             header("Location: index.php?error=اسم المستخدم غير موجود");
//             exit;
//         }
//     } catch (PDOException $e) {
//         die("خطأ في الاتصال: " . $e->getMessage());
//     }
// }
?>



<?php
session_start();
require_once "db.php"; 

if(isset($_POST['login' ])){
    $name = trim($_POST[ 'name' ]);
    $password = trim($_POST[ 'password' ]);

    $sql = "SELECT * FROM users WHERE name = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name]);
    $user = $stmt->fetch();

    if($user && password_verify($password, $user['password'])){
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];

        header("Location: index.php");
        exit;
    } else {
        $error = "❌ الاسم أو كلمة المرور غير صحيحة";
    }
}
var_dump($_post);
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>تسجيل الدخول</title>
</head>
<body>
    <h2>صفحة الدخول</h2>
    <?php if(isset($error)) echo "<p style= color:red; >$error</p>"; ?>
    <form method="post">
        <label>الاسم:</label>
        <input type="text" name="name" required><br>
        <label>كلمة المرور:</label>
        <input type="password_verify()" name="password" required><br>
        <label> المرور:</label>
        <input type="text" name="role" required><br>
        <button type="submit" name="login">دخول</button>
    </form>
</body>
</html>













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
    header('Location: inn.php');
    exit;
}
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
 <?php
// dashboard.php
session_start();

// 1) تأكد أن المستخدم مسجّل دخول
if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // إذا مش متواجد → نعيده لصفحة login
    header('Location: login.php');
    exit;
}

// 2) نعرّف قائمة الصلاحيات لكل دور (permissions)
$permissions = [
    'superadmin' => ['view_invoices', 'edit_invoices', 'manage_users', 'view_dashboard', 'all'],
    'admin'      => ['view_dashboard', 'manage_users'],
    'accountant' => ['view_invoices', 'edit_invoices', 'view_dashboard'],
    'client'     => ['view_invoices'],
    'guest'      => []
];

// 3) دور المستخدم الحالي
$role = $_SESSION['role'] ?? 'guest';
$user = $_SESSION['user'] ?? 'غير معروف';

// مساعدة: دالة صغيرة للتحقق من وجود صلاحية
function has_permission($perm, $role, $permissions) {
    // إذا الدور يملك 'all' يعني كل الصلاحيات
    if (in_array('all', $permissions[$role] ?? [])) return true;
    return in_array($perm, $permissions[$role] ?? []);
}

?>
<!DOCTYPE html>
<html lang="ar">
<head><meta charset="utf-8"><title>لوحة التحكم</title></head>
<body>
  <h1>أهلاً <?php echo htmlspecialchars($user); ?> — دورك: <?php echo htmlspecialchars($role); ?></h1>

  <nav>
    <ul>
      <?php if (has_permission('view_dashboard', $role, $permissions)): ?>
        <li><a href="index.php">الصفحة الرئيسية</a></li>
      <?php endif; ?>

        <li><a href="invoices.php">صفحة الفواتير</a></li>
      <?php endif; ?>

      <?php if (has_permission('edit_invoices', $role, $permissions)): ?>
        <li><a href="edit_invoices.php">تعديل الفواتير</a></li>
      <?php endif; ?>

      <?php if (has_permission('manage_users', $role, $permissions)): ?>
        <li><a href="manage_users.php">إدارة المستخدمين</a></li>
      <?php endif; ?>
    </ul>
  </nav>

  <hr>

  <h3>معلومات عامة</h3>
  <p>هذا محتوى عام في لوحة التحكم. يمكنك إضافة محتوى مخصص حسب الصلاحية.</p>

  <p><a href="logout.php">تسجيل الخروج</a></p>
</body>
</html>

[13/04/47 11:03 م] د/الغالي: في راس صغحه الفواتير

<?php
session_start();
$permissions = [
  'superadmin' => ['view_invoices', 'edit_invoices', 'manage_users', 'view_dashboard', 'all'],
  'admin'      => ['view_dashboard', 'manage_users'],
  'accountant' => ['view_invoices', 'edit_invoices', 'view_dashboard'],
  'client'     => ['view_invoices'],
  'guest'      => []
];

$role = $_SESSION['role'] ?? 'guest';
function has_permission($perm, $role, $permissions) {
  if (in_array('all', $permissions[$role] ?? [])) return true;
  return in_array($perm, $permissions[$role] ?? []);
}

if (!has_permission('view_invoices', $role, $permissions)) {
  // ممنوع الدخول
  header('Location: dashboard.php');
  exit;
}

// باقي كود الصفحة هنا (عرض الفواتير)
?>