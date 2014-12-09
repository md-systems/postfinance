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
  public static function generateShaIN($payment_data, $secret_key) {
    $string = null;

    // Sort array in alphabetical order by key.
    ksort($payment_data);

    foreach ($payment_data as $key => $value) {
      if (!empty($value)) {
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
