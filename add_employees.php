
<?php
 // 1. بدء الجلسة (Session) للتحقق من تسجيل الدخول
session_start();

// 2. التحقق من تسجيل الدخول - إذا لم يكن المستخدم مسجل دخوله، يتم تحويله لصفحة تسجيل الدخول
if (!isset($_SESSION['userid'])) {
    header("Location: login.php");
    exit;
}
require_once 'db.php';
// التحقق من إرسال النموذج
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // استقبال البيانات من النموذج
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    // $password = $_POST['password']; // سيتم تشفيره
    $email = $_POST['email'];
    $address = $_POST['address'];
    $salary = $_POST['salary'];
    $role = $_POST['role'];
    // $adminid = $_POST['adminid'];
    // تشفير كلمة المرور (أفضل استخدام bcrypt)
    // $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    try {
        // تجهيز استعلام الإدخال
        $stmt = $pdo->prepare("INSERT INTO employees (name, phone, email,address,salary, role)
                               VALUES (:name,:phone, :email, :address,:salary, :role)");

        // ربط البيانات بالاستعلام
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':phone', $phone);
        // $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':salary', $salary);
        $stmt->bindParam(':role', $role);
        // $stmt->bindParam(':adminid', $adminid);
        // تنفيذ الإدخال
        $stmt->execute();
        // إعادة التوجيه إلى قائمة المستخدمين
        header("Location: view_employees.php");
        exit;
    } catch (PDOException $e) {
        die("خطأ في الإدخال: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>إضافة مستخدم</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background-color: #f1f1f1;
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
        <h2>إضافة موظف جديد</h2>
        <form method="post">
            <div class="mb-3">
                <label class="form-label">اسم الموظف :</label>
                <input type="text" name="name" class="form-control" required>
            </div>

            <!-- <div class="mb-3">
                <label class="form-label">اسم المستخدم:</label>
                <input type="text" name="username" class="form-control" required>
            </div> -->

            <div class="mb-3">
                <label class="form-label">رقم الهاتف  :</label>
                <input type="number" name="phone" class="form-control" required>
            </div>
<!-- 
            <div class="mb-3">
                <label class="form-label">كلمة المرور:</label>
                <input type="password" name="password" class="form-control" required>
            </div> -->
            <div class="mb-3">
                <label class="form-label">الايميل :</label>
                <input type="email" name="email" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">العنوان:</label>
                <input type="text" name="address" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">الراتب:</label>
                <input type="number" name="salary" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">الدور (role):</label><br>
                <input type="text" name="role" value="موظف"  require class="form-control">
                <!-- <select name="role" class="form-select" required>
                    <option value="مدير">مدير</option>
                    <option value="موظف">موظف</option>
                    <option value="محاسب">محاسب</option>
                    <option value="موزع">موزع</option>
                    <option value="صيدلي">صيدلي</option>
                    <option value="مدير المخازن">مدير المخازن</option>
                    
                </select> -->
            </div>
<!-- 
            <div class="mb-3">
                <label class="form-label">معرف المسؤول (adminid):</label>
                <input type="number" name="adminid" class="form-control" required value="1">
            </div> -->
            <div class="text-center">
                <button type="submit" class="btn btn-success">حفظ الموظف</button>
                <a href="view_employees.php" class="btn btn-secondary">رجوع</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>
<!-- create table

employees ( employeid int primary key auto_increment,
           name varchar (150),
           phone int , email varchar(100),address varchar(100),salary int , role varchar(100)
          
          
          ); 
 -->

 

