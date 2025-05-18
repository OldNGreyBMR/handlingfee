<?php
//
// +----------------------------------------------------------------------+
// |zen-cart Open Source E-commerce                                       |
// +----------------------------------------------------------------------+
// | Copyright (c) 2007 Numinix Technology                                |
// |   http://www.numinix.com                                             |
// |                                                                      |   
// | Portions Copyright (c) 2003 osCommerce                               |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the GPL license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available through the world-wide-web at the following url:           |
// | http://www.zen-cart.com/license/2_0.txt.                             |
// | If you did not receive a copy of the zen-cart license and are unable |
// | to obtain it through the world-wide-web, please send a note to       |
// | license@zen-cart.com so we can mail you a copy immediately.          |
// +----------------------------------------------------------------------+
// $Id: ot_handlingfee.php 1011 2007-09-05 14:06:59Z numinix $
// BMH changes for PHP 8.2 2025-05-14 ln40, 62, 63, 64, 68, 87, 108, 120

class ot_handlingfee {
    public $code;                   // $code determines the internal 'code' name used to designate "this" payment module
    public $description;            // $description is a soft name for this payment method  @var string
    public $output =[];             // $output is an array of the display elements used on checkout pages
    public $sort_order;             // $sort_order is the order priority of this payment module when displayed  @var int
    public $title;                  // $title is the displayed name for this order total method  @var string
    public $shipping_method;
    public $include_methods;
    public $exclude_methods;
    
    protected $_check;              // $_check is used to check the configuration key set up @var int
    protected $_enabled;
        
    function __construct() {
        global $order;
        $this->code = 'ot_handlingfee';
        $this->title = MODULE_ORDER_TOTAL_HANDLINGFEE_TITLE;
        $this->description = MODULE_ORDER_TOTAL_HANDLINGFEE_DESCRIPTION;
        // BMH $this->enabled = (defined('MODULE_ORDER_TOTAL_HANDLINGFEE_STATUS') && MODULE_ORDER_TOTAL_HANDLINGFEE_STATUS == 'true');
        $this->sort_order = (defined('MODULE_ORDER_TOTAL_HANDLINGFEE_SORT_ORDER')) ? MODULE_ORDER_TOTAL_HANDLINGFEE_SORT_ORDER : null;
        if ($this->_enabled) {
            $this->shipping_method = (!empty($_SESSION['shipping'])) ? explode("_", $_SESSION['shipping']['id']) : ""; // storepickup_storepickup
            $this->shipping_method = ($order !== null) ? $order->info['shipping_method'] : ""; //$this->shipping_method[0]; // storepickup
            $this->include_methods = explode(",", MODULE_ORDER_TOTAL_HANDLINGFEE_INCLUDE_SHIPPING);
            $this->exclude_methods = explode(",", MODULE_ORDER_TOTAL_HANDLINGFEE_EXCLUDE_SHIPPING);
            if (MODULE_ORDER_TOTAL_HANDLINGFEE_INCLUDE_SHIPPING != '') {
                if (in_array($this->shipping_method, $this->include_methods)) {
                    $this->_enabled = true;
                } else {
                    $this->_enabled = false;
                }
            } elseif (MODULE_ORDER_TOTAL_HANDLINGFEE_EXCLUDE_SHIPPING != '') {
                if (in_array($this->shipping_method, $this->exclude_methods)) {
                    $this->_enabled = false;
                } else {
                    $this->_enabled = true;
                }
            }
      }
      $this->output = array();
      $handling_fee = '';       // BMH
      $key = '';                // BMH 
      $group_discountfee = 0;   // BMH
    }
    
