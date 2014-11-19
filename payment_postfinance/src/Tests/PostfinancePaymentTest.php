<?php

/**
 * @file
 * Contains \Drupal\payment_postfinance\Tests\PostfinancePaymentTest.
 */

namespace Drupal\payment_postfinance\Tests;

use Drupal\Core\Url;
use Drupal\currency\Entity\Currency;
use Drupal\field\Entity\FieldInstanceConfig;
use Drupal\node\NodeTypeInterface;
use Drupal\simpletest\WebTestBase;

/**
 * Token integration.
 *
 * @group Currency
 */
class PostfinancePaymentTest extends WebTestBase {

  public static $modules = array(
    'payment_postfinance',
    'payment',
    'payment_form',
    'payment_postfinance_test',
    'node',
    'field_ui',
    'config'
  );

  /**
   * A user with permission to create and edit books and to administer blocks.
   *
   * @var object
   */
  protected $admin_user;


  /**
   * Generic node used for testing.
   */
  protected $node;

  /**
   * Currency object
   *
   * @var object
   */
  protected $currency;


  /**
   * @var $field_name
   */
  protected $field_name;

  protected function setUp() {
    parent::setUp();

    // Create a field name
    $this->field_name = strtolower($this->randomMachineName());

    // Create article content type
    $node_type = $this->drupalCreateContentType(array(
      'type' => 'article',
      'name' => 'Article'
    ));

    $this->addPaymentFormField($node_type);

    // Create article node
    $title = $this->randomMachineName();

    // Create node with payment plugin configuration
    $this->node = $this->drupalCreateNode(array(
      'type' => 'article',
      $this->field_name => array(
        'plugin_configuration' => array(
          'amount' => '123',
          'currency_code' => 'XXX',
          'name' => 'payment_basic',
          'payment_id' => NULL,
          'quantity' => '2',
          'description' => 'pay me man',
        ),
        'plugin_id' => 'payment_basic',
      ),
      'title' => $title,
    ));

    // Create user with correct permission.
    $this->admin_user = $this->drupalCreateUser(array(
      'payment.payment_method_configuration.view.any',
      'payment.payment_method_configuration.update.any',
      'access content',
      'access administration pages',
      'access user profiles',
      'payment.payment.view.any'
    ));
    $this->drupalLogin($this->admin_user);
  }

  /**
   * Tests succesfull Postfinance payment.
   */
  function testPostfinanceSuccessPayment() {
    $generator = \Drupal::urlGenerator();

    $payment_config = \Drupal::config('payment_postfinance.settings');

    $payment_config->set('payment_link', $GLOBALS['base_url'] . $generator->generateFromRoute('postfinance_test.postfinance_test_form'));
    $payment_config->save();

    // Retrieve plugin configuration of created node
    $plugin_configuration = $this->node->{$this->field_name}->plugin_configuration;

    // Array of Saferpay payment method configuration.
    //$postfinance_payment_method_configuration = entity_load('payment_method_configuration', 'payment_postfinance_payment_form')->getPluginConfiguration();


    $payment_link = Url::fromRoute('postfinance_test.postfinance_test_form')->toString();

    $this->assertEqual($payment_config->get('payment_link'), $GLOBALS['base_url'] . $payment_link);

    $this->drupalGet($GLOBALS['base_url'] . $payment_link);

    $calculated_amount = $this->calculateAmount($plugin_configuration['amount'], $plugin_configuration['quantity'], 'XXX');

    // Create saferpay payment
    $this->drupalPostForm('node/' . $this->node->id(), array(), t('Pay'));
  }

  /**
   * Calculates the total amount
   *
   * @param $amount
   *  Base amount
   * @param $quantity
   *  Quantity
   * @param $currency
   *  Currency object
   * @return int
   *  Returns the total amount
   */
  function calculateAmount($amount, $quantity, $currency_code) {
    $base_amount = $amount * $quantity;
    $currency = Currency::load($currency_code);
    return intval($base_amount * $currency->getSubunits());
  }

  /**
   * Generates the sign
   *
   * @param $hmac_key
   *  hmac key
   * @param $merchant_id
   *  Merchant ID
   * @param $identifier
   * @param $amount
   *  The order amount
   * @param $currency
   *  Currency Code
   * @return string
   *  Returns the sign
   */
  function generateSign($hmac_key, $merchant_id, $identifier, $amount, $currency) {
    $hmac_data = $merchant_id . $amount . $currency . $identifier;
    return hash_hmac('md5', $hmac_data, pack('H*', $hmac_key));
  }

  /**
   * Adds the payment field to the node
   *
   * @param NodeTypeInterface $type
   *   Node type interface type
   *
   * @param string $label
   *   Field label
   *
   * @return \Drupal\Core\Entity\EntityInterface|static
   */
  function addPaymentFormField(NodeTypeInterface $type, $label = 'Payment Label') {
    $field_storage = entity_create('field_storage_config', array(
      'field_name' => $this->field_name,
      'entity_type' => 'node',
      'type' => 'payment_form',
    ));
    $field_storage->save();

    $instance = entity_create('field_config', array(
      'field_storage' => $field_storage,
      'bundle' => $type->id(),
      'label' => $label,
      'settings' => array('currency_code' => 'XXX'),
    ));
    $instance->save();

    // Assign display settings for the 'default' and 'teaser' view modes.
    entity_get_display('node', $type->type, 'default')
      ->setComponent($this->field_name, array(
        'label' => 'hidden',
        'type' => 'text_default',
      ))
      ->save();

    return $instance;
  }
}
