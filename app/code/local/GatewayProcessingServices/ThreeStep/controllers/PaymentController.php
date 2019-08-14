<?php
/**
 * ThreeStep Payment Controller
 *
 * @category   Local
 * @package    GatewayProcessingServices_ThreeStep
 * @author     GPS
 */
class GatewayProcessingServices_ThreeStep_PaymentController extends Mage_Core_Controller_Front_Action {

    /**
     * Get session model
    
     * @return GatewayProcessingServices_ThreeStep_Model_Session
     */
    protected function _getThreeStepSession()
    {
        return Mage::getSingleton('threestep/session');
    }
    
    /**
     * @return Mage_Checkout_Model_Session
     */
    protected function _getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }
    
    /**
     * Get iframe block instance
     *
     * @return GatewayProcessingServices_ThreeStep_Block_Payment_Iframe
     */
    protected function _getIframeBlock()
    {
        return $this->getLayout()->createBlock('threestep/payment_iframe');
    }
    
    /**
     * Send requests to GPS
     * 
     */
    public function placeAction()
    {
        
        $paymentParam = $this->getRequest()->getParam('payment');
        $controller = $this->getRequest()->getParam('controller');
        if (isset($paymentParam['method'])) {
            $params = Mage::helper('threestep')->getSaveOrderUrlParams($controller);
            $this->_getThreeStepSession()->setQuoteId($this->_getCheckout()->getQuote()->getId());
            $this->_forward(
                    $params['action'],
                    $params['controller'],
                    $params['module'],
                    $this->getRequest()->getParams()
            );
        } else {
            $result = array(
                    'error_messages' => $this->__('Please, choose payment method'),
                    'goto_section'   => 'payment'
            );
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
        }
    }

    /**
     * Response action.
     * 
     * Action for returning from GPS step 2
     */
    public function responseAction()
    {
        
        $result = array();
        
        $controllerActionName = $_GET['controller'];
        
        if ($controllerActionName == 'sales_order_create' || $controllerActionName == 'sales_order_edit') {
            $result['controller_action_name'] = 'admin';
        } else {
            $result['controller_action_name'] = 'onepage';
        }
              
        $data['token'] = $_GET['token-id'];
        $data['order_id'] = $_GET['order_id'];
        $data['orderSendConfirmation'] = $_GET['order_send_flag'];
        $data['key'] = $_GET['key'];
        
        $result['order_id'] = $data['order_id'];

        $paymentMethod = Mage::getModel('threestep/PaymentMethod');
    
        try {
            $paymentMethod->process($data);
            $result['success'] = 1;
        }
        catch (Mage_Core_Exception $e) {
            Mage::logException($e);
            $result['success'] = 0;
            $result['error_msg'] = $e->getMessage();
        }
        catch (Exception $e) {
            Mage::logException($e);
            $result['success'] = 0;
            $result['error_msg'] = $this->__('There was an error processing your order. Please contact us or try again later.');
        }
        
        if (!empty($data['key'])) {
            $result['key'] = $data['key'];
        }

        $params['redirect'] = Mage::helper('threestep')->getRedirectIframeUrl($result);    
        
        $block = $this->_getIframeBlock()->setParams($params);
        $this->getResponse()->setBody($block->toHtml());
    }

    /**
     * Retrieve params and put javascript into iframe
     *
     */
    public function redirectAction()
    {

        $redirectParams = $this->getRequest()->getParams();
        

        $params = array();
        if (!empty($redirectParams['success']) && isset($redirectParams['controller_action_name'])) {
            $this->_getThreeStepSession()->unsetData('quote_id');
            $params['redirect_parent'] = Mage::helper('threestep')->getSuccessOrderUrl($redirectParams);
        }
        if (!empty($redirectParams['error_msg'])) {
            $this->_returnCustomerQuote(true, $redirectParams['error_msg']);
        }

        $block = $this->_getIframeBlock()->setParams(array_merge($params, $redirectParams));
        $this->getResponse()->setBody($block->toHtml());
    }
    
    /**
     * Return customer quote by ajax
     *
     */
    public function returnQuoteAction()
    {
        $this->_returnCustomerQuote();
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode(array('success' => 1)));
    }
    
    /**
     * Return customer quote
     *
     * @param bool $cancelOrder
     * @param string $errorMsg
     */
    protected function _returnCustomerQuote($cancelOrder = false, $errorMsg = '')
    {
        $incrementId = $this->_getThreeStepSession()->getLastOrderIncrementId();

        if ($incrementId &&
                $this->_getThreeStepSession()->isCheckoutOrderIncrementIdExist($incrementId)
        ) {
            /* @var $order Mage_Sales_Model_Order */
            $order = Mage::getModel('sales/order')->loadByIncrementId($incrementId);
            if ($order->getId()) {
                $quote = Mage::getModel('sales/quote')
                ->load($order->getQuoteId());
                if ($quote->getId()) {
                    $quote->setIsActive(1)
                    ->setReservedOrderId(NULL)
                    ->save();
                    $this->_getCheckout()->replaceQuote($quote);
                }
                $this->_getThreeStepSession()->removeCheckoutOrderIncrementId($incrementId);
                $this->_getThreeStepSession()->unsetData('quote_id');
                if ($cancelOrder) {
                    $order->registerCancellation($errorMsg)->save();
                }
            }
        }
    }
    
}
