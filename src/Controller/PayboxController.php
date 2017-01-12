<?php

namespace Drupal\paybox\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Component\Utility\Unicode;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class PayboxController.
 *
 * @package Drupal\paybox\Controller
 *
 * A simple controller for paybox.
 */
class PayboxController extends ControllerBase {

  /**
   * The config object for paybox settings.
   *
   * @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Current requeststack object.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Logger object.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $loggerChannelFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactory $config, RequestStack $requestStack, LoggerChannelFactory $loggerChannelFactory) {
    $this->config = $config->get('paybox.settings');
    $this->requestStack = $requestStack;
    $this->loggerChannelFactory = $loggerChannelFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('request_stack'),
      $container->get('logger.factory')
    );
  }

  /**
   * Checks if the server ip belongs to Paybox.
   */
  public function isAllowed() {
    $allowed_ips = $this->config->get('paybox_authorized_ips');
    $allowed_ips = explode(",", $allowed_ips);

    if (in_array(\Drupal::request()->server->get('REMOTE_ADDR'), $allowed_ips)) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

  /**
   * Handles ipn callback from paybox.
   */
  public function ipnCallbackPage() {
    $order_id = $this->requestStack->getCurrentRequest()->query->get('order_id');
    $error = $this->requestStack->getCurrentRequest()->query->get('error');
    if ($this->checkSign($this->requestStack->getCurrentRequest()->server->get('QUERY_STRING'))) {
      // Check payment is accepted.
      if ($error === '00000') {
        $status = PAYBOX_PAYMENT_STATUS_SUCCESS;
      }
      else {
        $message = $this->getErrorMsg($error);
        // Log Paybox signature errors.
        \Drupal::logger('paybox')->error('Error @error: %msg', ['@error' => $error, '%msg' => $message]);

        // Invalidate the transaction.
        $status = PAYBOX_PAYMENT_STATUS_FAILURE;
      }
    }
    else {
      // Log paybox signature errors.
      // @todo: Check if report logs are generated or not.

      $this->loggerChannelFactory->get('paybox')->notice('Paybox System has failed to encrypt his own data for order @order_id', ['@order_id' => $order_id]);

      // Invalidate the transaction.
      $status = PAYBOX_PAYMENT_STATUS_FAILURE;
    }

    // @todo: Check if the module invoke all is implemented fine or not.
    \Drupal::moduleHandler()->invokeAll('paybox_update_status', $args = [$order_id, $status]);

    // Output an empty HTML page.
    return NULL;
  }

  /**
   * Check if signature of the Paybox server's response URL is correct.
   *
   * @return bool
   *   TRUE if signing is correct, FALSE otherwise.
   */
  public function checkSign($query_string) {
    $matches = array();
    if (preg_match('/(?:q=.*?&)?(.*)&sig=(.*)$/', $query_string, $matches)) {
      $data = $matches[1];
      $sig = base64_decode(urldecode($matches[2]));

      $key_file = drupal_get_path('module', 'paybox') . '/pubkey.pem';
      if ($key_file_content = file_get_contents($key_file)) {
        if ($key = openssl_pkey_get_public($key_file_content)) {
          return openssl_verify($data, $sig, $key);
        }
      }
      \Drupal::logger('paybox')->notice('Cannot read Paybox System public key file (@file)', ['@file' => $key_file]);
    }
    return FALSE;
  }

  /**
   * Retrieve the error message according to the error code from Paybox server.
   *
   * @param string $error
   *   The error code returned by the Paybox System server.
   *
   * @return string
   *   The translated error message.
   */
  public function getErrorMsg($error) {
    if (Unicode::substr($error, 0, 3) == '001') {
      $precise_error = Unicode::substr($error, 2);

      $precise_map = $this->getPreciseErrorsMap();

      if (isset($precise_map[$precise_error])) {
        return $this->t('Payment refused by authorisation center (error @error).', ['@error' => $precise_map[$precise_error]]);
      }

      $errors_map = $this->getErrorsMap();
    }

    if (isset($errors_map[$error])) {
      return $errors_map[$error];
    }
    else {
      return $this->t('Unknown error.');
    }
  }

  /**
   * Return mpa array of Paybox precise errors.
   *
   * @return array
   *   Precise Error Mapping.
   */
  public function getPreciseErrorsMap() {
    return array(
      '00' => $this->t('Transaction approved or successfully handled.'),
      '02' => $this->t('Contact the card issuer.'),
      '03' => $this->t('Invalid shop.'),
      '04' => $this->t('Keep the card.'),
      '07' => $this->t('Keep the card, special conditions.'),
      '08' => $this->t('Approve after holder identification.'),
      '12' => $this->t('Invalid transaction.'),
      '13' => $this->t('Invalid amount.'),
      '14' => $this->t('Invalid holder number.'),
      '15' => $this->t('Unknown card issuer.'),
      '17' => $this->t('Client has cancelled.'),
      '19' => $this->t('Try transaction again later.'),
      '20' => $this->t('Bad answer (error on server domain).'),
      '24' => $this->t('Unsupported file update.'),
      '25' => $this->t('Unable to locate record in file.'),
      '26' => $this->t('Duplicate record, old record has been replaced.'),
      '27' => $this->t('Edit error during file update.'),
      '28' => $this->t('Unauthorized file access.'),
      '29' => $this->t('Impossible file update.'),
      '30' => $this->t('Format error.'),
      '33' => $this->t('Validity date of the card reached.'),
      '34' => $this->t('Fraud suspicion.'),
      '38' => $this->t('Number of tries for confidential code reached.'),
      '41' => $this->t('Lost card.'),
      '43' => $this->t('Stolen card.'),
      '51' => $this->t('Insufficient funds or no credit left.'),
      '54' => $this->t('Validity date of the card reached.'),
      '55' => $this->t('Bad confidential code.'),
      '56' => $this->t('Card not in the file.'),
      '57' => $this->t('Transaction not authorized for this cardholder.'),
      '58' => $this->t('Transaction not authorized for this terminal.'),
      '59' => $this->t('Fraud suspicion.'),
      '61' => $this->t('Debit limit reached.'),
      '63' => $this->t('Security rules not followed.'),
      '68' => $this->t('Absent or late answer.'),
      '75' => $this->t('Number of tries for confidential code reached.'),
      '76' => $this->t('Cardholder already opposed, old record kept.'),
      '90' => $this->t('System temporary stopped.'),
      '91' => $this->t('Card provider is unreachable.'),
      '94' => $this->t('Duplicate question.'),
      '96' => $this->t('Bad system behavior.'),
      '97' => $this->t('Global surveillance timeout.'),
      '98' => $this->t('Server is unreachable.'),
      '99' => $this->t('Incident from initiator domain.'),
    );
  }

  /**
   * Return map array for Paybox error codes.
   *
   * @return array
   *   Error Mapping.
   */
  public function getErrorsMap() {
    return array(
      '00000' => $this->t('Operation successful.'),
      '00001' => $this->t('Connexion to autorise center failed.'),
      '00002' => $this->t('Connexion to autorise center failed.'),
      '00003' => $this->t('Paybox error.'),
      '00004' => $this->t('Owner number or cryptogram invalid.'),
      '00005' => $this->t('Invalid question number .'),
      '00006' => $this->t('Access refused or rank/site/is incorrect.'),
      '00007' => $this->t('Invalid date.'),
      '00008' => $this->t('Error on expiry date'),
      '00009' => $this->t('Error creating subscription.'),
      '00010' => $this->t('Unknown currency.'),
      '00011' => $this->t('Wrong order total.'),
      '00012' => $this->t('Invalid order reference.'),
      '00013' => $this->t('This version is no longer upheld.'),
      '00014' => $this->t('Incoherent frame received.'),
      '00015' => $this->t('Error in access to previously referenced data.'),
      '00016' => $this->t('User already exists.'),
      '00017' => $this->t('User does not exist.'),
      '00018' => $this->t('Transaction not found.'),
      '00020' => $this->t('CVV not present.'),
      '00021' => $this->t('Unauthorized card.'),
      '00024' => $this->t('Error loading of the key.'),
      '00025' => $this->t('Missing signature.'),
      '00026' => $this->t('Missing key but the signature is present.'),
      '00027' => $this->t('Error OpenSSL during the checking of the signature.'),
      '00028' => $this->t('Unchecked signature.'),
      '00029' => $this->t('Card non-compliant.'),
      '00030' => $this->t('Timeout on checkout page (> 15 mn).'),
      '00031' => $this->t('Reserved.'),
      '00097' => $this->t('Timeout of connection ended.'),
      '00098' => $this->t('Internal connection error.'),
      '00099' => $this->t('Incoherence between the question and the answer. Try again later.'),
    );
  }

  /**
   * Handles return from paybox.
   */
  public function returnPage() {
    $return_url = $this->requestStack->getCurrentRequest()->query->get('return_url');
    $result = $this->requestStack->getCurrentRequest()->query->get('result');
    switch ($result) {
      case 'validated':
        drupal_set_message(\Drupal::config('paybox.settings')->get('paybox_effectue_message'), 'status');
        break;

      case 'denied':
        drupal_set_message(\Drupal::config('paybox.settings')->get('paybox_refuse_message'), 'error');
        break;

      case 'canceled':
        drupal_set_message(\Drupal::config('paybox.settings')->get('paybox_annule_message'), 'warning');
        break;
    }

    $redirectResponse = new RedirectResponse($return_url);
    $redirectResponse->setTargetUrl($return_url)->send();
  }

}
