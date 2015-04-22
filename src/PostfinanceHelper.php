<?php
/**
 * @file
 * Contains \Drupal\payment_postfinance\PostfinanceHelper.
 *
 * Postfinance Helper class.
 */
namespace Drupal\payment_postfinance;
/**
 * PostfinanceHelper class.
 */
class PostfinanceHelper {
  /**
   * Generates the request signature with the payment data and a secret key.
   *
   * @param array $payment_data
   *   The data for the payment method.
   * @param string $secret_key
   *   Secret key which is used to validated the request.
   *
   * @return string
   *   The signature generated from the payment data and secret key.
   */
  public static function generateShaSign(array $payment_data, $secret_key) {
    $string = NULL;
    // Sort array in alphabetical order by key.
    $payment_data = array_change_key_case($payment_data, CASE_UPPER);
    ksort($payment_data);
    // Create SHA string that will be encrypted.
    foreach ($payment_data as $key => $value) {
      if ($value !== '') {
        $string .= $key . '=' . $value . $secret_key;
      }
    }
    return strtoupper(sha1($string));
  }
  /**
   * Calculates the payment amount in Subunits.
   *
   * @param float $amount
   *   The payment amount.
   * @param float $subunits
   *   Subunits the currency uses.
   *
   * @return int
   *   Calculated total for the payment in the currencies subunits.
   */
  public static function calculateAmount($amount, $subunits) {
    if ($subunits == 0) {
      return intval($amount);
    }
    return intval($amount * $subunits);
  }
}
