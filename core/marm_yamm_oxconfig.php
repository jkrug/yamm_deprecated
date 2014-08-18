<?php

if(false)
{
    class marm_yamm_oxconfig_parent extends oxConfig{}
}

class marm_yamm_oxconfig extends marm_yamm_oxconfig_parent
{    
    protected $_sConfigFile = 'marm_yamm.config.php';
    
    protected $_staticEntries = null;

    public function init()
    {
        parent::init();
        foreach (array('aModules', 'aDisabledModules', 'aModulePaths') as $name) {
            $this->_aConfigParams[$name] = oxUtilsObject::getInstance()->getModuleVar($name);
        }
    }
}
