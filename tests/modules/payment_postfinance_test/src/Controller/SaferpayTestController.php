namespace Drupal\payment_postfinance_test\Controller;

use Drupal\Core\Url;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Component\Utility\Crypt;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
  * Controller for testing purposes.
  */

  class PostfinanceTestController {
  public $config;

  public function __construct() {
       $this->config = \Drupal::config('payment_postfinance.settings');
     }
  }

 /**
    * For more documentation regarding this test controller see: \Drupal\payment_saferpay\README.txt
    */

public function orderstandard(Request $request = NULL) {
    return new Response(Url::fromRoute('postfinance_test.postfinance_test_form', array(), array(
        'query' => $request->query->all()))setAbsolute()->toString());
}
