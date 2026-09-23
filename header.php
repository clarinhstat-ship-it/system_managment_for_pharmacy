

<ul>
    <?php if (can('dashboard')): ?>
        <li><a href="index.php">الرئيسية</a></li>
    <?php endif; ?>

    <?php if (can('invoices')): ?>
        <li><a href="invo.php">الفواتير</a></li>
    <?php endif; ?>

    <?php if (can('medicines')): ?>
        <li><a href="medicines.php">الأدوية</a></li>
    <?php endif; ?>

    <?php if (can('reports')): ?>
        <li><a href="reports.php">التقارير</a></li>
    <?php endif; ?>

    <?php if (can('users')): ?>
        <li><a href="users.php">المستخدمين</a></li>
    <?php endif; ?>

    <?php if (can('print')): ?>
        <li><a href="print.php">طباعة</a></li>
    <?php endif; ?>
</ul>



