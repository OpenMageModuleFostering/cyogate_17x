<?php 
/**
 * ThreeStep form block
 *
 * @category   Local
 * @package    GatewayProcessingServices_ThreeStep
 * @author     GPS
 */
class GatewayProcessingServices_ThreeStep_Block_Payment_Form extends Mage_Payment_Block_Form_Cc
{
    /**
     * Internal constructor
     * Set info template for payment step
     *
     */
	protected function _construct() {

		parent::_construct();
		$this->setTemplate('threestep/payment/info.phtml');
        $this->setTemplate('../../../default/default/template/threestep/payment/info.phtml');
}

	/**
	 * Render block HTML
	 * If method is not threestep - nothing to return
	 *
	 * @return string
	 */
    protected function _toHtml()
    {
        if ($this->getMethod()->getCode() != Mage::getSingleton('threestep/PaymentMethod')->getCode()) {
            return null;
        }

        return parent::_toHtml();
    }

    /**
     * Set method info
     *
     * @return GatewayProcessingServices_ThreeStep_Block_Payment_Form
     */
    public function setMethodInfo()
    {
        $payment = Mage::getSingleton('checkout/type_onepage')
        ->getQuote()
        ->getPayment();
        $this->setMethod($payment->getMethodInstance());

        return $this;
    }

    /**
     * Get type of request
     *
     * @return bool
     */
    public function isAjaxRequest()
    {
        return $this->getAction()
        ->getRequest()
        ->getParam('isAjax');
    }
}


?>
