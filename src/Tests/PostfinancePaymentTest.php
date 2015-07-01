<?php

/**
 * @file
 * Contains \Drupal\payment_postfinance\Tests\PostfinancePaymentTest.
 */

namespace Drupal\payment_postfinance\Tests;
use Drupal\Core\Url;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\currency\Entity\Currency;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\NodeTypeInterface;
use Drupal\simpletest\WebTestBase;
use Drupal\payment_postfinance\PostfinanceHelper;

/**
 * Token integration.
 *
 * @group payment_postfinance
 */
class PostfinancePaymentTest extends WebTestBase {

  public static $modules = array(
    'payment_postfinance',
    'payment',
    'payment_form',
    'payment_postfinance_test',
    'node',
    'field_ui',
    'dblog',
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
   * Currency object.
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
      'name' => 'Article',
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
          'description' => 'Payment description',
          'payment_id' => '1',
          'quantity' => '2',
          'amount' => '123',
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
      'access site reports',
    ));
    $this->drupalLogin($this->admin_user);

    // Modify the postfinance configuration for testing purposes.
    $postfinance_configuration = array(
      'plugin_form[pspid]' => 'TESTACCOUNT',
      'plugin_form[message][value]' => 'Postfinance',
      'plugin_form[sha_in_key]' => 'SECRETSHAIN',
      'plugin_form[sha_out_key]' => 'SECRETSHAOUT',
      'plugin_form[language]' => 'en_US',
    );

    $this->drupalPostForm('admin/config/services/payment/method/configuration/payment_postfinance_payment_form', $postfinance_configuration, t('Save'));

    // Set payment link to test mode.
    $payment_config = $this->config('payment_postfinance.settings');
    $payment_config->set('payment_link', Url::fromRoute('postfinance_test.postfinance_test_form', array(), ['absolute' => TRUE])->toString());
    $payment_config->save();
    $this->config('');
  }

  /**
   * This function tests accepting a Postfinance payment.
   */
  function testPostfinanceAcceptPayment() {
    // Set payment to accept.
    \Drupal::state()->set('postfinance.return_url_key', 'ACCEPT');
    \Drupal::state()->set('postfinance.testing', TRUE);

    $this->drupalGet('admin/config/services/payment/method/configuration/payment_postfinance_payment_form');
    // Test the debug checkbox.
    $this->assertNoFieldChecked('edit-plugin-form-debug');
    $edit = [
        'plugin_form[debug]' => TRUE,
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->drupalGet('admin/config/services/payment/method/configuration/payment_postfinance_payment_form');
    $this->assertFieldChecked('edit-plugin-form-debug');

    // Create postfinance payment.
    $this->drupalPostForm('node/' . $this->node->id(), array(), t('Pay'));

    // Retrieve plugin configuration of created node.
    $plugin_configuration = $this->node->{$this->fieldName}->plugin_configuration;

    $generator = \Drupal::urlGenerator();

    $payment_data = array(
      'PSPID' => 'TESTACCOUNT',
      'ORDERID' => $plugin_configuration['payment_id'],
      'AMOUNT' => PostfinanceHelper::calculateAmount($plugin_configuration['amount'], $plugin_configuration['quantity'], $plugin_configuration['currency_code']) * 100,
      'CURRENCY' => $plugin_configuration['currency_code'],
      'LANGUAGE' => 'en_US',
      'ACCEPTURL' => $generator->generateFromRoute('payment_postfinance.response_accept', array('payment' => $plugin_configuration['payment_id']), array('absolute' => TRUE)),
      'DECLINEURL' => $generator->generateFromRoute('payment_postfinance.response_decline', array('payment' => $plugin_configuration['payment_id']), array('absolute' => TRUE)),
      'EXCEPTIONURL' => $generator->generateFromRoute('payment_postfinance.response_exception', array('payment' => $plugin_configuration['payment_id']), array('absolute' => TRUE)),
      'CANCELURL' => $generator->generateFromRoute('payment_postfinance.response_cancel', array('payment' => $plugin_configuration['payment_id']), array('absolute' => TRUE)),
    );

    // Assert sent values.
    $this->assertText('AMOUNT' . $payment_data['AMOUNT']);
    $this->assertText('TESTACCOUNT');
    $this->assertText('ORDERID1');
    $this->assertText('CURRENCYCHF');
    $this->assertText('LANGUAGEen_US');
    // The Signature depends on the global root, so we generate it explicitly.
    $this->assertText('SHASIGN' . PostfinanceHelper::generateShaSign($payment_data, 'SECRETSHAIN'));

    // Finish payment.
    $this->drupalPostForm(NULL, NULL, t('Submit'));
    $this->assertText('Payment successful.');

    // Check if payment was succesfully created.
    $this->drupalGet('payment/1');
    $this->assertNoText('Failed');
    $this->assertText('CHF 123.00');
    $this->assertText('CHF 246.00');
    $this->assertText('Completed');

    // Check the log for the response debug.
    $this->drupalGet('/admin/reports/dblog');
    $this->assertText('Payment success response:');
  }

  /**
   * Tests declining Postfinance payment.
   */
  function testPostfinanceDeclinePayment() {
    // Set payment to decline.
    \Drupal::state()->set('postfinance.return_url_key', 'DECLINE');
    $this->drupalPostForm('admin/config/services/payment/method/configuration/payment_postfinance_payment_form', ['plugin_form[debug]' => TRUE], t('Save'));

    // Load payment configuration.
    $payment_config = $this->config('payment_postfinance.settings');

    // Set callback status to decline payment.
    \Drupal::state()->set('postfinance.callback_status', 2);
    \Drupal::state()->set('postfinance.testing', TRUE);

    // Create Postfinance payment.
    $this->drupalPostForm('node/' . $this->node->id(), array(), t('Pay'));

    // Finish payment.
    $this->drupalPostForm(NULL, array(), t('Submit'));
    $this->assertText('Payment processing declined.');

    // Check if payment was succesfully declined.
    $this->drupalGet('payment/1');
    $this->assertNoText('Completed');
    $this->assertText('Failed');

    // Check the log for the response debug.
    $this->drupalGet('/admin/reports/dblog');
    $this->assertText('Payment decline response:');
  }

  /**
   * Tests exception Postfinance payment.
   */
  function testPostfinanceExceptionPayment() {
    // Enable logging of responses.
    $this->drupalPostForm('admin/config/services/payment/method/configuration/payment_postfinance_payment_form', ['plugin_form[debug]' => TRUE], t('Save'));

    // Set callback status to decline payment.
    \Drupal::state()->set('postfinance.callback_status', 52);
    \Drupal::state()->set('postfinance.testing', TRUE);

    // Set payment to exception case.
    \Drupal::state()->set('postfinance.return_url_key', 'EXCEPTION');

    // Create Postfinance payment.
    $this->drupalPostForm('node/' . $this->node->id(), array(), t('Pay'));

    // Finish payment.
    $this->drupalPostForm(NULL, array(), t('Submit'));
    $this->assertText('Payment processing exception.');

    // Check if payment was created with an exception error.
    $this->drupalGet('payment/1');
    $this->assertNoText('Completed');
    $this->assertText('Failed');

    // Check the log for the response debug.
    $this->drupalGet('/admin/reports/dblog');
    $this->assertText('Payment exception response:');
  }

  /**
   * Tests cancel Postfinance payment.
   */
  function testPostfinanceCancelPayment() {
    // Enable logging of responses.
    $this->drupalPostForm('admin/config/services/payment/method/configuration/payment_postfinance_payment_form', ['plugin_form[debug]' => TRUE], t('Save'));

    // Set callback status to decline payment.
    \Drupal::state()->set('postfinance.callback_status', 1);
    \Drupal::state()->set('postfinance.testing', TRUE);

    // Set payment to cancel.
    \Drupal::state()->set('postfinance.return_url_key', 'CANCEL');

    // Create Postfinance payment.
    $this->drupalPostForm('node/' . $this->node->id(), array(), t('Pay'));

    // Finish payment.
    $this->drupalPostForm(NULL, array(), t('Submit'));
    $this->assertText('Payment processing cancelled.');

    // Check if payment was succesfully cancelled.
    $this->drupalGet('payment/1');
    $this->assertNoText('Completed');
    $this->assertText('Cancelled');

    // Check the log for the response debug.
    $this->drupalGet('/admin/reports/dblog');
    $this->assertText('Payment cancel response:');
  }
  /**
   * Tests wrong signature validation in Postfinance payment.
   */
  function testPostfinanceWrongSignature() {
    $plugin_configuration = $this->node->{$this->fieldName}->plugin_configuration;
    $generator = \Drupal::urlGenerator();

    // Set callback status to accept payment and modify the signature.
    \Drupal::state()->set('postfinance.return_url_key', 'ACCEPT');
    \Drupal::state()->set('postfinance.testing', TRUE);
    \Drupal::state()->set('postfinance.signature', 'AAA');

    // Create Postfinance payment.
    $this->drupalPostForm('node/' . $this->node->id(), array(), t('Pay'));

    // Finish payment.
    $this->drupalPostForm(NULL, NULL, t('Submit'));
    $this->assertText('Payment failed. Signature invalid.');

    // Check if payment successfully failed.
    $this->drupalGet('payment/1');
    $this->assertNoText('Completed');
    $this->assertText('Failed');
  }

  /**
   * Tests wrong signature validation in Postfinance payment.
   */
  function testPostfinanceStatusError() {
    $plugin_configuration = $this->node->{$this->fieldName}->plugin_configuration;
    $generator = \Drupal::urlGenerator();

    // Set callback status to accept payment and modify the Status.
    \Drupal::state()->set('postfinance.return_url_key', 'ACCEPT');
    \Drupal::state()->set('postfinance.testing', TRUE);
    \Drupal::state()->set('postfinance.callback_status', 'error');

    $payment_data = array(
      'PSPID' => 'TESTACCOUNT',
      'ORDERID' => $plugin_configuration['payment_id'],
      'AMOUNT' => PostfinanceHelper::calculateAmount($plugin_configuration['amount'], $plugin_configuration['quantity'], $plugin_configuration['currency_code']) * 100,
      'CURRENCY' => $plugin_configuration['currency_code'],
      'LANGUAGE' => 'en_US',
      'ACCEPTURL' => $generator->generateFromRoute('payment_postfinance.response_accept', array('payment' => $plugin_configuration['payment_id']), array('absolute' => TRUE)),
      'DECLINEURL' => $generator->generateFromRoute('payment_postfinance.response_decline', array('payment' => $plugin_configuration['payment_id']), array('absolute' => TRUE)),
      'EXCEPTIONURL' => $generator->generateFromRoute('payment_postfinance.response_exception', array('payment' => $plugin_configuration['payment_id']), array('absolute' => TRUE)),
      'CANCELURL' => $generator->generateFromRoute('payment_postfinance.response_cancel', array('payment' => $plugin_configuration['payment_id']), array('absolute' => TRUE)),
    );

    // Create Postfinance payment.
    $this->drupalPostForm('node/' . $this->node->id(), array(), t('Pay'));

    // Finish payment.
    $this->drupalPostForm(NULL, NULL, t('Submit'));
    $this->assertText('Payment failed. There was an error processing the request.');

    // Check if payment successfully failed.
    $this->drupalGet('payment/1');
    $this->assertNoText('Completed');
    $this->assertText('Failed');
  }

  /**
   * Adds the payment field to the node.
   *
   * @param NodeTypeInterface $type
   *   Node type interface type.
   * @param string $label
   *   Field label.
   *
   * @return \Drupal\Core\Entity\EntityInterface|static
   *   Form instance.
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

}
