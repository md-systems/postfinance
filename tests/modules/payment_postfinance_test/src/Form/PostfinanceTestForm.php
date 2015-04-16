<?php

/**
 * @file
 * Contains \Drupal\payment_postfinance\Tests\modules\payment_postfinance_test\Form\PostfinanceTestForm.
 */

namespace Drupal\payment_postfinance_test\Form;

use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\payment\Entity\Payment;
use Drupal\payment_postfinance\Controller\PostfinanceResponseController;
use Drupal\payment_postfinance\PostfinanceHelper;
use Symfony\Component\HttpFoundation\Request;

/**
 * Builds the form to delete a forum term.
 */
class PostfinanceTestForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'postfinance_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {
    // Loop trough request and output all variables in a drupal message.
    foreach ($request->query->all() as $key => $value) {
      drupal_set_message($key . $value);
    }

    // Load payment.
    /** @var \Drupal\payment\Entity\payment $payment */
    $payment = Payment::load($request->query->get('ORDERID'));

    // Load payment method configuration.
    $plugin_definition = $payment->getPaymentMethod()->getPluginDefinition();

    // Callback status.
    $callback_status = \Drupal::state()->get('postfinance.callback_status');

    // Generate the callback parameters to be sent.
    $callback_parameters = array(
      '@ORDERID' => $request->query->get('ORDERID'),
      '@AMOUNT' => $request->query->get('AMOUNT'),
      '@CURRENCY' => $request->query->get('CURRENCY'),
      '@PM' => 'CreditCard',
      '@ACCEPTANCE' => 'test123',
      '@STATUS' => (empty($callback_status) ? 5 : $callback_status),
      '@CARDNO' => 'XXXXXXXXXXXX1111',
      '@PAYID' => 1136745,
      '@NCERROR' => 0,
      '@BRAND' => 'VISA',
    );

    // Put together the array which will be used to generate the signature.
    $hash_array = array_combine(str_replace('@', '', array_keys($callback_parameters)), array_values($callback_parameters));

    // Generate SHA-OUT signature.
    $callback_parameters['@SHASIGN'] = PostfinanceHelper::generateShaSign($hash_array, $plugin_definition['sha_out_key']);

    $data_string = SafeMarkup::format('<IDP ORDERID="@ORDERID" AMOUNT="@AMOUNT" CURRENCY="@CURRENCY" PM="@PM" ACCEPTANCE="@ACCEPTANCE" STATUS="@STATUS" CARDNO="@CARDNO" PAYID="@PAYID" NCERROR="@NCERROR" BRAND="@BRAND" SHASIGN="@SHASIGN" />',
      $callback_parameters);

    // Generate payment link with correct callback query parameters.
    $response_url_key = \Drupal::state()->get('postfinance.return_url_key') ?: 'ACCEPT';

    // The URL for the response is generated according to a key.
    switch ($response_url_key) {
      case 'ACCEPT':
        $response_url = $request->query->get('ACCEPTURL');
        break;

      case 'DECLINE':
        $response_url = $request->query->get('DECLINEURL');
        break;

      case 'EXCEPTION':
        $response_url = $request->query->get('EXCEPTIONURL');
        break;

      case 'CANCEL':
        $response_url = $request->query->get('CANCELURL');
        break;
    }
    $response_url .= '?data=' . urlencode($data_string);

    // Complete the form.
    $form['#action'] = $response_url;
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#button_type' => 'primary',
    );

    return $form;
  }
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }
}
