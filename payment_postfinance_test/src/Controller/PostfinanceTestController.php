<?php

/**
 * @file
 * Contains \Drupal\payment_postfinance_test\Controller\PostfinanceTestController.
 */

namespace Drupal\payment_postfinance_test\Controller;

use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for testing purposes.
 */
class PostfinanceTestController {

  public $config;

  public function __construct() {
    $this->config = \Drupal::config('payment_postfinance.settings');
  }

  /***
   * With CreatePayInit() a payment link can be generated.
   *
   * @return RedirectResponse
   */
  public function createPayInit() {
    return new RedirectResponse(Url::fromRoute('postfinance_test.postfinance_test_form'));
  }

  /**
   * @return Response
   */
  public function verifyPayConfirm() {
    return new Response("Verify Pay Confirm");
  }

  /**
   * Settles the payment.
   */
  public function payComplete() {
    return new Response("Pay Complete");
  }
}
