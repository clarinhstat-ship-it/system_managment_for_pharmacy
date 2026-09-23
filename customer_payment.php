



<?php
// 1. بدء الجلسة للتحقق من تسجيل الدخول
session_start();

// 2. التحقق من تسجيل الدخول
if (!isset($_SESSION['userid'])) {
    header("Location: login.php");
    exit;
}

// 3. تضمين ملف اتصال قاعدة البيانات
require_once 'db.php';

// 4. التحقق من وجود معرف العميل في الرابط
if (!isset($_GET['customer_id']) || !is_numeric($_GET['customer_id'])) {
    die("معرف العميل غير صحيح");
}

$customer_id = (int)$_GET['customer_id'];

// 5. جلب معلومات العميل
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

if (!$customer) {
    die("العميل غير موجود");
}

// 6. جلب رصيد العميل الحالي
$stmt = $pdo->prepare("SELECT balance FROM customer_accounts WHERE customer_id = ?");
$stmt->execute([$customer_id]);
$account = $stmt->fetch();
$balance = $account ? $account['balance'] : 0.00;

// 7. معالجة إضافة سند قبض
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment'])) {
    $amount = (float)$_POST['amount'];
    $payment_date = $_POST['payment_date'];
    $description = trim($_POST['description']);
    
    // 7.1. التحقق من صحة المبلغ
    if ($amount <= 0) {
        $error = "المبلغ يجب أن يكون أكبر من الصفر";
    } elseif ($amount > $balance) {
        $error = "المبلغ المدفوع أكبر من المديونية (الرصيد: " . number_format($balance, 2) . " ج.م)";
    } else {
        try {
            // 7.2. بدء المعاملة (Transaction)
            $pdo->beginTransaction();
            
            // 7.3. إضافة معاملة للعميل (دائن)
            $stmt = $pdo->prepare("INSERT INTO customer_transactions 
                                  (customer_id, transaction_type, amount, description, created_at) 
                                  VALUES (?, 'payment', ?, ?, ?)");
            $stmt->execute([
                $customer_id,
                $amount,
                $description,
                $payment_date
            ]);
            
            // 7.4. تحديث رصيد العميل
            $stmt = $pdo->prepare("UPDATE customer_accounts SET balance = balance - ? WHERE customer_id = ?");
            $stmt->execute([$amount, $customer_id]);
            
            // 7.5. إتمام المعاملة
            $pdo->commit();
            
            $success = "تم تسجيل سند القبض بنجاح";
            header("Location: customer_details.php?id=$customer_id&success=1");
            exit;
        } catch (PDOException $e) {
            // 7.6. التراجع عن التغييرات في حالة الخطأ
            $pdo->rollBack();
            error_log("خطأ في تسجيل سند قبض: " . $e->getMessage());
            $error = "حدث خطأ أثناء حفظ البيانات";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>سند قبض للعميل: <?= htmlspecialchars($customer['name']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
</head>
<body>
    <div class="container">
        <h2 class="page-title">سند قبض للعميل: <?= htmlspecialchars($customer['name']) ?></h2>
        <a href="customer_details.php?id=<?= $customer_id ?>" class="btn btn-outline-primary">العودة لتفاصيل العميل</a>

        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $success ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
            </div>
            <?php endif; ?>
            
        <!-- 8. نموذج سند قبض -->
        <div class="card">
            <div class="card-header">
                <h3>سند قبض</h3>
            </div>
            <div class="card-body">
                <div style="background: #f8fafc; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
                        <div>
                            <div style="font-weight: 600; color: #4a5568;">العميل:</div>
                            <div style="font-size: 1.1rem;"><?= htmlspecialchars($customer['name']) ?></div>
                        </div>
                        
                        <div>
                            <div style="font-weight: 600; color: #4a5568;">الهاتف:</div>
                            <div style="font-size: 1.1rem;"><?= htmlspecialchars($customer['phone']) ?></div>
                        </div>
                        
                        <div>
                            <div style="font-weight: 600; color: #4a5568;">المديونية الحالية:</div>
                            <div style="font-size: 1.2rem; color: <?= $balance > 0 ? '#e53e52' : '#38a169' ?>">
                                <?= number_format($balance, 2) ?> ج.م
                            </div>
                        </div>
                    </div>
                </div>
                
                <form method="POST">
                    <div class="form-row" style="display: flex; gap: 15px; margin-bottom: 20px;">
                        <div class="form-group" style="flex: 1;">
                            <label>المبلغ *</label>
                            <input type="number" step="0.01" name="amount" class="form-control" 
                                   value="<?= min(100, $balance) ?>" min="0.01" max="<?= $balance ?>" required>
                        </div>
                        
                        <div class="form-group" style="flex: 1;">
                            <label>التاريخ *</label>
                            <input type="date" name="payment_date" class="form-control" 
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>الوصف</label>
                        <textarea name="description" class="form-control" rows="3" 
                        <?= $amount=0;?>
                                  placeholder="ملاحظات إضافية...">
                                  <?= $balance == $amount ? 'تسديد كامل المديونية' : '' ?>
                                </textarea>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" name="add_payment" class="btn btn-primary" style="flex: 1;">
                            تسجيل سند القبض
                        </button>
                        <a href="customer_details.php?id=<?= $customer_id ?>" class="btn btn-secondary" style="flex: 1;">
                            إلغاء
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
