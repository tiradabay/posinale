<?php
require_once __DIR__.'/pending_schema.php';
// Transaction owner for immediate, confirmed Cash payments only.
class CheckoutError extends RuntimeException {}
class CheckoutReceiptConflict extends CheckoutError {}
function checkout_integer($v,$label,$max=2147483647) {
    if((!is_int($v)&&!is_string($v))||!preg_match('/^[1-9][0-9]*$/D',(string)$v)||(float)$v>$max)throw new CheckoutError('Invalid '.$label.'.',422);
    return(int)$v;
}
function checkout_scaled($v,$label) {
    if((!is_int($v)&&!is_string($v))||!preg_match('/^(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?$/D',(string)$v))throw new CheckoutError('Invalid '.$label.'; use up to two decimal places.',422);
    $p=explode('.',(string)$v);return(int)$p[0]*100+(int)str_pad($p[1]??'',2,'0');
}
function checkout_decimal($v){return intdiv($v,100).'.'.str_pad((string)($v%100),2,'0',STR_PAD_LEFT);}
function checkout_intent(array $data) {
    $key=$data['requestID']??null;
    if(!is_string($key)||!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',$key))throw new CheckoutError('A valid checkout request identifier is required.',422);
    if(($data['paymentMethod']??null)!=='Cash')throw new CheckoutError('Only confirmed Cash payments are currently supported.',422);
    if(($data['rewardID']??null)!==null)throw new CheckoutError('Reward fulfillment is not yet supported by secure checkout. Deselect the reward to continue; no points have been redeemed.',422);
    checkout_no_discount($data);
    $items=$data['items']??null;
    if(!is_array($items)||count($items)<1||count($items)>200||array_keys($items)!==range(0,count($items)-1))throw new CheckoutError('Provide between 1 and 200 order items.',422);
    $quantities=[];
    foreach($items as$item){if(!is_array($item))throw new CheckoutError('Invalid order item.',422);checkout_no_discount($item);$id=checkout_integer($item['productID']??null,'product');$qty=checkout_integer($item['quantity']??null,'quantity',1000000);$quantities[$id]=($quantities[$id]??0)+$qty;if($quantities[$id]>1000000)throw new CheckoutError('Quantity is too large.',422);}
    ksort($quantities,SORT_NUMERIC);
    $customer=($data['customerID']??null)===null?null:checkout_integer($data['customerID'],'customer');
    $cash=checkout_scaled($data['cashReceived']??null,'cash received');
    $intent=['quantities'=>$quantities,'customerID'=>$customer,'paymentMethod'=>'Cash','cashReceived'=>checkout_decimal($cash)];
    return[$key,hash('sha256',json_encode($intent,JSON_THROW_ON_ERROR)),$quantities,$customer,$cash];
}
function checkout_query($db,$sql,$types='',$args=[]) {
    $s=$db->prepare($sql);if($types!=='')$s->bind_param($types,...$args);$s->execute();return$s;
}
function checkout_execute(mysqli $db,$userID,$role,array $data) {
    $userID=checkout_integer($userID,'staff identity');
    if(!in_array($role,['admin','cashier'],true))throw new CheckoutError('Unauthorized checkout role.',403);
    [$key,$hash,$quantities,$customer,$cash]=checkout_intent($data);
    $db->begin_transaction();
    try {
        try{checkout_query($db,'INSERT INTO checkout_requests(RequestID,UserID,RequestHash) VALUES(?,?,?)','sis',[$key,$userID,$hash]);}
        catch(mysqli_sql_exception $e){
            if($e->getCode()!==1062)throw$e;
            $old=checkout_query($db,'SELECT UserID,RequestHash,OrderID,ResponseJSON FROM checkout_requests WHERE RequestID=? FOR UPDATE','s',[$key])->get_result()->fetch_assoc();
            if(!$old||(int)$old['UserID']!==$userID||!hash_equals($old['RequestHash'],$hash))throw new CheckoutError('This request identifier belongs to another checkout attempt.',409);
            if($old['OrderID']===null||$old['ResponseJSON']===null)throw new CheckoutError('Checkout result is not ready. Retry the same attempt.',503);
            $response=checkout_saved_receipt($db,$key,$userID,$role)['receipt'];$db->commit();return$response;
        }
        // Product locks precede recipe rows and ascending ingredient locks.
        // Recipe editing also locks the parent product before changing its rows.
        $products=[];$subtotal=0;$requirements=[];$lines=[];
        foreach($quantities as$id=>$qty){
            $p=checkout_query($db,'SELECT ProductID,Name,Size,Price,QtyStock,isRecipe,isDeleted FROM products WHERE ProductID=? FOR UPDATE','i',[$id])->get_result()->fetch_assoc();
            if(!$p||(int)$p['isDeleted']!==0)throw new CheckoutError('A product is unavailable. Refresh products before retrying.',409);
            $price=checkout_scaled($p['Price'],'product price');$line=$price*$qty;$subtotal+=$line;
            if($subtotal>9999999999)throw new CheckoutError('Order exceeds the supported amount.',422);
            $products[$id]=$p;$lines[]=['productID'=>$id,'name'=>$p['Name'],'size'=>$p['Size'],'quantity'=>$qty,'unitPrice'=>checkout_decimal($price),'subtotal'=>checkout_decimal($line)];
        }
        foreach($quantities as$id=>$qty){
            $recipe=checkout_query($db,'SELECT RawMatID,QtyUsed FROM productmaterial WHERE ProductID=? ORDER BY RawMatID FOR UPDATE','i',[$id])->get_result()->fetch_all(MYSQLI_ASSOC);
            if((int)$products[$id]['isRecipe']===1){
                if(!$recipe)throw new CheckoutError('A recipe-based product has no configured ingredients. Ask Admin to configure its recipe.',409);
                foreach($recipe as$r){$used=checkout_scaled($r['QtyUsed'],'recipe quantity');if($used<=0)throw new CheckoutError('A recipe contains an invalid ingredient quantity.',409);$rid=(int)$r['RawMatID'];$requirements[$rid]=($requirements[$rid]??0)+$used*$qty;if($requirements[$rid]>9999999999)throw new CheckoutError('Insufficient ingredient stock.',409);}
            }else{
                if($recipe)throw new CheckoutError('Product stock configuration conflicts with its recipe. Ask Admin to review it.',409);
                if((int)$products[$id]['QtyStock']<$qty)throw new CheckoutError('Insufficient product stock.',409);
            }
        }
        ksort($requirements,SORT_NUMERIC);
        foreach($requirements as$id=>$needed){$r=checkout_query($db,'SELECT StockAvailable FROM rawmaterial WHERE RawMatID=? FOR UPDATE','i',[$id])->get_result()->fetch_assoc();if(!$r||checkout_scaled($r['StockAvailable'],'ingredient stock')<$needed)throw new CheckoutError('Insufficient ingredient stock.',409);}
        $tax=intdiv($subtotal*12+50,100);$total=$subtotal+$tax;
        if($total>9999999999)throw new CheckoutError('Order exceeds the supported amount.',422);
        if($cash<$total)throw new CheckoutError('Cash received is less than the server total of PHP '.checkout_decimal($total).'.',422);
        $customerRow=null;$points=0;$remaining=null;
        if($customer!==null){$customerRow=checkout_query($db,'SELECT Name,LoyaltyPoints FROM customer WHERE CustomerID=? FOR UPDATE','i',[$customer])->get_result()->fetch_assoc();if(!$customerRow)throw new CheckoutError('Customer no longer exists.',409);$points=intdiv($subtotal,2000);$remaining=(int)$customerRow['LoyaltyPoints']+$points;if($remaining>2147483647)throw new CheckoutError('Customer points balance exceeds the supported limit.',409);}
        $totalString=checkout_decimal($total);
        checkout_query($db,"INSERT INTO orders(UserID,CustomerID,TotalAmount,Status) VALUES(?,?,?,'completed')",'iis',[$userID,$customer,$totalString]);$order=(int)$db->insert_id;
        if(p4b_schema_ready($db))checkout_query($db,"INSERT INTO payments(OrderID,Method,Amount,PaidAt,ConfirmedBy) VALUES(?,'Cash',?,UTC_TIMESTAMP(6),?)",'isi',[$order,$totalString,$userID]);
        else checkout_query($db,"INSERT INTO payments(OrderID,Method,Amount) VALUES(?,'Cash',?)",'is',[$order,$totalString]);
        foreach($lines as$line)checkout_query($db,'INSERT INTO orderdetails(OrderID,ProductID,Quantity,Subtotal) VALUES(?,?,?,?)','iiis',[$order,$line['productID'],$line['quantity'],$line['subtotal']]);
        foreach($requirements as$id=>$needed){$decimal=checkout_decimal($needed);$s=checkout_query($db,'UPDATE rawmaterial SET StockAvailable=StockAvailable-? WHERE RawMatID=? AND StockAvailable>=?','sis',[$decimal,$id,$decimal]);if($s->affected_rows!==1)throw new CheckoutError('Ingredient stock changed. Retry checkout.',409);}
        foreach($quantities as$id=>$qty)if((int)$products[$id]['isRecipe']!==1){$s=checkout_query($db,'UPDATE products SET QtyStock=QtyStock-? WHERE ProductID=? AND QtyStock>=?','iii',[$qty,$id,$qty]);if($s->affected_rows!==1)throw new CheckoutError('Product stock changed. Retry checkout.',409);}
        if($customer!==null&&$points>0)checkout_query($db,'UPDATE customer SET LoyaltyPoints=LoyaltyPoints+? WHERE CustomerID=?','ii',[$points,$customer]);
        $epoch=checkout_query($db,'SELECT UNIX_TIMESTAMP(Date) FROM orders WHERE OrderID=?','i',[$order])->get_result()->fetch_row()[0];
        $stamp=checkout_receipt_time($epoch);
        $response=['success'=>true,'orderID'=>$order,'paymentMethod'=>'Cash','pointsEarned'=>$points,'remainingPoints'=>$remaining,'customerName'=>$customerRow['Name']??null,'date'=>$stamp->format('Y-m-d H:i:s'),'timestamp'=>$stamp->format(DateTimeInterface::ATOM),'items'=>$lines,'subtotal'=>checkout_decimal($subtotal),'tax'=>checkout_decimal($tax),'total'=>$totalString,'cashReceived'=>checkout_decimal($cash),'change'=>checkout_decimal($cash-$total)];
        $json=json_encode($response,JSON_THROW_ON_ERROR);checkout_query($db,'UPDATE checkout_requests SET OrderID=?,ResponseJSON=? WHERE RequestID=?','iss',[$order,$json,$key]);
        // Reconcile saved details/payment and immutable journal before commit.
        $response=checkout_saved_receipt($db,$key,$userID,$role)['receipt'];
        $db->commit();return$response;
    }catch(Throwable$e){$db->rollback();throw$e;}
}

/** There are no configured ordinary discounts. Zero means no discount. */
function checkout_no_discount(array $data) {
    foreach(['discount','discountAmount','discountPercent','discountPercentage','pointsUsed','pointsRedeemed'] as $field) {
        if(!array_key_exists($field,$data))continue;
        try{$zero=checkout_scaled($data[$field],'discount')===0;}catch(CheckoutError $e){$zero=false;}
        if(!$zero)throw new CheckoutError('Discounts are not configured for checkout. Loyalty discounts are unavailable; no discount or points redemption was applied.',422);
    }
    foreach(['discountType','discountCode','coupon','couponCode','voucher','reward'] as $field) {
        if(array_key_exists($field,$data)&&$data[$field]!==null&&$data[$field]!=='')throw new CheckoutError('No ordinary discount or coupon is configured for checkout.',422);
    }
}

function checkout_receipt_time($epoch) {
    return (new DateTimeImmutable('@'.(int)$epoch))->setTimezone(new DateTimeZone('Asia/Manila'));
}

function checkout_receipt_require($condition) {
    if(!$condition)throw new CheckoutReceiptConflict('The saved receipt does not match its transaction. No new payment was created; ask Admin to review the order.',409);
}

function checkout_receipt_money($value) {
    try{return checkout_scaled($value,'saved receipt amount');}
    catch(CheckoutError $e){checkout_receipt_require(false);}
}

/** Caller owns the consistent read/checkout transaction. Never use current prices
 * or names to reconstruct a historical sale. Cash is saved in the journal; payment
 * Amount is the payable amount, not the amount tendered by the customer. */
function checkout_saved_receipt(mysqli $db,$requestID,$userID,$role) {
    $userID=checkout_integer($userID,'staff identity');
    if(!in_array($role,['admin','cashier'],true))throw new CheckoutError('Unauthorized receipt role.',403);
    if(!is_string($requestID)||!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',$requestID))throw new CheckoutError('Invalid receipt request identifier.',422);
    if(p4b_schema_ready($db)){
        $version=checkout_query($db,'SELECT IntentVersion FROM checkout_requests WHERE RequestID=?','s',[$requestID])->get_result()->fetch_row();
        if($version&&(int)$version[0]===2){require_once __DIR__.'/pending_service.php';return p4b_receipt($db,$requestID,$userID,$role);}
    }
    $saved=checkout_query($db,'SELECT j.UserID,j.OrderID,j.RequestHash,j.ResponseJSON,o.TotalAmount,o.CustomerID,o.Status,UNIX_TIMESTAMP(o.Date) AS Epoch,p.Method,p.Amount FROM checkout_requests j LEFT JOIN orders o ON o.OrderID=j.OrderID LEFT JOIN payments p ON p.OrderID=o.OrderID WHERE j.RequestID=?','s',[$requestID])->get_result()->fetch_assoc();
    if(!$saved||((int)$saved['UserID']!==$userID&&$role!=='admin'))throw new CheckoutError('Receipt not found.',404);
    checkout_receipt_require($saved['OrderID']!==null&&$saved['ResponseJSON']!==null&&$saved['Status']==='completed'&&$saved['Method']==='Cash'&&$saved['Epoch']!==null);
    try{$receipt=json_decode($saved['ResponseJSON'],true,512,JSON_THROW_ON_ERROR);}catch(JsonException $e){checkout_receipt_require(false);}
    checkout_receipt_require(is_array($receipt)&&($receipt['success']??false)===true&&($receipt['orderID']??null)===(int)$saved['OrderID']&&($receipt['paymentMethod']??null)===$saved['Method']);
    $items=$receipt['items']??null;
    checkout_receipt_require(is_array($items)&&count($items)>0&&array_keys($items)===range(0,count($items)-1));
    $details=checkout_query($db,'SELECT ProductID,Quantity,Subtotal FROM orderdetails WHERE OrderID=? ORDER BY ProductID','i',[(int)$saved['OrderID']])->get_result()->fetch_all(MYSQLI_ASSOC);
    checkout_receipt_require(count($details)===count($items));
    $snapshot=[];$quantities=[];$subtotal=0;
    foreach($items as $item) {
        checkout_receipt_require(is_array($item)&&is_int($item['productID']??null)&&is_int($item['quantity']??null)&&$item['quantity']>0&&is_string($item['name']??null)&&array_key_exists('size',$item)&&($item['size']===null||is_string($item['size']))&&!isset($snapshot[$item['productID']]));
        $price=checkout_receipt_money($item['unitPrice']??null);$line=checkout_receipt_money($item['subtotal']??null);
        checkout_receipt_require($price*$item['quantity']===$line);
        $snapshot[$item['productID']]=$item;$quantities[$item['productID']]=$item['quantity'];$subtotal+=$line;
    }
    foreach($details as $detail) {
        $id=(int)$detail['ProductID'];
        checkout_receipt_require(isset($snapshot[$id])&&$snapshot[$id]['quantity']===(int)$detail['Quantity']&&checkout_receipt_money($snapshot[$id]['subtotal'])===checkout_receipt_money($detail['Subtotal']));
    }
    $tax=intdiv($subtotal*12+50,100);$total=$subtotal+$tax;
    $cash=checkout_receipt_money($receipt['cashReceived']??null);$change=checkout_receipt_money($receipt['change']??null);
    checkout_receipt_require(checkout_receipt_money($receipt['subtotal']??null)===$subtotal&&checkout_receipt_money($receipt['tax']??null)===$tax&&checkout_receipt_money($receipt['total']??null)===$total&&checkout_receipt_money($saved['TotalAmount'])===$total&&checkout_receipt_money($saved['Amount'])===$total&&$cash>=$total&&$cash-$total===$change);
    ksort($quantities,SORT_NUMERIC);
    $intent=['quantities'=>$quantities,'customerID'=>$saved['CustomerID']===null?null:(int)$saved['CustomerID'],'paymentMethod'=>'Cash','cashReceived'=>checkout_decimal($cash)];
    checkout_receipt_require(hash_equals($saved['RequestHash'],hash('sha256',json_encode($intent,JSON_THROW_ON_ERROR))));
    $stamp=checkout_receipt_time($saved['Epoch']);
    checkout_receipt_require(($receipt['date']??null)===$stamp->format('Y-m-d H:i:s'));
    if(isset($receipt['timestamp']))checkout_receipt_require($receipt['timestamp']===$stamp->format(DateTimeInterface::ATOM));
    return ['success'=>true,'receipt'=>$receipt,'timestamp'=>$stamp->format(DateTimeInterface::ATOM),'timezone'=>'Asia/Manila'];
}
