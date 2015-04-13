<?php

/**
 * @file
 * Contains \Drupal\payment_postfinance\Tests\PostfinancePaymentTest.
 */

namespace Drupal\payment_postfinance\Tests;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Url;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\currency\Entity\Currency;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
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
    'config',
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
   * @var $fieldName
   */
  protected $fieldName;

  protected function setUp() {
    parent::setUp();

    // Create a field name.
    $this->fieldName = strtolower($this->randomMachineName());

    // Create article content type.
    $node_type = $this->drupalCreateContentType(array(
      'type' => 'article',
      'name' => 'Article'
    ));

    // Import the curreny configuration.
    $config_importer = \Drupal::service('currency.config_importer');
    $config_importer->importCurrency('CHF');

    // Adds the payment field to the node.
    $this->addPaymentFormField($node_type);

    // Create article node.
    $title = $this->randomMachineName();

    // Create node with payment plugin configuration.
    $this->node = $this->drupalCreateNode(array(
      'type' => 'article',
      $this->fieldName => array(
        'plugin_configuration' => array(
          'currency_code' => 'CHF',
          'name' => 'payment_basic',
          'payment_id' => NULL,
          'quantity' => '2',
          'amount' => '123',
          'description' => 'Payment Description',
          'customer_name' => 'John Doe',
          'email' => 'email@example.com',
          'zip' => '1000',
          'address' => 'Musterstrasse 1',
          'city' => 'Musterstadt',
          'town' => 'Musterheim',
          'telephone' => '012 345 67 89',
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
      'payment.payment.view.any',
    ));
    $this->drupalLogin($this->admin_user);
  }

  /**
   * This function tests accepting a Postfinance payment.
   */
  function testPostfinanceAcceptPayment() {
    // Set payment link to test mode.
    $payment_config = \Drupal::configFactory()->getEditable('payment_postfinance.settings');
    $payment_config->set('payment_link', Url::fromRoute('postfinance_test.postfinance_test_form', array(), ['absolute' => TRUE])->toString());
    $payment_config->save();

    // Set payment to accept.
    \Drupal::state()->set('postfinance.return_url_key', 'ACCEPT');
    \Drupal::state()->set('postfinance.testing', TRUE);

    // Load payment configuration.
    $payment_config = \Drupal::config('payment_postfinance.settings');

    // Check if payment link is correctly set.
    $this->assertEqual($payment_config->get('payment_link'), $GLOBALS['base_url'] . Url::fromRoute('postfinance_test.postfinance_test_form')->toString());

    // Modifies the postfinance configuration for testing purposes.
    $postfinance_configuration = array(
      'plugin_form[pspid]' => 'drupalDEMO',
      'plugin_form[message][value]' => 'Postfinance',
      'plugin_form[sha_in_key]' => 'Mysecretsig1875!?',
      'plugin_form[sha_out_key]' => 'ShaOUTpassphrase123!?',
      'plugin_form[language]' => 'en_US',
    );

    $this->drupalPostForm('admin/config/services/payment/method/configuration/payment_postfinance_payment_form', $postfinance_configuration, t('Save'));


    // Retrieve plugin configuration of created node.
    $plugin_configuration = $this->node->{$this->fieldName}->plugin_configuration;

    // Create postfinance payment.
    $this->drupalPostForm('node/' . $this->node->id(), array(), t('Pay'));

    $calculated_amount = $this->calculateAmount($plugin_configuration['amount'], $plugin_configuration['quantity'], $plugin_configuration['currency_code']);
    $this->assertText('AMOUNT' . $calculated_amount);

    // Assert AccountID.
    $this->assertText('drupalDEMO');
    $this->assertText('ORDERID1');
    $this->assertText('CURRENCYCHF');
    $this->assertText('LANGUAGEen_US');
    $this->assertText('SHASignDD1A045AFC36B29F0B4E472DFC72E869841F4B86');

    // Finish payment.
    $this->drupalPostForm(NULL, NULL, t('Submit'));

    // Check if payment was succesfully created.
    $this->drupalGet('payment/1');
    $this->assertNoText('Failed');
    $this->assertText('Payment Description');
    $this->assertText('CHF 123.00');
    $this->assertText('CHF 246.00');
    $this->assertText('Completed');
  }

  /**
   * Tests declining Postfinance payment.
   */
  function testPostfinanceDeclinePayment() {
    // Set callback status to decline payment.
    $payment_config = \Drupal::configFactory()->getEditable('payment_postfinance.settings');
    \Drupal::state()->set('postfinance.callback_status', 2);
    $payment_config->set('payment_link', Url::fromRoute('postfinance_test.postfinance_test_form', array(), ['absolute' => TRUE])->toString());

    // Set payment to decline.
    \Drupal::state()->set('postfinance.return_url_key', 'DECLINE');

    // Load payment configuration.
    $payment_config = \Drupal::config('payment_postfinance.settings');

    // Check if payment link is correctly set.
    $this->assertEqual($payment_config->get('payment_link'), $GLOBALS['base_url'] . Url::fromRoute('postfinance_test.postfinance_test_form')->toString());

    // Create Postfinance payment.
    $this->drupalPostForm('node/' . $this->node->id(), array(), t('Pay'));

    // Finish payment.
    $this->drupalPostForm(NULL, array(), t('Submit'));

    // Check if payment was succesfully created.
    $this->drupalGet('payment/1');
    $this->assertNoText('Completed');
    $this->assertText('Failed');
  }

  /**
   * Tests exception Postfinance payment.
   */
  function testPostfinanceExceptionPayment() {
    // Set callback status to decline payment.
    $payment_config = \Drupal::configFactory()->getEditable('payment_postfinance.settings');
    \Drupal::state()->set('postfinance.callback_status', 52);
    $payment_config->set('payment_link', Url::fromRoute('postfinance_test.postfinance_test_form', array(), ['absolute' => TRUE])->toString());

    // Set payment to accept.
    \Drupal::state()->set('postfinance.return_url_key', 'EXCEPTION');

    // Load payment configuration.
    $payment_config = \Drupal::config('payment_postfinance.settings');

    // Check if payment link is correctly set.
    $this->assertEqual($payment_config->get('payment_link'), $GLOBALS['base_url'] . Url::fromRoute('postfinance_test.postfinance_test_form')->toString());

    // Create Postfinance payment.
    $this->drupalPostForm('node/' . $this->node->id(), array(), t('Pay'));

    // Finish payment.
    $this->drupalPostForm(NULL, array(), t('Submit'));

    // Check if payment was succesfully created.
    $this->drupalGet('payment/1');
    $this->assertNoText('Completed');
    $this->assertText('Failed');
  }

  /**
   * Tests cancel Postfinance payment.
   */
  function testPostfinanceCancelPayment() {
    // Set callback status to decline payment.
    $payment_config = \Drupal::configFactory()->getEditable('payment_postfinance.settings');
    \Drupal::state()->set('postfinance.callback_status', 1);
    $payment_config->set('payment_link', Url::fromRoute('postfinance_test.postfinance_test_form', array(), ['absolute' => TRUE])->toString());

    // Set payment to accept.
    \Drupal::state()->set('postfinance.return_url_key', 'CANCEL');

    // Load payment configuration.
    $payment_config = \Drupal::config('payment_postfinance.settings');

    // Check if payment link is correctly set.
    $this->assertEqual($payment_config->get('payment_link'), $GLOBALS['base_url'] . Url::fromRoute('postfinance_test.postfinance_test_form')->toString());

    // Create Postfinance payment.
    $this->drupalPostForm('node/' . $this->node->id(), array(), t('Pay'));

    // Finish payment.
    $this->drupalPostForm(NULL, array(), t('Submit'));

    // Check if payment was succesfully created.
    $this->drupalGet('payment/1');
    $this->assertNoText('Completed');
    $this->assertText('Cancelled');
  }

  /**
   * Adds the payment field to the node.
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
      'field_name' => $this->fieldName,
      'entity_type' => 'node',
      'type' => 'payment_form',
    ));
    $field_storage->save();

    $instance = entity_create('field_config', array(
      'field_storage' => $field_storage,
      'bundle' => $type->id(),
      'label' => $label,
      'settings' => array('currency_code' => 'CHF'),
    ));
    $instance->save();

    // Assign display settings for the 'default' and 'teaser' view modes.
    entity_get_display('node', $type->id(), 'default')
      ->setComponent($this->fieldName, array(
        'label' => 'hidden',
        'type' => 'text_default',
      ))
      ->save();

    return $instance;
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
   *
   * @return int
   *  Returns the total amount
   */
  function calculateAmount($amount, $quantity, $currency_code) {
    $base_amount = $amount * $quantity;
    /** @var \Drupal\currency\Entity\Currency $currency */
    $currency = Currency::load($currency_code);
    return intval($base_amount * $currency->getSubunits());
  }
}

