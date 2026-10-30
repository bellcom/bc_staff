(function (Drupal, once) {
  Drupal.behaviors.bcStaffOrganizationDirectory = {
    attach(context) {
      once('bc-staff-org-directory', '[data-bc-org]', context).forEach((root) => {
        const input = root.querySelector('[data-bc-org-search]');
        const counter = root.querySelector('[data-bc-org-count]');
        const cards = [...root.querySelectorAll('[data-bc-org-card]')];
        const refresh = () => {
          const query = input.value.trim().toLocaleLowerCase();
          let count = 0;
          cards.forEach((card) => {
            let matching = 0;
            card.querySelectorAll('[data-bc-org-entry]').forEach((entry) => {
              const visible = !query || entry.dataset.bcOrgName.includes(query);
              entry.hidden = !visible;
              if (visible) {
                matching++;
                count++;
              }
            });
            const empty = card.querySelector('[data-bc-org-no-match]');
            if (empty) empty.hidden = !query || matching > 0;
          });
          counter.textContent = Drupal.formatPlural(count, '1 entry', '@count entries');
        };
        input.addEventListener('input', refresh);
        refresh();
      });
    }
  };
})(Drupal, once);
