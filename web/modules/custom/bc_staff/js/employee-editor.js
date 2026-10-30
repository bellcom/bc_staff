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
          const actions = document.createElement('div');
          actions.className = 'bc-editor__photo-actions';
          const choose = document.createElement('button');
          choose.type = 'button';
          choose.className = 'bc-editor__choose';
          choose.textContent = Drupal.t('Change photo');
          actions.append(choose);
          if (preview) preview.after(actions);
          else photo.before(actions);
          const sync = () => {
            const currentPhoto = form.querySelector('[data-drupal-selector="edit-general-photo"]')
              || form.querySelector('.form-type-managed-file') || photo;
            currentPhoto.classList.add('bc-editor__photo-control');
            const input = currentPhoto.querySelector('input[type="file"]');
            const remove = currentPhoto.querySelector('input[type="submit"][name$="_remove_button"]');
            choose.disabled = !input && !remove;
            currentPhoto.classList.toggle('has-existing-photo', Boolean(remove));
            if (remove) {
              remove.value = Drupal.t('Remove photo');
              remove.classList.add('bc-editor__remove');
            }
            if (input && !input.dataset.bcPhotoPreview) {
              input.dataset.bcPhotoPreview = '1';
              input.addEventListener('change', () => {
                const file = input.files?.[0];
                if (!file || !preview || !file.type.startsWith('image/')) return;
                const temporaryUrl = URL.createObjectURL(file);
                preview.src = temporaryUrl;
                preview.addEventListener('load', () => URL.revokeObjectURL(temporaryUrl), { once: true });
              });
            }
            return { input, remove };
          };
          choose.addEventListener('click', () => {
            const { input, remove } = sync();
            if (input) input.click();
            else if (remove) {
              // Drupal must remove the current managed file before it can
              // accept a replacement. Open the chooser after its AJAX rebuild.
              photo.dataset.bcChooseAfterRemove = '1';
              remove.click();
            }
          });
          const observer = new MutationObserver(() => {
            const { input } = sync();
            if (input && photo.dataset.bcChooseAfterRemove === '1') {
              delete photo.dataset.bcChooseAfterRemove;
              input.click();
            }
          });
          observer.observe(form, { childList: true, subtree: true });
          sync();
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
