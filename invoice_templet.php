<?php
/********************* صفحة إنشاء الفاتورة *********************/
// هنا نبدأ جلسة لتخزين بيانات الفاتورة مؤقتاً
session_start();

// عند إرسال الفورم يتم تخزين البيانات في السيشن
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['invoice'] = $_POST;
    // تحويل لصفحة الطباعة
    header("Location: invoice_print.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إنشاء فاتورة</title>
    <style>
        body {
            font-family: "Tahoma", Arial, sans-serif;
            direction: rtl;
            text-align: right;
            background: #f9f9f9;
            margin: 20px;
        }
        .container {
            max-width: 900px;
            margin: auto;
            background: #fff;
            padding: 20px;
            border: 2px solid #444;
            border-radius: 10px;
        }
        h2 { text-align: center; margin-bottom: 20px; }
        label { display: block; margin: 5px 0; }
        input, select {
            padding: 5px;
            margin: 3px 0 10px;
            width: 100%;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            border: 1px solid #444;
            padding: 6px;
            text-align: center;
        }
        .btn {
            background: #007bff;
            color: #fff;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
            border-radius: 5px;
        }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
<div class="container">
    <h2>إنشاء فاتورة جديدة</h2>
    <form method="post">

        <!-- بيانات العميل -->
        <label>اسم العميل:</label>
        <input type="text" name="customer_name" required>

        <label>رقم الهاتف:</label>
        <input type="text" name="customer_phone">

        <label>العنوان:</label>
        <input type="text" name="customer_address">

        <!-- جدول الأصناف -->
        <table id="itemsTable">
            <thead>
            <tr>
                <th>الصنف</th>
                <th>الوحدة</th>
                <th>الكمية</th>
                <th>سعر الوحدة</th>
                <th>الإجمالي</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td><input type="text" name="item_name[]"></td>
                <td><input type="text" name="unit[]"></td>
                <td><input type="number" name="quantity[]" oninput="calcRow(this)"></td>
                <td><input type="number" name="price[]" oninput="calcRow(this)"></td>
                <td><input type="number" name="total[]" readonly></td>
            </tr>
            </tbody>
        </table>

        <button type="button" onclick="addRow()">+ إضافة صنف</button>
        <br><br>

        <!-- القيم -->
        <label>الإجمالي:</label>
        <input type="number" name="grand_total" id="grandTotal" readonly>

        <label>المدفوع:</label>
        <input type="number" name="paid">

        <label>المتبقي:</label>
        <input type="number" name="remaining">

        <button type="submit" class="btn">حفظ وطباعة</button>
    </form>
</div>

<script>
// إضافة صف جديد
function addRow() {
    const table = document.getElementById("itemsTable").getElementsByTagName("tbody")[0];
    const row = table.rows[0].cloneNode(true);
    row.querySelectorAll("input").forEach(input => input.value = "");
    table.appendChild(row);
}

// حساب الإجمالي لكل صف
function calcRow(el) {
    const row = el.closest("tr");
    const qty = row.querySelector('[name="quantity[]"]').value || 0;
    const price = row.querySelector('[name="price[]"]').value || 0;
    row.querySelector('[name="total[]"]').value = qty * price;

    calcTotal();
}

// حساب الإجمالي الكلي
function calcTotal() {
    let total = 0;
    document.querySelectorAll('[name="total[]"]').forEach(input => {
        total += Number(input.value) || 0;
    });
    document.getElementById("grandTotal").value = total;
}
</script>
</body>
</html>
