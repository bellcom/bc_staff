(function (Drupal, once) {
  Drupal.behaviors.bcStaffEmployeeAdmin = {
    attach(context) {
      once('bc-staff-employees', '[data-bc-employees]', context).forEach((root) => {
        const search = root.querySelector('[data-bc-employee-search]');
        const department = root.querySelector('[data-bc-employee-department]');
        const cards = [...root.querySelectorAll('[data-bc-employee-card]')];
        const count = root.querySelector('[data-bc-employee-count]');
        const empty = root.querySelector('[data-bc-employee-empty]');
        const filter = () => {
          let visible = 0;
          const q = search.value.trim().toLocaleLowerCase();
          cards.forEach((card) => {
            const show = (!q || card.dataset.search.includes(q))
              && (!department.value || card.dataset.department === department.value);
            card.hidden = !show;
            if (show) visible++;
          });
          count.textContent = Drupal.formatPlural(visible, '1 employee', '@count employees');
          empty.hidden = visible > 0;
        };
        search.addEventListener('input', filter);
        department.addEventListener('change', filter);
        filter();
      });
    }
  };
})(Drupal, once);
