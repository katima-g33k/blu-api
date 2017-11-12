<?php
require_once '#/category.php';
require_once '#/employee.php';
require_once '#/item.php';
require_once '#/member.php';
require_once '#/reservation.php';
require_once '#/state.php';
require_once '#/statistics.php';
require_once '#/storage.php';
require_once '#/transaction.php';

$isSameMember = function () {
  global $params;
  return $_SESSION['memberNo'] == $params['no'];
};

$routes = [
  'DELETE' => [
    [ 'url' => '/employee/:id', 'fn' => $employeeDelete, 'type' => 'admin' ],
    [ 'url' => '/item/:id', 'fn' => $itemDelete, 'type' => 'admin' ],
    [ 'url' => '/item/storage', 'fn' => $storageDelete, 'type' => 'admin' ],
    [ 'url' => '/member/:no', 'fn' => $deleteMember, 'type' => 'admin' ],
    [ 'url' => '/member/:no/subscription/item/:id', 'fn' => $unsubscribe, 'type' => 'member', 'validationFn' => $isSameMember ],
    [ 'url' => '/member/comment/:id', 'fn' => $memberCommentDelete, 'type' => 'employee' ],
    [ 'url' => '/member/copy/:id', 'fn' => $memberDeleteCopy, 'type' => 'employee' ],
    [ 'url' => '/member/copy/:copyId/transaction', 'fn' => $transactionDelete, 'type' => 'employee' ],
    [ 'url' => '/reservation', 'fn' => $reservationDelete, 'type' => 'employee' ],
  ],
  'GET' => [
    [ 'url' => '/category', 'fn' => $getCategory, 'type' => 'employee' ],
    [ 'url' => '/employee', 'fn' => $employeeList, 'type' => 'admin' ],
    [ 'url' => '/item', 'fn' => $itemList, 'type' => 'public' ],
    [ 'url' => '/item/:id', 'fn' => $getItem, 'type' => 'public' ],
    [ 'url' => '/item/exists/:ean13', 'fn' => $itemExists, 'type' => 'employee' ],
    [ 'url' => '/item/:duplicate/merge/:id', 'fn' => $itemMerge, 'type' => 'admin' ],
    [ 'url' => '/item/name/:ean13', 'fn' => $getItemName, 'type' => 'employee' ],
    [ 'url' => '/item/storage', 'fn' => $storageList, 'type' => 'admin' ],
    [ 'url' => '/member', 'fn' => $memberSearch, 'type' => 'employee' ],
    [ 'url' => '/member/:no', 'fn' => $getMember, 'type' => 'member', 'validationFn' => $isSameMember ],
    [ 'url' => '/member/:no/isSubscribed/item/:id', 'fn' => $isSubscribed, 'type' => 'member', 'validationFn' => $isSameMember ],
    [ 'url' => '/member/:no/name', 'fn' => $memberName, 'type' => 'employee' ],
    [ 'url' => '/member/:no/pay', 'fn' => $memberPay, 'type' => 'employee' ],    
    [ 'url' => '/member/:no/renew', 'fn' => $memberRenew, 'type' => 'member', 'validationFn' => $isSameMember ],
    [ 'url' => '/member/:no/subscription/item/:id', 'fn' => $subscribe, 'type' => 'member', 'validationFn' => $isSameMember ],    
    [ 'url' => '/member/:no/transfer', 'fn' => $memberTransfer, 'type' => 'employee' ],        
    [ 'url' => '/member/duplicates', 'fn' => $memberDuplicates, 'type' => 'admin' ],
    [ 'url' => '/member/exists', 'fn' => $memberExists, 'type' => 'employee' ],
    [ 'url' => '/member/:duplicate/merge/:no', 'fn' => $memberMerge, 'type' => 'admin' ],
    [ 'url' => '/reservation', 'fn' => $reservationList, 'type' => 'admin' ],
    [ 'url' => '/state', 'fn' => $stateList, 'type' => 'employee' ],
    [ 'url' => '/statistics/amountdue', 'fn' => $amountDue, 'type' => 'admin' ],
    [ 'url' => '/statistics/interval', 'fn' => $intervalStatistics, 'type' => 'admin' ],
  ],
  'POST' => [
    [ 'url' => '/employee', 'fn' => $employeeInsert, 'type' => 'admin' ],
    [ 'url' => '/employee/:id', 'fn' => $employeeUpdate, 'type' => 'admin' ],    
    [ 'url' => '/employee/login', 'fn' => $employeeLogin, 'type' => 'public' ],
    [ 'url' => '/item', 'fn' => $itemInsert, 'type' => 'employee' ],
    [ 'url' => '/item/:id', 'fn' => $itemUpdate, 'type' => 'employee' ],
    [ 'url' => '/item/:id/status', 'fn' => $itemUpdateStatus, 'type' => 'employee' ],
    [ 'url' => '/item/:id/storage', 'fn' => $itemUpdateStorage, 'type' => 'employee' ],    
    [ 'url' => '/member', 'fn' => $memberInsert, 'type' => 'employee' ],
    [ 'url' => '/member/:no', 'fn' => $memberUpdate, 'type' => 'member', 'validationFn' => $isSameMember ],
    [ 'url' => '/member/:no/comment', 'fn' => $memberCommentInsert, 'type' => 'employee' ],
    [ 'url' => '/member/:no/copy', 'fn' => $memberInsertCopy, 'type' => 'employee' ],
    [ 'url' => '/member/:memberNo/copy/:copyId/transaction', 'fn' => $transactionInsert, 'type' => 'employee' ],
    [ 'url' => '/member/comment/:id', 'fn' => $memberCommentUpdate, 'type' => 'employee' ],
    [ 'url' => '/member/copy/:id', 'fn' => $memberUpdateCopy, 'type' => 'employee' ],
    [ 'url' => '/reservation', 'fn' => $reservationInsert, 'type' => 'employee' ],
  ],
];
?>