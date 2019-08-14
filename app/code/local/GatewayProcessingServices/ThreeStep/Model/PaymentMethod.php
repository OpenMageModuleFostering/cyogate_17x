<?php
/**
 * ThreeStep payment method model.
 *
 * @category   Local
 * @package    GatewayProcessingServices_ThreeStep
 * @author     GPS
 */
class GatewayProcessingServices_ThreeStep_Model_PaymentMethod extends Mage_Payment_Model_Method_Cc
{

	// The following flags determine functionality of the module to be used by frontend and backend
	// All flags can be found in Mage_Payment_Model_Method_Abstract

	protected $_code = 'threestep';
	protected $_isGateway = true;
	protected $_canAuthorize = true;
	protected $_canCapture = true;
	protected $_canCapturePartial = false;
	protected $_canRefund = true;
	protected $_canRefundInvoicePartial = true;
	protected $_canVoid = true;
	protected $_canUseInternal = true;
	protected $_canUseCheckout = true;
	protected $_canUseForMultishipping = false;
	protected $_canSaveCC = false;
	protected $_formBlockType = 'threestep/payment_form';
	protected $_isInitializeNeeded = true;
	protected $_infoBlockType = 'payment/info';

	private $_gatewayURL = 'https://secure.cyogate.net/api/v2/three-step';
	private $_gatewayQueryURL = 'https://secure.cyogate.net/api/query.php';

	public $formURL = 'defaultURL';
	public $token = '';
	private $_APIKey;
	private $_testAPIKey = '2F822Rw39fx762MaV7Yy86jXGTC7sCDy';
	
	public function __construct()
	{
	    $this->_APIKey = $this->getConfigData('api-key');
	}
	
	/**
	 * Do not validate payment form using server methods
	 *
	 * @return  bool
	 */
	public function validate() {
	    return true;
	}    

	/**
	 * Skips the normal authorization process
	 * 
	 */
    public function authorize(Varien_Object $payment, $amount)
    {
        $payment->setAdditionalInformation('payment_type', $this->getConfigData('payment_action'));
    }
    
    /**
     * Send capture request to gateway
     * 
     */
    public function capture(Varien_Object $payment, $amount) {

        $originalType = $payment->getAdditionalInformation('payment_type');
        
        $testMode = $this->getConfigData('test_mode');
        
        if ($originalType == 'authorize_capture') {

            $payment->setAdditionalInformation('payment_type', $this->getConfigData('payment_action'));
        } else {
            $info = $payment->getTransactionId();
            $transId = $info;
            
            $orderId = $payment->getOrder()->getIncrementId();
            
            $xmlRequest = new DOMDocument('1.0','UTF-8');
            $xmlRequest->formatOutput = true;
            $xmlCapture = $xmlRequest->createElement('capture');
            
            if ($testMode) {
                $this->appendXmlNode($xmlCapture,'api-key',$this->_testAPIKey);                
            } else {
                $this->appendXmlNode($xmlCapture,'api-key',$this->_APIKey);               
            }
            $this->appendXmlNode($xmlCapture,'transaction-id',$transId);
            $this->appendXmlNode($xmlCapture,'amount',$amount);
            $this->appendXmlNode($xmlCapture,'order-id',$orderId);
            $xmlRequest->appendChild($xmlCapture);
            $data = $this->sendXMLviaCurl($xmlRequest, $this->_gatewayURL);
            
            $gwResponse = @new SimpleXMLElement((string)$data);
            
            if ((string)$gwResponse->result == 1) {
                $payment->setTransactionId((string)$gwResponse->{('transaction-id')});
                $payment->setAdditionalInformation('transaction-id',(string)$gwResponse->{('transaction-id')});
                $payment->setIsTransactionClosed(0);
                return $this;
            } else {
                Mage::throwException('Transaction Declined: ' . (string)$gwResponse->{('result-text')});
            }
        }    
    }
    
    /**
     * Refund the amount
     *
     * @param Varien_Object $payment
     * @param decimal $amount
     * @return GatewayProcessingServices_ThreeStep_Model_PaymentMethod
     * @throws Mage_Core_Exception
     */
    public function refund(Varien_Object $payment, $amount) {
        
        $info = $payment->getAdditionalInformation();
        $transId = $info['transaction-id'];
        
        $testMode = $this->getConfigData('test_mode');

        $xmlRequest = new DOMDocument('1.0','UTF-8');
        $xmlRequest->formatOutput = true;
        $xmlRefund = $xmlRequest->createElement('refund');
        
        if ($testMode) {
            $this->appendXmlNode($xmlRefund,'api-key',$this->_testAPIKey);
        } else {
            $this->appendXmlNode($xmlRefund,'api-key',$this->_APIKey);            
        }
        $this->appendXmlNode($xmlRefund,'transaction-id',$transId);
        $this->appendXmlNode($xmlRefund,'amount',$amount);
        $xmlRequest->appendChild($xmlRefund);
        $data = $this->sendXMLviaCurl($xmlRequest, $this->_gatewayURL);
        
        $gwResponse = @new SimpleXMLElement((string)$data);
        
        if ((string)$gwResponse->result == 1) {
            $payment->setData('is_transaction_closed',1);
            return $this;
        } else {
            Mage::throwException('Refund Failed: ' . (string)$gwResponse->{('result-text')});
        }
    }
    
