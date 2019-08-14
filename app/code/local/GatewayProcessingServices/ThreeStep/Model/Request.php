<?php

/**
 * ThreeStep request model.
 *
 * @category   Local
 * @package    GatewayProcessingServices_ThreeStep
 * @author     GPS
 */
class GatewayProcessingServices_ThreeStep_Model_Request extends Varien_Object
{
    /**
     * Set entity data to request
     *
     * @param Mage_Sales_Model_Order $order
     * @param GatewayProcessingServices_ThreeStep_Model_PaymentMethod $paymentMethod
     * @return GatewayProcessingServices_ThreeStep_Model_Request
     */
    public function setDataFromOrder(Mage_Sales_Model_Order $order, GatewayProcessingServices_ThreeStep_Model_PaymentMethod $paymentMethod)
    {
        $payment = $order->getPayment();
        $this->setOrderSendConfirmation($order->getSendConfirmationFlag());
        $this->setOrderId($order->getIncrementId());
        $this->setKey($order->getKey());
        $this->setControllerActionName($order->getControllerActionName());
        $this->setAmount($payment->getBaseAmountAuthorized());
        $this->setCurrency($order->getBaseCurrencyCode());
        $this->setTaxAmount(sprintf('%.2F', $order->getBaseTaxAmount()))
        ->setShippingAmount(sprintf('%.2F', $order->getBaseShippingAmount()));

        $billing = $order->getBillingAddress();
        if (!empty($billing)) {
            $this->setBillingFirstName(strval($billing->getFirstname()))
                ->setBillingLastName(strval($billing->getLastname()))
                ->setBillingCompany(strval($billing->getCompany()))
                ->setBillingAddress1(strval($billing->getStreet(1)))
                ->setBillingAddress2(strval($billing->getStreet(2)))
                ->setBillingCity(strval($billing->getCity()))
                ->setBillingState(strval($billing->getRegion()))
                ->setBillingPostal(strval($billing->getPostcode()))
                ->setBillingCountry(strval($billing->getCountry()))
                ->setBillingPhone(strval($billing->getTelephone()))
                ->setBillingFax(strval($billing->getFax()))
                ->setBillingEmail(strval($order->getCustomerEmail()));
        }

        $shipping = $order->getShippingAddress();
        if (!empty($shipping)) {
            $this->setShippingFirstName(strval($shipping->getFirstname()))
                ->setShippingLastName(strval($shipping->getLastname()))
                ->setShippingCompany(strval($shipping->getCompany()))
                ->setShippingAddress1(strval($shipping->getStreet(1)))
                ->setShippingAddress2(strval($shipping->getStreet(2)))
                ->setShippingCity(strval($shipping->getCity()))
                ->setShippingState(strval($shipping->getRegion()))
                ->setShippingPostal(strval($shipping->getPostcode()))
                ->setShippingCountry(strval($shipping->getCountry()));
        }
        $this->setIpAddress(strval($order->getRemoteIp()));
        $this->setPoNum(strval($payment->getPoNumber()));

        return $this;
    }
}
