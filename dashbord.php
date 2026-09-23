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