    /**
     * Void the payment through gateway
     *
     * @param Varien_Object $payment
     * @return GatewayProcessingServices_ThreeStep_Model_PaymentMethod
     * @throws Mage_Core_Exception
     */
    public function void(Varien_Object $payment) {
        
        $info = $payment->getAdditionalInformation();
        $transId = $info['transaction-id'];
        
        $testMode = $this->getConfigData('test_mode');
        
        $xmlRequest = new DOMDocument('1.0','UTF-8');
        $xmlRequest->formatOutput = true;
        $xmlVoid = $xmlRequest->createElement('void');
        if ($testMode) {
            $this->appendXmlNode($xmlVoid,'api-key',$this->_testAPIKey);
        } else {
            $this->appendXmlNode($xmlVoid,'api-key',$this->_APIKey);            
        }
        $this->appendXmlNode($xmlVoid,'transaction-id',$transId);
        $xmlRequest->appendChild($xmlVoid);
        $data = $this->sendXMLviaCurl($xmlRequest, $this->_gatewayURL);
        
        $gwResponse = @new SimpleXMLElement((string)$data);

        if ((string)$gwResponse->result == 1) {
            $payment->setData('is_transaction_closed', 1);
            $payment->setData('should_close_parent_transaction', 1);
            return $this;
        } else {
            Mage::throwException('Void Failed: ' . (string)$gwResponse->{('result-text')});
        }
    }
    
    public function cancel(Varien_Object $payment)
    {
        return $this->void($payment);
    }
    
