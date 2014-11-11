<?php

/**
 * @file
 * Contains \Drupal\payment_postfinance\Plugin\Payment\MethodConfiguration\PostfinancePaymentFormConfiguration.
 */

namespace Drupal\payment_postfinance\Plugin\Payment\MethodConfiguration;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\payment\Plugin\Payment\MethodConfiguration\PaymentMethodConfigurationBase;
use Drupal\payment\Plugin\Payment\Status\PaymentStatusManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the configuration for the Postfinance PaymentForm payment method plugin.
 *
 * @PaymentMethodConfiguration(
 *   description = @Translation("Postfinance Payment Form."),
 *   id = "payment_postfinance_payment_form",
 *   label = @Translation("Postfinance Payment Form")
 * )
 */
class PostfinancePaymentFormConfiguration extends PaymentMethodConfigurationBase implements ContainerFactoryPluginInterface {

  /**
   * The payment status manager.
   *
   * @var \Drupal\payment\Plugin\Payment\Status\PaymentStatusManagerInterface
   */
  protected $paymentStatusManager;

  /**
   * Constructs a new class instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\payment\Plugin\Payment\Status\PaymentStatusManagerInterface $payment_status_manager
   *   The payment status manager.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   A string containing the English string to translate.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Interface for classes that manage a set of enabled modules.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, PaymentStatusManagerInterface $payment_status_manager, TranslationInterface $string_translation, ModuleHandlerInterface $module_handler) {
    $configuration += $this->defaultConfiguration();
    parent::__construct($configuration, $plugin_id, $plugin_definition, $string_translation, $module_handler);
    $this->paymentStatusManager = $payment_status_manager;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.payment.status'),
      $container->get('string_translation'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + array(
      'account_id' => '99867-94913159',
    );
  }

  /**
   * Sets the Postfinance account id.
   *
   * @param string $account_id
   *   Account id.
   *
   * @return \Drupal\payment_saferpay\Plugin\Payment\MethodConfiguration\SaferpayPaymentFormConfiguration
   *   The configuration object for the Saferpay Payment Form payment method plugin.
   */
  public function setAccountId($account_id) {
    $this->configuration['account_id'] = $account_id;

    return $this;
  }

  /**
   * Gets the Saferpay account id.
   *
   * @return string
   *   The account id.
   */
  public function getAccountId() {
    return $this->configuration['account_id'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['#element_validate'][] = array($this, 'formElementsValidate');

    $form['account_id'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Account ID'),
      '#description' => 'Development Account ID: "99867-94913159".',
      '#default_value' => $this->getAccountId(),
    );

    return $form;
  }

  /**
   * Implements form validate callback for self::formElements().
   */
  public function formElementsValidate(array $element, FormStateInterface $form_state, array $form) {
    $values = NestedArray::getValue($form_state->getValues(), $element['#parents']);

    $this->setAccountId($values['account_id']);
  }

}
