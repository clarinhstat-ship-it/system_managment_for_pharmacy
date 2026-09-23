// // function updateForm() {
// //     const type = document.querySelector('[name="type"]').value;
// //     const options = document.querySelectorAll('[name="medicine_id[]"] option');
// //     options.forEach(opt => {
// //         const price = opt.getAttribute('data-price');
// //         if (type === 'purchase') {
// //             opt.textContent = opt.textContent.replace(/-.*$/, '') + ` - سعر الشراء`;
// //         } else {
// //             opt.textContent = opt.textContent.replace(/-.*$/, '') + ` - ${price} ج.م`;
// //         }
// //     });
// // }

// // function addItem() {
// //     const container = document.getElementById('items');
// //     const row = document.createElement('div');
// //     row.className = 'item-row';
// //     row.innerHTML = `
// //         <select name="medicine_id[]" required>
// //             <option value="">اختر دواء</option>
// //             <?php foreach ($medicines as $m): ?>
// //                 <option value="<?php echo $m['medicineid']; ?>" data-price="<?php echo $m['selling_price']; ?>">
// //                     <?php echo $m['name']; ?> - <?php echo $m['selling_price']; ?> ج.م
// //                 </option>
// //             <?php endforeach; ?>
// //         </select>
// //         <input type="number" name="quantity[]" placeholder="الكمية" min="1" required>
// //     `;
// //     container.appendChild(row);
// // }
// ///************************************************** */


// // function addItem() {
// //     const itemsDiv = document.getElementById('items');
// //     const firstRow = itemsDiv.querySelector('.item-row');
// //     if (!firstRow) return;
// //     const newRow = firstRow.cloneNode(true);
// //     newRow.querySelector('select[name="medicine_id[]"]').selectedIndex = 0;
// //     newRow.querySelector('input[name="quantity[]"]').value = '';
// //     itemsDiv.appendChild(newRow);
// //   }
  
// //   function updateForm() {
// //     const type = document.querySelector('[name="type"]')?.value || '';
// //     document.querySelectorAll('select[name="medicine_id[]"]').forEach(select => {
// //       Array.from(select.options).forEach(opt => {
// //         if (!opt.value) return;
// //         const baseLabel = opt.dataset.label || opt.textContent.replace(/-.*$/, '').trim();
// //         const price = opt.dataset.price || '';
// //         opt.textContent = type === 'purchase' ? ${baseLabel} - سعر الشراء : ${baseLabel} - ${price} ج.م;
// //         // opt.textContent = opt.textContent.replace(/-.*$/, '') + ` - سعر الشراء`;

// //         opt.dataset.label = baseLabel;
// //      });
// //      });
// //   }
  
// // function updateForm() {
// //     const type = document.querySelector('[name="type"]')?.value || '';
// //     // لف على كل قوائم الأدوية
// //     document.querySelectorAll('select[name="medicine_id[]"]').forEach(select => {
// //       Array.from(select.options).forEach(opt => {
// //         if (!opt.value) return; // تخطي "اختر دواء"
// //         // خزن/اقرأ الاسم الأصلي من data-label (إن لم يوجد، استخرجه مرة)
// //         const baseLabel = opt.dataset.label || opt.textContent.replace(/-.*$/, '').trim();
// //         const price = opt.dataset.price || '';
  
// //         // حدث النص حسب النوع
// //         if (type === 'purchase') {
// //           opt.textContent = ${baseLabel} - سعر الشراء;
// //         } else {
// //           opt.textContent = ${baseLabel} - ${price} ج.م;
// //         }
// //         // احفظ الاسم الأصلي للمرات القادمة
// //         opt.dataset.label = baseLabel;
// //       });
// //     });
// //   }
  



// // ====== updateForm (معلّق سطر-سطر) ======
// function updateForm() {
//     // 1) نقرأ نوع الفاتورة من select اسم "type" (purchase أو sale)
//     const type = document.querySelector('[name="type"]')?.value || '';
  
//     // 2) نمر على كل قوائم الأدوية في الصفحة
//     document.querySelectorAll('select[name="medicine_id[]"]').forEach(select => {
//       // 3) نحصل كل option داخل الـ select
//       Array.from(select.options).forEach(opt => {
//         // 4) نتخطى خيار "اختر دواء" (value فارغ)
//         if (!opt.value) return;
  
//         // 5) نقرأ الاسم الأصلي للسجل من data-label إذا موجود، وإلا نبني الاسم من النص الحالي (مرة واحدة)
//         const baseLabel = opt.dataset.label || opt.textContent.replace(/-.*$/, '').trim();
  
//         // 6) نقرأ السعر من data-price (قد يكون فارغ)
//         const price = opt.dataset.price || '';
  
