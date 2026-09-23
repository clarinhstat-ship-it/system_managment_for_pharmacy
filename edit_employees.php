
<?php
 // 1. بدء الجلسة (Session) للتحقق من تسجيل الدخول
session_start();

// 2. التحقق من تسجيل الدخول - إذا لم يكن المستخدم مسجل دخوله، يتم تحويله لصفحة تسجيل الدخول
if (!isset($_SESSION['userid'])) {
    header("Location: login.php");
    exit;
}
require_once 'db.php';

// تحقق من وجود معرف المستخدم
if (!isset($_GET['employeid'])) {
    die("المعرف غير موجود!");
}
$employeid = $_GET['employeid'];
// جلب بيانات الموظف
try {
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE employeid = :id");
    $stmt->bindParam(':id', $employeid, PDO::PARAM_INT);
    $stmt->execute();
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$emp) {
        die("الموظف غير موجود!");
    }
} catch (PDOException $e) {
    die("خطأ في جلب الموظف: " . $e->getMessage());
}

// إذا تم تعديل النموذج
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    // $username = $_POST['username'];
    $phone = $_POST['phone'];
    // $password = $_POST['password'];
    $address = $_POST['address'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $salary = $_POST['salary'];
    // $adminid = $_POST['adminid'];

    try {
        $stmt = $pdo->prepare("UPDATE employees 
                               SET name = :name, phone=:phone, email=:email, address = :address,salary=:salary, role = :role   /*, adminid = :adminid */
                               WHERE employeid = :id");

        $stmt->bindParam(':name', $name);
        // $stmt->bindParam(':username', $username);
        $stmt->bindParam(':phone', $phone);
        // $stmt->bindParam(':password', $password);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':salary', $salary);
        $stmt->bindParam(':role', $role);
        // $stmt->bindParam(':adminid', $adminid);
        $stmt->bindParam(':id', $employeid);

        $stmt->execute();

        // إعادة التوجيه بعد التحديث
        header("Location: view_employees.php");
        exit;
    } catch (PDOException $e) {
        die("خطأ في التحديث: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>تعديل مستخدم</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background-color: #f2f2f2;
            direction: rtl;
        }

        .form-box {
            background: #fff;
            padding: 30px;
            margin-top: 60px;
            border-radius: 15px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }

        h2 {
            text-align: center;
            margin-bottom: 25px;
        }
    </style>
</head>
<body>

<div class="container col-md-6">
    <div class="form-box">
        <h2>تعديل بيانات الموظف</h2>
        <form method="post">
            <div class="mb-3">
                <label class="form-label">الاسم :</label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($emp['name']) ?>" required>
            </div>
<!--                     <td><?= htmlspecialchars($emp['salary']) ?></td>

            <div class="mb-3">
                <label class="form-label">اسم الموظف:</label>
                <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required> -->
            </div>

            <div class="mb-3">
                <label class="form-label">رقم الهاتف :</label>
                <input type="number" name="phone" class="form-control" value="<?= htmlspecialchars($emp['phone']) ?>" required>
            <!-- </div>
            <div class="mb-3">
                <label class="form-label">كلمه المرور:</label>
                <input type="password" name="password" class="form-control" value="<?= htmlspecialchars($user['password']) ?>" required>
            </div> -->

            
                        <div class="mb-3">
                            <label class="form-label">العنوان:</label>
                            <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($emp['address']) ?>" required>
                        </div>
            <div class="mb-3">
                <label class="form-label">الايميل :</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($emp['email']) ?>" required>
            </div>
        </div>
        
        <div class="mb-3">
            <label class="form-label">الدور (role):</label>
            <input type="text" name="role" class=" form-control" value="<?= htmlspecialchars($emp['role']) ?>" required>
            
            </div>
        
                        <div class="mb-3">
                            <label class="form-label">الراتب:</label>
                            <input type="number" name="salary" class="form-control" value="<?= htmlspecialchars($emp['salary']) ?>" required>
            
            <!-- <div class="mb-3">
                <label class="form-label">معرف المسؤول (adminid):</label>
                <input type="number" name="adminid" class="form-control" value="<?= htmlspecialchars($emp['adminid']) ?>" required>
            </div> -->

            <div class="text-center">
                <button type="submit" class="btn btn-warning">تحديث البيانات</button>
                <a href="view_employees.php" class="btn btn-secondary">إلغاء</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>
