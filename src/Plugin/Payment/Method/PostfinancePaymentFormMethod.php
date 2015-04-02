<?php

/**
 * @file
 * Contains \Drupal\payment_postfinance\Plugin\Payment\Method\PostfinancePaymentFormMethod.
 */

namespace Drupal\payment_postfinance\Plugin\Payment\Method;

use Drupal\Component\Plugin\ConfigurablePluginInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\Token;
use Drupal\currency\Entity\Currency;
use Drupal\payment\Plugin\Payment\Method\PaymentMethodBase;
use Drupal\payment\Plugin\Payment\Status\PaymentStatusManager;
use Drupal\payment_postfinance\PostfinanceHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

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
   * Performs the actual payment execution.
   */
  protected function doExecutePayment() {
    /** @var \Drupal\payment\Entity\PaymentInterface $payment */
    $payment = $this->getPayment();
    $generator = \Drupal::urlGenerator();

    /** @var \Drupal\currency\Entity\CurrencyInterface $currency */
    $currency = Currency::load($payment->getCurrencyCode());

    // Payment data to be send to Postfinance.
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

    // Generate SHA-IN signature
    $payment_data['SHASign'] = PostfinanceHelper::generateShaSign($payment_data, $this->pluginDefinition['sha_in_key']);

    // Generate payment link with correct query.
    $payment_link = (Url::fromUri($this->pluginDefinition['payment_link'], array(
      'absolute' => TRUE,
      'query' => $payment_data,
    )));

    // Redirect to generated payment link.
    $response = new RedirectResponse($payment_link);
    $listener = function (FilterResponseEvent $event) use ($response) {
      $event->setResponse($response);
      $event->stopPropagation();
    };
    $this->eventDispatcher->addListener(KernelEvents::RESPONSE, $listener, 999);

    // Save payment
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function getSupportedCurrencies() {
    return TRUE;
  }

}
