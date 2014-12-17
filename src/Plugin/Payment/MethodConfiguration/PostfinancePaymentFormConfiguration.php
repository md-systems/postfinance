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
      'pspid' => 'drupalDEMO',
      'sha_in_key' => 'Mysecretsig1875!?',
      'sha_out_key' => 'ShaOUTpassphrase123!?',
    );
  }

  /**
   * @param $psid
   * @return $this
   */
  public function setPSPID($psid) {
    $this->configuration['pspid'] = $psid;

    return $this;
  }

  /**
   * @return mixed
   */
  public function getPSPID() {
    return $this->configuration['pspid'];
  }

  /**
   * @param $sha_in_key
   * @return $this
   */
  public function setShaInKey($sha_in_key) {
    $this->configuration['sha_in_key'] = $sha_in_key;

    return $this;
  }

  /**
   * @return mixed
   */
  public function getShaInKey() {
    return $this->configuration['sha_in_key'];
  }

  /**
   * @param $sha_out_key
   * @return $this
   */
  public function setShaOutKey($sha_out_key) {
    $this->configuration['sha_out_key'] = $sha_out_key;

    return $this;
  }

  /**
   * @return mixed
   */
  public function getShaOutKey() {
    return $this->configuration['sha_out_key'];
  }

  /**
   * @param $language
   * @return $this
   */
  public function setLanguage($language) {
    $this->configuration['language'] = $language;

    return $this;
  }

  /**
   * @return mixed
   */
  public function getLanguage() {
    return $this->configuration['language'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['#element_validate'][] = array($this, 'formElementsValidate');

    $form['pspid'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('PSPID'),
      '#description' => 'Your affiliation name in the postfinance system.',
      '#default_value' => $this->getPSPID(),
    );

    $form['sha_in_key'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('SHA-IN Security pass phrase'),
      '#description' => 'Security pass phrase for validation on Postfinance page',
      '#default_value' => $this->getShaInKey(),
    );

    $form['sha_out_key'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('SHA-OUT Security pass phrase'),
      '#description' => 'Security pass phrase for response validation.',
      '#default_value' => $this->getShaOutKey(),
    );

    // @TODO: Add more languages
    $form['language'] = array(
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => array(
        'en_US' => t('English'),
      ),
      '#description' => 'Your affiliation name in the postfinance system.',
      '#default_value' => $this->getLanguage(),
    );

    // @TODO: Create production and testing option.

    return $form;
  }

  /**
   * @param array $element
   * @param FormStateInterface $form_state
   * @param array $form
   */
  public function formElementsValidate(array $element, FormStateInterface $form_state, array $form) {
    $values = NestedArray::getValue($form_state->getValues(), $element['#parents']);

    $this->setPSPID($values['pspid']);
    $this->setShaInKey($values['sha_in_key']);
    $this->setShaOutKey($values['sha_out_key']);
    $this->setLanguage($values['language']);
  }

}
