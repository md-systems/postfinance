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
    // Set payment link to test mode
    $payment_config = \Drupal::config('payment_postfinance.settings');
    $payment_config->set('payment_link', $GLOBALS['base_url'] . Url::fromRoute('postfinance_test.postfinance_test_form')->toString());
    $payment_config->save();

//    // Retrieve plugin configuration holds the payment data.
//    $plugin_configuration = $this->node->{$this->field_name}->plugin_configuration;
//
//    // Postfinance payment method configuration values.
//    $postfinance_payment_method_configuration = entity_load('payment_method_configuration', 'postfinance_payment_form')->getPluginConfiguration();

    // Check if payment link is correctly set.
    $this->assertEqual($payment_config->get('payment_link'), $GLOBALS['base_url'] . Url::fromRoute('postfinance_test.postfinance_test_form')->toString());

    // Create saferpay payment
    $this->drupalPostForm('node/' . $this->node->id(), array(), t('Pay'));

    $this->assertText('pspid12345-12345678');
    $this->assertText('orderID1');
    $this->assertText('amount0');
    $this->assertText('currencyXXX');
    $this->assertText('languageen_US');
    $this->assertText('SHASign7CCD396B6BE3CC7C519DCE7A54BBA9DB982D8D5E');

    // Finish payment
    $this->drupalPostForm(NULL, array(), t('Submit'));

    // Check if payment was succesfully created
    $this->drupalGet('payment/1');
    $this->assertNoText('Failed');
    $this->assertText('pay me man');
    $this->assertText('XXX 123.00');
    $this->assertText('XXX 246.00');
    $this->assertText('Completed');
  }

  /**
   * Calculates the total amount
   *
   * @param $amount
   *  Base amount
   * @param $quantity
   *  Quantity
   * @param $currency_code
   *  Currency code
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

