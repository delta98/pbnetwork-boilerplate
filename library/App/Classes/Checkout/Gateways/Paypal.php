<?php
namespace App\Classes\Checkout\Gateways;

class Paypal
{
    private $_environment; /* set to live when ready */

    /**
     * The username used to identify user with PayPal
     * @var mixed username
     */
    private $_username;

    /**
     * The password used to secure account access with Paypal
     * @var mixed password
     */
    private $_password;

    /**
     * The api signature provided by Paypal
     * @var mixed signature
     */
    private $_signature;

    /**
     * The paypal payer's ID
     * @var mixed payerId
     */
    private $_payerID;

    /**
     * The returned access token from PayPal after an API call
     * @var mixed token
     */
    private $_token;

    /**
     * Setup required values to access PayPal services
     * @param mixed $username
     * @param mixed $password
     * @param mixed $signature
     * @param mixed $environment
     */
    public function __construct($username, $password, $signature, $environment = 'sandbox'){
        $this->_username = $username;
        $this->_password = $password;
        $this->_signature = $signature;
        $this->_environment = $environment;
    }

    /**
     * Get the PayPal server environment
     * @return mixed environment
     */
    public function getEnvironment()
    {
        return $this->_environment;
    }

    /**
     * Set the PayPal server environment
     * @param mixed $env
     */
    public function setEnvironment($env)
    {
        $this->_environment = $env;
    }

    /**
     * Send HTTP POST Request
     *
     * @param	string	The API method name
     * @param	string	The POST Message fields in &name=value pair format
     * @return	array	Parsed HTTP Response body
     */
    protected function PPHttpPost($methodName_, $nvpStr_) {

        // Set up your API credentials, PayPal end point, and API version.
        $API_UserName = urlencode($this->_username);
        $API_Password = urlencode($this->_password);
        $API_Signature = urlencode($this->_signature);
        $API_Endpoint = "https://api-3t.paypal.com/nvp";
        if("sandbox" === $this->_environment || "beta-sandbox" === $this->_environment) {
                $API_Endpoint = "https://api-3t.$this->_environment.paypal.com/nvp";
        }

        $version = urlencode('51.0');

        // Set the curl parameters.
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $API_Endpoint);
        curl_setopt($ch, CURLOPT_VERBOSE, 1);

