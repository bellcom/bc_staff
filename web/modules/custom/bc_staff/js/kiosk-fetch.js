(function (Drupal, once) {
  Drupal.behaviors.bcStaffKioskFetch = {
    attach(context) {
      once('bc-staff-kiosk-fetch', 'form[data-bc-kiosk-fetch]', context).forEach((form) => {
        let busy = false;
        form.addEventListener('submit', async (event) => {
          const button = event.submitter;
          if (!button || !/^(in|out)_[1-9][0-9]*$/.test(button.name)) return;
          if (!window.fetch || !window.DOMParser || !window.FormData) return;
          event.preventDefault();
          if (busy) return;
          busy = true;
          const card = button.closest('[data-bc-touch-card]');
          const search = form.querySelector('[data-bc-touch-search]');
          const query = search ? search.value : '';
          const buttons = Array.from(form.querySelectorAll('input[type="submit"]'));
          buttons.forEach((item) => { item.disabled = true; });
          card?.classList.add('bc-kiosk--busy');
          let feedback = form.querySelector('[data-bc-kiosk-feedback]');
          if (!feedback) {
            feedback = document.createElement('div');
            feedback.setAttribute('data-bc-kiosk-feedback', '');
            feedback.setAttribute('role', 'status');
            feedback.setAttribute('aria-live', 'polite');
            form.prepend(feedback);
          }
          feedback.textContent = Drupal.t('Registrerer fremmøde…');
          try {
            const payload = new FormData(form);
            payload.set(button.name, button.value);
            const response = await fetch(form.action || window.location.href, {
              method: 'POST',
              body: payload,
              credentials: 'same-origin',
              cache: 'no-store',
              redirect: 'follow',
              headers: { 'Accept': 'text/html' },
            });
            if (!response.ok || new URL(response.url).pathname !== window.location.pathname) {
              throw new Error('Unexpected response');
            }
            const documentResult = new DOMParser().parseFromString(await response.text(), 'text/html');
            const replacement = documentResult.querySelector('form[data-bc-kiosk-fetch]');
            if (!replacement) throw new Error('Missing kiosk form');
            // Preserve the keyboard search and refresh Drupal form build tokens.
            const newSearch = replacement.querySelector('[data-bc-touch-search]');
            if (newSearch) newSearch.value = query;
            Drupal.detachBehaviors(form, drupalSettings, 'unload');
            form.replaceWith(replacement);
            Drupal.attachBehaviors(replacement.parentElement, drupalSettings);
            const refreshed = replacement.querySelector('[data-bc-touch-search]');
            if (refreshed) refreshed.dispatchEvent(new Event('input', { bubbles: true }));
            let notice = replacement.querySelector('[data-bc-kiosk-feedback]');
            if (!notice) {
              notice = document.createElement('div');
              notice.setAttribute('data-bc-kiosk-feedback', '');
              notice.setAttribute('role', 'status');
              notice.setAttribute('aria-live', 'polite');
              replacement.prepend(notice);
            }
            notice.textContent = Drupal.t('Fremmøde registreret.');
          }
          catch (error) {
            buttons.forEach((item) => {
              // Restore actual server-rendered disabled states from before submit.
              item.disabled = item.dataset.bcInitiallyDisabled === '1';
            });
            feedback.textContent = Drupal.t('Kunne ikke bekræfte registreringen. Opdater siden og kontrollér status, før du prøver igen.');
            feedback.classList.add('is-error');
            busy = false;
            card?.classList.remove('bc-kiosk--busy');
          }
        });
        form.querySelectorAll('input[type="submit"]').forEach((item) => {
          item.dataset.bcInitiallyDisabled = item.disabled ? '1' : '0';
        });
      });
    }
  };
})(Drupal, once, drupalSettings);
