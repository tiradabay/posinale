<?php
// No activation by schema alone. The deployment flag is off by default.
function p4b_schema_ready(mysqli $db) {
    static $ready=[];
    $key=$db->thread_id;
    if(!array_key_exists($key,$ready)) {
        $sql="SELECT (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN('order_requests','order_item_snapshots','order_item_ingredients'))+(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='orders' AND COLUMN_NAME='SnapshotVersion') OR (TABLE_NAME='products' AND COLUMN_NAME='ConfigVersion') OR (TABLE_NAME='payments' AND COLUMN_NAME='PaidAt') OR (TABLE_NAME='checkout_requests' AND COLUMN_NAME='IntentVersion'))) AS markers";
        $ready[$key]=(int)$db->query($sql)->fetch_row()[0]===7;
    }
    return $ready[$key];
}
function p4b_enabled(mysqli $db) { return getenv('POS_PENDING_ENABLED')==='1' && p4b_schema_ready($db); }