    /**
     * Send Step 1 to GPS
     * 
     * @param Array $infoArr
     * @param unknown_type $orderData
     * @return unknown
     */
    public function doStepOne($infoArr) {
        
        $testMode = $this->getConfigData('test_mode');
        $type = $this->getConfigData('payment_action');
        
        if ($type == 'authorize') {
            $type = 'auth';
        } else {
            $type = 'sale';
        }

        $xmlRequest = new DOMDocument('1.0','UTF-8');
        $xmlRequest->formatOutput = true;
                
        $xmlTranType = $xmlRequest->createElement($type);   
        
        // Api Key
        if ($testMode) {
            $this->appendXmlNode($xmlTranType,'api-key',$this->_testAPIKey);
        } else {
            $this->appendXmlNode($xmlTranType, 'api-key', $this->_APIKey);
        }

        // Redirect URL
        $baseUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
        $redirectUrl = $this->getRelayUrl();
        $urlVars = '?order_id=' . $infoArr['order_id'] . '&order_send_flag=' . $infoArr['order_send_confirmation'] . '&key='  . $infoArr['key'] . '&controller=' . $infoArr['controller_action_name'];   
        $redirectUrl .= htmlentities($urlVars); 
        $this->appendXmlNode($xmlTranType, 'redirect-url', $redirectUrl);
        
        // Transaction Information
        $this->appendXmlNode($xmlTranType, 'amount', $infoArr['amount']);        
        $this->appendXmlNode($xmlTranType, 'currency', $infoArr['currency']);
        $this->appendXmlNode($xmlTranType, 'tax-amount', $infoArr['tax_amount']);
        $this->appendXmlNode($xmlTranType, 'shipping-amount', $infoArr['shipping_amount']);
        $this->appendXmlNode($xmlTranType, 'order-id', $infoArr['order_id']);
        $this->appendXmlNode($xmlTranType, 'ip-address', $infoArr['ip_address']);
        
        // Billing Information
        $xmlBillingAddress = $xmlRequest->createElement('billing');
        $this->appendXmlNode($xmlBillingAddress, 'first-name', $infoArr['billing_first_name']);
        $this->appendXmlNode($xmlBillingAddress, 'last-name', $infoArr['billing_last_name']);
        $this->appendXmlNode($xmlBillingAddress, 'address1', $infoArr['billing_address1']);
        $this->appendXmlNode($xmlBillingAddress, 'address2', $infoArr['billing_address2']);
        $this->appendXmlNode($xmlBillingAddress, 'city', $infoArr['billing_city']);
        $this->appendXmlNode($xmlBillingAddress, 'state', $infoArr['billing_state']);
        $this->appendXmlNode($xmlBillingAddress, 'postal', $infoArr['billing_postal']);
        $this->appendXmlNode($xmlBillingAddress, 'country', $infoArr['billing_country']);
        $this->appendXmlNode($xmlBillingAddress, 'phone', $infoArr['billing_phone']);
        $this->appendXmlNode($xmlBillingAddress, 'email', $infoArr['billing_email']);
        $this->appendXmlNode($xmlBillingAddress, 'company', $infoArr['billing_company']);
        $this->appendXmlNode($xmlBillingAddress, 'fax', $infoArr['billing_fax']);
        $xmlTranType->appendChild($xmlBillingAddress);
         
        // Shipping Information
        $xmlShippingAddress = $xmlRequest->createElement('shipping');
        $this->appendXmlNode($xmlShippingAddress, 'first-name', $infoArr['shipping_first_name']);
        $this->appendXmlNode($xmlShippingAddress, 'last-name', $infoArr['shipping_last_name']);
        $this->appendXmlNode($xmlShippingAddress, 'address1', $infoArr['shipping_address1']);
        $this->appendXmlNode($xmlShippingAddress, 'address2', $infoArr['shipping_address2']);
        $this->appendXmlNode($xmlShippingAddress, 'city', $infoArr['shipping_city']);
        $this->appendXmlNode($xmlShippingAddress, 'state', $infoArr['shipping_state']);
        $this->appendXmlNode($xmlShippingAddress, 'postal', $infoArr['shipping_postal']);
        $this->appendXmlNode($xmlShippingAddress, 'country', $infoArr['shipping_country']);
        $this->appendXmlNode($xmlShippingAddress, 'company', $infoArr['shipping_company']);
        $xmlTranType->appendChild($xmlShippingAddress);

        $xmlRequest->appendChild($xmlTranType);
        
        $data = $this->sendXMLviaCurl($xmlRequest,$this->_gatewayURL);
        
        $gwResponse = @new SimpleXMLElement($data);
        
        if ((string)$gwResponse->result == 1) {
            $payment = Mage::getSingleton('checkout/session')->getQuote()->getPayment();
            $payment->setAdditionalInformation('transaction-id',(string)$gwResponse->{('transaction-id')});
            $formURL = $gwResponse->{'form-url'};
        } else {
            Mage::throwException('Error, received ' . $data);
        }        
        return $formURL;
    }

    /**
     *  XML helper to attach child nodes
     */
    public function appendXmlNode($parentNode,$name, $value) {
        $tempNode = new DOMElement($name,$value);
        $parentNode->appendChild($tempNode);
    }
    
    /**
     * Submit XML to GPS
     * 
     */
    public function sendXMLviaCurl($xmlRequest,$gatewayURL) {    
    
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $gatewayURL);
    
