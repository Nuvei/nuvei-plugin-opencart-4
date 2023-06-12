<?php

/**
 * Resolve the conflicts in different OC4 versions.
 *
 * @author Nuvei
 */
class Nuvei_Version_Resolver
{
    /**
     * Helper function to resolve event action differences between OC4 versions.
     * 
     * @param string $action
     * @return string
     */
    public static function get_event_action($action)
    {
        $oc_v = self::get_oc_version_int();
        
        // for version 4.0.2.0 and up
        if ($oc_v >= 4020) {
            return str_replace('|', '.', $action);
        }
        
        return $action;
    }
    
    /**
     * Different check for different versions
     * 
     * @param array $order_info
     * @return bool
     */
    public static function check_for_nuvei_order($order_info)
    {
        if (empty($order_info) || !is_array($order_info)) {
            return false;
        }
        
        $oc_v = self::get_oc_version_int();
        
        // for version 4.0.2.0 and up
        if ($oc_v >= 4020) {
            if (empty($order_info['payment_method']['code'])
                || (NUVEI_PLUGIN_CODE . '.' . NUVEI_PLUGIN_CODE) != $order_info['payment_method']['code']
            ) {
                return false;
            }
            
            return true;
        }
        
        // for version 4.0.1.x
        if (NUVEI_PLUGIN_CODE != $order_info['payment_code']) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get Checkout payment method from the session.
     * 
     * @param type $session The session data
     * @return string
     */
    public static function get_checkout_pm($session)
    {
        $oc_v = self::get_oc_version_int();
        
        // for version 4.0.2.0 and up
        if ($oc_v >= 4020) {
            return $session['payment_method']['code']; // expected nuvei.nuvei
        }
        
        return $session['payment_method']; // expected nuvei
    }
    
    /**
     * Returns the int representation of OC version.
     * 
     * @return int
     */
    private static function get_oc_version_int()
    {
        return (int) str_replace('.', '', VERSION);
    }
    
}
