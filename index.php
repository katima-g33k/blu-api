<?php
require_once '#/const.php';

$req = null;

if (isset($_POST['req'])) {
  $req = json_decode($_POST['req'], true);
} elseif (isset($_GET['req'])) {
  $req = json_decode($_GET['req'], true);
}

if ($req != null) {
  if (isset($req['apikey']) && $req['apikey'] == API_KEY) {
    require_once '#/' . $req['object'] . '_sql.php';
    echo json_encode([ 'data' => executeQuery($req['function'], $req['data'])]);
  } else {
    echo json_encode([ 'data' => [ 'code' => 403, 'message' => 'INVALID_API_KEY']]);
  }
} else {
  echo json_encode([ 'data' => [ 'code' => 400, 'message' => 'BAD_REQUEST']]);
}
?>
