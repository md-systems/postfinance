<?php
/**
 * @file
 * Contains \Drupal\payment_postfinance\Controller\PostfinanceResponseController
 */

namespace Drupal\payment_postfinance\Controller;

use Drupal\currency\Entity\Currency;
use Drupal\payment\Entity\Payment;
use Drupal\payment\Entity\PaymentInterface;
use Drupal\payment_postfinance\PostfinanceHelper;
use Symfony\Component\HttpFoundation\Request;

/**
 * Postfinance response controller.
 */
class PostfinanceResponseController {

  /**
   * URL of the web page to display to the customer when the payment has been
   * authorized (status 5), accepted (status 9) or is waiting to be accepted (pending,
   * status 51 or 91).
   *
   * @param Request $request
   *   Request
   * @param PaymentInterface $payment
   *   The Payment Entity type.
   */
  public function processAcceptResponse(Request $request, PaymentInterface $payment) {
    // The definition of the plugin implementation.
    $plugin_definition = $payment->getPaymentMethod()->getPluginDefinition();

    // Generate local SHASign.
    $sha_sign = PostfinanceHelper::generateShaIN($request->query->all(), $plugin_definition['sha_out_key']);

    // Check correctly generated SHASign
    debug($sha_sign, 'generated sign');
    debug($request->query->all(), 'all request data');

    if ($sha_sign == $request->get('SHASIGN')) {
      drupal_set_message(t('Payment succesfull.'), 'error');
      $this->savePayment($payment, 'payment_success');
    } else {
      $this->savePayment($payment, 'payment_failed');
      \Drupal::logger(t('Payment verification failed: @error'),array('@error' => 'SHASign did not equal'))->warning('PostfinanceResponseController.php');
      drupal_set_message(t('Payment verification failed: @error.', array('@error' => 'Verification code incorrect')), 'error');
    }


  }

  /**
   * URL of the web page to show the customer when the acquirer declines the authorization
   * (status 2) more than the maximum permissible number of times.
   *
   * @param Request $request
   *   Request
   * @param PaymentInterface $payment
   *   The Payment Entity type.
   */
  public function processDeclineResponse(Request $request, PaymentInterface $payment) {
    $this->savePayment($payment, 'payment_failed');

    $message = 'Postfinance communication declined. Invalid data received from Postfinance.';
    \Drupal::logger('postfinance')->error('Processing declined with exception @e.', array('@e' => $message));
    drupal_set_message(t('Payment processing declined.'), 'error');
  }

  /**
   * URL of the web page to display to the customer when the payment result is
   * uncertain (status 52 or 92).
   * If this field is empty the customer will be displayed the accepturl instead.
   *
   * @param Request $request
   *   Request
   * @param PaymentInterface $payment
   *   The Payment Entity type.
   */
  public function processExceptionResponse(Request $request, PaymentInterface $payment) {
    $this->savePayment($payment, 'payment_failed');

    $message = 'Postfinance communication exception. Invalid data received from Postfinance.';
    \Drupal::logger('postfinance')->error('Processing failed with exception @e.', array('@e' => $message));
    drupal_set_message(t('Payment processing exception.'), 'error');
  }

  /**
   * URL of the web page to display to the customer when he cancels the payment
   * (status 1).
   * If this field is empty the declineurl will be displayed to the customer instead.
   *
   * @param Request $request
   *   Request
   * @param PaymentInterface $payment
   *   The Payment Entity type.
   */
  public function processCancelResponse(Request $request, PaymentInterface $payment) {
    $this->savePayment($payment, 'payment_cancelled');

    $message = 'Postfinance communication cancelled. Payment cancelled';
    \Drupal::logger('postfinance')->error('Processing failed with exception @e.', array('@e' => $message));
    drupal_set_message(t('Payment processing cancelled.'), 'error');
  }

  /**
   * Saves success/cancelled/failed payment.
   *
   * @param $payment
   *  Payment Interface.
   * @param string $status
   *  Payment Status
   */
  public function savePayment(PaymentInterface $payment, $status = 'payment_failed') {
    $payment->setPaymentStatus(\Drupal::service('plugin.manager.payment.status')
      ->createInstance($status));
    $payment->save();
    $payment->getPaymentType()->resumeContext();
  }
}
