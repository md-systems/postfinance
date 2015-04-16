<?php

/**
 * @file
 * Contains \Drupal\payment_postfinance\Plugin\Payment\Method\PostfinancePaymentFormMethod.
 */

namespace Drupal\payment_postfinance\Plugin\Payment\Method;

use Drupal\Component\Plugin\ConfigurablePluginInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\currency\Entity\Currency;
use Drupal\payment\PaymentExecutionResult;
use Drupal\payment\Plugin\Payment\Method\PaymentMethodBase;
use Drupal\payment_postfinance\PostfinanceHelper;
use Drupal\payment\Response\Response;

/**
 * Postfinance Payment Form payment method.
 *
 * @PaymentMethod(
 *   id = "payment_postfinance_payment_form",
 *   label = @Translation("Postfinance Payment Form"),
 *   deriver = "\Drupal\payment_postfinance\Plugin\Payment\Method\PostfinancePaymentFormDeriver"
 * )
 */
class PostfinancePaymentFormMethod extends PaymentMethodBase implements ContainerFactoryPluginInterface, ConfigurablePluginInterface {

  /**
   * Stores a configuration.
   *
   * @param string $key
   *   Configuration key.
   * @param mixed $value
   *   Configuration value.
   *
   * @return $this
   */
  public function setConfigField($key, $value) {
    $this->configuration[$key] = $value;
    return $this;
  }
  /**
   * Executes the Payment and returns the result.
   *
   * @return \Drupal\Payment\PaymentExecutionResult
   *   Return with Payment Result.
   */
  public function getPaymentExecutionResult() {
    /** @var \Drupal\payment\Entity\PaymentInterface $payment */
    $payment = $this->getPayment();
    $generator = \Drupal::urlGenerator();

    $payment_config = \Drupal::configFactory()->getEditable('payment_postfinance.settings');

    /** @var \Drupal\currency\Entity\CurrencyInterface $currency */
    $currency = Currency::load($payment->getCurrencyCode());

    // Payment data to be sent to Postfinance.
    $payment_data = array(
      'PSPID' => $this->pluginDefinition['pspid'],
      'ORDERID' => $payment->id(),
      'AMOUNT' => intval($payment->getAmount() * $currency->getSubunits()),
      'CURRENCY' => $payment->getCurrencyCode(),
      'LANGUAGE' => $this->pluginDefinition['language'],
      'ACCEPTURL' => $generator->generateFromRoute('payment_postfinance.response_accept', array('payment' => $payment->id()), array('absolute' => TRUE)),
      'DECLINEURL' => $generator->generateFromRoute('payment_postfinance.response_decline', array('payment' => $payment->id()), array('absolute' => TRUE)),
      'EXCEPTIONURL' => $generator->generateFromRoute('payment_postfinance.response_exception', array('payment' => $payment->id()), array('absolute' => TRUE)),
      'CANCELURL' => $generator->generateFromRoute('payment_postfinance.response_cancel', array('payment' => $payment->id()), array('absolute' => TRUE)),
    );

    // Save the payment data if we are testing the module.
    if (\Drupal::state()->get('postfinance.testing')) {
      \Drupal::state()->set('postfinance.payment_data', $payment_data);
    }

    // Generate SHA-IN signature.
    $payment_data['SHASign'] = PostfinanceHelper::generateShaSign($payment_data, $this->pluginDefinition['sha_in_key']);

    // Generate payment link with correct query.
    $payment_link = (Url::fromUri($payment_config->get('payment_link'), array(
      'absolute' => TRUE,
      'query' => $payment_data,
    )));

    return new PaymentExecutionResult(new Response($payment_link));
  }

  /**
   * Performs the actual payment execution.
   */
  protected function doExecutePayment() {
  }

  /**
   * {@inheritdoc}
   */
  protected function getSupportedCurrencies() {
    return TRUE;
  }

}
