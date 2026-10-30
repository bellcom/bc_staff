(function (Drupal, once) {
  Drupal.behaviors.bcAttendanceSearch = {
    attach(context) {
      once('bc-attendance-search', '[data-bc-attendance-search]', context).forEach((input) => {
        input.addEventListener('input', () => {
          const query = input.value.trim().toLocaleLowerCase();
          document.querySelectorAll('[data-bc-attendance-card]').forEach((card) => {
            card.hidden = !card.dataset.name.includes(query);
          });
        });
      });
    },
  };
})(Drupal, once);
