<?php
if(!defined('MODULE_ORDER_TOTAL_HANDLINGFEE_FEES') && !defined('MODULE_ORDER_TOTAL_HANDLINGFEE_DESCRIPTIONS')) return;
$data = array_combine(explode(",", MODULE_ORDER_TOTAL_HANDLINGFEE_FEES), explode(",", MODULE_ORDER_TOTAL_HANDLINGFEE_DESCRIPTIONS));
if (count($data) > 0) {
  foreach ($data as $key => $val) {
    $handling_fee_array[] = array('id' => $val, 'text' => $val);
  }
}

?>

          <div class="form-group">
              <label for="discontinue" class="col-sm-3 control-label"><?php echo TEXT_PRODUCTS_HANDLING_FEE; ?></label>            
              <div class="col-sm-9 col-md-6">
                <?php echo zen_draw_pull_down_menu('products_EHF', $handling_fee_array, $pInfo->products_EHF, "class='form-control'");?>
              </div>
          </div>