        $headers = array();
        $headers[] = "Content-type: text/xml";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $xmlString = $xmlRequest->saveXML();
        curl_setopt($ch, CURLOPT_FAILONERROR, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_PORT, 443);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlString);
    
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    
        if (!($data = curl_exec($ch))) {
            Mage::throwException(" CURL ERROR :" . curl_error($ch));
        }
        curl_close($ch);
    
        return $data;
    }
    
    /**
     * Generate request object and fill its fields from Quote or Order object
     *
     * @param Mage_Core_Model_Abstract $entity Quote or order object.
     * @return GatewayProcessingServices_ThreeStep_Model_Request
     */
    public function generateRequestFromOrder(Mage_Sales_Model_Order $order)
    {
        $request = $this->_getRequestModel();
        $request->setDataFromOrder($order, $this);
    
        $this->_debug(array('request' => $request->getData()));

        return $request;
    }
    
    /**
     * Return request model for form data building
     *
     * @return GatewayProcessingServices_ThreeStep_Model_Request
     */
    protected function _getRequestModel()
    {
        return Mage::getModel('threestep/request');
    }
    
    /**
     * Return URL on which GPS will return payment result data in hidden request.
     *
     * @param int $storeId
     * @return string
     */
    public function getRelayUrl($storeId = null)
    {
        if ($storeId == null && $this->getStore()) {
            $storeId = $this->getStore();
        }
        return Mage::app()->getStore($storeId)
        ->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK, Mage::app()->getStore()->isCurrentlySecure()).
        'threestep/payment/response';
    }
    
    /**
     * Operate with order using data from $_GET which came from GPS by Redirect URL.
     *
     * @param array $responseData data from GPS from $_GET
     * @throws Mage_Core_Exception in case of validation error or order creation error
     */
    public function process($info)
    {
        $orderIncrementId = $info['order_id'];
        $token = $info['token'];
        
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
        $payment = $order->getPayment();
        
        $xmlRequest = new DOMDocument('1.0','UTF-8');
        $xmlRequest->formatOutput = true;
        $xmlCompleteTransaction = $xmlRequest->createElement('complete-action');
        
        $testMode = $this->getConfigData('test_mode');
        
        if ($testMode) {
            $this->appendXmlNode($xmlCompleteTransaction,'api-key',$this->_testAPIKey);
        } else {
            $this->appendXmlNode($xmlCompleteTransaction,'api-key',$this->_APIKey);
        }
        
        $this->appendXmlNode($xmlCompleteTransaction,'token-id',$token);
        $xmlRequest->appendChild($xmlCompleteTransaction);
        $data = $this->sendXmlviaCurl($xmlRequest,$this->_gatewayURL);
        
        $gwResponse = @new SimpleXMLElement((string)$data);
        if ((string)$gwResponse->result == 1) {
            $payment->setTransactionId((string)$gwResponse->{('transaction-id')});
            $payment->setIsTransactionClosed(0);
            $payment->setParentTransactionId(null);
            $payment->setAdditionalInformation('transaction-id',(string)$gwResponse->{('transaction-id')});
            
            // Create the transaction within shopping cart
            if ($this->getConfigData('payment_action') == 'authorize') {
                $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH);                
            } else {
                $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE);
            }
            
            $message = Mage::helper('threestep')->__(
                    'Amount of %s approved by payment gateway. Transaction ID: "%s".',
                    $order->getBaseCurrency()->formatTxt($payment->getBaseAmountAuthorized()),
                    (string)$gwResponse->{('transaction-id')}
            );
            
            $orderState = Mage_Sales_Model_Order::STATE_PROCESSING;
            $orderStatus = $this->getConfigData('order_status');
            if (!$orderStatus || $order->getIsVirtual()) {
                $orderStatus = $order->getConfig()->getStateDefaultStatus($orderState);
            }
            
            $order->setState($orderState, $orderStatus ? $orderStatus : true, $message, false)
            ->save();
            
            if ($payment->getAdditionalInformation('payment_type') == self::ACTION_AUTHORIZE_CAPTURE) {
                try {
                    $payment->setTransactionId(null)
                    ->setParentTransactionId((string)$gwResponse->{('transaction-id')})
                    ->capture(null);
            
                    // set status from config for AUTH_AND_CAPTURE orders.
                    if ($order->getState() == Mage_Sales_Model_Order::STATE_PROCESSING) {
                        $orderStatus = $this->getConfigData('order_status');
                        if (!$orderStatus || $order->getIsVirtual()) {
                            $orderStatus = $order->getConfig()
                            ->getStateDefaultStatus(Mage_Sales_Model_Order::STATE_PROCESSING);
                        }
                        if ($orderStatus) {
                            $order->setStatus($orderStatus);
                        }
                    }
            
                    $order->save();
                } catch (Exception $e) {
                    Mage::logException($e);
                }
            }
            // Send Order Confirmations
            try {
                if ($info['orderSendConfirmation'] === '' || $info['orderSendConfirmation'] == '1') {
                    $order->sendNewOrderEmail();
                }
            
                Mage::getModel('sales/quote')
                ->load($order->getQuoteId())
                ->setIsActive(false)
                ->save();
            } catch (Exception $e) {
            } // do not cancel order if we couldn't send email
        } else {
            Mage::throwException('Transaction Declined: ' . (string)$gwResponse->{('result-text')});
        }     
    }
    
    /**
     * Instantiate state and set it to state object
     *
     * @param string $paymentAction
     * @param Varien_Object
     */
    public function initialize($paymentAction, $stateObject)
    {
        switch ($paymentAction) {
            case self::ACTION_AUTHORIZE:
            case self::ACTION_AUTHORIZE_CAPTURE:
                $payment = $this->getInfoInstance();
                $order = $payment->getOrder();
                $order->setCanSendNewEmailFlag(false);
                $payment->authorize(true, $order->getBaseTotalDue());
                $payment->setAmountAuthorized($order->getTotalDue());
    
                $order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, 'pending_payment', '', false);
    
                $stateObject->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
                $stateObject->setStatus('pending_payment');
                $stateObject->setIsNotified(false);
                break;
            default:
                break;
        }
    }
    
    	
}

?>
