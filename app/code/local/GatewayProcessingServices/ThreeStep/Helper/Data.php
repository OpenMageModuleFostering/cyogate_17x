<?php


/**
 * ThreeStep Data Helper
 *
 * @category   Local
 * @package    GatewayProcessingServices_ThreeStep
 * @author     GPS
 */
class GatewayProcessingServices_ThreeStep_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Return URL for admin area
     *
     * @param string $route
     * @param array $params
     * @return string
     */
    public function getAdminUrl($route, $params) {
        return Mage::getModel('adminhtml/url')->getUrl($route, $params);
    }
    
    protected function _getUrl($route,$params = array()) {
        $params['_type'] = Mage_Core_Model_Store::URL_TYPE_LINK;
        if (isset($params['is_secure'])) {
            $params['_secure'] = (bool)$params['is_secure'];
        } elseif (Mage::app()->getStore()->isCurrentlySecure()) {
            $params['_secure'] = true;
        }
        
        return parent::_getUrl($route, $params);
    }


 
    /**
     * Retrieve place order url on front
     *
     * @return  string
     */
    public function getPlaceOrderFrontUrl()
    {
        return $this->_getUrl('threestep/payment/place');
    }
    
    /**
     * Get controller name
     *
     * @return string
     */
    public function getControllerName()
    {
        return Mage::app()->getFrontController()->getRequest()->getControllerName();
    }
    
    /**
     * Retrieve save order url params
     *
     * @param string $controller
     * @return array
     */
    public function getSaveOrderUrlParams($controller)
    {
        $route = array();
        switch ($controller) {
            case 'onepage':
                $route['action'] = 'saveOrder';
                $route['controller'] = 'onepage';
                $route['module'] = 'checkout';
                break;

            case 'sales_order_create':
            case 'sales_order_edit':
                $route['action'] = 'save';
                $route['controller'] = 'sales_order_create';
                $route['module'] = 'admin';
                break;

            default:
                break;
        }

        return $route;
    }
    
    /**
     * Retrieve redirect iframe url
     *
     * @param array params
     * @return string
     */
    public function getRedirectIframeUrl($params)
    {
        
        switch ($params['controller_action_name']) {
            case 'onepage':
                $route = 'threestep/payment/redirect';
                break;

            case 'admin':
                $route = 'adminhtml/payment/redirect';
                break;
    
            default:
                $route = 'threestep/payment/redirect';
                break;
        }
        return $this->_getUrl($route,$params);
        
    }
    
    /**
     * Retrieve place order url
     *
     * @param array params
     * @return  string
     */
    public function getSuccessOrderUrl($params)
    {  
        $param = array();
        switch ($params['controller_action_name']) {
            case 'onepage':
                $route = 'checkout/onepage/success';
                break;

            case 'admin':
                $route = 'adminhtml/sales_order/view';
                $order = Mage::getModel('sales/order')->loadByIncrementId($params['order_id']);
                $param['order_id'] = $order->getId();
                return $this->getAdminUrl($route, $param);
        
            default :
                $route = 'checkout/onepage/success';
                break;
        }
        
        return $this->_getUrl($route, $param);
    }
    
    /**
     * Retrieve place order url in admin
     *
     * @return  string
     */
    public function getPlaceOrderAdminUrl()
    {
        return $this->getAdminUrl('*/payment/place', array());
    }
    
    
    /**
     * Update all child and parent order's edit increment numbers.
     * Needed for Admin area.
     *
     * @param Mage_Sales_Model_Order $order
     */
    public function updateOrderEditIncrements(Mage_Sales_Model_Order $order)
    {
        if ($order->getId() && $order->getOriginalIncrementId()) {
            $collection = $order->getCollection();
            $quotedIncrId = $collection->getConnection()->quote($order->getOriginalIncrementId());
            $collection->getSelect()->where(
                    "original_increment_id = {$quotedIncrId} OR increment_id = {$quotedIncrId}"
                    );
    
            foreach ($collection as $orderToUpdate) {
                $orderToUpdate->setEditIncrement($order->getEditIncrement());
                $orderToUpdate->save();
            }
        }
    }
}

?>
