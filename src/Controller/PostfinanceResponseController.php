<?php
/**
 * @file
 * Contains \Drupal\payment_postfinance\Controller\PostfinanceResponseController
 */

namespace Drupal\payment_postfinance\Controller;

use Drupal\currency\Entity\Currency;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Component\Utility\Crypt;
use Drupal\payment\Entity\Payment;
use Drupal\payment\Entity\PaymentInterface;
use Drupal\payment_postfinance\PostfinanceHelper;
use Drupal\payment_postfinance_test\Form\PostfinanceTestForm;
use Symfony\Component\HttpFoundation\Request;

/**
 * Postfinance response controller.
 */
class PostfinanceResponseController {

  /**
   * Handles successful payment responses.
   *
   * URL of the web page to display to the customer when the payment has been
   * authorized (status 5), accepted (status 9) or is waiting to be accepted
   * (pending, status 51 or 91).
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
   * @param \Drupal\payment\Entity\PaymentInterface $payment
   *   The Payment Entity type.
   */
  public function processAcceptResponse(Request $request, PaymentInterface $payment) {
    // The definition of the plugin implementation.
    $plugin_definition = $payment->getPaymentMethod()->getPluginDefinition();

    // Generate local SHASign.
    $request_values = $request->query->all();

    // Generate SHA-OUT signature.
    $sha_sign = PostfinanceHelper::generateShaSign($request_values, $plugin_definition['sha_out_key']);

    if ($sha_sign == $request->get('SHASIGN')) {
      drupal_set_message(t('Payment succesfull.'), 'error');
      $this->savePayment($payment, 'payment_success');
    }
    else {
      $this->savePayment($payment, 'payment_failed');
      \Drupal::logger(t('Payment verification failed: @error'), array('@error' => 'SHASign did not equal'))->warning('PostfinanceResponseController.php');
      drupal_set_message(t('Payment verification failed: @error.', array('@error' => 'Verification code incorrect')), 'error');
    }

  }

  /**
   * Handles declined payment responses.
   *
   * URL of the web page to show the customer when the acquirer declines the
   * authorization (status 2) more than the maximum permissible number of times.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request
   * @param \Drupal\payment\Entity\PaymentInterface $payment
   *   The Payment Entity type.
   */
  public function processDeclineResponse(Request $request, PaymentInterface $payment) {
    $this->savePayment($payment, 'payment_failed');

    $message = 'Postfinance communication declined. Invalid data received from Postfinance.';
    \Drupal::logger('postfinance')->error('Processing declined with exception @e.', array('@e' => $message));
    drupal_set_message(t('Payment processing declined.'), 'error');
  }

  /**
   * Handles exception payment responses.
   *
   * URL of the web page to display to the customer when the payment result is
   * uncertain (status 52 or 92).
   * If this field is empty the customer will be displayed the accepturl instead.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request
   * @param \Drupal\payment\Entity\PaymentInterface $payment
   *   The Payment Entity type.
   */
  public function processExceptionResponse(Request $request, PaymentInterface $payment) {
    $this->savePayment($payment, 'payment_failed');

    $message = 'Postfinance communication exception. Invalid data received from Postfinance.';
    \Drupal::logger('postfinance')->error('Processing failed with exception @e.', array('@e' => $message));
    drupal_set_message(t('Payment processing exception.'), 'error');
  }

  /**
   * Handles cancel payment responses.
   *
   * URL of the web page to display to the customer when he cancels the payment
   * (status 1).
   * If this field is empty the declineurl will be displayed to the customer instead.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request
   * @param \Drupal\payment\Entity\PaymentInterface $payment
   *   The Payment Entity type.
   */
  public function processCancelResponse(Request $request, PaymentInterface $payment) {
    $this->savePayment($payment, 'payment_cancelled');

    $message = 'Postfinance communication cancelled. Payment cancelled';
    \Drupal::logger('postfinance')->error('Processing failed with exception @e.', array('@e' => $message));
    drupal_set_message(t('Payment processing cancelled.'), 'error');
  }

  /**
   * Saves the payment.
   *
   * @param \Drupal\payment\Entity\PaymentInterface $payment
   *  Payment Interface.
   * @param string $status
   *  Payment Status
   */
  public function savePayment(PaymentInterface $payment, $status) {
    $payment->setPaymentStatus(\Drupal::service('plugin.manager.payment.status')
      ->createInstance($status));
    $payment->save();
    $payment->getPaymentType()->resumeContext();
  }

  /**
   * Generates a SHA sign.
   *
   * @param array $payment_data
   *  Payment Data.
   * @param string $sha_in_key
   *  Payment Signature
   */
  static public function generateShaSign($payment_data, $sha_in_key) {
    $string = null;
    // Sort array in alphabetical order by key.
    $payment_data = array_change_key_case($payment_data, CASE_UPPER);
    ksort($payment_data);
    // Unset values that are not allowed in SHA-IN or SHA-OUT calls.
    unset($payment_data['SHASIGN'], $payment_data['FORM_BUILD_ID'], $payment_data['FORM_TOKEN'],
      $payment_data['FORM_ID'], $payment_data['OP']);
    // Create SHA string that will be encrypted.
    foreach ($payment_data as $key => $value) {
      if (isset($value)) {
        $string .= $key . '=' . $value . $sha_in_key;
      }
    }
    return strtoupper(sha1($string));
  }
}
