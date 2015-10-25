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
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The Response to the accepting request.
   */
  public function processAcceptResponse(Request $request, PaymentInterface $payment) {
    // The definition of the plugin implementation.
    $plugin_definition = $payment->getPaymentMethod()->getPluginDefinition();

    $request_data = $request->query->all();
    $sha_sent = $request_data['SHASIGN'];
    unset($request_data['SHASIGN']);

    // Generate SHASign from request data.
    $sha_sign = PostfinanceHelper::generateShaSign($request_data, $plugin_definition['sha_out_key']);

    // Check if the sent signature is valid.
    if ($sha_sign != $sha_sent) {
      drupal_set_message(t('Payment failed. Signature invalid.'), 'error');
      \Drupal::logger(t('Payment failed: @error'), array('@error' => 'Signature invalid.'))
        ->warning('PostfinanceResponseController.php');
      return $this->savePayment($payment, 'payment_failed');
    }

    if ($request_data['STATUS'] == 5 || $request_data['STATUS'] == 9) {
      drupal_set_message(t('Payment successful.'));
      return $this->savePayment($payment, 'payment_success');
    }
    // If no case fits, fail the payment.
    drupal_set_message(t('Payment failed. There was an error processing the request.'), 'error');
    \Drupal::logger(t('Payment failed: @error'), array('@error' => 'There was an error processing the request.'))
      ->warning('PostfinanceResponseController.php');
    return $this->savePayment($payment, 'payment_failed');

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
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The Response to the accepting request.
   */
  public function processDeclineResponse(Request $request, PaymentInterface $payment) {

    $request_data = $request->query->all();
    if ($request_data['STATUS'] == 2) {
      drupal_set_message(t('Payment processing declined.'), 'error');
      \Drupal::logger('payment_postfinance')
        ->warning('Payment declined: @error', array('@error' => 'Payment processing declined.'));
      return $this->savePayment($payment, 'payment_failed');
    }

    \Drupal::logger('payment_postfinance')->error('Processing failed: @error', array('@error' => 'There was an error processing the request.'));
    drupal_set_message(t('Processing failed: @error', array('@error' => 'There was an error processing the request.')), 'error');
    return $this->savePayment($payment, 'payment_failed');

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
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The Response to the accepting request.
   */
  public function processExceptionResponse(Request $request, PaymentInterface $payment) {

    $request_data = $request->query->all();
    if ($request_data['STATUS'] == 52 || $request_data['STATUS'] == 92) {
      drupal_set_message(t('Payment processing exception.'), 'error');
      \Drupal::logger('payment_postfinance')
        ->warning('Payment declined: @error', array('@error' => 'Payment processing exception.'));
      return $this->savePayment($payment, 'payment_failed');
    }

    \Drupal::logger('postfinance')->error('Processing failed: @error', array('@error' => 'There was an error processing the request.'));
    drupal_set_message(t('Processing failed: @error', array('@error' => 'There was an error processing the request.')), 'error');
    return $this->savePayment($payment, 'payment_failed');

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
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The Response to the accepting request.
   */
  public function processCancelResponse(Request $request, PaymentInterface $payment) {

    $request_data = $request->query->all();
    if ($request_data['STATUS'] == 1) {
      drupal_set_message(t('Payment processing cancelled.'), 'warning');
      return $this->savePayment($payment, 'payment_cancelled');
    }

    \Drupal::logger('payment_postfinance')->error('Processing failed: @error', array('@error' => 'There was an error processing the request.'));
    drupal_set_message(t('Processing failed: @error', array('@error' => 'There was an error processing the request.')), 'error');
    return $this->savePayment($payment, 'payment_failed');
  }


  /**
   * Saves the payment.
   *
   * @param \Drupal\payment\Entity\PaymentInterface $payment
   *   The Payment Entity type.
   * @param string $status
   *   The Payment Status.
   *
   * @return \Drupal\Core\Url
   *   Return the Response with the new status.
   */
  public function savePayment(PaymentInterface $payment, $status = 'payment_failed') {
    $payment->setPaymentStatus(\Drupal::service('plugin.manager.payment.status')
      ->createInstance($status));
    $payment->save();
    return $payment->getPaymentType()->getResumeContextResponse()->getResponse();
  }

}
