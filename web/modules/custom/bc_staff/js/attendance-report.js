(function (Drupal, once) {
  Drupal.behaviors.bcAttendanceReportFilter = {
    attach(context) {
      once('bc-attendance-report-filter', '[data-bc-attendance-report-filter]', context).forEach((form) => {
        const from = form.querySelector('input[name="from"]');
        const to = form.querySelector('input[name="to"]');
        const exportLink = form.querySelector('[data-bc-attendance-export]');
        if (!from || !to) return;
        const update = () => {
          if (!from.value || !to.value || !from.validity.valid || !to.validity.valid || from.value > to.value) return;
          if (exportLink) {
            const csv = new URL(exportLink.href);
            csv.searchParams.set('from', from.value);
            csv.searchParams.set('to', to.value);
            exportLink.href = csv.toString();
          }
          const url = new URL(form.action || window.location.href);
          url.searchParams.set('from', from.value);
          url.searchParams.set('to', to.value);
          window.location.assign(url.toString());
        };
        from.addEventListener('change', update);
        to.addEventListener('change', update);
      });
    }
  };
})(Drupal, once);
