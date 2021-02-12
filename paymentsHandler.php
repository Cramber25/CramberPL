<?php
date_default_timezone_set('Europe/Warsaw');
class PAYMENTSHANDLER {
  private $pdo = null;
  private $stmt = null;
  public $error = "";
  function __construct () {
    try {
      $this->pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8;",
        DB_USER, DB_PASSWORD, [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
      );
    } catch (Exception $ex) { die($ex->getMessage()); }
  }
  function __destruct () {
    if ($this->stmt!==null) { $this->stmt = null; }
    if ($this->pdo!==null) { $this->pdo = null; }
  }
  function query ($sql, $data=null) {
    try {
      $this->stmt = $this->pdo->prepare($sql);
      $this->stmt->execute($data);
      return true;
    } catch (Exception $ex) {
      $this->error = $ex->getMessage();
      return false;
    }
  }
  function createDeposit ($id, $type, $amount, $price) {
    $timestamp = date("Y-m-d H:i:s");
    $this->stmt = $this->pdo->prepare(
      "INSERT INTO `deposits` (`user`, `type`, `amount`, `price`, `date`) VALUES (?, ?, ?, ?, ?)"
    );
    $this->stmt->execute([
      $id, $type, $amount, $price, $timestamp
    ]);
    return $this->pdo->lastInsertId();
  }
  function updateDeposit ($status, $id) {
    $this->stmt = $this->pdo->prepare(
      "UPDATE `deposits` SET `status`=? WHERE `id`=?"
    );
    $this->stmt->execute([
      $status, $id
    ]);
    return;
  }
  function payUserMoney ($money, $to, $from, $type) {
    if($from['money'] < $money) return $this->error="Uzytkownik ".$from['nick']." nie ma tyle monet!";
    $newfrommoney = intval($from['money']) - $money;
    $this->stmt = $this->pdo->prepare("UPDATE `users` SET `money`=? WHERE `id`=?");
    $this->stmt->execute([$newfrommoney, $from['id']]);
    $newmoney = intval($to['money']) + $money; 
    $this->stmt = $this->pdo->prepare("UPDATE `users` SET `money`=? WHERE `id`=?");
    $this->stmt->execute([$newmoney, $to['id']]);
    $timestamp = date("Y-m-d H:i:s");
    $this->stmt = $this->pdo->prepare(
      "INSERT INTO `transakcje` (`od`, `do`, `amount`, `date`, `type`) VALUES (?, ?, ?, ?, ?)"
    );
    $this->stmt->execute([
      $from['id'], $to['id'], $money, $timestamp, $type
    ]);
    return;
  }
  function getDepositByID ($id) {
    $this->stmt = $this->pdo->prepare("SELECT * FROM `deposits` WHERE `id`=?");
    $this->stmt->execute([$id]);
    return $this->stmt->fetch();
  }
  function getTransactionByID ($id) {
    $this->stmt = $this->pdo->prepare("SELECT * FROM `transakcje` WHERE `id`=?");
    $this->stmt->execute([$id]);
    return $this->stmt->fetch();
  }
  function getTransactions ($uid, $iloscnastrone=null, $usunilosc=null) {
    $transakcje = [];
    if($iloscnastrone !== null) {
      $this->query(
        "SELECT * FROM `transakcje` WHERE `do`=? OR `od`=? ORDER BY `id` DESC LIMIT $iloscnastrone OFFSET $usunilosc", [$uid, $uid]
      );
    }else{
      $this->query(
        "SELECT * FROM `transakcje` WHERE `do`=? OR `od`=? ORDER BY `id` DESC", [$uid, $uid]
      );
    }
    while ($row = $this->stmt->fetch()) {
      if($row['od'] == $uid) {
        array_push($row, "minus");
      }else {
        array_push($row, "plus");
      }
      $transakcje[] = $row;
    }
    return $transakcje;
  }
}
define('DB_HOST', '-');
define('DB_NAME', '-');
define('DB_USER', '-');
define('DB_PASSWORD', '-');
$PAYMENTSHANDLER = new PAYMENTSHANDLER();