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
    $success = TRUE;
    foreach ($request->query->all() as $key => $value) {
      drupal_set_message($key . $value);
    }

    $payment = Payment::load($request->query->get('ORDERID'));
    $plugin_definition = $payment->getPaymentMethod()->getPluginDefinition();

    $callback_parameters = array();

    if($success) {
      $callback_parameters = array(
        'orderID' => $request->query->get('ORDERID'),
        'amount' => $request->query->get('AMOUNT'),
        'currency' => $request->query->get('CURRENCY'),
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
    }

    foreach ($callback_parameters as $key => $value) {
      $form[$key] = array(
        '#type' => 'hidden',
        '#value' => $value,
      );
    }

    // Don't generate the route, use the submitted url.
    $response_url_key = \Drupal::state()->get('postfinance.return_url_key') ?: 'accept';
    $response_url = $request->query->get($response_url_key . 'URL');

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
