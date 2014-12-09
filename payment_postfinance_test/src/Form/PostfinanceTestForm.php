<?php

/**
 * @file
 * Contains \Drupal\payment_postfinance_test\Form\PostfinanceTestForm.
 */

namespace Drupal\payment_postfinance_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\payment\Entity\Payment;
use Drupal\taxonomy\TermInterface;
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
    $payment = Payment::load($request->query->get('ORDERID'));

    // Load payment method configuration.
    $plugin_definition = $payment->getPaymentMethod()->getPluginDefinition();

    // Generate the callback parameters to be send back.
    $callback_parameters = array(
      'ORDERID' => $request->query->get('ORDERID'),
      'AMOUNT' => $request->query->get('AMOUNT'),
      'CURRENCY' => $request->query->get('CURRENCY'),
      'PM' => 'CreditCard',
      'ACCEPTANCE' => 'test123',
      'STATUS' => 5,
      'CARDNO' => 'XXXXXXXXXXXX1111',
      'PAYID' => 1136745,
      'NCERROR' => 0,
      'BRAND' => 'VISA',
      'SHASIGN' => strtoupper(sha1($request->query->get('ORDERID') .
        $request->query->get('AMOUNT') . $request->query->get('CURRENCY') .
        $request->query->get('PSPID') . $plugin_definition['security_key'])),
    );

    // Loop trough the callback parameters and put them in hidden fields to be send.
    foreach ($callback_parameters as $key => $value) {
      $form[$key] = array(
        '#type' => 'hidden',
        '#value' => $value,
      );
    }

    // Generate the route by getting the return url key.
    $response_url_key = \Drupal::state()->get('postfinance.return_url_key') ?: 'ACCEPT';
    $response_url = $request->query->get($response_url_key . 'URL');

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
  public function submitForm(array &$form, FormStateInterface $form_state) {
    drupal_set_message("Form submitted");
  }

}
