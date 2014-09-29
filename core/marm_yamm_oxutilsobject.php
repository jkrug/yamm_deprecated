<?php
/**
 * This file is part of a marmalade GmbH project
 *
 * It is Open Source and may be redistributed.
 * For contact information please visit http://www.marmalade.de
 *
 * Version:    1.0
 * Author URI: http://www.marmalade.de
 */

class marm_yamm_oxutilsobject extends marm_yamm_oxutilsobject_parent
{

    protected $_sConfigFile = 'marm_yamm.config.php';

    protected $_staticEntries = null;

    const ENABLED = 'aYAMMEnabledModules';
    const DISABLED = 'aYAMMDisabledModules';
    const CLASS_ORDER = 'aYAMMSpecialClassOrder';
    const BLOCK_CONTROL = 'bYAMMBlockControl';

    private function handleConfigChanges($modulePathes)
    {
        $data = oxConfig::getInstance()->getShopConfVar('aCachedConfig', null, 'marm/yamm');
        $oModule = oxNew('oxModule');
        if ( !$data )
            $data = array('metafiles' => array(), 'config' => array(self::ENABLED => array(), self::DISABLED => array(), ));
        $toActivate = array();

        if ( oxConfig::getInstance()->getShopConfVar('iLastModified', null, 'marm/yamm') < filemtime(getShopBasePath() . $this->_sConfigFile) ) {

            $toActivate = array_diff($this->_staticEntries[self::ENABLED], $data['config'][self::ENABLED]);
            foreach ($toActivate as $id) {
                error_log("Activate {$id}");
                $oModule->load($id);
                $oModule->activate();
            }
            // @formatter:off
            $toDeactivate = array_diff(
                $this->_staticEntries[self::DISABLED],
                $data['config'][self::DISABLED],
                $this->_staticEntries[self::ENABLED]
            );
            // @formatter:on
            foreach ($toDeactivate as $id) {
                error_log("Deactivate {$id}");
                $oModule->load($id);
                $oModule->deactivate();
            }
        }

        // Reactivate modules whoms metadata.php has changed.
        // Must be done to ensure that blocks are loaded, otherwise
        // some modules might break.
        foreach ($this->_staticEntries[self::ENABLED] as $id) {
            if ( in_array($id, $toActivate) || $id == 'marm/yamm' )
                continue;
            $metaFile = getShopBasePath() . '/modules/' . $modulePathes[$id] . '/metadata.php';
            if ( filemtime($metaFile) > $data['metafiles'][$id]['last_modified'] ) {
                error_log("Reactivate {$id}");
                $oModule->load($id);
                $oModule->deactivate();
                $oModule->activate();
            }
        }

        $data = array('config' => $this->_staticEntries, 'metafiles' => array(), );
        foreach ($this->_staticEntries[self::ENABLED] as $id) {
            $metaFile = getShopBasePath() . '/modules/' . $modulePathes[$id] . '/metadata.php';
            $data['metafiles'][$id] = array('metafile' => $metaFile, 'last_modified' => filemtime($metaFile), );
        }
        oxConfig::getInstance()->saveShopConfVar('arr', 'aCachedConfig', $data, null, 'marm/yamm');
        oxConfig::getInstance()->saveShopConfVar('num', 'iLastModified', filemtime(getShopBasePath() . $this->_sConfigFile), null, 'marm/yamm');
    }

    public function getYAMMKeys()
    {
        return isset($this->_staticEntries) ? array_keys($this->_staticEntries) : array();
    }

    private function getOrderForClass($class)
    {
        $result = $this->_staticEntries[self::ENABLED];
        if ( isset($this->_staticEntries[self::CLASS_ORDER]) ) {
            if ( array_key_exists($class, $this->_staticEntries[self::CLASS_ORDER]) ) {
                $result = array_merge(array_diff($result, $this->_staticEntries[self::CLASS_ORDER][$class]), $this->_staticEntries[self::CLASS_ORDER][$class]);
            }
        }
        return $result;
    }

