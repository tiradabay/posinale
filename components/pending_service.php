<?php
require_once __DIR__.'/checkout_service.php';
require_once __DIR__.'/pending_schema.php';
class PendingError extends CheckoutError {
    public $reason;public $retryable;
    function __construct($message,$status=422,$reason='invalid_request',$retryable=false){parent::__construct($message,$status);$this->reason=$reason;$this->retryable=$retryable;}
}
function p4b_guard($db,$user,$role) {
    checkout_integer($user,'staff identity');
    if(!in_array($role,['admin','cashier'],true))throw new PendingError('Unauthorized role.',403,'forbidden');
    if(!p4b_enabled($db))throw new PendingError('Pending orders are not enabled. Migration and deployment approval are required.',503,'feature_disabled');
}
function p4b_uuid($v) {
    if(!is_string($v)||!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',$v))throw new PendingError('Valid request UUID required.');
    return $v;
}
function p4b_hash($v){return hash('sha256',json_encode($v,JSON_THROW_ON_ERROR));}
function p4b_quote_hash($v){if(!is_string($v)||!preg_match('/^[0-9a-f]{64}$/D',$v))throw new PendingError('Valid saved quote identifier required.');return$v;}
function p4b_items(array $data) {
    checkout_no_discount($data);
    if(($data['rewardID']??null)!==null)throw new PendingError('Reward redemption remains unavailable.');
    if(isset($data['paymentMethod'])&&$data['paymentMethod']!=='Cash')throw new PendingError('Only Cash is supported in Phase4B.');
    foreach(['addons','addOns','flavor','flavors']as$field)if(isset($data[$field])&&!in_array($data[$field],['',[]],true))throw new PendingError('Flavors and add-ons are not enabled.');
    $items=$data['items']??null;
    if(!is_array($items)||count($items)<1||count($items)>200||array_keys($items)!==range(0,count($items)-1))throw new PendingError('Provide 1 to 200 order items.');
    $out=[];
    foreach($items as$item) {
        if(!is_array($item))throw new PendingError('Invalid order item.');
        checkout_no_discount($item);
        foreach(['addons','addOns','flavor','flavors']as$field)if(isset($item[$field])&&!in_array($item[$field],['',[]],true))throw new PendingError('Flavors and add-ons are not enabled.');
        $id=checkout_integer($item['productID']??null,'product');
        $qty=checkout_integer($item['quantity']??null,'quantity',1000000);
        $out[$id]=($out[$id]??0)+$qty;
        if($out[$id]>1000000)throw new PendingError('Quantity exceeds limit.');
    }
    ksort($out,SORT_NUMERIC);return$out;
}
function p4b_customer($data){return($data['customerID']??null)===null?null:checkout_integer($data['customerID'],'customer');}
function p4b_reason($data) {
    $reason=$data['reason']??null;
    if(!is_string($reason)||preg_match('//u',$reason)!==1||preg_match('/[\x00-\x1f\x7f]/u',$reason)||trim($reason)===''||(function_exists('mb_strlen')?mb_strlen($reason,'UTF-8'):strlen($reason))>255)throw new PendingError('A cancellation/replacement reason of 1–255 characters is required.');
    return trim($reason);
}
function p4b_order($db,$id,$user,$role,$lock=false) {
    $row=checkout_query($db,'SELECT *,UNIX_TIMESTAMP(Date) AS CreatedEpoch FROM orders WHERE OrderID=?'.($lock?' FOR UPDATE':''),'i',[$id])->get_result()->fetch_assoc();
    if(!$row||($role!=='admin'&&(int)$row['UserID']!==(int)$user))throw new PendingError('Order not found.',404,'not_found');
    if((int)$row['SnapshotVersion']!==2)throw new PendingError('This legacy order has no Pending snapshot. Manual review is required.',409,'legacy_order');
    return$row;
}
function p4b_unpaid($row) {
    if($row['Status']!=='pending')throw new PendingError($row['Status']==='completed'?'Order already paid. Do not collect another payment.':'Order is cancelled.',409,$row['Status']==='completed'?'already_paid':'already_cancelled');
}
// Products -> recipes -> ascending ingredient locks, matching Phase2/recipe editors.
// This validates configuration, not availability; saving does not reserve stock.
function p4b_build_quote($db,$quantities,$customer,$frozenName=null,$useFrozenName=false) {
    $products=[];$items=[];$materials=[];$subtotal=0;
    foreach($quantities as$id=>$qty) {
        $p=checkout_query($db,'SELECT ProductID,Name,Size,Price,isRecipe,isDeleted,ConfigVersion FROM products WHERE ProductID=? FOR UPDATE','i',[$id])->get_result()->fetch_assoc();
        if(!$p||(int)$p['isDeleted']!==0||!in_array((int)$p['isRecipe'],[0,1],true))throw new PendingError('Product unavailable or incorrectly configured.',409,'configuration_invalid');
        $products[$id]=$p;
    }
    foreach($quantities as$id=>$qty) {
        $p=$products[$id];$price=checkout_scaled($p['Price'],'product price');$line=$price*$qty;$subtotal+=$line;
        if($subtotal>9999999999)throw new PendingError('Order exceeds supported amount.');
        $recipe=checkout_query($db,'SELECT RawMatID,QtyUsed FROM productmaterial WHERE ProductID=? ORDER BY RawMatID FOR UPDATE','i',[$id])->get_result()->fetch_all(MYSQLI_ASSOC);
        if(((int)$p['isRecipe']===1&&!$recipe)||((int)$p['isRecipe']===0&&$recipe))throw new PendingError('Product recipe configuration is incomplete.',409,'configuration_invalid');
        $ingredients=[];
        foreach($recipe as$r){$used=checkout_scaled($r['QtyUsed'],'recipe quantity');if($used<=0)throw new PendingError('Invalid recipe quantity.',409,'configuration_invalid');$rid=(int)$r['RawMatID'];$materials[$rid]=true;$ingredients[]=['rawMatID'=>$rid,'qty'=>checkout_decimal($used),'unit'=>null];}
        $items[]=['productID'=>(int)$id,'name'=>$p['Name'],'size'=>$p['Size'],'quantity'=>$qty,'unitPrice'=>checkout_decimal($price),'subtotal'=>checkout_decimal($line),'configVersion'=>(string)$p['ConfigVersion'],'stockMode'=>(int)$p['isRecipe']===1?'recipe':'product','ingredients'=>$ingredients];
    }
    ksort($materials,SORT_NUMERIC);$units=[];
    foreach($materials as$id=>$_){$r=checkout_query($db,'SELECT Unit FROM rawmaterial WHERE RawMatID=? FOR UPDATE','i',[$id])->get_result()->fetch_assoc();if(!$r||!in_array($r['Unit'],['mg','g','kg','ml','l','pcs'],true))throw new PendingError('Invalid ingredient unit.',409,'configuration_invalid');$units[$id]=$r['Unit'];}
    foreach($items as&$item)foreach($item['ingredients']as&$ingredient)$ingredient['unit']=$units[$ingredient['rawMatID']];unset($item,$ingredient);
    $name=null;
    if($customer!==null){$r=checkout_query($db,'SELECT Name FROM customer WHERE CustomerID=?','i',[$customer])->get_result()->fetch_assoc();if(!$r)throw new PendingError('Customer not found.',409,'customer_missing');$name=$useFrozenName?$frozenName:$r['Name'];}
    $tax=intdiv($subtotal*12+50,100);if($subtotal+$tax>9999999999)throw new PendingError('Order exceeds supported amount.');
    return['customerID'=>$customer,'customerName'=>$name,'items'=>$items,'subtotal'=>checkout_decimal($subtotal),'tax'=>checkout_decimal($tax),'total'=>checkout_decimal($subtotal+$tax),'taxRateBps'=>1200];
}
function p4b_snapshot($db,$order) {
    $items=[];$subtotal=0;
    $rows=checkout_query($db,'SELECT d.OrderDetailID,d.ProductID,d.Quantity,d.Subtotal,s.ProductNameSnapshot,s.SizeSnapshot,s.ProductConfigVersion,s.StockMode,s.UnitPrice,s.BaseSubtotal FROM orderdetails d LEFT JOIN order_item_snapshots s ON s.OrderDetailID=d.OrderDetailID WHERE d.OrderID=? ORDER BY d.ProductID,d.OrderDetailID','i',[(int)$order['OrderID']])->get_result()->fetch_all(MYSQLI_ASSOC);
    checkout_receipt_require(count($rows)>0);$seen=[];
    foreach($rows as$r) {
        $id=(int)$r['ProductID'];checkout_receipt_require($r['ProductNameSnapshot']!==null&&!isset($seen[$id])&&(int)$r['Quantity']>0);$seen[$id]=true;
        $line=checkout_receipt_money($r['Subtotal']);checkout_receipt_require($line===checkout_receipt_money($r['UnitPrice'])*(int)$r['Quantity']&&$line===checkout_receipt_money($r['BaseSubtotal']));$subtotal+=$line;
        $ingredients=[];$rs=checkout_query($db,'SELECT RawMatID,QtyPerServing,UnitSnapshot FROM order_item_ingredients WHERE OrderDetailID=? ORDER BY RawMatID','i',[(int)$r['OrderDetailID']])->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach($rs as$x){checkout_receipt_require(checkout_receipt_money($x['QtyPerServing'])>0);$ingredients[]=['rawMatID'=>(int)$x['RawMatID'],'qty'=>$x['QtyPerServing'],'unit'=>$x['UnitSnapshot']];}
        checkout_receipt_require(($r['StockMode']==='recipe'&&count($ingredients)>0)||($r['StockMode']==='product'&&count($ingredients)===0));
        $items[]=['productID'=>$id,'name'=>$r['ProductNameSnapshot'],'size'=>$r['SizeSnapshot'],'quantity'=>(int)$r['Quantity'],'unitPrice'=>$r['UnitPrice'],'subtotal'=>$r['Subtotal'],'configVersion'=>(string)$r['ProductConfigVersion'],'stockMode'=>$r['StockMode'],'ingredients'=>$ingredients];
    }
    $tax=intdiv($subtotal*12+50,100);
    checkout_receipt_require($order['Subtotal']===checkout_decimal($subtotal)&&$order['TaxAmount']===checkout_decimal($tax)&&$order['TotalAmount']===checkout_decimal($subtotal+$tax)&&(int)$order['TaxRateBps']===1200);
    $q=['customerID'=>$order['CustomerID']===null?null:(int)$order['CustomerID'],'customerName'=>$order['CustomerNameSnapshot'],'items'=>$items,'subtotal'=>$order['Subtotal'],'tax'=>$order['TaxAmount'],'total'=>$order['TotalAmount'],'taxRateBps'=>1200];
    checkout_receipt_require(hash_equals($order['QuoteHash'],p4b_hash($q)));return$q;
}
function p4b_public_quote($q) {
    $items=[];foreach($q['items']as$item)$items[]=['productID'=>$item['productID'],'name'=>$item['name'],'size'=>$item['size'],'quantity'=>$item['quantity'],'unitPrice'=>$item['unitPrice'],'subtotal'=>$item['subtotal']];
    return['items'=>$items,'customerName'=>$q['customerName'],'subtotal'=>$q['subtotal'],'tax'=>$q['tax'],'total'=>$q['total'],'quoteHash'=>p4b_hash($q)];
}
function p4b_summary($db,$row) {
    return ['orderID'=>(int)$row['OrderID'],'ownerID'=>(int)$row['UserID'],'status'=>$row['Status'],'paymentMethod'=>null,'createdAt'=>checkout_receipt_time($row['CreatedEpoch'])->format(DateTimeInterface::ATOM),'replacesOrderID'=>$row['ReplacesOrderID']===null?null:(int)$row['ReplacesOrderID']]+p4b_public_quote(p4b_snapshot($db,$row));
}
function p4b_store($db,$owner,$q,$replaces=null) {
    $hash=p4b_hash($q);$customer=$q['customerID'];$name=$q['customerName'];
    checkout_query($db,"INSERT INTO orders(UserID,CustomerID,TotalAmount,Status,SnapshotVersion,Subtotal,TaxAmount,TaxRateBps,QuoteHash,CustomerNameSnapshot,ReplacesOrderID) VALUES(?,?,?,'pending',2,?,?,1200,?,?,?)",'iisssssi',[$owner,$customer,$q['total'],$q['subtotal'],$q['tax'],$hash,$name,$replaces]);
    $order=(int)$db->insert_id;
    foreach($q['items']as$item) {
        checkout_query($db,'INSERT INTO orderdetails(OrderID,ProductID,Quantity,Subtotal) VALUES(?,?,?,?)','iiis',[$order,$item['productID'],$item['quantity'],$item['subtotal']]);$detail=(int)$db->insert_id;
        checkout_query($db,'INSERT INTO order_item_snapshots(OrderDetailID,ProductNameSnapshot,SizeSnapshot,ProductConfigVersion,StockMode,UnitPrice,BaseSubtotal) VALUES(?,?,?,?,?,?,?)','issssss',[$detail,$item['name'],$item['size'],$item['configVersion'],$item['stockMode'],$item['unitPrice'],$item['subtotal']]);
        foreach($item['ingredients']as$r)checkout_query($db,'INSERT INTO order_item_ingredients(OrderDetailID,RawMatID,QtyPerServing,UnitSnapshot) VALUES(?,?,?,?)','iiss',[$detail,$r['rawMatID'],$r['qty'],$r['unit']]);
    }
    return$order;
}
function p4b_reserve($db,$table,$key,$user,$operation,$intent) {
    $hash=p4b_hash($intent);
    try {
        if($table==='order_requests')checkout_query($db,'INSERT INTO order_requests(RequestID,UserID,Operation,RequestHash) VALUES(?,?,?,?)','siss',[$key,$user,$operation,$hash]);
        elseif($table==='checkout_requests')checkout_query($db,'INSERT INTO checkout_requests(RequestID,UserID,RequestHash,IntentVersion) VALUES(?,?,?,2)','sis',[$key,$user,$hash]);
        else throw new LogicException('Invalid journal.');
    }catch(mysqli_sql_exception$e) {
        if($e->getCode()!==1062)throw$e;
        $old=checkout_query($db,'SELECT * FROM '.$table.' WHERE RequestID=? FOR UPDATE','s',[$key])->get_result()->fetch_assoc();
        if(!$old||(int)$old['UserID']!==(int)$user||(int)$old['IntentVersion']!==2||!hash_equals($old['RequestHash'],$hash)||($table==='order_requests'&&$old['Operation']!==$operation))throw new PendingError('Request identifier belongs to another operation.',409,'request_conflict');
        if($old['OrderID']===null||$old['ResponseJSON']===null)throw new PendingError('Result unavailable. Retry the same request.',503,'result_unavailable',true);
        return json_decode($old['ResponseJSON'],true,512,JSON_THROW_ON_ERROR);
    }
    return null;
}
function p4b_finish($db,$table,$key,$order,$response) {
    if(!in_array($table,['order_requests','checkout_requests'],true))throw new LogicException('Invalid journal.');
    checkout_query($db,'UPDATE '.$table.' SET OrderID=?,ResponseJSON=? WHERE RequestID=?','iss',[$order,json_encode($response,JSON_THROW_ON_ERROR),$key]);
}
function p4b_action(mysqli$db,$user,$role,array$data) {
    p4b_guard($db,$user,$role);$user=(int)$user;$operation=$data['operation']??null;$key=p4b_uuid($data['requestID']??null);
    if($operation==='pay_pending')return p4b_pay($db,$user,$role,$data);
    if(!in_array($operation,['create_pending','cancel_pending','replace_pending'],true))throw new PendingError('Unsupported Pending action.');
    if($operation==='create_pending'){$quantities=p4b_items($data);$customer=p4b_customer($data);$intent=['operation'=>$operation,'items'=>$quantities,'customerID'=>$customer];}
    else{$id=checkout_integer($data['orderID']??null,'order');$reason=p4b_reason($data);$intent=['operation'=>$operation,'orderID'=>$id,'reason'=>$reason];if($operation==='replace_pending')$intent['quoteHash']=p4b_quote_hash($data['quoteHash']??null);}
    $db->begin_transaction();
    try {
        $replay=p4b_reserve($db,'order_requests',$key,$user,$operation,$intent);
        if($replay!==null){$db->commit();return$replay;}
        if($operation==='create_pending'){$q=p4b_build_quote($db,$quantities,$customer);$id=p4b_store($db,$user,$q);}
        else {
            $old=p4b_order($db,$id,$user,$role,true);p4b_unpaid($old);$saved=p4b_snapshot($db,$old);
            if($operation==='replace_pending') {
                $quantities=[];foreach($saved['items']as$item)$quantities[$item['productID']]=$item['quantity'];
                $q=p4b_build_quote($db,$quantities,$saved['customerID']);
                if(!hash_equals(p4b_hash($q),$intent['quoteHash']))throw new PendingError('Configuration changed after preview. Review the replacement again.',409,'quote_outdated');
                if(hash_equals(p4b_hash($q),$old['QuoteHash']))throw new PendingError('The saved quote is current; replacement is unnecessary.',409,'quote_current');
                $newID=p4b_store($db,(int)$old['UserID'],$q,$id);
            }
            checkout_query($db,"UPDATE orders SET Status='cancelled',CancelledAt=UTC_TIMESTAMP(6),CancelledBy=?,CancellationReason=? WHERE OrderID=? AND Status='pending'",'isi',[$user,$reason,$id]);
            if($operation==='replace_pending')$id=$newID;
        }
        $row=p4b_order($db,$id,$user,$role);$result=['success'=>true,'order'=>p4b_summary($db,$row)];
        p4b_finish($db,'order_requests',$key,$id,$result);$db->commit();return$result;
    }catch(Throwable$e){$db->rollback();throw$e;}
}
function p4b_preview($db,$user,$role,$id) {
    p4b_guard($db,$user,$role);$db->begin_transaction();
    try{$row=p4b_order($db,$id,$user,$role,true);p4b_unpaid($row);$saved=p4b_snapshot($db,$row);$quantities=[];foreach($saved['items']as$item)$quantities[$item['productID']]=$item['quantity'];$q=p4b_build_quote($db,$quantities,$saved['customerID']);$result=['success'=>true,'original'=>p4b_summary($db,$row),'replacement'=>p4b_public_quote($q),'outdated'=>!hash_equals($row['QuoteHash'],p4b_hash($q))];$db->commit();return$result;}catch(Throwable$e){$db->rollback();throw$e;}
}
function p4b_list($db,$user,$role,$before=null) {
    p4b_guard($db,$user,$role);$sql="SELECT *,UNIX_TIMESTAMP(Date) AS CreatedEpoch FROM orders WHERE SnapshotVersion=2 AND Status='pending'";$args=[];$types='';
    if($role!=='admin'){$sql.=' AND UserID=?';$args[]=(int)$user;$types.='i';}
    if($before!==null){$sql.=' AND OrderID<?';$args[]=checkout_integer($before,'cursor');$types.='i';}
    $rs=checkout_query($db,$sql.' ORDER BY OrderID DESC LIMIT 51',$types,$args)->get_result()->fetch_all(MYSQLI_ASSOC);$more=count($rs)>50;$rs=array_slice($rs,0,50);$out=[];foreach($rs as$row)$out[]=p4b_summary($db,$row);
    return['success'=>true,'orders'=>$out,'nextCursor'=>$more?(int)end($rs)['OrderID']:null];
}
function p4b_payment_intent($id,$quoteHash,$cash) {return['operation'=>'pay_pending','intentVersion'=>2,'orderID'=>$id,'quoteHash'=>$quoteHash,'cashReceived'=>checkout_decimal($cash)];}
function p4b_requirements($q) {
    $required=[];foreach($q['items']as$item)foreach($item['ingredients']as$r){$used=checkout_scaled($r['qty'],'snapshot usage');if($used>intdiv(9999999999,$item['quantity']))throw new PendingError('Insufficient ingredient stock.',409,'insufficient_stock');$id=$r['rawMatID'];$required[$id]=($required[$id]??0)+$used*$item['quantity'];if($required[$id]>9999999999)throw new PendingError('Insufficient ingredient stock.',409,'insufficient_stock');}ksort($required,SORT_NUMERIC);return$required;
}
function p4b_pay($db,$user,$role,$data) {
    p4b_guard($db,$user,$role);$key=p4b_uuid($data['requestID']??null);$id=checkout_integer($data['orderID']??null,'order');$hash=p4b_quote_hash($data['quoteHash']??null);$cash=checkout_scaled($data['cashReceived']??null,'cash received');checkout_no_discount($data);
    if(($data['paymentMethod']??'Cash')!=='Cash'||($data['rewardID']??null)!==null)throw new PendingError('Only Cash without reward redemption is supported.');
    $intent=p4b_payment_intent($id,$hash,$cash);$db->begin_transaction();
    try {
        $replay=p4b_reserve($db,'checkout_requests',$key,$user,'pay_pending',$intent);
        if($replay!==null){$response=p4b_receipt($db,$key,$user,$role)['receipt'];$db->commit();return$response;}
        $row=p4b_order($db,$id,$user,$role,true);p4b_unpaid($row);$q=p4b_snapshot($db,$row);
        if(!hash_equals($row['QuoteHash'],$hash))throw new PendingError('Saved quote identifier does not match.',409,'quote_outdated');
        $quantities=[];foreach($q['items']as$item)$quantities[$item['productID']]=$item['quantity'];
        try{$current=p4b_build_quote($db,$quantities,$q['customerID'],$q['customerName'],true);}catch(PendingError$e){throw new PendingError('Product/recipe configuration changed. Review and replace the Pending order.',409,'quote_outdated');}
        if(!hash_equals(p4b_hash($current),$hash))throw new PendingError('Prices or recipe configuration changed. Explicitly review and replace the Pending order.',409,'quote_outdated');
        $required=p4b_requirements($q);
        foreach($q['items']as$item)if($item['stockMode']==='product'){$stock=checkout_query($db,'SELECT QtyStock FROM products WHERE ProductID=? FOR UPDATE','i',[$item['productID']])->get_result()->fetch_row()[0];if((int)$stock<$item['quantity'])throw new PendingError('Insufficient product stock.',409,'insufficient_stock');}
        foreach($required as$rid=>$qty){$r=checkout_query($db,'SELECT StockAvailable FROM rawmaterial WHERE RawMatID=? FOR UPDATE','i',[$rid])->get_result()->fetch_assoc();if(!$r||checkout_scaled($r['StockAvailable'],'ingredient stock')<$qty)throw new PendingError('Insufficient ingredient stock.',409,'insufficient_stock');}
        $total=checkout_scaled($q['total'],'total');if($cash<$total)throw new PendingError('Insufficient Cash. Required PHP '.$q['total'].'.',422,'insufficient_cash');
        $points=0;$remaining=null;$customer=$q['customerID'];
        if($customer!==null){$c=checkout_query($db,'SELECT LoyaltyPoints FROM customer WHERE CustomerID=? FOR UPDATE','i',[$customer])->get_result()->fetch_assoc();if(!$c)throw new PendingError('Customer unavailable.',409);$points=intdiv(checkout_scaled($q['subtotal'],'subtotal'),2000);$remaining=(int)$c['LoyaltyPoints']+$points;if($remaining>2147483647)throw new PendingError('Points balance exceeds supported limit.',409);}
        checkout_query($db,"INSERT INTO payments(OrderID,Method,Amount,PaidAt,ConfirmedBy) VALUES(?,'Cash',?,UTC_TIMESTAMP(6),?)",'isi',[$id,$q['total'],$user]);
        foreach($required as$rid=>$qty){$d=checkout_decimal($qty);$s=checkout_query($db,'UPDATE rawmaterial SET StockAvailable=StockAvailable-? WHERE RawMatID=? AND StockAvailable>=?','sis',[$d,$rid,$d]);if($s->affected_rows!==1)throw new PendingError('Ingredient stock changed.',409,'insufficient_stock');}
        foreach($q['items']as$item)if($item['stockMode']==='product'){$s=checkout_query($db,'UPDATE products SET QtyStock=QtyStock-? WHERE ProductID=? AND QtyStock>=?','iii',[$item['quantity'],$item['productID'],$item['quantity']]);if($s->affected_rows!==1)throw new PendingError('Product stock changed.',409,'insufficient_stock');}
        if($customer!==null&&$points>0)checkout_query($db,'UPDATE customer SET LoyaltyPoints=LoyaltyPoints+? WHERE CustomerID=?','ii',[$points,$customer]);
        checkout_query($db,"UPDATE orders SET Status='completed' WHERE OrderID=? AND Status='pending'",'i',[$id]);
        $paidAt=checkout_query($db,'SELECT PaidAt FROM payments WHERE OrderID=?','i',[$id])->get_result()->fetch_row()[0];$epoch=(new DateTimeImmutable($paidAt,new DateTimeZone('UTC')))->getTimestamp();$stamp=checkout_receipt_time($epoch);$public=p4b_public_quote($q);
        $response=['success'=>true,'receiptVersion'=>2,'orderID'=>$id,'paymentMethod'=>'Cash','pointsEarned'=>$points,'remainingPoints'=>$remaining,'customerName'=>$q['customerName'],'date'=>$stamp->format('Y-m-d H:i:s'),'timestamp'=>$stamp->format(DateTimeInterface::ATOM),'createdAt'=>checkout_receipt_time($row['CreatedEpoch'])->format(DateTimeInterface::ATOM),'items'=>$public['items'],'subtotal'=>$q['subtotal'],'tax'=>$q['tax'],'total'=>$q['total'],'cashReceived'=>checkout_decimal($cash),'change'=>checkout_decimal($cash-$total),'quoteHash'=>$hash];
        p4b_finish($db,'checkout_requests',$key,$id,$response);$response=p4b_receipt($db,$key,$user,$role)['receipt'];$db->commit();return$response;
    }catch(Throwable$e){$db->rollback();throw$e;}
}
function p4b_receipt($db,$key,$user,$role) {
    if(!in_array($role,['admin','cashier'],true))throw new PendingError('Unauthorized receipt role.',403);
    $key=p4b_uuid($key);$j=checkout_query($db,'SELECT * FROM checkout_requests WHERE RequestID=?','s',[$key])->get_result()->fetch_assoc();
    if(!$j||(int)$j['IntentVersion']!==2)throw new PendingError('Receipt not found.',404);
    $row=p4b_order($db,(int)$j['OrderID'],$user,$role);checkout_receipt_require($row['Status']==='completed');$q=p4b_snapshot($db,$row);
    $pay=checkout_query($db,'SELECT * FROM payments WHERE OrderID=?','i',[(int)$row['OrderID']])->get_result()->fetch_assoc();
    checkout_receipt_require($pay&&$pay['Method']==='Cash'&&$pay['Amount']===$q['total']&&$pay['PaidAt']!==null&&(int)$pay['ConfirmedBy']===(int)$j['UserID']);
    try{$res=json_decode($j['ResponseJSON'],true,512,JSON_THROW_ON_ERROR);}catch(JsonException$e){checkout_receipt_require(false);}
    checkout_receipt_require(is_array($res)&&($res['success']??false)===true&&($res['receiptVersion']??null)===2&&($res['orderID']??null)===(int)$row['OrderID']&&($res['paymentMethod']??null)==='Cash');
    $public=p4b_public_quote($q);foreach(['items','subtotal','tax','total','quoteHash','customerName']as$field)checkout_receipt_require(($res[$field]??null)===($public[$field]??null));
    $cash=checkout_receipt_money($res['cashReceived']??null);$total=checkout_receipt_money($q['total']);checkout_receipt_require($cash>=$total&&checkout_receipt_money($res['change']??null)===$cash-$total);
    checkout_receipt_require(hash_equals($j['RequestHash'],p4b_hash(p4b_payment_intent((int)$row['OrderID'],$row['QuoteHash'],$cash))));
    $epoch=(new DateTimeImmutable($pay['PaidAt'],new DateTimeZone('UTC')))->getTimestamp();$stamp=checkout_receipt_time($epoch);
    checkout_receipt_require(($res['date']??null)===$stamp->format('Y-m-d H:i:s')&&($res['timestamp']??null)===$stamp->format(DateTimeInterface::ATOM)&&($res['createdAt']??null)===checkout_receipt_time($row['CreatedEpoch'])->format(DateTimeInterface::ATOM));
    return['success'=>true,'receipt'=>$res,'timestamp'=>$stamp->format(DateTimeInterface::ATOM),'timezone'=>'Asia/Manila'];
}
