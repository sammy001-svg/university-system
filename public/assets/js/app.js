/* University Management System - front-end behaviour */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {

    /* Sidebar toggle on small screens */
    var toggle = document.querySelector('[data-sidebar-toggle]');
    var sidebar = document.querySelector('.sidebar');
    if (toggle && sidebar) {
      toggle.addEventListener('click', function () { sidebar.classList.toggle('open'); });
      document.addEventListener('click', function (e) {
        if (window.innerWidth <= 992 && sidebar.classList.contains('open') &&
            !sidebar.contains(e.target) && !toggle.contains(e.target)) {
          sidebar.classList.remove('open');
        }
      });
    }

    /* Confirm before destructive actions */
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        if (!window.confirm(form.getAttribute('data-confirm'))) { e.preventDefault(); }
      });
    });
    document.querySelectorAll('a[data-confirm]').forEach(function (link) {
      link.addEventListener('click', function (e) {
        if (!window.confirm(link.getAttribute('data-confirm'))) { e.preventDefault(); }
      });
    });

    /* Auto-submit filter forms when a select changes */
    document.querySelectorAll('[data-auto-submit]').forEach(function (el) {
      el.addEventListener('change', function () { el.closest('form').submit(); });
    });

    /* Select-all checkbox for bulk tables */
    document.querySelectorAll('[data-check-all]').forEach(function (master) {
      var scope = document.querySelector(master.getAttribute('data-check-all'));
      if (!scope) { return; }
      master.addEventListener('change', function () {
        scope.querySelectorAll('input[type=checkbox][name^="ids"]').forEach(function (box) {
          box.checked = master.checked;
        });
      });
    });

    /* Dismiss flash messages automatically */
    document.querySelectorAll('.alert-dismissible[data-auto-dismiss]').forEach(function (alert) {
      setTimeout(function () {
        alert.classList.add('fade');
        setTimeout(function () { alert.remove(); }, 350);
      }, 6000);
    });

    /* Live client-side filtering of a table */
    document.querySelectorAll('[data-table-filter]').forEach(function (input) {
      var table = document.querySelector(input.getAttribute('data-table-filter'));
      if (!table) { return; }
      input.addEventListener('input', function () {
        var term = input.value.toLowerCase();
        table.querySelectorAll('tbody tr').forEach(function (row) {
          row.style.display = row.textContent.toLowerCase().indexOf(term) > -1 ? '' : 'none';
        });
      });
    });

    /* Keep computed totals in mark-entry grids up to date */
    document.querySelectorAll('[data-mark-row]').forEach(function (row) {
      var cw = row.querySelector('[data-cw]');
      var ex = row.querySelector('[data-ex]');
      var out = row.querySelector('[data-total]');
      if (!cw || !ex || !out) { return; }
      var cwWeight = parseFloat(row.getAttribute('data-cw-weight') || '30');
      var exWeight = parseFloat(row.getAttribute('data-ex-weight') || '70');
      function recalc() {
        var a = parseFloat(cw.value || '0');
        var b = parseFloat(ex.value || '0');
        var total = (a * cwWeight / 100) + (b * exWeight / 100);
        out.textContent = isNaN(total) ? '-' : total.toFixed(1);
      }
      cw.addEventListener('input', recalc);
      ex.addEventListener('input', recalc);
      recalc();
    });

    /* Print button */
    document.querySelectorAll('[data-print]').forEach(function (btn) {
      btn.addEventListener('click', function () { window.print(); });
    });
  });
})();
