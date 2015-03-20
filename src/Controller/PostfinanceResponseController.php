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
use Symfony\Component\HttpFoundation\Response;

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
   *   Request
   * @param \Drupal\payment\Entity\PaymentInterface $payment
   *   The Payment Entity type.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function processAcceptResponse(Request $request, PaymentInterface $payment) {
    // The definition of the plugin implementation.
    $plugin_definition = $payment->getPaymentMethod()->getPluginDefinition();

    // Generate local SHASign.
    $request_values = $request->query->all();

    // Generate SHA-OUT signature
    $sha_sign = PostfinanceHelper::generateShaSign($request_values, $plugin_definition['sha_out_key']);

    if ($sha_sign == $request->get('SHASIGN')) {
      drupal_set_message(t('Payment succesfull.'), 'error');
      return $this->savePayment($payment, 'paymet_success');
    } else {
      \Drupal::logger(t('Payment verification failed: @error'),array('@error' => 'SHASign did not equal'))->warning('PostfinanceResponseController.php');
      drupal_set_message(t('Payment verification failed: @error.', array('@error' => 'Verification code incorrect')), 'error');
      return $this->savePayment($payment, 'payment_failed');
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
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function processDeclineResponse(Request $request, PaymentInterface $payment) {
    $message = 'Postfinance communication declined. Invalid data received from Postfinance.';
    \Drupal::logger('postfinance')->error('Processing declined with exception @e.', array('@e' => $message));
    drupal_set_message(t('Payment processing declined.'), 'error');
    return $this->savePayment($payment, 'payment_failed');
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
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function processCancelResponse(Request $request, PaymentInterface $payment) {
    $message = 'Postfinance communication cancelled. Payment cancelled';
    \Drupal::logger('postfinance')->error('Processing failed with exception @e.', array('@e' => $message));
    drupal_set_message(t('Payment processing cancelled.'), 'error');
    return $this->savePayment($payment, 'payment_cancelled');
  }

  /**
   * Saves the payment.
   *
   * @param \Drupal\payment\Entity\PaymentInterface $payment
   *  Payment entity.
   * @param string $status
   *  Payment status to set
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   */
  public function savePayment(PaymentInterface $payment, $status) {
    $payment->setPaymentStatus(\Drupal::service('plugin.manager.payment.status')
      ->createInstance($status));
    $payment->save();
    return $payment->getPaymentType()->getResumeContextResponse();
  }
}
