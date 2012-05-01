<?php

namespace App;

class Acl extends \Zend_Acl
{
    /**
     * Define xml config for building Acl
     * @var string 
     */
    const XML = 'XML';
    
    /**
     * Define database config for building Acl
     * @var string 
     */
    const DB = 'DB';
    
    /**
     * Define role constants  
     */
    const GUEST = "Guest";
    const MEMBER = "Member";
    const ORGANIZER = "Organizer";
    const WRITER = "Writer";
    const ADMIN = "Admin";
    
    protected $_configType;
    
    protected $_config;
    
    public function __construct($configType = null, \Zend_Config $config = null)
    {
        $this->_configType = $configType;
        $this->_config = $config;
        
        switch($this->_configType)
        {
            case 'XML':
                // Build Acl from Xml
                $this->_buildAclFromXml();
                break;
            
            case 'DB':
                // Build Acl from database
                $this->__buildAclFromDb();
                break;
        }
    }
    
    /**
     * Build the ACL
     *
     * @return void
     */
    protected function _buildAclFromXml()
    {
        if (!isset($this->_config->resources->resource)) {
            throw new \Zend_Acl_Exception('No resources have been defined.');
        }
        
        // Add the resources
        //\Zend_Debug::dump(count($this->_config->resources));die;
        // Check theres more than one resource available
        foreach ($this->_config->resources->resource as $resource) {
            if (!$this->has($resource)) {
                $this->addResource(new \Zend_Acl_Resource($resource));
            }
        }
        
        $roles = array($this->getCurrentRole());
        /*if ($roles[0] == self::AUTH_ROLE) {
            $roles[] = self::AUTH_INACTIVE_MEMBER_ROLE;
        }*/

        foreach ($roles as $role) {
            if (!isset($this->_config->roles->$role)) {
                throw new \Zend_Acl_Exception("The role '" . $role . "' has not been defined.");
            } else {
                if (!$this->hasRole($role)) {
                    $this->addRole($role);
                }
                
                // set a global deny for this role
                $this->deny($role);
                if (isset($this->_config->roles->{$role}->allow)) {
                    $allow = $this->_config->roles->{$role}->allow;
                    
                    // always use an array of resources, even if there's only 1
                    if ($allow->resource instanceof \Zend_Config) {
                        $resources = $allow->resource->toArray();
                    } else {
                        $resources = array($allow->resource);
                    }

                    foreach ($resources as $resource) {

                        if ($resource === '*') {
                            
                            $this->allow($role); // global allow
                        } elseif ($resource && $this->has($resource)) {
                            $this->allow($role, $resource);
                        }
                    }
                }
            }
        }
        \Zend_Debug::dump($this);die;
    }
    
    public function __buildAclFromDb()
    {
        // Add resources
        $this->add(new \Zend_Acl_Resource('site:index'));
        $this->add(new \Zend_Acl_Resource('site:auth'));
        $this->add(new \Zend_Acl_Resource('site:profile'));
        
        $this->add(new \Zend_Acl_Resource('event:index'));
        $this->add(new \Zend_Acl_Resource('event:calendar'));
        
        $this->add(new \Zend_Acl_Resource('basket:index'));
        $this->add(new \Zend_Acl_Resource('basket:checkout'));
        
        $this->add(new \Zend_Acl_Resource('community:index'));
        $this->add(new \Zend_Acl_Resource('community:group'));
        
        $this->add(new \Zend_Acl_Resource('forum:category'));
        $this->add(new \Zend_Acl_Resource('forum:index'));
        $this->add(new \Zend_Acl_Resource('forum:thread'));
        $this->add(new \Zend_Acl_Resource('forum:post'));
        
        $this->add(new \Zend_Acl_Resource('news:index'));
        
        
        // Admin resources
        $this->add(new \Zend_Acl_Resource('admin:index'));
        $this->add(new \Zend_Acl_Resource('admin:user'));
        $this->add(new \Zend_Acl_Resource('admin:order'));
        $this->add(new \Zend_Acl_Resource('admin:community'));
        $this->add(new \Zend_Acl_Resource('admin:news'));
        
        // Create roles
        $guest = new \Zend_Acl_Role(\App\Acl::GUEST);
        $this->addRole($guest);
        $member = new \Zend_Acl_Role(\App\Acl::MEMBER);
        $this->addRole($member, $guest);
        $writer = new \Zend_Acl_Role(\App\Acl::WRITER);
        $this->addRole($writer, $member);
        $admin = new \Zend_Acl_Role(\App\Acl::ADMIN);
        $this->addRole($admin, $writer);
        
        switch($this->getCurrentRole())
        {
            case \App\Acl::ADMIN:
                $this->allow($admin, 'event:index',array('add'));
                $this->allow($admin, 'forum:category',array('add'));
                $this->allow($admin, 'forum:index',array('add', 'edit', 'delete', 'lock', 'unlock'));
                $this->allow($admin, 'forum:thread',array('add'));
                $this->allow($admin, 'admin:index',array('index', 'clearcache'));
                $this->allow($admin, 'admin:user',array('index', 'add', 'edit', 'delete', 'block', 'permissions'));
                $this->allow($admin, 'admin:order',array('index', 'view', 'audit'));
                $this->allow($admin, 'admin:community',array('index', 'forum'));
                $this->allow($admin, 'admin:news',array('index'));
                $this->allow($admin, 'news:index',array('delete', 'publish'));
                
            case \App\Acl::WRITER:
                $this->allow($writer, 'news:index',array('add', 'edit'));
                
            case \App\Acl::MEMBER:
                // Setup access rights
                $this->allow($member, 'site:auth',array('logout'));
                $this->allow($member, 'site:profile',array('view', 'create', 'interests', 'facebook'));
                $this->allow($member, 'event:calendar',array('index', 'view'));
                $this->allow($member, 'basket:checkout',array('index', 'paypal', 'complete'));
                $this->allow($guest, 'forum:index',array('index'));
                $this->allow($guest, 'forum:thread',array('view'));
                $this->allow($member, 'forum:post',array('index', 'view', 'add', 'reply'));
         
            case \App\Acl::GUEST:
                // Setup access rights
                $this->allow($guest, 'site:index',array('index', 'notification'));
                $this->allow($guest, 'site:auth',array('index', 'login', 'register', 'forbidden', 'activate', 'username'));
                
                $this->allow($guest, 'event:index',array('index','view'));
                $this->allow($guest, 'event:calendar',array('index', 'view'));
                
                $this->allow($guest, 'basket:index',array('index', 'update', 'remove', 'empty', 'trash'));
                
                $this->allow($guest, 'community:index',array('index'));
                $this->allow($guest, 'community:group',array('index'));
                
                $this->allow($guest, 'news:index',array('index', 'view'));
                
                break;
        }
    }
    
    public function getCurrentRole()
    {
        // Get Auth Object
        $auth = \Zend_Auth::getInstance();
        
        // Check if there is a logged in user
        if($auth->hasIdentity())
        {
            $user = $auth->getIdentity();
            return $user->getRoleId();
        }else{
            // No one logged in assume guest
            return \App\Acl::GUEST;
        }
    }
}
?>
