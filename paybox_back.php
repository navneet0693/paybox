<?php

/**
 * @file
 * Handle the request coming back from paybox after payment
 */

// load in all the drupal functions & includes
$drupal_directory = $_SERVER['DOCUMENT_ROOT'];
$current_directory = getcwd();
chdir($drupal_directory);

require_once './includes/bootstrap.inc';
if (function_exists('drupal_bootstrap')) {
  // Based on conf_init, needed for url() to work properly,
  // as we are not in the root path
  $base_root = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https' : 'http';
  $base_url = $base_root .= '://' . $_SERVER['HTTP_HOST'];
  $base_path = '/';

  drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

  $info = $_GET;

  unset($info['q']);
  // We will check the ref-amount matching.
  $amounts = variable_get('paybox_order_amounts', array());
  // First we ensure that the request is right.
  if (!isset($amounts[$info['maref']]) || $amounts[$info['maref']] != $info['montant'] || !_paybox_verif_sign()) {
    return;
  }
  // We can now forget the ref-amount matching for this order.
  unset($amounts[$info['maref']]);
  variable_set('paybox_order_amounts', $amounts);
  $success = (isset($info['auto']) && $info['erreur'] === '00000');
  // What we do with this info is outside this API's scope, so we invoke a hook.
  module_invoke_all('paybox_update_status', $info['maref'], $success, $info);
}
chdir($current_directory);
