<?php
require_once __DIR__.'/auth_check.php';
require_api_auth(['admin','cashier']);
require_once __DIR__.'/security.php';
header('Content-Type: application/json');
$method=$_SERVER['REQUEST_METHOD']??'';
if(!in_array($method,['GET','POST'],true)){header('Allow: GET, POST');http_response_code(405);echo json_encode(['success'=>false,'error'=>'Use GET or POST.']);exit;}
if($method==='POST'){security_require_csrf();if(strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json'){http_response_code(415);echo json_encode(['success'=>false,'error'=>'Use JSON.']);exit;}}
$user=$_SESSION['user_id'];$role=$_SESSION['role'];session_write_close();
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
require_once __DIR__.'/../connection.php';
require_once __DIR__.'/pending_service.php';
try{
 $conn->set_charset('utf8mb4');$conn->query('SET SESSION innodb_lock_wait_timeout=10');
 if($method==='GET'&&($_GET['action']??'')==='capabilities'){echo json_encode(['success'=>true,'enabled'=>p4b_enabled($conn)]);exit;}
 p4b_guard($conn,$user,$role);
 if($method==='POST'){
  $raw=file_get_contents('php://input');if(strlen($raw)>65536)throw new PendingError('Request too large.',413);
  $data=json_decode($raw,true,512,JSON_THROW_ON_ERROR);if(!is_array($data))throw new PendingError('Invalid request.');
  $out=p4b_action($conn,$user,$role,$data);
 }else{
  $action=$_GET['action']??'list';
  if($action==='preview')$out=p4b_preview($conn,$user,$role,checkout_integer($_GET['orderID']??null,'order'));
  else{
   $conn->query('START TRANSACTION READ ONLY');
   if($action==='list')$out=p4b_list($conn,$user,$role,$_GET['before']??null);
   elseif($action==='detail'){$row=p4b_order($conn,checkout_integer($_GET['orderID']??null,'order'),$user,$role);$out=['success'=>true,'order'=>p4b_summary($conn,$row)];}
   else throw new PendingError('Unknown read action.');
   $conn->commit();
  }
 }
 echo json_encode($out,JSON_THROW_ON_ERROR);
}catch(PendingError$e){$conn->rollback();http_response_code($e->getCode()?:422);echo json_encode(['success'=>false,'error'=>$e->getMessage(),'code'=>$e->reason,'retryable'=>$e->retryable]);}
catch(CheckoutError$e){$conn->rollback();http_response_code($e->getCode()?:422);echo json_encode(['success'=>false,'error'=>$e->getMessage(),'retryable'=>$e instanceof CheckoutReceiptConflict]);}
catch(JsonException$e){$conn->rollback();http_response_code(422);echo json_encode(['success'=>false,'error'=>'Invalid JSON.']);}
catch(Throwable$e){$conn->rollback();error_log('Pending action failed: '.get_class($e).' code '.$e->getCode());http_response_code(503);echo json_encode(['success'=>false,'retryable'=>true,'error'=>'Result could not be confirmed. Retry the same Pending action.']);}
