<?php
/**
 * ThreeStep payment observer
 *
 * @category   Local
 * @package    GatewayProcessingServices_ThreeStep
 * @author     GPS
 */
class GatewayProcessingServices_ThreeStep_Model_Observer
{
    /**
     * Save order into registry to use it in the overloaded controller.
     *
     * @param Varien_Event_Observer $observer
     * @return GatewayProcessingServices_ThreeStep_Model_Observer
     */
    public function saveOrderAfterSubmit(Varien_Event_Observer $observer)
    {
        /* @var $order Mage_Sales_Model_Order */
        $order = $observer->getEvent()->getData('order');
        Mage::register('threestep_order', $order, true);

        return $this;
    }

    /**
     * Set data for response of frontend saveOrder action
     *
     * @param Varien_Event_Observer $observer
     * @return GatewayProcessingServices_ThreeStep_Model_Observer
     */
    public function addAdditionalFieldsToResponseFrontend(Varien_Event_Observer $observer)
    {
        $order = $observer->getEvent()->getData('order');
        $order = Mage::registry('threestep_order');

        if ($order && $order->getId()) {
            
            $payment = $order->getPayment();
            if ($payment && $payment->getMethod() == Mage::getModel('threestep/PaymentMethod')->getCode()) {

                $controller = $observer->getEvent()->getData('controller_action');
                $result = Mage::helper('core')->jsonDecode(
                    $controller->getResponse()->getBody('default'),
                    Zend_Json::TYPE_ARRAY
                );

                if (empty($result['error'])) {
                    
                    $order->setControllerActionName($controller->getRequest()->getControllerName());
                    
                    $payment = $order->getPayment();
                    //if success, then set order to session and add new fields
                    $session = Mage::getSingleton('threestep/session');
                    $session->addCheckoutOrderIncrementId($order->getIncrementId());
                    $session->setLastOrderIncrementId($order->getIncrementId());                    
                    
                    $requestToPaygate = $payment->getMethodInstance()->generateRequestFromOrder($order);
                    $requestToPaygate->setIsSecure((string)Mage::app()->getStore()->isCurrentlySecure());
                    
                    $threestep = Mage::getModel('threestep/paymentmethod');

                    // Submit Step one
                    $formUrl = $threestep->doStepOne($requestToPaygate->getData());
                    $result['formUrl'] = $formUrl;

                    $result['threestep'] = array('fields' => $requestToPaygate->getData());

                    $controller->getResponse()->clearHeader('Location');
                    $controller->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
                }
            }
        }

        return $this;
    }

    /**
     * Update all edit increments for all orders if module is enabled.
     * Needed for correct work of edit orders in Admin area.
     *
     * @param Varien_Event_Observer $observer
     * @return GatewayProcessingServices_ThreeStep_Model_Observer
     */
    public function updateAllEditIncrements(Varien_Event_Observer $observer)
    {
         /* @var $order Mage_Sales_Model_Order */
        $order = $observer->getEvent()->getData('order');
        Mage::helper('threestep')->updateOrderEditIncrements($order);

        return $this;
    }
}
