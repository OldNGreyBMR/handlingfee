<?php   
$tmp_value = zen_db_prepare_input($_POST['products_EHF'] ?? '');
$products_EHF = (!zen_not_null($tmp_value) || $tmp_value=='' || $tmp_value == 'none') ? 'none' : $tmp_value;
// eof