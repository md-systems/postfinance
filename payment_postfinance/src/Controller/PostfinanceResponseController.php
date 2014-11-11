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
   * URL to which the customer is to be forwarded to via browser redirect
   * after the successful reservation. Postfinance appends the
   * confirmation message (PayConfirm) by GET to this URL.
   *
   * @param Request $request
   *   Request
   * @param PaymentInterface $payment
   *   The Payment Entity type.
   */
  public function processSuccessResponse(Request $request, PaymentInterface $payment) {

  }

  /**
   * URL to which the customer is to be forwarded to via browser redirect if the authorization attempt failed.
   *
   * @param Request $request
   *   Request
   * @param PaymentInterface $payment
   *   The Payment Entity type.
   */
  public function processFailResponse(Request $request, PaymentInterface $payment) {

  }

  /**
   * URL to which the customer is to be forwarded to via browser redirect if he aborts the transaction.
   *
   * @param Request $request
   *   Request
   * @param PaymentInterface $payment
   *   The Payment Entity type.
   */
  public function processBackResponse(Request $request, PaymentInterface $payment) {

  }

  /**
   *
   * @param Request $request
   *   Request
   * @param PaymentInterface $payment
   *   The Payment Entity type.
   */
  public function processNotifyResponse(Request $request, PaymentInterface $payment) {
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
