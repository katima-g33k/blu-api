<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: X-Authorization');
header('Access-Control-Allow-Methods: OPTIONS, true, 200');
header("Content-Type: application/json;charset=utf-8");

$API = [];
$req = null;

require_once '#/const.php';

require_once '#/category.php';
require_once '#/comment.php';
require_once '#/copy.php';
require_once '#/item.php';
require_once '#/member.php';
require_once '#/reservation.php';
require_once '#/state.php';
require_once '#/storage.php';
require_once '#/subject.php';
require_once '#/transaction.php';

if (isset($_POST['req'])) {
  $req = json_decode($_POST['req'], true);
} else if (isset($_GET['req'])) {
  $req = json_decode($_GET['req'], true);
}

$apiKey = '';
if (isset(getallheaders()['X-Authorization'])) {
  $apiKey = getallheaders()['X-Authorization'];
} else if (isset($req['api-key'])) {
  $apiKey = $req['api-key'];
}

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
  http_response_code(200);
  echo json_encode([]);
} else if (isValidRequest($req)) {
  if ($apiKey == API_KEY) {
    echo json_encode([ 'data' => $API[$req['object']][$req['function']](sanatize($req['data'])) ]);
  } else {
    http_response_code(403);
    echo json_encode([ 'data' => INVALID_API_KEY ]);
  }
} else {
  http_response_code(400);
  echo json_encode([ 'data' => BAD_REQUEST ]);
}

function isValidRequest($req) {
  $keys = ['object', 'function', 'data'];

  if ($req == null) {
    return false;
  }

  foreach ($keys as $key) {
    if (!isset($req[$key])) {
      return false;
    }
  }

  return true;
}

function sanatize($data) {
  foreach ($data as $key => $value) {
    if (is_array($value)) {
      $data[$key] = sanatize($value);
    } else if (is_string($value)) {
      $data[$key] = str_replace("'", "''", $value);
    }
  }

  return $data;
}
?>
