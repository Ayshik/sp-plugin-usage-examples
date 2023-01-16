<?php

require_once 'configaration.php';
require_once 'shurjoPay_validation.php';

/**
 *
 * PHP Plug-in service to provide shurjoPay get way services.
 *
 * @author Md Wali Mosnad Ayshik
 * @since 2022-10-15
 */


/**-Funtions Introduction
There are three mendatory public functions which will need to access shurjoPay.
1.authenticate()-> makes client authenticate
2.makePayment()-> generates payment url for checkout
3.verifyPayment()-> makes payment verification.
prepareCurlRequest(),prepareTransactionPayload() & logInfo() used for service function.
*/
class ShurjopayPlugin
{
	/**-Main class-ShurjopayPlugin
	There are private veriables which will come from configaration.php
	1.shurjopay_api->This is Engine URL for shurjoPay.
	2.return_url-> This URL will be redirect after completing a payment.
	3.prefix-> This is merchant prefix.
	4.SP_USER-> This is shurjoPay merchant username.
	5.SP_PASS-> This is shurjopay merchant password.
	6.log_location-> Here a merchant can define their shurjoPay log location.
	*/
	private $shurjopay_api = SHURJOPAY_API;
	private $return_url = SP_CALLBACK;
	private $prefix = PREFIX;
	private $SP_USER = SP_USERNAME;
	private $SP_PASS = SP_PASSWORD;
	private $log_location = LOG_LOCATION;

	public function __construct()
	{ /**-Function-function __construct
	 There are veriables which will come from configaration.php
	 1.domainName->This is Engine URL for shurjoPay.
	 2.auth_token_url-> This is concatenation of Engine URL and auth_token_url endpoint.
	 3.checkout->This is concatenation of Engine URL and checkout endpoint.
	 4.verification->This is concatenation of Engine URL and verification_url endpoint.
	 */
		$this->domainName = $this->shurjopay_api;
		$this->auth_token_url = $this->domainName . "api/get_token";
		$this->checkout = $this->domainName . "api/secret-pay";
		$this->verification_url = $this->domainName . "api/verification";
	}

	public function authenticate()
	{
		/**-Function-authenticate
		This function is used to autheticate user and genatere token.
		***Process-
		1.Make header array.
		2.Make postFields array.
		3.Send header,postFields,auth_token_url in prepareCurlRequest function perameter.
		Now this authenticate function will return token.
		*/

		$header = array(
			''
		);
		//Content-Type: application/json
		#Authinticate
		$postFields = array(
			'username' => $this->SP_USER,
			'password' => $this->SP_PASS,
		);
		if (empty($this->auth_token_url) || empty($postFields))
			exit("Token is Emply Kindly check your Username & Password");

		#checking auth_token_url or postFields are empty or not.
		try {
			$response = $this->prepareCurlRequest($this->auth_token_url, 'POST', $postFields, $header);
			#send all requried data to prepareCurlRequest
			$this->logInfo("ShurjoPay has been authenticated successfully !" . "\n" . "Authenticate Response:" . json_encode($response));

			# Got object as response from prepareCurlRequest in $response variable
			# and returning that object from here
			return $response;

		} catch (Exception $e) {
			$this->logInfo("Invalid User name or Password due to shurjoPay authentication.");
			return $e->getMessage();
		}

	}

