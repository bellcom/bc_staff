(function (Drupal, once) {
  Drupal.behaviors.bcStaffNotificationMenu = {
    attach(context) {
      once('bc-staff-notification-menu', '[data-bc-notification-menu]', context).forEach((menu) => {
        const toggle = menu.querySelector('[data-bc-notification-toggle]');
        const panel = menu.querySelector('[data-bc-notification-popover]');
        if (!toggle || !panel) return;
        const close = () => { panel.hidden = true; toggle.setAttribute('aria-expanded', 'false'); };
        toggle.addEventListener('click', () => {
          const open = panel.hidden;
          panel.hidden = !open;
          toggle.setAttribute('aria-expanded', String(open));
        });
        document.addEventListener('click', (event) => {
          if (!menu.contains(event.target)) close();
        });
        const endpoint = menu.dataset.bcNotificationStatusUrl;
        const refresh = async () => {
          if (!endpoint || document.hidden) return;
          try {
            const response = await fetch(endpoint, { credentials: 'same-origin', cache: 'no-store' });
            if (!response.ok) return;
            const data = await response.json();
            const badge = menu.querySelector('[data-bc-notification-count]');
            if (badge) {
              badge.textContent = String(data.count);
              badge.hidden = !data.count;
            }
            else if (data.count) {
              const element = document.createElement('span');
              element.className = 'bc-app__notifications-count';
              element.dataset.bcNotificationCount = '';
              element.textContent = String(data.count);
              toggle.append(element);
            }
            const list = menu.querySelector('[data-bc-notification-items]');
            if (list && Array.isArray(data.items)) {
              list.replaceChildren();
              if (!data.items.length) {
                const empty = document.createElement('p');
                empty.className = 'bc-app__notification-empty';
                empty.textContent = Drupal.t('Ingen notifikationer endnu.');
                list.append(empty);
              }
              data.items.forEach((notice) => {
                const link = document.createElement('a');
                link.className = 'bc-app__notification-item' + (notice.unread ? ' is-unread' : '');
                link.href = notice.url;
                const label = document.createElement('span');
                label.textContent = notice.message;
                const date = document.createElement('small');
                date.textContent = notice.time;
                link.append(label, date);
                list.append(link);
              });
            }
          }
          catch (_) { /* Network errors must not interfere with the portal. */ }
        };
        const refreshTimer = window.setInterval(refresh, 30000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
        menu.addEventListener('keydown', (event) => {
          if (event.key === 'Escape' && !panel.hidden) { close(); toggle.focus(); }
        });
      });
    }
  };
})(Drupal, once);
