<?php
/**
 * shipping class
 *
 * @copyright Copyright 2003-2023 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: Scott C Wilson 2022 Oct 16 Modified in v1.5.8a $
 */ // BMH 2025-05-14 handling fee modified to run on PHP 8.1 and 8.2
 //                 ln36, 178, 361
 // BMH 2025-06-11 correct calcs, checked spaces in input params, no errors PHP 8.1 to 8.3 ZenCart 1.5.8a
 //         tax calc comes from tax on shipping method; handling fee is ADDITIONAL to aupost handling fee
 // Version 158a
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}
/**
 * shipping class
 * Class used for interfacing with shipping modules
 *
 */
class shipping extends base
{
    /** 
     * $enabled allows notifier to turn off shipping method
     * @var boolean
     */
    public $enabled;
    
    /**
     * $modules is an array of installed shipping module names can be altered by notifier
     * @var array
     */
    public $modules;
    /**
     * $abort_legacy_calculations allows a notifier to enable the calculate_boxes_weight_and_tare method
     * @var boolean
     */
    public $abort_legacy_calculations;
     public $handling_fee; // BMH

    public function __construct($module = null)
    {
        global $PHP_SELF, $messageStack, $languageLoader;

        if (defined('MODULE_SHIPPING_INSTALLED') && !empty(MODULE_SHIPPING_INSTALLED)) {
            $this->modules = explode(';', MODULE_SHIPPING_INSTALLED);
        }
        $this->notify('NOTIFY_SHIPPING_CLASS_GET_INSTALLED_MODULES', $module);

        if (empty($this->modules)) {
            return;
        }

        $include_modules = [];

        if (!empty($module) && (in_array(substr($module['id'], 0, strpos($module['id'], '_')) . '.' . substr($PHP_SELF, (strrpos($PHP_SELF, '.')+1)), $this->modules))) {
            $include_modules[] = [
            'class' => substr($module['id'], 0, strpos($module['id'], '_')),
            'file' => substr($module['id'], 0, strpos($module['id'], '_')) . '.' . substr($PHP_SELF, (strrpos($PHP_SELF, '.')+1))
            ];
        } else {
            foreach($this->modules as $value) {
                $class = substr($value, 0, strrpos($value, '.'));
                $include_modules[] = [
                    'class' => $class,
                    'file' => $value
                ];
            }
        }

        for ($i = 0, $n = count($include_modules); $i < $n; $i++) {
            $lang_file = null;
            $module_file = DIR_WS_MODULES . 'shipping/' . $include_modules[$i]['file'];
            if (IS_ADMIN_FLAG === true) {
                $lang_file = zen_get_file_directory(DIR_FS_CATALOG . DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/shipping/', $include_modules[$i]['file'], false);
                $module_file = DIR_FS_CATALOG . $module_file;
            } else {
                $lang_file = zen_get_file_directory(DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/shipping/', $include_modules[$i]['file'], false);
            }
            if ($languageLoader->hasLanguageFile(DIR_FS_CATALOG . DIR_WS_LANGUAGES,  $_SESSION['language'], $include_modules[$i]['file'], '/modules/shipping')) {
                $languageLoader->loadExtraLanguageFiles(DIR_FS_CATALOG . DIR_WS_LANGUAGES,  $_SESSION['language'], $include_modules[$i]['file'], '/modules/shipping');
            } else {
                if (is_object($messageStack)) {
                    if (IS_ADMIN_FLAG === false) {
                        $messageStack->add('checkout_shipping', WARNING_COULD_NOT_LOCATE_LANG_FILE . $lang_file, 'caution');
                    } else {
                        $messageStack->add_session(WARNING_COULD_NOT_LOCATE_LANG_FILE . $lang_file, 'caution');
                    }
                }
                continue;
            }
            $this->enabled = true;
            $this->notify('NOTIFY_SHIPPING_MODULE_ENABLE', $include_modules[$i]['class'], $include_modules[$i]['class']);
            if ($this->enabled) {
                include_once $module_file;
                $GLOBALS[$include_modules[$i]['class']] = new $include_modules[$i]['class'];

                $enabled = $this->check_enabled($GLOBALS[$include_modules[$i]['class']]);
                if ($enabled == false ) {
                    unset($GLOBALS[$include_modules[$i]['class']]);
                }
            }
        }
    }

    public function check_enabled($class)
    {
        $enabled = $class->enabled;
        if (method_exists($class, 'check_enabled_for_zone') && $class->enabled) {
            $enabled = $class->check_enabled_for_zone();
        }
        $this->notify('NOTIFY_SHIPPING_CHECK_ENABLED_FOR_ZONE', [], $class, $enabled);
        if (method_exists($class, 'check_enabled') && $enabled) {
            $enabled = $class->check_enabled();
        }
        $this->notify('NOTIFY_SHIPPING_CHECK_ENABLED', [], $class, $enabled);
        return $enabled;
    }

    public function calculate_boxes_weight_and_tare()
    {
        global $total_weight, $shipping_weight, $shipping_quoted, $shipping_num_boxes;

        $this->abort_legacy_calculations = false;
        $this->notify('NOTIFY_SHIPPING_MODULE_PRE_CALCULATE_BOXES_AND_TARE', [], $total_weight, $shipping_weight, $shipping_quoted, $shipping_num_boxes);
        if ($this->abort_legacy_calculations) {
            return;
        }

        if (is_array($this->modules)) {
            $shipping_quoted = '';
            $shipping_num_boxes = 1;
            $shipping_weight = $total_weight;

            $za_tare_array = preg_split("/[:,]/" , str_replace(' ', '', !empty(SHIPPING_BOX_WEIGHT) ? SHIPPING_BOX_WEIGHT : '0:0'));
            $zc_tare_percent= (float)$za_tare_array[0];
            $zc_tare_weight= (float)$za_tare_array[1];

            $za_large_array = preg_split("/[:,]/" , str_replace(' ', '', !empty(SHIPPING_BOX_PADDING) ? SHIPPING_BOX_PADDING : '0:0'));
            $zc_large_percent= (float)$za_large_array[0];
            $zc_large_weight= (float)$za_large_array[1];

            // SHIPPING_BOX_WEIGHT = tare
            // SHIPPING_BOX_PADDING = Large Box % increase
            // SHIPPING_MAX_WEIGHT = Largest package

            switch (true) {
                // large box add padding
                case (SHIPPING_MAX_WEIGHT <= $shipping_weight):
                    $shipping_weight = $shipping_weight + ($shipping_weight*($zc_large_percent/100)) + $zc_large_weight;
                    break;

                default:
                    // add tare weight < large
                    $shipping_weight = $shipping_weight + ($shipping_weight*($zc_tare_percent/100)) + $zc_tare_weight;
                    break;
            }

            // total weight with Tare
            $_SESSION['shipping_weight'] = $shipping_weight;
            if ($shipping_weight > SHIPPING_MAX_WEIGHT) { // Split into many boxes
//        $shipping_num_boxes = ceil($shipping_weight/SHIPPING_MAX_WEIGHT);
                $zc_boxes = zen_round(($shipping_weight/SHIPPING_MAX_WEIGHT), 2);
                $shipping_num_boxes = ceil($zc_boxes);
                $shipping_weight = $shipping_weight/$shipping_num_boxes;
            }
        }
        $this->notify('NOTIFY_SHIPPING_MODULE_CALCULATE_BOXES_AND_TARE', [], $total_weight, $shipping_weight, $shipping_quoted, $shipping_num_boxes);
    }

    public function quote($method = '', $module = '', $calc_boxes_weight_tare = true, $insurance_exclusions = [])
    {
        global $shipping_weight, $uninsurable_value; 
        $quotes_array = [];

        if ($calc_boxes_weight_tare) {
            $this->calculate_boxes_weight_and_tare();
        }

        // calculate amount not to be insured on shipping
       // BMH get_uninsurable_value not defined anywhere $uninsurable_value = (method_exists($this, method: 'get_uninsurable_value')) ? $this->get_uninsurable_value($insurance_exclusions) : 0;

        if (is_array($this->modules)) {
            $include_quotes = [];

            foreach($this->modules as $value) {
                $class = substr($value, 0, strrpos($value, '.'));
                if (!empty($module)) {
                    if ($module == $class && isset($GLOBALS[$class]) && $GLOBALS[$class]->enabled) {
                        $include_quotes[] = $class;
                    }
                } elseif (isset($GLOBALS[$class]) && $GLOBALS[$class]->enabled) {
                    $include_quotes[] = $class;
                }
            }

            $size = count($include_quotes);
            for ($i = 0; $i < $size; $i++) {
                if (method_exists($GLOBALS[$include_quotes[$i]], 'update_status')) {
                    $GLOBALS[$include_quotes[$i]]->update_status();
                }
                if (false === $GLOBALS[$include_quotes[$i]]->enabled) {
                    continue;
                }
                $save_shipping_weight = $shipping_weight;
                $quotes = $GLOBALS[$include_quotes[$i]]->quote($method);
                if (!isset($quotes['tax']) && !empty($quotes)) {
                    $quotes['tax'] = 0;
                }
                $shipping_weight = $save_shipping_weight;
                if (is_array($quotes)) {
                    $quotes_array[] = $quotes;
                }
            }
        }
// ********************************************************************************
        // bof - handling fee module edit [1 of 3]
    global $output ; 
    $handling_fee = '';       // BMH
    $key = '';                // BMH 
    $group_discountfee = 0;   // BMH
        //include("includes\modules\order_total\ot_handlingfee.php");  // BMH 
    if ($this->handling_fee_test()) {
        $this->handling_fee = $this->handling_fee();
        $include_methods = array_map('trim', explode(",", MODULE_ORDER_TOTAL_HANDLINGFEE_INCLUDE_SHIPPING));  // BMH trim
        $exclude_methods = array_map ('trim', explode(",", MODULE_ORDER_TOTAL_HANDLINGFEE_EXCLUDE_SHIPPING));  // BMH  trim
        $shipping_methods_size = sizeof($quotes_array);
        for ($j=0; $j<$shipping_methods_size; $j++) {
          $size = sizeof($quotes_array[$j]['methods']);
          for ($i=0; $i<$size; $i++) {
            $enabled = false; // default
            if (isset($quotes_array[$j]['methods'][$i]['cost'])){
              if (MODULE_ORDER_TOTAL_HANDLINGFEE_INCLUDE_SHIPPING != '') {
                if (in_array($quotes_array[$j]['id'], $include_methods)) {
                  $enabled = true;
                } else {
                  $enabled = false;
                }
              } elseif (MODULE_ORDER_TOTAL_HANDLINGFEE_EXCLUDE_SHIPPING != '') {
                if (in_array($quotes_array[$j]['id'], $exclude_methods)) {
                  $enabled = false;
                } else {
                  $enabled = true;
                }
              }
              if ($enabled) {
                $quotes_array[$j]['methods'][$i]['cost'] += $this->handling_fee; // add handling fee to shipping method cost
              }
            }
          }        
        }
      }
      // eof - handling fee module edit [1 of 3]
      // **************** +++++++++++++++++++++++++
        $this->notify('NOTIFY_SHIPPING_MODULE_GET_ALL_QUOTES', $quotes_array, $quotes_array);
        return $quotes_array;
    }

    function cheapest()
    {
        if (!is_array($this->modules)) {
            return false;
        }
        $rates = [];

        foreach($this->modules as $value) {
            $class = substr($value, 0, strrpos($value, '.'));
            if (isset($GLOBALS[$class]) && is_object($GLOBALS[$class]) && $GLOBALS[$class]->enabled) {
                $quotes = isset($GLOBALS[$class]->quotes) ? $GLOBALS[$class]->quotes : null;
                if (empty($quotes['methods']) || isset($quotes['error'])) {
                    continue;
                }
                $size = count($quotes['methods']);
                for ($i = 0; $i < $size; $i++) {
                    if (isset($quotes['methods'][$i]['cost'])) {

                            // bof - handling fee module edit [2 of 3]
                            $quotes['methods'][$i]['cost'] += $this->handling_fee; // BMH add handling fee to shipping method cost
                            // eof - handling fee module edit [2 of 3]

                            $rates[] = [
                            'id' => $quotes['id'] . '_' . $quotes['methods'][$i]['id'],
                            'title' => $quotes['module'] . ' (' . $quotes['methods'][$i]['title'] . ')',
                            'cost' => $quotes['methods'][$i]['cost'],
                            'module' => $quotes['id']
                        ];
                    }
                }
            }
        }

        $cheapest = false;
        $size = count($rates);
        for ($i = 0; $i < $size; $i++) {
            if ($cheapest !== false) {
                // never quote storepickup as lowest - needs to be configured in shipping module
                if ($rates[$i]['cost'] < $cheapest['cost'] && $rates[$i]['module'] !== 'storepickup') {
                    // -----
                    // Give a customized shipping module the opportunity to exclude itself from being quoted
                    // as the cheapest.  The observer must set the $exclude_from_cheapest to specifically
                    // (bool)true to be excluded.
                    //
                    $exclude_from_cheapest = false;
                    $this->notify('NOTIFY_SHIPPING_EXCLUDE_FROM_CHEAPEST', $rates[$i]['module'], $exclude_from_cheapest);
                    if ($exclude_from_cheapest === true) {
                        continue;
                    }
                    $cheapest = $rates[$i];
                }
            } elseif ($size === 1 || $rates[$i]['module'] !== 'storepickup') {
                $cheapest = $rates[$i];
            }
        }
        $this->notify('NOTIFY_SHIPPING_MODULE_CALCULATE_CHEAPEST', $cheapest, $cheapest, $rates);
        return $cheapest;
    }
    // ***********************************************
    // bof - handling fee module edit [3 of 3]
    function handling_fee_test() {
        global $order, $db, $currencies, $output, $title;
        $enabled = ((defined('MODULE_ORDER_TOTAL_HANDLINGFEE_STATUS') && MODULE_ORDER_TOTAL_HANDLINGFEE_STATUS == 'true') ? true : false);
        if ($enabled) {
            $pass = false;
            switch (MODULE_ORDER_TOTAL_HANDLINGFEE_DESTINATION) {
            case 'provincial':
                if ($_SESSION['cart_zone_id'] == STORE_ZONE) $pass = true; break; // BMH
            case 'national':
                if ($_SESSION['cart_country_id'] == STORE_COUNTRY) $pass = true; break; // BMH 
            case 'international':
                if ($_SESSION['cart_country_id'] != STORE_COUNTRY) $pass = true; break; // BMH
            case 'all':
                $pass = true; break;
            default:
                $pass = false; break;
        }
             
        $enabled = $pass; 
        }
        return $enabled;
    }
    
    // function for checking handling fee of each product
    function handling_fee() {
        global $tax;
        global $db, $output, $order, $currencies;
        $tax = zen_get_tax_rate(MODULE_ORDER_TOTAL_HANDLINGFEE_TAX_CLASS, $order->delivery['country']['id'], $order->delivery['zone_id']);
        $tax_description = zen_get_tax_description(MODULE_ORDER_TOTAL_HANDLINGFEE_TAX_CLASS, $order->delivery['country']['id'], $order->delivery['zone_id']);    
        $EHF_total = 0;
        $products_cart = $_SESSION['cart']->get_products();         
        for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {	  
            $products_query = "SELECT products_id, products_EHF  
                                FROM " . TABLE_PRODUCTS . " 
                                WHERE products_id = '" . $order->products[$i]['id'] . "' 
                                ORDER BY products_id ASC"; 
            $products = $db->Execute($products_query);
            // BMH bof ****************
            if (MODULE_ORDER_TOTAL_HANDLINGFEE_CALCULATION == 'sum') {
                $products_EHF = $this->handling_fee_calculator($products->fields['products_EHF']) * $order->products[$i]['qty'];
                $EHF_total += $products_EHF;

                } else {
                    // get max rate
                    if ($this->handling_fee_calculator($products->fields['products_EHF']) > $EHF_total) {
                        $EHF_total = $this->handling_fee_calculator($products->fields['products_EHF']);
                    }
                 }
            if ($EHF_total > 0 && MODULE_ORDER_TOTAL_HANDLINGFEE_CALCULATION == 'sum') {
                // calculate tax
                    $order->total_tax += zen_calculate_tax($EHF_total, $tax);
                    $order->total_cost += $products_EHF + zen_calculate_tax($products_EHF, $tax);
                }
        }  
        if (MODULE_ORDER_TOTAL_HANDLINGFEE_CALCULATION == 'max') {
                    // calculate tax          
                    $order->total_tax += zen_calculate_tax($EHF_total, $tax);
                    $order->total_cost += $EHF_total + zen_calculate_tax($EHF_total, $tax);
        }

        // BMH eof *********************

        return $EHF_total;  
    } 
 
    function handling_fee_calculator($products_EHF) {
        if(!function_exists('array_combine')) {
            function array_combine($a, $b) {
                $c = array();
                $at = array_values($a);
                $bt = array_values($b);
                foreach($at as $key=>$aval) $c[$aval] = $bt[$key];
                return $c;
            }
        }
        $fees_descriptions = array_combine(explode(",", MODULE_ORDER_TOTAL_HANDLINGFEE_FEES), explode(",", MODULE_ORDER_TOTAL_HANDLINGFEE_DESCRIPTIONS));
        foreach ($fees_descriptions as $key => $value) {
            if (trim(strtolower($products_EHF)) == trim(strtolower($value))) {
            return $key;
            }
        }

    }
        
    // eof - handling fee module edit [3 of 3]
    //********************** ++++++++++++++++++++++++
}
