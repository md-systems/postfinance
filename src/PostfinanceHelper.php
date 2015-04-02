<?php
/**
 * @file
 * Contains \Drupal\payment_postfinance\PostfinanceHelper.
 *
 * Postfinance Helper class.
 */
namespace Drupal\payment_postfinance;
/**
 * PostfinanceHelper class
 */
class PostfinanceHelper {
  /**
   * @param $payment_data
   * @param $secret_key
   * @return string
   */
  public static function generateShaSign($payment_data, $secret_key) {
    $string = null;
    // Sort array in alphabetical order by key.
    $payment_data = array_change_key_case($payment_data, CASE_UPPER);
    ksort($payment_data);
    // Unset values that are not allowed in SHA-IN or SHA-OUT calls.
    unset($payment_data['SHASIGN'], $payment_data['FORM_BUILD_ID'], $payment_data['FORM_TOKEN'],
      $payment_data['FORM_ID'], $payment_data['OP']);
    // Create SHA string that will be encrypted.
    foreach ($payment_data as $key => $value) {
      if (isset($value)) {
        $string .= $key . '=' . $value . $secret_key;
      }
    }
    return strtoupper(sha1($string));
  }
  /**
   * @param $amount
   * @param $subunits
   * @return int
   */
  public static function calculateAmount($amount, $subunits) {
    if ($subunits == 0) {
      return intval($amount);
    }
    return intval($amount * $subunits);
  }
}
