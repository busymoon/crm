<?php

namespace OroCRM\Bundle\MagentoBundle\ImportExport\Strategy;

use Doctrine\ORM\EntityRepository;

use Oro\Bundle\ImportExportBundle\Context\ContextAwareInterface;
use Oro\Bundle\ImportExportBundle\Context\ContextInterface;
use Oro\Bundle\ImportExportBundle\Strategy\Import\ImportStrategyHelper;
use Oro\Bundle\ImportExportBundle\Strategy\StrategyInterface;

use OroCRM\Bundle\MagentoBundle\Entity\Customer;

class AddOrUpdateCustomer implements StrategyInterface, ContextAwareInterface
{
    const ENTITY_NAME = 'OroCRMMagentoBundle:Customer';

    /** @var ImportStrategyHelper */
    protected $strategyHelper;

    /** @var ContextInterface */
    protected $importExportContext;

    /**
     * @param ImportStrategyHelper $strategyHelper
     */
    public function __construct(ImportStrategyHelper $strategyHelper)
    {
        $this->strategyHelper = $strategyHelper;
    }

    /**
     * Process item strategy
     *
     * @param mixed $entity
     * @return mixed|null
     */
    public function process($entity)
    {
        $entity = $this->findAndReplaceEntity($entity);

        // update all related entities
        $this
            ->updateAccount($entity)
            ->updateContact($entity)
            ->updateAddresses($entity);

        // update owner for addresses, emails and phones
        $this->updateRelatedEntitiesOwner($entity);

        // validate and update context - increment counter or add validation error
        $entity = $this->validateAndUpdateContext($entity);

        return $entity;
    }

    /**
     * @param Customer $entity
     * @return Customer
     */
    protected function findAndReplaceEntity(Customer $entity)
    {
        $existingEntity = $this->getEntityOrNull($entity, self::ENTITY_NAME);

        if ($existingEntity) {
            $this->strategyHelper->importEntity($existingEntity, $entity);
            $entity = $existingEntity;
        } else {
            $entity->setId(null);
        }

        return $entity;
    }

    /**
     * @param Customer $entity
     * @return null|Customer
     */
    protected function validateAndUpdateContext(Customer $entity)
    {
        // validate contact
        $validationErrors = $this->strategyHelper->validateEntity($entity);
        if ($validationErrors) {
            $this->importExportContext->incrementErrorEntriesCount();
            $this->strategyHelper->addValidationErrors($validationErrors, $this->importExportContext);
            return null;
        }

        // increment context counter
        if ($entity->getId()) {
            $this->importExportContext->incrementReplaceCount();
        } else {
            $this->importExportContext->incrementAddCount();
        }

        return $entity;
    }

    /**
     * @param Customer $entity
     * @param string $entityClass
     * @return Customer|null
     */
    protected function getEntityOrNull(Customer $entity, $entityClass)
    {
        $existingEntity = null;
        $originalId = $entity->getOriginalId();

        if ($originalId) {
            $existingEntity = $this->getEntityRepository($entityClass)->findOneBy(['originalId' => $originalId]);
        }

        return $existingEntity ?: null;
    }

    /**
     * @param string $entityName
     * @return EntityRepository
     */
    protected function getEntityRepository($entityName)
    {
        return $this->strategyHelper->getEntityManager($entityName)->getRepository($entityName);
    }

    /**
     * {@inheritDoc}
     */
    public function setImportExportContext(ContextInterface $importExportContext)
    {
        $this->importExportContext = $importExportContext;
    }

    /**
     * @param Customer $entity
     * @return $this
     */
    public function updateAddresses(Customer $entity)
    {
        return $this;
    }

    /**
     * @param Customer $entity
     * @return $this
     */
    public function updateAccount(Customer $entity)
    {
        return $this;
    }

    /**
     * @param Customer $entity
     * @return $this
     */
    public function updateContact(Customer $entity)
    {
        return $this;
    }

    /**
     * @param Customer $entity
     * @return $this
     */
    public function updateRelatedEntitiesOwner(Customer $entity)
    {
        return $this;
    }
}
