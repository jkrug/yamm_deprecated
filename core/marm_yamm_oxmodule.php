<?php

class marm_yamm_oxmodule extends marm_yamm_oxmodule_parent {
	
	public function isYAMM() {
		return in_array($this->getId(), oxUtilsObject::getInstance()->getModuleVar('aYAMMEnabledModules'));
	}

}