    private function extendsForClass($class)
    {
        return array_key_exists($class, $this->_staticEntries['aModules']) ? $this->_staticEntries['aModules'][$class] : array();
    }
    
    public function  init()
    {
        if ( !isset($this->_staticEntries) && file_exists(getShopBasePath() . $this->_sConfigFile) ) {
            include (getShopBasePath() . $this->_sConfigFile);
            $this->_staticEntries = $aYAMMConfig;
            // ["aModuleFiles","aModuleTemplates"]
            $modulePathes = array_merge(parent::getModuleVar('aModulePaths'), isset($this->_staticEntries['aModulePaths']) ? $this->_staticEntries['aModulePaths'] : array());
            $this->handleConfigChanges($modulePathes);
            $this->_staticEntries['aModules'] = parent::getModuleVar('aModules');
            $this->_staticEntries['aModuleFiles'] = parent::getModuleVar('aModuleFiles') ? parent::getModuleVar('aModuleFiles') : array();
            $this->_staticEntries['aModuleTemplates'] = parent::getModuleVar('aModuleTemplates') ? parent::getModuleVar('aModuleTemplates') : array();
            
            foreach ($this->_staticEntries['aModules'] as $key => $value) {
                $this->_staticEntries['aModules'][$key] = explode('&', $value);
            }

            $moduleMeta = array();
            foreach ($this->_staticEntries[self::ENABLED] as $module) {
                $metaFile = getShopBasePath() . '/modules/' . $modulePathes[$module] . '/metadata.php';
                $aModule = array();
                @include ($metaFile);
                $moduleMeta[$module] = $aModule;
                $this->_staticEntries['aModuleTemplates'][$module] = isset($aModule['templates']) ? $aModule['templates'] : null;
                $this->_staticEntries['aModuleFiles'][$module] = isset($aModule['files']) ? $aModule['files'] : null;
            }

            // @formatter:off
            $extensions = array_map(function($meta) {
                return array_key_exists('extend', $meta) ? array_keys($meta['extend']) : array();
            }, $moduleMeta);
            // @formatter:on
            $extensions = call_user_func_array(array_merge, array_values($extensions));
            $extensions = array_unique($extensions);

            foreach ($extensions as $class) {
                $classes = array();
                foreach ($this->getOrderForClass($class) as $module) {
                    if ( isset($moduleMeta[$module]['extend']) && array_key_exists($class, $moduleMeta[$module]['extend']) ) {
                        $classes[] = $moduleMeta[$module]['extend'][$class];
                    }
                }

                $this->_staticEntries['aModules'][$class] = array_merge(array_diff($this->extendsForClass($class), $classes), $classes);
            }

            foreach ($this->_staticEntries['aModules'] as $key => $value) {
                $this->_staticEntries['aModules'][$key] = implode('&', $value);
            }
        }
    }

    public function getModuleVar($sModuleVarName)
    {

        if ( isset($this->_staticEntries) && array_key_exists($sModuleVarName, $this->_staticEntries) ) {
            if ( $sModuleVarName === 'aDisabledModules' ) {
                // @formatter:off
        		return array_diff(
        		  array_merge(
        		      parent::getModuleVar($sModuleVarName),
        		      $this->_staticEntries[self::DISABLED]
                  ),
                  $this->_staticEntries[self::ENABLED]
                );
                // @formatter:on
            } elseif ( is_array($this->_staticEntries[$sModuleVarName]) && parent::getModuleVar($sModuleVarName) ) {
                $old = parent::getModuleVar($sModuleVarName);
                $new = $this->_staticEntries[$sModuleVarName];
                return ($new == $old) ? $new : array_merge($old, $new);
            } else {
                return $this->_staticEntries[$sModuleVarName];
            }
        }
        $result = parent::getModuleVar($sModuleVarName);

        return $result;
    }

}
