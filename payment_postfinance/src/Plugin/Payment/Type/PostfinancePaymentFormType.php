<?php

/**
 * @file
 * Contains \Drupal\payment_postfinance\Plugin\Payment\Type\PostfinancePaymentFormType.
 */

namespace Drupal\payment_postfinance\Plugin\Payment\Type;

use Drupal\Core\Session\AccountInterface;
use Drupal\payment\Plugin\Payment\Type\PaymentTypeBase;

/**
 * A testing payment type.
 *
 * @PaymentType(
 *   id = "payment_postfinance_payment_form",
 *   label = @Translation("Postfinance Payment Form"),
 *   description = @Translation("Postfinance Payment Form payment type.")
 * )
 */
class PostfinancePaymentFormType extends PaymentTypeBase {

  /**
   * {@inheritdoc}
   */
  public function paymentDescription($language_code = NULL) {
    // @todo - provide correct description
    return 'some nice description that I have no idea of what it should describe...';
  }

  /**
   * {@inheritdoc
   */
  public function resumeContextAccess(AccountInterface $account) {
    return FALSE;
  }

  /**
   * {@inheritdoc
   */
  public function doResumeContext() {
  }
}
