<?php

namespace Drupal\bc_staff\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Administers permanent kiosk links for individual departments.
 */
final class KioskAdminForm extends FormBase {

  public function getFormId(): string { return 'bc_staff_kiosk_admin'; }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->configFactory()->getEditable('bc_staff.kiosks');
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
      ->loadTree('bc_staff_department');
    $links = (array) $config->get('departments');
    $form['description'] = ['#markup' => '<p>' . $this->t('Each department has one permanent terminal URL. Anyone with the URL can register attendance for that department. Keep the link private and use a dedicated secured tablet.') . '</p>'];
    $form['departments'] = ['#type' => 'table',
      '#header' => [$this->t('Employee administration'), $this->t('Permanent URL'), $this->t('Create URL')]];
    foreach ($terms as $term) {
      $id = (string) $term->tid;
      $token = $links[$id] ?? '';
      $form['departments'][$id]['name'] = ['#plain_text' => $term->name];
      $form['departments'][$id]['url'] = $token
        ? ['#type' => 'textfield', '#title' => $this->t('Permanent URL for @name', ['@name' => $term->name]),
          '#title_display' => 'invisible', '#attributes' => ['readonly' => 'readonly'],
          '#default_value' => Url::fromRoute('bc_staff.kiosk', ['token' => $token], ['absolute' => TRUE])->toString()]
        : ['#markup' => $this->t('Not created')];
      $form['departments'][$id]['create'] = ['#type' => 'submit',
        '#value' => $token ? $this->t('Created') : $this->t('Create URL'),
        '#disabled' => (bool) $token, '#name' => 'create_' . $id,
        '#limit_validation_errors' => []];
    }
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $name = $form_state->getTriggeringElement()['#name'] ?? '';
    if (!preg_match('/^create_([1-9][0-9]*)$/D', $name, $matches)) { return; }
    $id = $matches[1];
    $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($id);
    if (!$term || $term->bundle() !== 'bc_staff_department') { return; }
    $config = $this->configFactory()->getEditable('bc_staff.kiosks');
    $links = (array) $config->get('departments');
    if (empty($links[$id])) {
      $links[$id] = bin2hex(random_bytes(32));
      $config->set('departments', $links)->save();
      $this->messenger()->addStatus($this->t('Permanent terminal URL created.'));
    }
    $form_state->setRebuild();
  }
}