    function process() {
        global $order, $currencies, $db, $handling_fee, $ot_handlingfee, $order_totals, $ot_total; // BMH
      
        if ($this->_enabled) {
            if(!function_exists('handling_fee_calculator')) {
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
                           // BMH unreachable code break;
                        }
                    }
                }
            }	      	      
	        $pass = false;
	        
            switch (MODULE_ORDER_TOTAL_HANDLINGFEE_DESTINATION) {
                case 'provincial':
                    if ($order->delivery['zone_id'] == STORE_ZONE) $pass = true; break;
                case 'national':
                    if ($order->delivery['country_id'] == STORE_COUNTRY) $pass = true; break;
                case 'international':
                    if ($order->delivery['country_id'] != STORE_COUNTRY) $pass = true; break;
                case 'all':
                    $pass = true; break;
                default:
                    $pass = false; break;
            }
        
            if ($pass == true) { 
                echo '<br> ln121 @pass==true'; // BMH 
                print_r("<br> ln121 @pass==true");
                $tax = zen_get_tax_rate(MODULE_ORDER_TOTAL_HANDLINGFEE_TAX_CLASS, $order->delivery['country']['id'], $order->delivery['zone_id']);
                $tax_description = zen_get_tax_description(MODULE_ORDER_TOTAL_HANDLINGFEE_TAX_CLASS, $order->delivery['country']['id'], $order->delivery['zone_id']);    
                $EHF_total = 0;
                for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {	   
                    $products_query = "SELECT products_id, products_EHF  
                                    FROM " . TABLE_PRODUCTS . " 
                                WHERE products_id = '" . $order->products[$i]['id'] . "'
                                ORDER BY products_id ASC";
                    $products = $db->Execute($products_query);
                    if (MODULE_ORDER_TOTAL_HANDLINGFEE_CALCULATION == 'sum') {
                        echo '<br> <br> ln131 CALCULATION=SUM'; // BMH
                        $products_EHF = handling_fee_calculator($products->fields['products_EHF']) * $order->products[$i]['qty'];
                        $EHF_total += $products_EHF;
                    } else {
                        // get max rate
                        if (handling_fee_calculator($products->fields['products_EHF']) > $EHF_total) {
                            $EHF_total = handling_fee_calculator($products->fields['products_EHF']);
                        }
                    }
                    if ($EHF_total > 0 && MODULE_ORDER_TOTAL_HANDLINGFEE_CALCULATION == 'sum') {
                    // calculate tax
                        $order->info['tax'] += zen_calculate_tax($products_EHF, $tax);
                        $order->info['tax_groups']["$tax_description"] += zen_calculate_tax($products_EHF, $tax);
                        $order->info['total'] += $products_EHF + zen_calculate_tax($products_EHF, $tax);
                    }
                }
                if (MODULE_ORDER_TOTAL_HANDLINGFEE_CALCULATION == 'max') {
                    // calculate tax
                    $order->info['tax'] += zen_calculate_tax($EHF_total, $tax);
                    $order->info['tax_groups']["$tax_description"] += zen_calculate_tax($EHF_total, $tax);
                    $order->info['total'] += $EHF_total + zen_calculate_tax($EHF_total, $tax);
                }
                if ($EHF_total > 0) {
                    $this->output[] = array('title' => $this->title . ':', 
                                            'text' => $currencies->format(zen_add_tax($EHF_total, $tax), true, $order->info['currency'], $order->info['currency_value']),
                                            'value' => zen_add_tax($EHF_total, $tax)); 
                }                     
            }  
        }
    }  

    function check() {
        global $db;
        if (!isset($this->_check)) {
            $check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_ORDER_TOTAL_HANDLINGFEE_STATUS'");
            $this->_check = $check_query->RecordCount();
        }

        return $this->_check;
    }

    function keys() {
        // Include MODULE_ORDER_TOTAL_HANDLINGFEE_HIDDEN to remove from older modules (prior to v1.2.1)
        return array('MODULE_ORDER_TOTAL_HANDLINGFEE_STATUS', 'MODULE_ORDER_TOTAL_HANDLINGFEE_SORT_ORDER', 'MODULE_ORDER_TOTAL_HANDLINGFEE_DESCRIPTIONS', 'MODULE_ORDER_TOTAL_HANDLINGFEE_FEES', 'MODULE_ORDER_TOTAL_HANDLINGFEE_INCLUDE_SHIPPING', 'MODULE_ORDER_TOTAL_HANDLINGFEE_EXCLUDE_SHIPPING', 'MODULE_ORDER_TOTAL_HANDLINGFEE_DESTINATION', 'MODULE_ORDER_TOTAL_HANDLINGFEE_HIDDEN', 'MODULE_ORDER_TOTAL_HANDLINGFEE_CALCULATION', 'MODULE_ORDER_TOTAL_HANDLINGFEE_TAX_CLASS');
    }

    function install() {
	    global $db, $sniffer;  
      
        (!$sniffer->field_exists(TABLE_PRODUCTS, 'products_EHF')) ? $db->Execute("ALTER TABLE products ADD products_EHF varchar(100) NOT NULL default 'none';") : false;
        
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Display Handling Fee', 'MODULE_ORDER_TOTAL_HANDLINGFEE_STATUS', 'true', 'Do you want to display the handling fee?', '6', '1','zen_cfg_select_option(array(\'true\', \'false\'), ', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort Order', 'MODULE_ORDER_TOTAL_HANDLINGFEE_SORT_ORDER', '250', 'Sort order of display.', '6', '2', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Handling Fee Groups', 'MODULE_ORDER_TOTAL_HANDLINGFEE_DESCRIPTIONS', 'none', 'Example: none,monitors,notebooks', '6', '4', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Handling Fees', 'MODULE_ORDER_TOTAL_HANDLINGFEE_FEES', '0', 'Retain same order as above. Example: 0,12,5', '6', '5', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Include Shipping', 'MODULE_ORDER_TOTAL_HANDLINGFEE_INCLUDE_SHIPPING', 'fedexexpress', 'Restrict shipping methods to (list code names separated by commas):', '6', '6', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Exclude Shipping', 'MODULE_ORDER_TOTAL_HANDLINGFEE_EXCLUDE_SHIPPING', 'storepickup', 'Include all shipping methods except (list code names separated by commas):', '6', '7', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Attach Handling Fee On Orders Made', 'MODULE_ORDER_TOTAL_HANDLINGFEE_DESTINATION', 'all', 'Attach handling fee for orders sent to the set destination.', '6', '8', 'zen_cfg_select_option(array(\'provincial\', \'national\', \'international\', \'all\'), ', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Calculation Method', 'MODULE_ORDER_TOTAL_HANDLINGFEE_CALCULATION', 'sum', 'Return handling fee as a sum of all products, or the highest handling fee?', '6', '10', 'zen_cfg_select_option(array(\'sum\', \'max\'), ', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Tax Class', 'MODULE_ORDER_TOTAL_HANDLINGFEE_TAX_CLASS', '0', 'Use the following tax class on the handling fee.', '6', '11', 'zen_get_tax_class_title', 'zen_cfg_pull_down_tax_classes(', now())");
    }

    function remove() {
	    global $db;    
        $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }
}
?>