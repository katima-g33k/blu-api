<?php
require_once '#/const.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Access-Control-Allow-Headers, Access-Control-Allow-Methods, Access-Control-Allow-Origin');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Content-Type: application/json;charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
  http_response_code(200);
  echo json_encode([]);
  return;
}

// Check API key
$apiKey = isset(getallheaders()['X-Authorization']) ? getallheaders()['X-Authorization'] : '';
if (API_KEY != $apiKey) {
  http_response_code(401);
  echo json_encode([ 'message' => 'Invalid API key' ]);
  return;
}

require_once '#/routes.php';
foreach ($routes[$_SERVER['REQUEST_METHOD']] as $route) {
  $regex = '/^' . preg_replace('/\d+/', ':(\w+)', str_replace('/', '\/', $_SERVER['PATH_INFO'])) . '$/';
  if (preg_match($regex, $route['url'], $paramKeys) == 1) {
    $apiCall = $route;
    break;
  }
}

if (!isset($apiCall)) {
  http_response_code(404);
  echo json_encode([ 'message' => 'Invalid API URL' ]);
  return;  
}

$params = [];
preg_match_all('/(\d+)/', $_SERVER['PATH_INFO'], $paramValues, PREG_PATTERN_ORDER);

if (preg_match($regex, $apiCall['url'], $paramKeys) == 1) {
  for ($i = 1; $i < count($paramKeys); $i++) {
    $params[$paramKeys[$i]] = (int)$paramValues[0][$i - 1];
  }
}

switch ($_SERVER['REQUEST_METHOD']) {
  case 'GET':
    $data = $_GET;
    break;
  case 'POST':
    $data = array_merge(json_decode(file_get_contents('php://input'), true), $_POST);
    break;
  default:
    $data = [];
}

try {
  if (count($params) > 0 && count($data) > 0) {
    echo json_encode($apiCall['fn']($params, $data));    
  } else if (count($params) > 0) {
    echo json_encode($apiCall['fn']($params));
  } else if (count($data) > 0) {
    echo json_encode($apiCall['fn']($data));    
  } else {
    echo json_encode($apiCall['fn']());    
  }
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([ 'data' => $e ]);
}
?>
