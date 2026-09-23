<?php
session_start();
if (!isset($_SESSION['userid'])) {
    header('Location: login.php');
    exit;
}

require_once 'db.php';

// انشاء جدول شحن إذا لم يكن موجوداً
try {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS order_shipping (
            shipping_id INT AUTO_INCREMENT PRIMARY KEY,
            orderid INT NOT NULL,
            shipping_method VARCHAR(100) DEFAULT NULL,
            delivery_address TEXT DEFAULT NULL,
            tracking_number VARCHAR(100) DEFAULT NULL,
            shipping_date DATE DEFAULT NULL,
            expected_delivery_date DATE DEFAULT NULL,
            delivery_status VARCHAR(50) DEFAULT 'pending',
            notes TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (orderid) REFERENCES orders(orderid) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
} catch (PDOException $e) {
    die('خطأ في إعداد جدول الشحن: ' . $e->getMessage());
}

$successMessage = ''; 
$errorMessage = '';

$orderShipping = null;
$order = null;
$orderid = isset($_GET['orderid']) ? (int)$_GET['orderid'] : null;

if ($orderid) {
    try {
        $stmt = $pdo->prepare(
            "SELECT o.*, s.name AS suppliername, os.shipping_id, os.shipping_method, os.delivery_address,
                    os.tracking_number, os.shipping_date, os.expected_delivery_date, os.delivery_status, os.notes AS shipping_notes
               FROM orders o
               LEFT JOIN suppliers s ON o.supplierid = s.supplierid
               LEFT JOIN order_shipping os ON o.orderid = os.orderid
              WHERE o.orderid = :orderid
              LIMIT 1"
        );
        $stmt->execute([':orderid' => $orderid]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die('خطأ في جلب بيانات الطلب: ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderid = isset($_POST['orderid']) ? (int)$_POST['orderid'] : null;
    $shipping_method = trim($_POST['shipping_method'] ?? '');
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $tracking_number = trim($_POST['tracking_number'] ?? '');
    $shipping_date = !empty($_POST['shipping_date']) ? $_POST['shipping_date'] : null;
    $expected_delivery_date = !empty($_POST['expected_delivery_date']) ? $_POST['expected_delivery_date'] : null;
    $delivery_status = trim($_POST['delivery_status'] ?? 'pending');
    $notes = trim($_POST['notes'] ?? '');

    if (!$orderid) {
        $errorMessage = 'معرف الطلب غير محدد.';
    }

    if (!$errorMessage) {
        try {
            $selectStmt = $pdo->prepare("SELECT shipping_id FROM order_shipping WHERE orderid = :orderid LIMIT 1");
            $selectStmt->execute([':orderid' => $orderid]);
            $existing = $selectStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $updateStmt = $pdo->prepare(
                    "UPDATE order_shipping SET shipping_method = :shipping_method,
                        delivery_address = :delivery_address,
                        tracking_number = :tracking_number,
                        shipping_date = :shipping_date,
                        expected_delivery_date = :expected_delivery_date,
                        delivery_status = :delivery_status,
                        notes = :notes
                      WHERE orderid = :orderid"
                );
                $updateStmt->execute([
                    ':shipping_method' => $shipping_method,
                    ':delivery_address' => $delivery_address,
                    ':tracking_number' => $tracking_number,
                    ':shipping_date' => $shipping_date,
                    ':expected_delivery_date' => $expected_delivery_date,
                    ':delivery_status' => $delivery_status,
                    ':notes' => $notes,
                    ':orderid' => $orderid,
                ]);
                $successMessage = 'تم تحديث بيانات الشحن بنجاح.';
            } else {
                $insertStmt = $pdo->prepare(
                    "INSERT INTO order_shipping (orderid, shipping_method, delivery_address, tracking_number, shipping_date, expected_delivery_date, delivery_status, notes)
                     VALUES (:orderid, :shipping_method, :delivery_address, :tracking_number, :shipping_date, :expected_delivery_date, :delivery_status, :notes)"
                );
                $insertStmt->execute([
                    ':orderid' => $orderid,
                    ':shipping_method' => $shipping_method,
                    ':delivery_address' => $delivery_address,
                    ':tracking_number' => $tracking_number,
                    ':shipping_date' => $shipping_date,
                    ':expected_delivery_date' => $expected_delivery_date,
                    ':delivery_status' => $delivery_status,
                    ':notes' => $notes,
                ]);
                $successMessage = 'تم حفظ بيانات الشحن بنجاح.';
            }

            $statusMap = [
                'pending' => 'pending',
                'shipped' => 'shipped',
                'in_transit' => 'in_transit',
                'delivered' => 'delivered',
                'cancelled' => 'cancelled'
            ];
            if (isset($statusMap[$delivery_status])) {
                $pdo->prepare("UPDATE orders SET status = :status WHERE orderid = :orderid")
                    ->execute([':status' => $statusMap[$delivery_status], ':orderid' => $orderid]);
            }

            header('Location: shipping.php?orderid=' . $orderid . '&success=1');
            exit;
        } catch (PDOException $e) {
            $errorMessage = 'فشل حفظ بيانات الشحن: ' . $e->getMessage();
        }
    }
}

try {
    $ordersStmt = $pdo->query(
        "SELECT o.orderid, o.order_date, o.status AS order_status, s.name AS suppliername,
                os.delivery_status, os.tracking_number, os.shipping_method
           FROM orders o
           LEFT JOIN suppliers s ON o.supplierid = s.supplierid
           LEFT JOIN order_shipping os ON o.orderid = os.orderid
          ORDER BY o.order_date DESC"
    );
    $ordersList = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('خطأ في جلب قائمة الطلبات: ' . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>الشحن والتوصيل</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { direction: rtl; font-family: 'Cairo', sans-serif; background:#f2f2f2; padding:30px; }
        .card { border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
        .table th, .table td { vertical-align: middle; }
        .badge-status { padding: 0.45em 0.8em; border-radius: 12px; font-size: 0.92em; }
        .badge-pending { background:#ffc107; color:#000; }
        .badge-shipped { background:#17a2b8; color:#fff; }
        .badge-in_transit { background:#0d6efd; color:#fff; }
        .badge-delivered { background:#28a745; color:#fff; }
        .badge-cancelled { background:#dc3545; color:#fff; }
    </style>
</head>
<body>
<div class="container">
    <div class="card p-4 mb-4">
        <h2 class="mb-3">الشحن والتوصيل</h2>
        <p>صفحة إدارة الشحن المرتبطة بالطلبات الحالية. اختر طلباً لتسجيل بيانات الشحن أو تحديثها.</p>
        <a href="order_all.php" class="btn -secondary">عرض الطلبات</a>
        <a href="index.php" class="btn -secondary">
                <i class="btn -secondary"></i> الرئيسية
            </a>
    </div>

    <?php if (!empty($successMessage) || isset($_GET['success'])): ?>
        <div class="alert alert-success"><?php echo $successMessage ?: 'تم حفظ البيانات بنجاح.'; ?></div>
    <?php endif; ?>
    <?php if ($errorMessage): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
    <?php endif; ?>

    <div class="card p-4 mb-4">
        <h4>قائمة الطلبات</h4>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th>رقم الطلب</th>
                        <th>المورد</th>
                        <th>تاريخ الطلب</th>
                        <th>حالة الطلب</th>
                        <th>حالة الشحن</th>
                        <th>رقم التتبع</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ordersList as $row): ?>
                        <tr>
                            <td><?= $row['orderid'] ?></td>
                            <td><?= htmlspecialchars($row['suppliername'] ?? 'غير معروف') ?></td>
                            <td><?= htmlspecialchars($row['order_date']) ?></td>
                            <td><?= htmlspecialchars($row['order_status']) ?></td>
                            <td>
                                <?php $ds = $row['delivery_status'] ?? 'pending'; ?>
                                <span class="badge-status badge-<?= htmlspecialchars($ds) ?>"><?= htmlspecialchars($ds) ?></span>
                            </td>
                            <td><?= htmlspecialchars($row['tracking_number'] ?? '-') ?></td>
                            <td>
                                <a href="shipping.php?orderid=<?= $row['orderid'] ?>" class="btn btn-primary btn-sm">تسجيل / تعديل</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($order): ?>
        <div class="card p-4 mb-4">
            <h4>تحديث الشحن للطلب رقم <?= $order['orderid'] ?></h4>
            <p>المورد: <?= htmlspecialchars($order['suppliername'] ?? 'غير معروف') ?> | حالة الطلب: <?= htmlspecialchars($order['status']) ?></p>
            <form method="post">
                <input type="hidden" name="orderid" value="<?= $order['orderid'] ?>">
                <div class="row gy-3">
                    <div class="col-md-6">
                        <label class="form-label">طريقة الشحن</label>
                        <input name="shipping_method" class="form-control" value="<?= htmlspecialchars($order['shipping_method'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">رقم التتبع</label>
                        <input name="tracking_number" class="form-control" value="<?= htmlspecialchars($order['tracking_number'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">تاريخ الشحن</label>
                        <input type="date" name="shipping_date" class="form-control" value="<?= htmlspecialchars($order['shipping_date'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">تاريخ التوصيل المتوقع</label>
                        <input type="date" name="expected_delivery_date" class="form-control" value="<?= htmlspecialchars($order['expected_delivery_date'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">عنوان التوصيل</label>
                        <textarea name="delivery_address" class="form-control" rows="3"><?= htmlspecialchars($order['delivery_address'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">حالة التوصيل</label>
                        <select name="delivery_status" class="form-control">
                            <?php
                            $statuses = ['pending' => 'قيد الانتظار', 'shipped' => 'تم الشحن', 'in_transit' => 'في الطريق', 'delivered' => 'تم التوصيل', 'cancelled' => 'ملغي'];
                            foreach ($statuses as $value => $label):
                            ?>
                                <option value="<?= $value ?>" <?= ($order['delivery_status'] ?? 'pending') === $value ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">ملاحظات الشحن</label>
                        <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($order['shipping_notes'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="mt-4 text-center">
                    <button type="submit" class="btn btn-success">حفظ بيانات الشحن</button>
                    <a href="shipping.php" class="btn btn-secondary">إلغاء</a>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
