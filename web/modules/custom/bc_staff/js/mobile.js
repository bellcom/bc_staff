(function (Drupal, once) {
  Drupal.behaviors.bcStaffMobile = {
    attach(context) {
      once('bc-staff-mobile', '.bc-staff-mobile', context).forEach((root) => {
        const form = root.closest('form');
        if (!form) return;
        const radios = root.querySelectorAll('input[name="status"]');
        const submit = form.querySelector('input[type="submit"], button[type="submit"]');
        radios.forEach((radio) => {
          radio.addEventListener('change', () => {
            if (!radio.checked || !submit || form.dataset.bcStaffSubmitting === '1') return;
            form.dataset.bcStaffSubmitting = '1';
            submit.disabled = true;
            submit.value = 'Gemmer status…';
            // Submit with Drupal's standard CSRF-protected form semantics.
            if (form.requestSubmit) {
              form.requestSubmit();
            }
            else {
              submit.disabled = false;
              submit.click();
            }
          });
        });
      });
    }
  };
})(Drupal, once);
