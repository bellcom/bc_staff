(function (Drupal, once) {
  Drupal.behaviors.bcStaffEmployeeEditor = {
    attach(context) {
      once('bc-editor', '[data-bc-employee-editor]', context).forEach((form) => {
        const sections = [...form.querySelectorAll('[data-bc-editor-section]')];
        const navigation = document.createElement('nav');
        navigation.className = 'bc-editor__tabs';
        navigation.setAttribute('aria-label', Drupal.t('Employee edit sections'));
        sections.forEach((section, index) => {
          section.open = index === 0;
          const button = document.createElement('button');
          button.type = 'button';
          button.textContent = section.querySelector('summary')?.textContent.trim() || '';
          button.className = index === 0 ? 'is-active' : '';
          button.addEventListener('click', () => {
            sections.forEach((item, i) => {
              item.open = i === index;
              navigation.children[i].classList.toggle('is-active', i === index);
              navigation.children[i].setAttribute('aria-selected', String(i === index));
            });
          });
          button.setAttribute('aria-selected', String(index === 0));
          navigation.append(button);
        });
        if (sections.length) {
          sections[0].before(navigation);
        }
        form.addEventListener('invalid', (event) => {
          const section = event.target.closest('[data-bc-editor-section]');
          const index = sections.indexOf(section);
          if (index >= 0) navigation.children[index].click();
        }, true);
      });
    }
  };
})(Drupal, once);
