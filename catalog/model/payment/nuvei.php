<?php

namespace Opencart\Catalog\Model\Extension\Nuvei\Payment;

require_once DIR_EXTENSION . DIRECTORY_SEPARATOR . 'nuvei' . DIRECTORY_SEPARATOR . 'nuvei_class.php';

/**
 * @author Nuvei
 */
class Nuvei extends \Opencart\System\Engine\Model
{
    public function getMethod($address): array
    {
		$this->language->load(NUVEI_CONTROLLER_PATH);
		
		$query = $this->db->query(
            "SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone "
            ."WHERE geo_zone_id = '". (int)$this->config->get(NUVEI_SETTINGS_PREFIX . 'geo_zone_id') . "' "
                ."AND country_id = '" . (int)$address['country_id'] . "' "
                ."AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')"
        );
		
        if ($this->cart->hasSubscription()) {
            if (!$this->customer->isLogged()) {
                $status = false;
            }

            $status = true;
		}
        elseif (!$this->cart->hasShipping()) {
			$status = false;
		}
        elseif (!$this->config->get(NUVEI_SETTINGS_PREFIX . 'geo_zone_id')) {
			$status = true;
		}
        elseif ($query->num_rows) {
			$status = true;
		}
        else {
			$status = false;
		}
		
		$method_data = array();
	
		if ($status) {  
      		$method_data = array( 
        		'code'       => NUVEI_PLUGIN_CODE,
        		'title'      => NUVEI_PLUGIN_TITLE,
				'terms'      => '',
				'sort_order' => $this->config->get(NUVEI_SETTINGS_PREFIX . 'sort_order')
      		);
    	}
   
    	return $method_data;
  	}
    
    /**
     * Check if a Subscription plan belongs to Nuvei
     * 
     * @param int $subscr_id
     * @return bool
     */
    public function isNuveiSubscr($subscr_id): bool
    {
        $query = $this->db->query(
            "SELECT name "
            . "FROM " . DB_PREFIX . "subscription_plan_description "
            . "WHERE subscription_plan_id = " . (int) $subscr_id
        );
        
        if (!$query->num_rows) {
            return false;
        }
        
        if (!empty($query->row['name'])
            && false !== \Opencart\System\Helper\Utf8\strpos(\Opencart\System\Helper\Utf8\strtolower($query->row['name']), 'nuvei')
        ) {
            return true;
        }
        
		return false;
    }
    
}
