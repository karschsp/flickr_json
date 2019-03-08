<?php

namespace Drupal\flickr_json\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ConfigForm.
 */
class ConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'flickr_json.config',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('flickr_json.config');
    $form['flickr_jpgs_location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Location of flickr JPGs'),
      '#description' => $this->t('Directory in public:// where your flickr jpegs are stored.'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('flickr_jpgs_location'),
    ];
    $form['flickr_json_location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Location of flickr JSON'),
      '#description' => $this->t('Directory in public:// where the individual flickr JSON files are located'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('flickr_json_location'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('flickr_json.config')
      ->set('flickr_jpgs_location', $form_state->getValue('flickr_jpgs_location'))
      ->set('flickr_json_location', $form_state->getValue('flickr_json_location'))
      ->save();
  }

}
