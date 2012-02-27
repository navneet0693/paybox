<?php

/**
 * @file
 * Set the params and print the paybox page
 */

// Load in all the drupal functions & includes
$drupal_directory = $_SERVER['DOCUMENT_ROOT'];
$current_directory = getcwd();
chdir($drupal_directory);

require_once './includes/bootstrap.inc';
if (function_exists('drupal_bootstrap')) {
  //  Based on conf_init, needed for url() to work properly,
  // as we are not in the root path
  $base_root = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https' : 'http';
  $base_url = $base_root .= '://' . $_SERVER['HTTP_HOST'];
  $base_path = '/';

  drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
  $cgi_path = variable_get('paybox_cgi_path', '');

  // Paybox params
  $params = array();
  $params['PBX_MODE'] = 4;
  // 4 => command line call

  // site params
  // variable fallback values are for the paybox test account
  $params['PBX_SITE'] = variable_get('paybox_PBX_SITE', '1999888');
  $params['PBX_RANG'] = variable_get('paybox_PBX_RANG', '99');
  $params['PBX_IDENTIFIANT'] = variable_get('paybox_PBX_IDENTIFIANT', '2');

  // payment data
  $params['PBX_TOTAL'] = '000' . $_GET['cents'];
  // 3 digits needed
  $params['PBX_DEVISE'] = $_GET['devise'];
  $params['PBX_CMD'] = $_GET['order_id'];
  $params['PBX_PORTEUR'] = $_GET['payer_email'];

  $destination = $_GET['destination'];

  $params['PBX_EFFECTUE'] = url('paybox_back_page', array(
      'absolute' => TRUE,
      'query' => array(
          'destination' => $destination,
          'result' => 'effectue'
      )
  ));
  $params['PBX_REFUSE'] = url('paybox_back_page', array(
      'absolute' => TRUE,
      'query' => array(
          'destination' => $destination,
          'result' => 'refuse'
      )
  ));
  $params['PBX_ANNULE'] = url('paybox_back_page', array(
      'absolute' => TRUE,
      'query' => array(
          'destination' => $destination,
          'result' => 'annule'
      )
  ));

  $params['PBX_RETOUR'] = 'montant:M;maref:R;auto:A;erreur:E;sign:K';
  $params['PBX_REPONDRE_A'] = url($path = drupal_get_path('module', 'paybox') . '/paybox_back.php', array('absolute' => TRUE));
  $cmd = $cgi_path;
  foreach ($params as $key => $value) {
    $cmd .= " $key='$value'";
  }

  $output = shell_exec(trim($cmd));
  if ($output) {
    $output = ereg_replace("Content-type: text/html\nCache-Control: no-cache, no-store\nPragma: no-cache", '', $output);
    header('Cache-Control: no-cache, no-store\nPragma: no-cache');
    print $output;
  }
  else {
    drupal_set_message(t('An error has occured during payment, please contact us.'), 'error');
    watchdog('paybox', 'CGI error: order @orderid, path: @path', array(
        '@orderid' => $order_id,
        '@path' => $cgi_path
    ), WATCHDOG_WARNING);
    drupal_goto($destination);
  }
}

chdir($current_directory);

