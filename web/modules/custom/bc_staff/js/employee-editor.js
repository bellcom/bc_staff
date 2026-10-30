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
        const photo = form.querySelector('[data-drupal-selector="edit-general-photo"]')
          || form.querySelector('.form-type-managed-file');
        if (photo) {
          photo.classList.add('bc-editor__photo-control');
          const preview = form.querySelector('.bc-editor__photo-preview');
          const fileInput = photo.querySelector('input[type="file"]');
          const remove = photo.querySelector('input[type="submit"][name$="_remove_button"]');
          if (fileInput) {
            const choose = document.createElement('button');
            choose.type = 'button';
            choose.className = 'bc-editor__choose';
            choose.textContent = Drupal.t('Change photo');
            choose.addEventListener('click', () => fileInput.click());
            fileInput.before(choose);
            fileInput.addEventListener('change', () => {
              const file = fileInput.files?.[0];
              if (file && preview && file.type.startsWith('image/')) {
                const temporaryUrl = URL.createObjectURL(file);
                preview.src = temporaryUrl;
                preview.addEventListener('load', () => URL.revokeObjectURL(temporaryUrl), { once: true });
              }
            });
          }
          if (remove) {
            remove.value = Drupal.t('Remove photo');
            remove.classList.add('bc-editor__remove');
          }
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
