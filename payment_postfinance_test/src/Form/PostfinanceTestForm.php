<?php

/**
 * @file
 * Contains \Drupal\payment_postfinance_test\Form\PostfinanceTestForm.
 */

namespace Drupal\payment_postfinance_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\TermInterface;

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
  public function buildForm(array $form, FormStateInterface $form_state, TermInterface $taxonomy_term = NULL) {
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Test It!'),
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
