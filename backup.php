<?php
// ==============================================================================
// 1. بدء الجلسة والمصادقة والتحقق من صلاحية المدير
// ==============================================================================

// تضمين ملف المصادقة الذي يفحص الجلسة والاتصال بقاعدة البيانات PDO
require_once 'auth.php'; // حماية الصفحة

// التأكد من أن المستخدم مدير نظام (admin) لاستخدام أداة النسخ الاحتياطي
if (!checkRole('admin')) {
    die("❌ ليس لديك صلاحية لأخذ نسخة احتياطية لقاعدة البيانات.");
}

// ==============================================================================
// 2. معالجة طلب تنزيل النسخة الاحتياطية لقاعدة البيانات (MySQL Backup Export)
// ==============================================================================

if (isset($_GET['action']) && $_GET['action'] === 'download') {
    try {
        // جلب جميع الجداول الموجودة في قاعدة البيانات
        $tables = [];
        $result = $pdo->query("SHOW TABLES");
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        $sqlScript = "-- ==========================================================\n";
        $sqlScript .= "-- النسخة الاحتياطية لقاعدة بيانات صيدلية الشرية المركزية\n";
        $sqlScript .= "-- التاريخ والوقت: " . date('Y-m-d H:i:s') . "\n";
        $sqlScript .= "-- ==========================================================\n\n";

        $sqlScript .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        // التكرار على كل جدول لبناء استعلام التشييد والبيانات
        foreach ($tables as $table) {
            // جلب كود إنشاء الجدول
            $createRow = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
            $sqlScript .= "DROP TABLE IF EXISTS `$table`;\n";
            $sqlScript .= $createRow[1] . ";\n\n";

            // جلب جميع صفوف البيانات من الجدول
            $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $keys = array_map(function($k) { return "`$k`"; }, array_keys($row));
                $values = array_map(function($v) use ($pdo) {
                    if ($v === null) return "NULL";
                    return $pdo->quote($v);
                }, array_values($row));

                $sqlScript .= "INSERT INTO `$table` (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $values) . ");\n";
            }
            $sqlScript .= "\n";
        }

        $sqlScript .= "SET FOREIGN_KEY_CHECKS=1;\n";

        // إرسال الترويسات وتنزيل الملف بصيغة .sql
        $filename = 'pharmacy_backup_' . date('Y-m-d_H-i-s') . '.sql';
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($sqlScript));

        // تسجيل العملية في سجل الأمان
        $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_name, action, details) VALUES (:uname, 'تنزيل نسخة احتياطية', 'قام المدير بتنزيل نسخة احتياطية لقاعدة البيانات بنجاح')");
        $logStmt->execute([':uname' => $_SESSION['name']]);

        echo $sqlScript;
        exit;

    } catch (Exception $e) {
        die("❌ فشل إنشاء النسخة الاحتياطية: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>النسخ الاحتياطي لقاعدة البيانات - صيدلية الشرية</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background-color: #f4f6f9;
            direction: rtl;
            padding-bottom: 40px;
        }

        .backup-card {
            background-color: #ffffff;
            border-radius: 16px;
            padding: 35px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            max-width: 600px;
            margin: 40px auto;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="backup-card">
        
        <div class="mb-4">
            <i class="fa-solid fa-database text-warning display-3 mb-3"></i>
            <h3 class="fw-bold text-dark">أداة النسخ الاحتياطي لقاعدة البيانات</h3>
            <p class="text-muted">يمكنك تنزيل نسخة احتياطية كاملة من قاعدة البيانات (.SQL) لحفظ بيانات الصيدلية واسترجاعها في أي وقت بأمان.</p>
        </div>

        <div class="d-grid gap-3">
            <!-- زر تنزيل النسخة الاحتياطية -->
            <a href="backup.php?action=download" class="btn btn-warning py-3 fs-5 fw-bold text-dark shadow-sm">
                <i class="fa-solid fa-download me-2"></i> تنزيل النسخة الاحتياطية الآن (.SQL)
            </a>

            <!-- زر العودة للرئيسية -->
            <a href="index.php" class="btn btn-outline-secondary py-2">
                <i class="fa-solid fa-arrow-right me-1"></i> العودة للصفحة الرئيسية
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
