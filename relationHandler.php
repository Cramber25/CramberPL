<?php
date_default_timezone_set('Europe/Warsaw');
class Relation {
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
  function checkFollow ($sender, $recipient) {
    $this->stmt = $this->pdo->prepare("SELECT * FROM `relations` WHERE `sender`=? AND `recipient`=? AND `type`='O'");
    $this->stmt->execute([$sender, $recipient]);
    return is_array($this->stmt->fetch());
  }
  function numFollows ($recipient) {
    $this->stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `relations` WHERE `recipient`=? AND `type`='O'");
    $this->stmt->execute([$recipient]);
    return $this->stmt->fetchColumn();
  }
  function checkPending ($sender, $recipient) {
    $this->stmt = $this->pdo->prepare("SELECT * FROM `relations` WHERE `sender`=? AND `recipient`=? AND `type`='P'");
    $this->stmt->execute([$sender, $recipient]);
    return is_array($this->stmt->fetch());
  }
  function checkFriend ($sender, $recipient) {
    $this->stmt = $this->pdo->prepare("SELECT * FROM `relations` WHERE `sender`=? AND `recipient`=? AND `type`='F'");
    $this->stmt->execute([$sender, $recipient]);
    return is_array($this->stmt->fetch());
  }
  function numFriends ($recipient) {
    $this->stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `relations` WHERE `recipient`=? AND `type`='F'");
    $this->stmt->execute([$recipient]);
    return $this->stmt->fetchColumn();
  }
  function request ($sender, $recipient) {
    $this->query(
      "SELECT * FROM `relations` WHERE `sender`=? AND `recipient`=? AND `type`='F'",
      [$sender, $recipient]
    );
    $result = $this->stmt->fetch();
    if (is_array($result)) {
      $this->error = "Już jesteście znajomymi.";
      return false;
    }
    $this->query(
      "SELECT * FROM `relations` WHERE ".
      "(`type`='P' AND `sender`=? AND `recipient`=?) OR ".
      "(`type`='P' AND `sender`=? AND `recipient`=?)",
      [$sender, $recipient, $recipient, $sender]
    );
    $result = $this->stmt->fetch();
    if (is_array($result)) {
      $this->error = "Już istnieje zaproszenie do znajomych.";
      return false;
    }
    return $this->query(
      "INSERT INTO `relations` (`sender`, `recipient`, `type`, `date`) VALUES (?,?,'P',?)",
      [$sender, $recipient, date("Y-m-d H:i:s")]
    );
  }
  function acceptReq ($sender, $recipient) {
    $this->query(
      "UPDATE `relations` SET `type`='F' WHERE `type`='P' AND `sender`=? AND `recipient`=?",
      [$sender, $recipient]
    );
    if ($this->stmt->rowCount()==0) {
      $this->error = "Nieprawidłowe zaproszenie";
      return false;
    }
    return $this->query(
      "INSERT INTO `relations` (`sender`, `recipient`, `type`, `date`) VALUES (?,?,'F',?)",
      [$recipient, $sender, date("Y-m-d H:i:s")]
    );
  }
  function forceFriend ($user1, $user2) {
    if($this->checkFriend($user1, $user2) === false) {
        if($this->checkPending($user1, $user2)) {
            $this->cancelReq($user1, $user2);
        }
        if($this->checkPending($user2, $user1)) {
            $this->cancelReq($user2, $user1);
        }
        $this->query("INSERT INTO `relations` (`sender`, `recipient`, `type`, `date`) VALUES (?,?,'F',?)",[$user1, $user2, date("Y-m-d H:i:s")]);
        $this->query("INSERT INTO `relations` (`sender`, `recipient`, `type`, `date`) VALUES (?,?,'F',?)",[$user2, $user1, date("Y-m-d H:i:s")]);
        return true;
    }else {
        $this->error = "Już są znajomymi";
        return false;
    }
  }
  function cancelReq ($sender, $recipient) {
    return $this->query(
      "DELETE FROM `relations` WHERE `type`='P' AND `sender`=? AND `recipient`=?",
      [$sender, $recipient]
    );
  }
  function unfriend ($sender, $recipient) {
    return $this->query(
      "DELETE FROM `relations` WHERE ".
      "(`type`='F' AND `sender`=? AND `recipient`=?) OR ".
      "(`type`='F' AND `sender`=? AND `recipient`=?)",
      [$sender, $recipient, $recipient, $sender]
    );
  }
  function follow ($sender, $recipient, $follow=true) {
    if ($follow) {
        if(!$this->checkFollow($sender, $recipient)) {
            return $this->query("INSERT INTO `relations` (`sender`, `recipient`, `type`, `date`) VALUES (?,?,'O',?)",[$sender, $recipient, date("Y-m-d H:i:s")]);
        }else {
            $this->error = "Już obserwujesz tego użytkownika";
            return false;
        }
    }
    else {
        if($this->checkFollow($sender, $recipient)) {
            return $this->query("DELETE FROM `relations` WHERE `sender`=? AND `recipient`=? AND `type`='O'",[$sender, $recipient]);
        }else {
            $this->error = "Nie obserwujesz tego użytkownika";
            return false;
        }
    }
  }
  function getReq ($uid, $iloscnastrone=null, $usunilosc=null) {
    $req = ["in"=>[], "out"=>[]];
    if($iloscnastrone !== null) {
      $this->query(
        "SELECT * FROM `relations` WHERE `type`='P' AND `sender`=? ORDER BY `date` DESC LIMIT $iloscnastrone OFFSET $usunilosc", [$uid]
      );
    }else {
      $this->query(
        "SELECT * FROM `relations` WHERE `type`='P' AND `sender`=? ORDER BY `date` DESC", [$uid]
      );
    }
    while ($row = $this->stmt->fetch()) { $req['out'][$row['recipient']] = $row['date']; }
    $this->query(
      "SELECT * FROM `relations` WHERE `type`='P' AND `recipient`=?", [$uid]
    );
    while ($row = $this->stmt->fetch()) { $req['in'][$row['sender']] = $row['date']; }
    return $req;
  }
  function getFriends ($uid, $iloscnastrone=null, $usunilosc=null) {
    $friends = ["f"=>[], "o"=>[]];
    if($iloscnastrone !== null) {
      $this->query(
        "SELECT * FROM `relations` WHERE `type`='F' AND `sender`=? ORDER BY `date` DESC LIMIT $iloscnastrone OFFSET $usunilosc", [$uid]
      );
    }else {
      $this->query(
        "SELECT * FROM `relations` WHERE `type`='F' AND `sender`=? ORDER BY `date` DESC", [$uid]
      );
    }
    while ($row = $this->stmt->fetch()) { $friends["f"][$row['recipient']] = $row['date']; }
    if($iloscnastrone !== null) {
      $this->query(
        "SELECT * FROM `relations` WHERE `type`='O' AND `sender`=? ORDER BY `date` DESC LIMIT $iloscnastrone OFFSET $usunilosc", [$uid]
      );
    }else {
      $this->query(
        "SELECT * FROM `relations` WHERE `type`='O' AND `sender`=? ORDER BY `date` DESC", [$uid]
      );
    }
    while ($row = $this->stmt->fetch()) { $friends["oi"][$row['recipient']] = $row['date']; }
    if($iloscnastrone !== null) {
      $this->query(
        "SELECT * FROM `relations` WHERE `type`='O' AND `recipient`=? ORDER BY `date` DESC LIMIT $iloscnastrone OFFSET $usunilosc", [$uid]
      );
    }else {
      $this->query(
        "SELECT * FROM `relations` WHERE `type`='O' AND `recipient`=? ORDER BY `date` DESC", [$uid]
      );
    }
    while ($row = $this->stmt->fetch()) { $friends["oy"][$row['sender']] = $row['date']; }
    return $friends;
  }
  function getUsers () {
    $this->query("SELECT * FROM `users`");
    $users = [];
    while ($row = $this->stmt->fetch()) { $users[$row['id']] = $row['nick']; }
    return $users;
  }
}
define('DB_HOST', '-');
define('DB_NAME', '-');
define('DB_USER', '-');
define('DB_PASSWORD', '-');
$REL = new Relation();