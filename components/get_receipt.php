<?php
require_once __DIR__.'/auth_check.php';
require_api_auth(['admin','cashier']);
header('Content-Type: application/json');
if(($_SERVER['REQUEST_METHOD']??'')!=='GET'){header('Allow: GET');http_response_code(405);echo json_encode(['success'=>false,'error'=>'Use GET for receipt verification.']);exit;}
$userID=$_SESSION['user_id'];$role=$_SESSION['role'];session_write_close();
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
require_once __DIR__.'/../connection.php';
require_once __DIR__.'/checkout_service.php';
try{
    $conn->set_charset('utf8mb4');$conn->query('START TRANSACTION READ ONLY');
    $result=checkout_saved_receipt($conn,$_GET['requestID']??null,$userID,$role);
    $conn->commit();echo json_encode($result,JSON_THROW_ON_ERROR);
}catch(CheckoutError $e){$conn->rollback();http_response_code($e->getCode()?:409);echo json_encode(['success'=>false,'error'=>$e->getMessage()]);}
catch(Throwable $e){$conn->rollback();http_response_code(503);echo json_encode(['success'=>false,'error'=>'Saved receipt verification is unavailable. Retry View Receipt; do not create another payment.']);}
