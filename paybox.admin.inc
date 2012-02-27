<?php

/*
 * @file
 * Administration form
 */
function paybox_settings_form() {
  $form = array();

  // default values are for test account
  $form['paybox_PBX_SITE'] = array(
    '#type' => 'textfield',
    '#title' => t('Site number, given by the bank'),
    '#default_value' => variable_get('paybox_PBX_SITE', '1999888'),
    '#size' => 7,
    '#maxlength' => 7,
    '#required' => TRUE,
    '#element_validate' => array('paybox_site_number_validate'),
  );

  $form['paybox_PBX_RANG'] = array(
    '#type' => 'textfield',
    '#title' => t('Rank number, given by the bank'),
    '#default_value' => variable_get('paybox_PBX_RANG', '99'),
    '#size' => 2,
    '#maxlength' => 2,
    '#required' => TRUE,
    '#element_validate' => array('paybox_rank_number_validate'),
  );

  $form['paybox_PBX_IDENTIFIANT'] = array(
    '#type' => 'textfield',
    '#title' => t('PAYBOX identifier, given by PAYBOX SERVICES when you subscribed.'),
    '#default_value' => variable_get('paybox_PBX_IDENTIFIANT', '2'),
    '#size' => 9,
    '#maxlength' => 9,
    '#required' => TRUE,
    '#element_validate' => array('paybox_identifier_validate'),
  );

  $form['paybox_cgi_path'] = array(
    '#type' => 'textfield',
    '#title' => t('Path to the cgi bin on the server.'),
    '#default_value' => variable_get('paybox_cgi_path', '/path/to/modulev3.cgi'),
    '#required' => TRUE,
  );

  $form['paybox_effectue_message'] = array(
    '#type' => 'textfield',
    '#title' => t('Message when payment succeeded.'),
    '#default_value' => variable_get('paybox_effectue_message', t('Payment completed. Thank you.')),
    '#required' => TRUE,
  );

  $form['paybox_refuse_message'] = array(
    '#type' => 'textfield',
    '#title' => t('Message when payment was refused.'),
    '#default_value' => variable_get('paybox_refuse_message', t('Payment refused. Sorry.')),
    '#required' => TRUE,
  );

  $form['paybox_annule_message'] = array(
    '#type' => 'textfield',
    '#title' => t('Message when payment was cancelled.'),
    '#default_value' => variable_get('paybox_annule_message', t('Payment cancelled.')),
    '#required' => TRUE,
  );

  return system_settings_form($form);
}

function paybox_site_number_validate($element) {
  if (preg_match('/[0-9]{7}/', $element['#value'])) return;
  form_error($element, t('The site number must have 7 digits.'));
}

function paybox_rank_number_validate($element) {
  if (preg_match('/[0-9]{2}/', $element['#value'])) return;
  form_error($element, t('The rank number must have 2 digits.'));
}

function paybox_identifier_validate($element) {
  if (preg_match('/[0-9]{1,9}/', $element['#value'])) return;
  form_error($element, t('The identifier must have 1 to 9 digits.'));
}