//         // 7) نحدّث نص الخيار بحسب نوع الفاتورة (شراء أم بيع)
//         if (type === 'purchase') {
//           opt.textContent = ${baseLabel} - سعر الشراء;
//         } else {
//           opt.textContent = ${baseLabel} - ${price} ج.م;
//         }
  
//         // 8) نتأكّد نخزن baseLabel في dataset حتى لا نفقد الاسم بعد استدعاءات لاحقة
//         opt.dataset.label = baseLabel;
//       });
//     });
//   }
  
//   // ====== addItem (معلّق سطر-سطر) ======
//   function addItem() {
//     // 1) نحصل على الحاوية التي بداخلها كل الصفوف
//     const itemsDiv = document.getElementById('items');
//     if (!itemsDiv) return;
  
//     // 2) نأخذ أول صف كنموذج / قالب
//     const firstRow = itemsDiv.querySelector('.item-row');
//     if (!firstRow) return;
  
//     // 3) ننسخ الصف كاملًا (مع الـ options و data-attributes)
//     const newRow = firstRow.cloneNode(true);
  
//     // 4) نقرّب قيم الحقول: نعيد الاختيار للـ default ونفرّغ الكمية
//     const select = newRow.querySelector('select[name="medicine_id[]"]');
//     const qty = newRow.querySelector('input[name="quantity[]"]');
//     if (select) select.selectedIndex = 0;
//     if (qty) qty.value = '';
  
//     // 5) نضيف الصف الجديد للحاوية
//     itemsDiv.appendChild(newRow);
  
//     // 6) إن أردت، بعد إضافة صف جديد تُحدّث النصوص بحسب نوع الفاتورة الحالي
//     updateForm();
//   }
  
//   // ====== تأكد استدعاء updateForm عند تغيير نوع الفاتورة وعند تحميل الصفحة ======
//   document.addEventListener('DOMContentLoaded', function() {
//     // عند تغيير نوع الفاتورة
//     const typeSelect = document.querySelector('[name="type"]');
//     if (typeSelect) {
//       typeSelect.addEventListener('change', updateForm);
//     }
  
//     // استدعاء مبدئي لتحديث النصوص عند تحميل الصفحة
//     updateForm();
//   });
  
function updateForm() {
    // 1) نحصل على قيمة نوع الفاتورة (purchase أو sale)
    const type = document.querySelector('[name="type"]')?.value || '';
  
    // 2) نمر على كل قوائم الأدوية في الصفحة
    document.querySelectorAll('select[name="medicine_id[]"]').forEach(select => {
      // 3) نحول مجموعة الخيارات إلى مصفوفة ونمر على كل option
      Array.from(select.options).forEach(opt => {
        // 4) إذا الخيار هو "اختر دواء" (value فارغ) نتخطاه
        if (!opt.value) return;
  
        // 5) نحصل على الاسم الأصلي من data-label إن وُجد، وإلا نقتطع الاسم من النص الحالي
        //    هذا يمنع فقدان الاسم بعد التعديلات المتكررة
        const baseLabel = opt.dataset.label || opt.textContent.replace(/-.*$/, '').trim();
  
        // 6) نحصل على السعر من data-price (قد يكون فارغًا)
        const price = opt.dataset.price || '';
  
        // 7) نحدّث نص الخيار حسب نوع الفاتورة
        //    *مهم:* نستخدم backticks ` ... ` لأننا نريد إدراج المتغيرات داخل النص
        if (type === 'purchase') {
          // نص عربي ثابت يجب أن يكون داخل backticks لأننا نستعمل template literal
          opt.textContent = $({baseLabel}) -" سعر الشراء";
        } else {
          // هنا نعرض السعر المخزن في data-price
          opt.textContent = $({baseLabel}) - $({price})- ج.م;
        }
  
        // 8) نخزن baseLabel في data-label حتى لا يفقد الاسم الأصلي لاحقًا
        opt.dataset.label = baseLabel;
});
  });
  }
  
  function addItem() {
    // نحصل على الحاوية
    const itemsDiv = document.getElementById('items');
  
    // نأخذ أول صف كنموذج
    const firstRow = itemsDiv.querySelector('.item-row');
    if (!firstRow) return;
  
    // نعمل نسخة عميقة من الصف (مع كل العناصر داخله)
    const newRow = firstRow.cloneNode(true);
  
    // نفرّغ الاختيارات والقيم
    const select = newRow.querySelector('select[name="medicine_id[]"]');
    const qty    = newRow.querySelector('input[name="quantity[]"]');
    if (select) select.selectedIndex = 0;
    if (qty)    qty.value = '';
  
    // نضيف الصف الجديد
    itemsDiv.appendChild(newRow);
  }