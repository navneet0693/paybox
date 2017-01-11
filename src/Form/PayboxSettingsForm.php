<?php

namespace Drupal\paybox\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class to generate Paybox Admin Settings.
 */
class PayboxSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return ['paybox.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'paybox_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('paybox.settings');
    $form['paybox_activate_real_payments'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Activate real payments.<br/><b>WARNING</b>: CHECK THIS ONLY ON <b>PRODUCTION MODE</b>. This will make payment requests use the Paybox production server, not the sandbox host.'),
      '#default_value' => $config->get('paybox_activate_real_payments'),
    );

    $form['hosts'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Hosts'),
    );
    $form['hosts']['production'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Production'),
    );
    $form['hosts']['sandbox'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Sandbox'),
    );

    $form['hosts']['production']['paybox_production_host'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Paybox production host'),
      '#default_value' => $config->get('paybox_production_host'),
      '#size' => 100,
      '#maxlength' => 100,
    );

    $form['hosts']['production']['paybox_production_hash_key'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Hash key for hmac mode - Production.'),
      '#default_value' => $config->get('paybox_production_hash_key'),
      '#description' => 'Generate this key ay admin.paybox.com.',
      '#size' => 200,
      '#disabled' => $config->get('paybox_mode') == '1',
    );

    $form['hosts']['sandbox']['paybox_sandbox_host'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Paybox sandbox host'),
      '#default_value' => $config->get('paybox_sandbox_host'),
      '#size' => 100,
      '#maxlength' => 100,
    );

    $form['hosts']['sandbox']['paybox_sandbox_hash_key'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Hash key for hmac mode - Sandbox.'),
      '#default_value' => $config->get('paybox_sandbox_hash_key'),
      '#description' => 'Generate this key ay preprod-admin.paybox.com.',
      '#size' => 200,
      '#required' => TRUE,
    );

    // Default values are for test account.
    $form['paybox_PBX_SITE'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Site number'),
      '#description' => 'PBX_SITE',
      '#default_value' => $config->get('paybox_PBX_SITE'),
      '#size' => 7,
      '#maxlength' => 7,
      '#required' => TRUE,
    );

    $form['paybox_PBX_RANG'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Rank number'),
      '#description' => 'PBX_RANG',
      '#default_value' => $config->get('paybox_PBX_RANG'),
      '#size' => 2,
      '#maxlength' => 2,
      '#required' => TRUE,
    );

    $form['paybox_PBX_IDENTIFIANT'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('PAYBOX identifier'),
      '#description' => 'PBX_IDENTIFIANT',
      '#default_value' => $config->get('paybox_PBX_IDENTIFIANT'),
      '#size' => 9,
      '#maxlength' => 9,
      '#required' => TRUE,
    );

    $form['paybox_authorized_ips'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Authorized ips on callback urls, comma separated'),
      '#default_value' => $config->get('paybox_authorized_ips'),
      '#size' => 200,
      '#required' => TRUE,
    );

    $form['paybox_effectue_message'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Message when payment succeeded.'),
      '#default_value' => $config->get('paybox_effectue_message'),
      '#required' => TRUE,
    );

    $form['paybox_refuse_message'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Message when payment was refused.'),
      '#default_value' => $config->get('paybox_refuse_message'),
      '#required' => TRUE,
    );

    $form['paybox_annule_message'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Message when payment was cancelled.'),
      '#default_value' => $config->get('paybox_annule_message'),
      '#required' => TRUE,
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!preg_match('/[0-9]{7}/', $form_state->getValue('paybox_PBX_SITE'))) {
      $form_state->setErrorByName('paybox_PBX_SITE', $this->$this->t('The site number must have 7 digits.'));
    }
    if (!preg_match('/[0-9]{2}/', $form_state->getValue('paybox_PBX_RANG'))) {
      $form_state->setErrorByName('paybox_PBX_SITE', $this->$this->t('The rank number must have 2 digits.'));
    }
    if (!preg_match('/[0-9]{1,9}/', $form_state->getValue('paybox_PBX_IDENTIFIANT'))) {
      $form_state->setErrorByName('paybox_PBX_SITE', $this->$this->t('The identifier must have 1 to 9 digits.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $paybox_settings = $this->config('paybox.settings');

    $paybox_settings->set('paybox_activate_real_payments', $values['paybox_activate_real_payments'])
      ->set('paybox_production_host', $values['paybox_production_host'])
      ->set('paybox_mode', $values['paybox_mode'])
      ->set('paybox_sandbox_host', $values['paybox_sandbox_host'])
      ->set('paybox_sandbox_hash_key', $values['paybox_sandbox_hash_key'])
      ->set('paybox_mode', $values['paybox_mode'])
      ->set('paybox_PBX_SITE', $values['paybox_PBX_SITE'])
      ->set('paybox_PBX_RANG', $values['paybox_PBX_RANG'])
      ->set('paybox_PBX_IDENTIFIANT', $values['paybox_PBX_IDENTIFIANT'])
      ->set('paybox_effectue_message', $values['paybox_effectue_message'])
      ->set('paybox_refuse_message', $values['paybox_refuse_message'])
      ->set('paybox_annule_message', $values['paybox_annule_message'])
      ->save();

    parent::submitForm($form, $form_state);
  }

}
