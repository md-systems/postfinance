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

    // Adds the payment field to the node
    $this->addPaymentFormField($node_type);

    // Create article node
    $title = $this->randomMachineName();

    // Create node with payment plugin configuration
    $this->node = $this->drupalCreateNode(array(
      'type' => 'article',
      $this->field_name => array(
        'plugin_configuration' => array(
          'currency_code' => 'XXX',
          'name' => 'payment_basic',
          'quantity' => '2',
          'amount' => '123',
          'description' => 'pay me man',
        ),
        'plugin_id' => 'payment_basic',
      ),
      'title' => $title,
    ));

    // Create user with correct permission and login.
    $this->admin_user = $this->drupalCreateUser(array(
      'payment.payment_method_configuration.view.any',
      'payment.payment_method_configuration.update.any',
      'access content',
      'access administration pages',
      'access user profiles',
      'payment.payment.view.any'
    ));
    $this->drupalLogin($this->admin_user);

    // Set payment link to test mode
    $payment_config = \Drupal::config('payment_postfinance.settings');
    $payment_config->set('payment_link', $GLOBALS['base_url'] . Url::fromRoute('postfinance_test.postfinance_test_form')->toString());
    $payment_config->save();
  }

  /**
   * Tests accept Postfinance payment.
   */
  function testPostfinanceAcceptPayment() {
    // Set payment to accept
    \Drupal::state()->set('postfinance.return_url_key', 'ACCEPT');

    // Load payment configuration
    $payment_config = \Drupal::config('payment_postfinance.settings');

    // Check if payment link is correctly set.
    $this->assertEqual($payment_config->get('payment_link'), $GLOBALS['base_url'] . Url::fromRoute('postfinance_test.postfinance_test_form')->toString());

    // Create saferpay payment
    $this->drupalPostForm('node/' . $this->node->id(), array(), t('Pay'));

    $this->assertText('PSPIDdrupalDEMO');
    $this->assertText('ORDERID1');
    $this->assertText('AMOUNT246');
    $this->assertText('CURRENCYXXX');
    $this->assertText('LANGUAGEen_US');
    $this->assertText('SHASignE5CED4AA85915279F55A517AC42E21067CAB0AF5');

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
   * Tests declining Postfinance payment.
   */
  function testPostfinanceDeclinePayment() {
    // Set payment to accept
    \Drupal::state()->set('postfinance.return_url_key', 'DECLINE');

    // Load payment configuration
    $payment_config = \Drupal::config('payment_postfinance.settings');

    // Check if payment link is correctly set.
    $this->assertEqual($payment_config->get('payment_link'), $GLOBALS['base_url'] . Url::fromRoute('postfinance_test.postfinance_test_form')->toString());

    // Create saferpay payment
    $this->drupalPostForm('node/' . $this->node->id(), array(), t('Pay'));

    // Finish payment
    $this->drupalPostForm(NULL, array(), t('Submit'));

    // Check if payment was succesfully created
    $this->drupalGet('payment/1');
    $this->assertNoText('Completed');
    $this->assertText('Failed');
  }

  /**
   * Tests exception Postfinance payment.
   */
  function testPostfinanceExceptionPayment() {
    // Set payment to accept
    \Drupal::state()->set('postfinance.return_url_key', 'EXCEPTION');

    // Load payment configuration
    $payment_config = \Drupal::config('payment_postfinance.settings');

    // Check if payment link is correctly set.
    $this->assertEqual($payment_config->get('payment_link'), $GLOBALS['base_url'] . Url::fromRoute('postfinance_test.postfinance_test_form')->toString());

    // Create saferpay payment
    $this->drupalPostForm('node/' . $this->node->id(), array(), t('Pay'));

    // Finish payment
    $this->drupalPostForm(NULL, array(), t('Submit'));

    // Check if payment was succesfully created
    $this->drupalGet('payment/1');
    $this->assertNoText('Completed');
    $this->assertText('Failed');
  }

  /**
   * Tests cancel Postfinance payment.
   */
  function testPostfinanceCancelPayment() {
    // Set payment to accept
    \Drupal::state()->set('postfinance.return_url_key', 'CANCEL');

    // Load payment configuration
    $payment_config = \Drupal::config('payment_postfinance.settings');

    // Check if payment link is correctly set.
    $this->assertEqual($payment_config->get('payment_link'), $GLOBALS['base_url'] . Url::fromRoute('postfinance_test.postfinance_test_form')->toString());

    // Create saferpay payment
    $this->drupalPostForm('node/' . $this->node->id(), array(), t('Pay'));

    // Finish payment
    $this->drupalPostForm(NULL, array(), t('Submit'));

    // Check if payment was succesfully created
    $this->drupalGet('payment/1');
    $this->assertNoText('Completed');
    $this->assertText('Cancelled');
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

