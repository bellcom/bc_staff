(function (Drupal, once) {
  Drupal.behaviors.bcStaffEmployeeAdmin = {
    attach(context) {
      once('bc-staff-employees', '[data-bc-employees]', context).forEach((root) => {
        const search = root.querySelector('[data-bc-employee-search]');
        const department = root.querySelector('[data-bc-employee-department]');
        const house = root.querySelector('[data-bc-employee-house]');
        const group = root.querySelector('[data-bc-employee-group]');
        const status = root.querySelector('[data-bc-employee-status]');
        const cards = [...root.querySelectorAll('[data-bc-employee-card]')];
        const count = root.querySelector('[data-bc-employee-count]');
        const empty = root.querySelector('[data-bc-employee-empty]');
        const reset = root.querySelector('[data-bc-employee-reset]');
        const grid = root.querySelector('[data-bc-employee-grid]');
        const drawer = root.querySelector('[data-bc-employee-drawer]');
        const shade = root.querySelector('[data-bc-employee-shade]');
        const frame = root.querySelector('[data-bc-employee-frame]');
        const title = root.querySelector('[data-bc-employee-drawer-title]');
        const close = root.querySelector('[data-bc-employee-close]');
        const filters = [department, house, group, status];
        const filter = () => {
          let visible = 0;
          const q = search.value.trim().toLocaleLowerCase();
          cards.forEach((card) => {
            const show = (!q || card.dataset.search.includes(q))
              && (!department.value || card.dataset.department === department.value)
              && (!house.value || card.dataset.house === house.value)
              && (!group.value || card.dataset.groups.split('|').includes(group.value))
              && (!status.value || card.dataset.status === status.value);
            card.hidden = !show;
            if (show) visible++;
          });
          count.textContent = Drupal.formatPlural(visible, '1 employee', '@count employees');
          empty.hidden = visible > 0;
        };
        search.addEventListener('input', filter);
        filters.forEach((control) => control.addEventListener('change', filter));
        reset.addEventListener('click', () => {
          search.value = '';
          filters.forEach((control) => { control.value = ''; });
          filter();
        });
        root.querySelectorAll('[data-bc-employee-view]').forEach((button) => {
          button.addEventListener('click', () => {
            const list = button.dataset.bcEmployeeView === 'list';
            grid.classList.toggle('is-list', list);
            root.querySelectorAll('[data-bc-employee-view]').forEach((candidate) => {
              const active = candidate === button;
              candidate.classList.toggle('is-active', active);
              candidate.setAttribute('aria-pressed', String(active));
            });
          });
        });
        let previousFocus = null;
        const closeDrawer = () => {
          drawer.hidden = true;
          shade.hidden = true;
          frame.removeAttribute('src');
          document.body.classList.remove('bc-employee-drawer-open');
          if (previousFocus) previousFocus.focus();
        };
        root.querySelectorAll('[data-bc-employee-edit]').forEach((link) => {
          link.addEventListener('click', (event) => {
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            previousFocus = link;
            title.textContent = link.dataset.employeeName;
            const panelUrl = new URL(link.href, window.location.href);
            panelUrl.searchParams.set('panel', '1');
            frame.src = panelUrl.toString();
            shade.hidden = false;
            drawer.hidden = false;
            document.body.classList.add('bc-employee-drawer-open');
            drawer.focus();
          });
        });
        frame.addEventListener('load', () => {
          if (drawer.hidden) return;
          try {
            if (frame.contentWindow.location.pathname === root.dataset.listPath) {
              window.location.reload();
            }
          } catch (error) {
            // Preserve the native edit link fallback if embedding is blocked.
          }
        });
        close.addEventListener('click', closeDrawer);
        shade.addEventListener('click', closeDrawer);
        root.addEventListener('keydown', (event) => {
          if (event.key === 'Escape' && !drawer.hidden) closeDrawer();
        });
        filter();
      });
    }
  };
})(Drupal, once);
