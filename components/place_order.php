<?php
require_once __DIR__.'/auth_check.php';
require_api_auth(['admin','cashier']);
require_once __DIR__.'/security.php';
header('Content-Type: application/json');
security_require_post();
security_require_csrf();
$userID=$_SESSION['user_id'];$role=$_SESSION['role'];
// Release PHP's session lock before acquiring database locks.
session_write_close();
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
require_once __DIR__.'/../connection.php';
require_once __DIR__.'/checkout_service.php';
try{
    $body=file_get_contents('php://input');
    if(strlen($body)>65536)throw new CheckoutError('Checkout request is too large.',413);
    $data=json_decode($body,true,512,JSON_THROW_ON_ERROR);
    if(!is_array($data))throw new CheckoutError('Invalid checkout request.',422);
    $conn->set_charset('utf8mb4');
    echo json_encode(checkout_execute($conn,$userID,$role,$data),JSON_THROW_ON_ERROR);
}catch(CheckoutError$e){http_response_code($e->getCode()?:422);echo json_encode(['success'=>false,'retryable'=>$e instanceof CheckoutReceiptConflict,'error'=>$e->getMessage()]);}
catch(JsonException$e){http_response_code(422);echo json_encode(['success'=>false,'error'=>'Invalid checkout JSON.']);}
catch(Throwable$e){http_response_code(503);error_log('Checkout failed: '.get_class($e).' code '.$e->getCode());echo json_encode(['success'=>false,'retryable'=>true,'error'=>'Checkout could not be confirmed. Retry the same attempt to check its result.']);}
