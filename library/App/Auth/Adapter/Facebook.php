<?php

class App_Auth_Adapter_Facebook implements Zend_Auth_Adapter_Interface
{
    /**
     * Doctrine EntityManager
     *
     * @var Doctrine\ORM\EntityManager
     */
    protected $em;

    /**
     * The entity name to check for an identity.
     *
     * @var string
     */
    protected $entityName;
    
    /**
     * Results of database authentication query
     *
     * @var array
     */
    protected $resultRowObject;
    
    /**
     * Constructor sets configuration options.
     *
     * @param  Doctrine\ORM\EntiyManager
     * @param  string
     * @param  string
     * @param  string
     * @return void
     */
    public function __construct($em, $entityName = null, $identityField = null, $credentialField = null)
    {
        $this->em = $em;

        if (null !== $entityName) {
            $this->setEntityName($entityName);
        }

        if (null !== $identityField) {
            $this->setIdentityField($identityField);
        }
    }

    /**
     * Set entity name.
     *
     * @param  string
     * @return LoSo_Zend_Auth_Adapter_Doctrine2
     */
    public function setEntityName($entityName)
    {
        $this->entityName = $entityName;
        return $this;
    }

    /**
     * Set identity field.
     *
     * @param  string
     * @return LoSo_Zend_Auth_Adapter_Doctrine2
     */
    public function setIdentityField($identityField)
    {
        $this->identityField = $identityField;
        return $this;
    }

    /**
     * Set the value to be used as identity.
     *
     * @param  string
     * @return LoSo_Zend_Auth_Adapter_Doctrine2
     */
    public function setIdentity($identity)
    {
        $this->identity = $identity;
        return $this;
    }

    /**
     * Defined by Zend_Auth_Adapter_Interface.  This method is called to
     * attempt an authentication.  Previous to this call, this adapter would have already
     * been configured with all necessary information to successfully connect to a database
     * table and attempt to find a record matching the provided identity.
     *
     * @throws Zend_Auth_Adapter_Exception if answering the authentication query is impossible
     * @return Zend_Auth_Result
     */
    public function authenticate()
    {
        $this->_authenticateSetup();
        $query = $this->_getQuery();

        $authResult = array(
            'code'     => Zend_Auth_Result::FAILURE,
            'identity' => null,
            'messages' => array()
        );

        try {
            // Get result as an Array
            $result = $query->getArrayResult();
            // Get result as an Entity object and store
            $this->resultRowObject = $query->getResult();
            
            // Count number of items returned
            $resultCount = count($result);
            if ($resultCount > 1) {
                $authResult['code'] = Zend_Auth_Result::FAILURE_IDENTITY_AMBIGUOUS;
                $authResult['messages'][] = 'More than one entity matches the supplied identity.';
            } else if ($resultCount < 1) {
                $authResult['code'] = Zend_Auth_Result::FAILURE_IDENTITY_NOT_FOUND;
                $authResult['messages'][] = 'A record with the supplied identity could not be found.';
            } else if (1 == $resultCount) {
                // Need to get User object from returned Facebook user
                $object = $this->resultRowObject; 
                $uid = $object[0]->getUserId();
                $this->resultRowObject = array($this->em->find('App\Entity\User', $uid));
                $authResult['code'] = Zend_Auth_Result::SUCCESS;
                $authResult['identity'] = $this->identity;
                $authResult['messages'][] = 'Authentication successful.';
            }
        } catch (\Doctrine\ORM\Query\QueryException $qe) {
            $authResult['code'] = Zend_Auth_Result::FAILURE_UNCATEGORIZED;
            $authResult['messages'][] = $qe->getMessage();
        }

        return new Zend_Auth_Result(
            $authResult['code'],
            $authResult['identity'],
            $authResult['messages']
        );
    }
    
    public function getResultRowObject()
    {
        return $this->resultRowObject;
    }
    
    /**
     * This method abstracts the steps involved with
     * making sure that this adapter was indeed setup properly with all
     * required pieces of information.
     *
     * @throws Zend_Auth_Adapter_Exception - in the event that setup was not done properly
     */
    protected function _authenticateSetup()
    {
        $exception = null;

        if (null === $this->em || !$this->em instanceof \Doctrine\ORM\EntityManager) {
            $exception = 'A Doctrine2 EntityManager must be supplied for the Zend_Auth_Adapter_Doctrine2 authentication adapter.';
        } elseif (empty($this->identityField)) {
            $exception = 'An identity field must be supplied for the Zend_Auth_Adapter_Doctrine2 authentication adapter.';
        } elseif (empty($this->identity)) {
            $exception = 'A value for the identity was not provided prior to authentication with Zend_Auth_Adapter_Doctrine2.';
        }
        if (null !== $exception) {
            /**
             * @see Zend_Auth_Adapter_Exception
             */
            throw new Zend_Auth_Adapter_Exception($exception);
        }
    }

    /**
     * Construct the Doctrine query.
     *
     * @return Doctrine\ORM\Query
     */
    protected function _getQuery()
    {
        $qb = $this->em->createQueryBuilder()
            ->select('e')
            ->from($this->entityName, 'e')
            ->where('e.' . $this->identityField . ' = ?1')
            ->setParameters(array(1 => $this->identity));
        
        return $qb->getQuery();
    }
}