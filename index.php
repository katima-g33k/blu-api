<?php
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

header("Content-Type: application/json;charset=utf-8");

if (isValidRequest($req)) {
  if (getallheaders()['X-Authorization'] == API_KEY) {
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
