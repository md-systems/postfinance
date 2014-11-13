<?php
/**
 * @file
 * Contains \Drupal\payment_postfinance\Controller\PostfinanceResponseController
 */

namespace Drupal\payment_postfinance\Controller;

use Drupal\payment\Entity\Payment;
use Drupal\payment\Entity\PaymentInterface;
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
    $this->savePayment($payment, 'payment_config');

    // @todo: Logger & drupal_set_message payment config.
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
