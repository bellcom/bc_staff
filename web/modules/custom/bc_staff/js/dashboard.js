(function (Drupal, once) {
  Drupal.behaviors.bcStaffDashboard = {
    attach(context) {
      once('bc-staff-dashboard', '[data-bc-staff-dashboard]', context).forEach((root) => {
        const select = root.querySelector('[data-bc-staff-filter]');
        const cards = [...root.querySelectorAll('[data-bc-staff-card]')];
        const count = root.querySelector('[data-bc-staff-count]');
        const empty = root.querySelector('[data-bc-staff-empty]');
        const storageKey = 'bc-staff-department';
        try {
          const saved = sessionStorage.getItem(storageKey);
          if (saved && [...select.options].some((option) => option.value === saved)) {
            select.value = saved;
          }
        } catch (error) {
          // Browser storage is optional.
        }
        const filter = () => {
          let visible = 0;
          cards.forEach((card) => {
            const show = !select.value || card.dataset.department === select.value;
            card.hidden = !show;
            if (show) visible++;
          });
          count.textContent = visible + ' medarbejdere';
          empty.hidden = visible > 0;
          try { sessionStorage.setItem(storageKey, select.value); } catch (error) {}
        };
        select.addEventListener('change', filter);
        filter();
        // Never refresh while the page is hidden.
        window.setInterval(() => {
          if (!document.hidden) window.location.reload();
        }, 30000);
      });
    }
  };
})(Drupal, once);