	public function makePayment($payload)
	{
		/**-Function-makePayment
		This function is used to generate shurjoPay checkout_url.
		***Process-
		1.Make trxn_data array(All the payload data array will come from prepareTransactionPayload function).
		2.Make header array.
		3.Send header,trxn_data,checkout in prepareCurlRequest function perameter.
		Now this makePayment function will return and redirect to shurjoPay checkout_url.
		*/
		if (checkInternetConnection()) {
			$trxn_data = $this->prepareTransactionPayload($payload);
			#All data from payload in trxn_data as array.
			if (Validation($trxn_data)) {
				$header = array(
					'Content-Type:application/json',
					'Authorization: Bearer ' . json_decode($trxn_data)->token
				);

				try {
					$response = $this->prepareCurlRequest($this->checkout, 'POST', $trxn_data, $header);
					#send all requried data to prepareCurlRequest
					if (!empty($response->checkout_url)) {
						$this->logInfo("Payment URL has been generated by shurjoPay!");
						return header('Location: ' . $response->checkout_url);
						#redirecting to checkout_url of shurjoPay.
					} else {
						return $response; //object
					}
				} catch (Exception $e) {
					return $e->getMessage();
				}

			}
		} else {
			print_r("Your have no internet connection! Please check your internet connection.");
			exit;
		}
	}
	public function verifyPayment($shurjopay_order_id)
	{
		/**-Function-verifyPayment
		This function is used to verify payment.
		***Process-
		1.Make header array(token).
		2.Make postFields array (order_id).
		3.Send header,postFields,verification_url in prepareCurlRequest function perameter.
		Now this verifyPayment function will return payment status.
		*/
		$token = json_decode(json_encode($this->authenticate()), true);
		#Call authenticate function to get token.
		$header = array(
			'Content-Type:application/json',
			'Authorization: Bearer ' . $token['token']
		);
		$postFields = json_encode(
			array(
				'order_id' => $shurjopay_order_id
			)
		);
		try {
			$response = $this->prepareCurlRequest($this->verification_url, 'POST', $postFields, $header);
			$this->logInfo("Payment verification is done successfully!");
			return $response; //object
		} catch (Exception $e) {
			return $e->getMessage();
		}
	}
	public function prepareTransactionPayload($payload)
	{

		/**-Function-prepareTransactionPayload
		This function is used to create payload body.
		This prepareTransactionPayload function will return payload data.
		*/
		$token = json_decode(json_encode($this->authenticate()), true);
		$createpaybody = json_encode(
			array(
				// store information
				'token' => $token['token'],
				'store_id' => $token['store_id'],
				'prefix' => $this->prefix,
				'currency' => $payload['currency'],
				'return_url' => $this->return_url,
				'cancel_url' => $this->return_url,
				'amount' => $payload['amount'],
				// Order information
				'order_id' => $this->prefix . uniqid(),
				'discsount_amount' => $payload['discsount_amount'],
				'disc_percent' => $payload['disc_percent'],
				// Customer information
				'client_ip' => $_SERVER['REMOTE_ADDR'] ?: ($_SERVER['HTTP_X_FORWARDED_FOR'] ?: $_SERVER['HTTP_CLIENT_IP']),
				'customer_name' => $payload['customer_name'],
				'customer_phone' => $payload['customer_phone'],
				'customer_email' => $payload['email'],
				'customer_address' => $payload['customer_address'],
				'customer_city' => $payload['customer_city'],
				'customer_state' => $payload['customer_state'],
				'customer_postcode' => $payload['customer_postcode'],
				'customer_country' => $payload['customer_country'],
				'value1' => $payload['value1'],
				'value2' => $payload['value2'],
				'value3' => $payload['value3'],
				'value4' => $payload['value4']
			)
		);

		return $createpaybody;

	}
	public function prepareCurlRequest($url, $method, $payload_data, $header)
	{
		/**-Function-prepareCurlRequest
		This function is used to prepare curl body.
		This prepareCurlRequest function will return curl response.
		*/
		try {
			$curl = curl_init();

			curl_setopt_array($curl, array(
			CURLOPT_URL => $url,
			CURLOPT_HTTPHEADER => $header,
			CURLOPT_POST => 1,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POSTFIELDS => $payload_data,
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
				#If HTTPS not working in local project
				#Please Uncomment |CURLOPT_SSL_VERIFYPEER|
				#NOTE-Please Comment Again before going Live
				//CURLOPT_SSL_VERIFYPEER => 0,
			)
			);
		} catch (Exception $e) {
			logInfo("ShurjoPay has been failed for preparing Curl request !");
			return $e->getMessage();

		} finally {
			$response = curl_exec($curl);
			//print_r($response);exit();
			curl_close($curl);

			# here , returning object instead of Json to our core three method
			return (json_decode($response));

		}
	}

	public function logInfo($log_msg)
	{
		/**-Function-logInfo
		This function is used to create log template.
		*/
		$this->sp_log("************** Time'" . gmdate('Y-m-d H:i:s') . "'**********");
		$this->sp_log($log_msg);
	}
	public function sp_log($log_msg)
	{
		/**-Function-sp_log
		This function is used to create log and make derectory if not exist.
		*/
		$log_location = $this->log_location;
		if (!file_exists($log_location . 'shurjopay-plugin-log'))
			mkdir($log_location . 'shurjopay-plugin-log', 0777, true);
		$log_file_data = $log_location . 'shurjopay-plugin-log' . '/shurjoPay-plugin' . '.log';
		file_put_contents($log_file_data, $log_msg . "\n", FILE_APPEND);
	}



}
