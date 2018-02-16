<?php

namespace Oro\Bundle\SalesBundle\Form\Handler;

use Doctrine\ORM\EntityManagerInterface;

use Oro\Bundle\SalesBundle\Entity\B2bCustomer;
use Oro\Bundle\SalesBundle\Entity\B2bCustomerEmail;
use Oro\Bundle\SalesBundle\Validator\B2bCustomerEmailDeleteValidator;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use Oro\Bundle\SoapBundle\Entity\Manager\ApiEntityManager;

class B2bCustomerEmailHandler
{
    /** @var FormInterface */
    protected $form;

    /** @var RequestStack */
    protected $requestStack;

    /** @var EntityManagerInterface */
    protected $manager;

    /** @var  B2bCustomerEmailDeleteValidator */
    protected $b2bCustomerEmailDeleteValidator;

    /** @var AuthorizationCheckerInterface */
    protected $authorizationChecker;

    /**
     * @param FormInterface $form
     * @param RequestStack $requestStack
     * @param EntityManagerInterface $manager
     * @param B2bCustomerEmailDeleteValidator $b2bCustomerEmailDeleteValidator
     * @param AuthorizationCheckerInterface $authorizationChecker
     */
    public function __construct(
        FormInterface $form,
        RequestStack $requestStack,
        EntityManagerInterface $manager,
        B2bCustomerEmailDeleteValidator $b2bCustomerEmailDeleteValidator,
        AuthorizationCheckerInterface $authorizationChecker
    ) {
        $this->form    = $form;
        $this->requestStack = $requestStack;
        $this->manager = $manager;
        $this->b2bCustomerEmailDeleteValidator = $b2bCustomerEmailDeleteValidator;
        $this->authorizationChecker = $authorizationChecker;
    }

    /**
     * Process form
     *
     * @param B2bCustomerEmail $entity
     *
     * @return bool True on successful processing, false otherwise
     *
     * @throws AccessDeniedException
     */
    public function process(B2bCustomerEmail $entity)
    {
        $this->form->setData($entity);

        $request = $this->requestStack->getCurrentRequest();
        $submitData = [
            'email' => $request->request->get('email'),
            'primary' => $request->request->get('primary')
        ];

        if (in_array($request->getMethod(), ['POST', 'PUT'], true)) {
            $this->form->submit($submitData);

            $b2bCustomerId = $request->request->get('entityId');
            if ($this->form->isValid() && $b2bCustomerId) {
                $customer = $this->manager->find(
                    'OroSalesBundle:B2bCustomer',
                    $b2bCustomerId
                );
                if (!$this->authorizationChecker->isGranted('EDIT', $customer)) {
                    throw new AccessDeniedException();
                }

                if ($customer->getPrimaryEmail() && $request->request->get('primary') === true) {
                    return false;
                }

                $this->onSuccess($entity, $customer);

                return true;
            }
        }

        return false;
    }

    /**
     * @param $id
     * @param ApiEntityManager $manager
     * @throws \Exception
     */
    public function handleDelete($id, ApiEntityManager $manager)
    {
        /** @var B2bCustomerEmail $customerEmail */
        $customerEmail = $manager->find($id);
        if (!$this->authorizationChecker->isGranted('EDIT', $customerEmail->getOwner())) {
            throw new AccessDeniedException();
        }

        if ($this->b2bCustomerEmailDeleteValidator->validate($customerEmail)) {
            $em = $manager->getObjectManager();
            $em->remove($customerEmail);
            $em->flush();
        } else {
            throw new \Exception("oro.sales.email.error.delete.more_one", 500);
        }
    }

    /**
     * @param B2bCustomerEmail $entity
     * @param B2bCustomer $customer
     */
    protected function onSuccess(B2bCustomerEmail $entity, B2bCustomer $customer)
    {
        $entity->setOwner($customer);
        $this->manager->persist($entity);
        $this->manager->flush();
    }
}
