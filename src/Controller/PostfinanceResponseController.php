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
    $request_array = $request->query->all();

    // Remove the SHASign from the request data and save as the sent signature.
    $sha_sent = array_pop($request_array);

    // Generate SHASign from request data.
    $sha_sign = PostfinanceHelper::generateShaSign($request_array, $plugin_definition['sha_out_key']);

    // Check if the sent signature is valid.
    if ($sha_sign == $sha_sent) {
      drupal_set_message(t('Payment succesfull.'));
      $this->savePayment($payment, 'payment_success');
    }
    else {
      $this->savePayment($payment, 'payment_failed', 'error');
      \Drupal::logger(t('Payment verification failed: @error'), array('@error' => 'SHASign did not equal'))->warning('PostfinanceResponseController.php');
      drupal_set_message(t('Payment verification failed: @error.', array('@error' => 'Verification code incorrect')), 'error');
    }

    debug($request->query->all(), "Request");

  }

  /**
   * Handles declined payment responses.
   *
   * URL of the web page to show the customer when the acquirer declines the
   * authorization (status 2) more than the maximum permissible number of times.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
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
   * If the field is empty the customer will be displayed the accepturl instead.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
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
   * If the field is empty the declineurl will be displayed instead.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
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
   *   Payment Interface.
   * @param string $status
   *   Payment Status.
   */
  public function savePayment(PaymentInterface $payment, $status) {
    $payment->setPaymentStatus(\Drupal::service('plugin.manager.payment.status')
      ->createInstance($status));
    $payment->save();
    $payment->getPaymentType()->getResumeContextResponse()->getRedirectUrl()->toString();
  }

}