        // Turn off the server and peer verification (TrustManager Concept).
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);

        // Set the API operation, version, and API signature in the request.
        $nvpreq = "METHOD=$methodName_&VERSION=$version&PWD=$API_Password&USER=$API_UserName&SIGNATURE=$API_Signature$nvpStr_";

        // Set the request as a POST FIELD for curl.
        curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);

        // Get response from the server.
        $httpResponse = curl_exec($ch);

        if(!$httpResponse) {
                exit('$methodName_ failed: '.curl_error($ch).'('.curl_errno($ch).')');
        }

        // Extract the response details.
        $httpResponseAr = explode("&", $httpResponse);

        $httpParsedResponseAr = array();
        foreach ($httpResponseAr as $i => $value) {
                $tmpAr = explode("=", $value);
                if(sizeof($tmpAr) > 1) {
                        $httpParsedResponseAr[$tmpAr[0]] = $tmpAr[1];
                }
        }

        if((0 == sizeof($httpParsedResponseAr)) || !array_key_exists('ACK', $httpParsedResponseAr)) {
                exit("Invalid HTTP Response for POST request($nvpreq) to $API_Endpoint.");
        }

        return $httpParsedResponseAr;
    }

    /**
     * PayPal API request SetExpressCheckout using NVP (Name Value Pairs)
     * @param mixed $paymentAmount - amount being requested to be paid
     * @param mixed $returnURL - the URL used when the customer completes the transaction
     * @param cancelURL - the URL used when the customer cancels the transaction
     * @param float $currency - currency used for payment: GBP, EUR, JPY, CAD, AUD etc.
     * @param mixed $paymentType - type of payment: Authorization, Sale or Order  * @param mixed $cancelURL - the URL used when the customer cancels the transaction
     * @param mixed $currency - currency used for payment: GBP, EUR, JPY, CAD, AUD etc.
     * @param mixed $paymentType - type of payment: Authorization, Sale or Order
     */

    public function SetExpressCheckout($paymentAmount,$returnURL, $cancelURL, $currency = 'GBP', $paymentType = 'Authorization'){
        // Set request-specific fields.
        $paymentAmount = urlencode($paymentAmount);
        $currencyID = urlencode($currency);
        $paymentType = urlencode($paymentType);

        $returnURL = urlencode($returnURL);
        $cancelURL = urlencode($cancelURL);

        // Add request-specific fields to the request string.
        $nvpStr = "&Amt=$paymentAmount&ReturnUrl=$returnURL&CANCELURL=$cancelURL&PAYMENTACTION=$paymentType&CURRENCYCODE=$currencyID";

        // Execute the API operation; see the PPHttpPost function above.
        $httpParsedResponseAr = $this->PPHttpPost('SetExpressCheckout', $nvpStr);

        if("SUCCESS" == strtoupper($httpParsedResponseAr["ACK"]) || "SUCCESSWITHWARNING" == strtoupper($httpParsedResponseAr["ACK"])) {
                // Redirect to paypal.com.
                $this->_token = urldecode($httpParsedResponseAr["TOKEN"]);
                $payPalURL = "https://www.paypal.com/webscr&cmd=_express-checkout&token=$this->_token";
                if("sandbox" === $environment || "beta-sandbox" === $environment) {
                        $payPalURL = "https://www.$environment.paypal.com/webscr&cmd=_express-checkout&token=$this->_token";
                }
                header("Location: $payPalURL");
                exit;
        } else  {
                exit('SetExpressCheckout failed: ' . print_r($httpParsedResponseAr, true));
        }
    }

    /**
     * PayPal API request GetExpressCheckoutDetails using NVP (Name Value Pairs)
     */
    public function GetExpressCheckoutDetails(){
        // Obtain the token from PayPal.
        /*if(!array_key_exists('token', $_REQUEST)) {
                exit('Token is not received.');
        }*/

        if(!isset($this->_token))
        {
            exit('Token not received.');
        }

        // Set request-specific fields.
        $this->_token = urlencode(htmlspecialchars($this->_token));

        // Add request-specific fields to the request string.
        $nvpStr = "&TOKEN=$this->_token";

        // Execute the API operation; see the PPHttpPost function above.
        $httpParsedResponseAr = $this->PPHttpPost('GetExpressCheckoutDetails', $nvpStr);

        if("SUCCESS" == strtoupper($httpParsedResponseAr["ACK"]) || "SUCCESSWITHWARNING" == strtoupper($httpParsedResponseAr["ACK"])) {
                // Extract the response details.
                $this->_payerID = $httpParsedResponseAr['PAYERID'];
                $street1 = $httpParsedResponseAr["SHIPTOSTREET"];
                if(array_key_exists("SHIPTOSTREET2", $httpParsedResponseAr)) {
                        $street2 = $httpParsedResponseAr["SHIPTOSTREET2"];
                }
                $city_name = $httpParsedResponseAr["SHIPTOCITY"];
                $state_province = $httpParsedResponseAr["SHIPTOSTATE"];
                $postal_code = $httpParsedResponseAr["SHIPTOZIP"];
                $country_code = $httpParsedResponseAr["SHIPTOCOUNTRYCODE"];

                exit('Get Express Checkout Details Completed Successfully: '.print_r($httpParsedResponseAr, true));
        } else  {
                exit('GetExpressCheckoutDetails failed: ' . print_r($httpParsedResponseAr, true));
        }
    }
    
    /**
     * PayPal API request DoExpressCheckoutPayment using NVP (Name Value Pairs)
     * @param float $paymentAmount - amount being requested to be paid
     * @param mixed $currency - currency used for payment: GBP, EUR, JPY, CAD, AUD etc.
     * @param mixed $paymentType - type of payment: Authorization, Sale or Order
     */
    public function DoExpressCheckoutPayment($paymentAmount, $currency = 'GBP', $paymentType = 'Authorization'){
        // Set request-specific fields.
        $payerID = urlencode($this->_payerID);
        $token = urlencode($this->_token);

        $paymentType = urlencode($paymentType);
        $paymentAmount = urlencode($paymentAmount);
        $currencyID = urlencode($currency);

        // Add request-specific fields to the request string.
        $nvpStr = "&TOKEN=$token&PAYERID=$payerID&PAYMENTACTION=$paymentType&AMT=$paymentAmount&CURRENCYCODE=$currencyID";

        // Execute the API operation; see the PPHttpPost function above.
        $httpParsedResponseAr = $this->PPHttpPost('DoExpressCheckoutPayment', $nvpStr);

        if("SUCCESS" == strtoupper($httpParsedResponseAr["ACK"]) || "SUCCESSWITHWARNING" == strtoupper($httpParsedResponseAr["ACK"])) {
                exit('Express Checkout Payment Completed Successfully: '.print_r($httpParsedResponseAr, true));
        } else  {
                exit('DoExpressCheckoutPayment failed: ' . print_r($httpParsedResponseAr, true));
        }
    }
}

?>
