(function (Drupal, once) {
  Drupal.behaviors.bcStaffTouchDirectory = {
    attach(context) {
      once('bc-staff-touch-directory', '[data-bc-touch-search]', context).forEach((input) => {
        input.addEventListener('input', () => {
          const query = input.value.trim().toLocaleLowerCase();
          document.querySelectorAll('[data-bc-touch-card]').forEach((card) => {
            card.hidden = !card.dataset.bcTouchName.includes(query);
          });
        });
      });
    },
  };
})(Drupal, once);
