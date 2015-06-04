<?php

namespace OroCRM\Bundle\MagentoBundle\ImportExport\Strategy;

use Doctrine\Common\Util\ClassUtils;

use OroCRM\Bundle\MagentoBundle\Entity\MagentoSoapTransport;
use OroCRM\Bundle\MagentoBundle\Entity\Order;
use OroCRM\Bundle\MagentoBundle\Provider\Reader\ContextCartReader;
use OroCRM\Bundle\MagentoBundle\Provider\Reader\ContextCustomerReader;

class OrderWithExistingCustomerStrategy extends OrderStrategy
{
    const CONTEXT_ORDER_POST_PROCESS = 'postProcessOrders';

    /**
     * @param Order $importingOrder
     *
     * {@inheritdoc}
     */
    public function process($importingOrder)
    {
        if (!$this->isProcessingAllowed($importingOrder)) {
            $this->appendDataToContext(self::CONTEXT_ORDER_POST_PROCESS, $this->context->getValue('itemData'));

            return null;
        }

        return parent::process($importingOrder);
    }

    /**
     * @param Order $order
     * @return bool
     */
    protected function isProcessingAllowed(Order $order)
    {
        $isProcessingAllowed = true;
        $customer = $this->findExistingEntity($order->getCustomer());
        $customerOriginId = $order->getCustomer()->getOriginId();
        if (!$customer && $customerOriginId) {
            $this->appendDataToContext(ContextCustomerReader::CONTEXT_POST_PROCESS_CUSTOMERS, $customerOriginId);

            $isProcessingAllowed = false;
        }

        // Do not try to load cart if bridge does not installed
        /** @var MagentoSoapTransport $transport */
        $channel = $this->databaseHelper->findOneByIdentity($order->getChannel());
        $transport = $channel->getTransport();
        if ($transport->getIsExtensionInstalled()) {
            $cart = $this->findExistingEntity($order->getCart());
            $cartOriginId = $order->getCart()->getOriginId();
            if (!$cart && $cartOriginId) {
                $this->appendDataToContext(ContextCartReader::CONTEXT_POST_PROCESS_CARTS, $cartOriginId);

                $isProcessingAllowed = false;
            }
        }

        if ($isProcessingAllowed && $order->getIsGuest()) {
            $entityName = ClassUtils::getClass($order->getCustomer());
            $customer = $this->databaseHelper->findOneBy(
                $entityName,
                [
                    'email' => $order->getCustomerEmail(),
                    'guest' => true
                ]
            );
            if (!$customer) {
                $customer = $order->getCustomer();
            }
            $customer->setEmail($order->getCustomerEmail())
                ->setGuest(true)
                ->setChannel($channel)
                ->setOrganization($channel->getOrganization());
            $this->databaseHelper->persist($customer);
            $order->setCustomer($customer);
        }

        return $isProcessingAllowed;
    }
}
