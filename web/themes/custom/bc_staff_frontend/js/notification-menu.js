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
        menu.addEventListener('keydown', (event) => {
          if (event.key === 'Escape' && !panel.hidden) { close(); toggle.focus(); }
        });
      });
    }
  };
})(Drupal, once);
