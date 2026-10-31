(function (Drupal, once) {
  Drupal.behaviors.bcStaffSelfProfile = {
    attach(context) {
      once('bc-staff-self-profile', '[data-bc-self-profile]', context).forEach((link) => {
        const drawer = document.querySelector('[data-bc-self-drawer]');
        const shade = document.querySelector('[data-bc-self-shade]');
        const frame = document.querySelector('[data-bc-self-frame]');
        const close = document.querySelector('[data-bc-self-close]');
        if (!drawer || !shade || !frame || !close) return;
        const hide = () => {
          drawer.hidden = true;
          shade.hidden = true;
          frame.removeAttribute('src');
          document.body.classList.remove('bc-employee-drawer-open');
          link.focus();
        };
        link.addEventListener('click', (event) => {
          if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
          event.preventDefault();
          const url = new URL(link.href, window.location.href);
          url.searchParams.set('panel', '1');
          frame.src = url.toString();
          shade.hidden = false;
          drawer.hidden = false;
          document.body.classList.add('bc-employee-drawer-open');
          drawer.focus();
        });
        shade.addEventListener('click', hide);
        close.addEventListener('click', hide);
        document.addEventListener('keydown', (event) => {
          if (event.key === 'Escape' && !drawer.hidden) hide();
        });
        frame.addEventListener('load', () => {
          if (drawer.hidden) return;
          try {
            if (frame.contentWindow.location.pathname === new URL(link.href).pathname) {
              // Keep the drawer open after a photo update.
            }
          } catch (error) {
            // Ignore cross-origin iframe inspection.
          }
        });
      });
    },
  };
})(Drupal, once);
