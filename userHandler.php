<?php
date_default_timezone_set('Europe/Warsaw');
use PHPMailer\PHPMailer\PHPMailer;
session_start();
class UHANDLER {
  private $pdo = null;
  private $stmt = null;
  public $error = null;
  function __construct () {
    try {
      $this->pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8;",
        DB_USER, DB_PASSWORD, [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
      );
    } catch (Exception $ex) { exit($ex->getMessage()); }
  }
  function __destruct () {
    if ($this->stmt !== null) { $this->stmt = null; }
    if ($this->pdo !== null) { $this->pdo = null; }
  }
  function getByEmail ($email) {
    $this->stmt = $this->pdo->prepare("SELECT * FROM `users` WHERE `email`=?");
    $this->stmt->execute([$email]);
    return $this->stmt->fetch();
  }
  function getByNick ($nick) {
    $this->stmt = $this->pdo->prepare("SELECT * FROM `users` WHERE `nick`=?");
    $this->stmt->execute([$nick]);
    return $this->stmt->fetch();
  }
  function getByID ($id) {
    $this->stmt = $this->pdo->prepare("SELECT * FROM `users` WHERE `id`=?");
    $this->stmt->execute(array($id));
    return $this->stmt->fetch();
  }
  function getByTOKEN ($token) {
    $this->stmt = $this->pdo->prepare("SELECT * FROM `users` WHERE `token`=?");
    $this->stmt->execute(array($token));
    return $this->stmt->fetch();
  }
  //-----------------------------------------------------------------------------------------
  function updateUser ($id, $type, $data) {
    if(!$this->getByID($id)) {
      $this->error = "Nie ma takiego użytkownika";
      return 0;
    }
    $user = $this->getByID($id);
    switch($type) {
      default:
        $this->error = "Podano niepoprawny typ edycji użytkownika.";
        return 0;
        break;
      case 'nick':
        if($this->getByNick($data)) {
          $this->error = "Ten nick jest niedostępny!";
          return 0;
        }
        if($user['type'] >= 1) {
          $this->error = "Zweryfikowani użytkownicy nie mogą zmienić nicku!";
          return 0;
        }
        if (preg_match("/[^A-Za-z0-9]/", $data)) {
          $this->error = "Nick może zawierać tylko litery i cyfry.";
          return 0;
        }
        if (strlen($data) > 20 || strlen($data) < 3) {
          $this->error = "Nick musi mieć od 3 do 20 znaków.";
          return 0;
        }
        try {
          $this->stmt = $this->pdo->prepare(
            "UPDATE `users` SET `nick`=? WHERE `id`=?"
          );
          $this->stmt->execute([
            $data, $id
          ]);
          $this->lastID = $this->pdo->lastInsertId();
          $_SESSION['user']['nick'] = $data;
        } catch (Exception $ex) {
          $this->error = $ex;
          return 0;
        }
        break;
      case 'email':
        $this->error = "Nie możesz zmienić adresu e-mail.";
        return 0;
        break;
      case 'description':
        if (strlen($data) > 512) {
          $this->error = "Opis może mieć maksymalnie 512 znaków.";
          return 0;
        }
        try {
          $this->stmt = $this->pdo->prepare(
            "UPDATE `users` SET `description`=? WHERE `id`=?"
          );
          $this->stmt->execute([
            $data, $id
          ]);
          $this->lastID = $this->pdo->lastInsertId();
        } catch (Exception $ex) {
          $this->error = $ex;
          return 0;
        }
        break;
      case 'avatar':
        try {
          $this->stmt = $this->pdo->prepare(
            "UPDATE `users` SET `avatar`=? WHERE `id`=?"
          );
          $this->stmt->execute([
            $data, $id
          ]);
          $this->lastID = $this->pdo->lastInsertId();
        } catch (Exception $ex) {
          $this->error = $ex;
          return 0;
        }
        break;
      case 'background':
        try {
          $this->stmt = $this->pdo->prepare(
            "UPDATE `users` SET `background`=? WHERE `id`=?"
          );
          $this->stmt->execute([
            $data, $id
          ]);
          $this->lastID = $this->pdo->lastInsertId();
        } catch (Exception $ex) {
          $this->error = $ex;
          return 0;
        }
        break;
    }
  }
  //-----------------------------------------------------------------------------------------
  function getBadge ($nick) {
    $this->stmt = $this->pdo->prepare("SELECT * FROM `users` WHERE `nick`=?");
    $this->stmt->execute(array($nick));
    switch($this->stmt->fetch()['type']) {
      default:
        return '';
        break;
      case 1:
        return ' <a class="text-success" rel="tooltip" title="Konto zweryfikowane"><i class="fas fa-check"></i></a>';
        break;
      case 2:
        return ' <a class="text-warning" rel="tooltip" title="Partner"><i class="fas fa-user-tie"></i></a>';
        break;
      case 5:
        return ' <a class="text-info" rel="tooltip" title="Pomocnik"><i class="fas fa-user-edit"></i></a>';
        break;
      case 6:
        return ' <a class="text-info" rel="tooltip" title="Moderator"><i class="fas fa-user-shield"></i></a>';
        break;
      case 7:
        return ' <a class="text-danger" rel="tooltip" title="Administrator"><i class="fas fa-user-cog"></i></a>';
        break;
      case 8:
        return ' <a class="text-warning" rel="tooltip" title="Właściciel"><i class="fas fa-user-tie"></i></a>';
        break;
      case 9:
        return ' <a class="text-warning" rel="tooltip" title="Superadministrator"><i class="fas fa-crown"></i></a>';
        break;
    }
  }
  function register ($nick, $email, $pass, $cpass) {
    $check1 = $this->getByEmail($email);
    if (is_array($check1)) {
      switch ($check1['status']) {
        default:
          $this->error = "Coś poszlo nie tak...";
          return 0; break;
        case 1:
          $this->error = "Adres $email już jest zarejestrowany.";
          return 2; break;
        case 0:
          $this->error = "Adres $email oczekuje na weryfikację maila.";
          return 3; break;
        case 3:
          $this->error = "Adres $email został zbanowany.";
          return 4; break;
      }
    }
    $check2 = $this->getByNick($nick);
    if (is_array($check2)) {
      switch ($check2['status']) {
        default:
          $this->error = "Coś poszło nie tak...";
          return 0; break;
        case 1:
          $this->error = "Nick $nick jest już zajęty.";
          return 2; break;
        case 0:
          $this->error = "Nick $nick jest już zajęty.";
          return 3; break;
        case 3:
          $this->error = "Konto $nick zostało zbanowane.";
          return 4; break;
      }
    }
    if ($pass != $cpass) {
      $this->error = "Hasła nie są takie same.";
      return 0;
    }
    if (preg_match("/[^A-Za-z0-9]/", $nick)) {
      $this->error = "Nick może zawierać tylko litery i cyfry.";
      return 0;
    }
    if (strlen($nick) > 20 || strlen($nick) < 3) {
      $this->error = "Nick musi mieć od 3 do 20 znaków.";
      return 0;
    }
    if (strlen($pass) > 50 || strlen($pass) < 5) {
      $this->error = "Haslo musi mieć pomiędzy 5 a 50 znaków.";
      return 0;
    }
    require_once "arrays.php";
    $maildomain = strstr($email, "@");
    $maildomain = substr($maildomain, 1); 
    if(in_array($maildomain, $bannedmails, true)) {
      $this->error = "Nie, nie mozesz uzyc 10 minute mail :(";
      return 0;
    }
    // ----------------------------------------------------------------------------------------------------------------------------------------------------
    $this->error = "Rejestracja nowych kont jest wyłączona do momentu ukończenia strony.";
    return 0;
    // ----------------------------------------------------------------------------------------------------------------------------------------------------
    $token = md5(date("YmdHis") . $email);
    $timestamp = date("Y-m-d H:i:s");

    require_once './PHPMailer/src/Exception.php';
    require_once './PHPMailer/src/PHPMailer.php';
    require_once './PHPMailer/src/SMTP.php';
    $mail = new PHPMailer();
    $mail->isSMTP();
    $mail->Host = '-';
    $mail->SMTPAuth = true;
    $mail->Username = '-';
    $mail->Password = '-';
    $mail->SMTPSecure = "ssl";
    $mail->Port = 465;
    $mail->isHTML(true);
    $mail->CharSet = "UTF-8";
    $mail->setFrom('-', 'Cramber.PL');
    $mail->addAddress($email);
    $mail->Subject = 'Weryfikacja adresu email.';
    $mail->Body = '
    <!DOCTYPE html>
<html>
<head>
    <title></title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <style type="text/css">
        @media screen {
            @font-face {
                font-family: "Lato";
                font-style: normal;
                font-weight: 400;
                src: local("Lato Regular"), local("Lato-Regular"), url(https://fonts.gstatic.com/s/lato/v11/qIIYRU-oROkIk8vfvxw6QvesZW2xOQ-xsNqO47m55DA.woff) format("woff");
            }

            @font-face {
                font-family: "Lato";
                font-style: normal;
                font-weight: 700;
                src: local("Lato Bold"), local("Lato-Bold"), url(https://fonts.gstatic.com/s/lato/v11/qdgUG4U09HnJwhYI-uK18wLUuEpTyoUstqEm5AMlJo4.woff) format("woff");
            }

            @font-face {
                font-family: "Lato";
                font-style: italic;
                font-weight: 400;
                src: local("Lato Italic"), local("Lato-Italic"), url(https://fonts.gstatic.com/s/lato/v11/RYyZNoeFgb0l7W3Vu1aSWOvvDin1pK8aKteLpeZ5c0A.woff) format("woff");
            }

            @font-face {
                font-family: "Lato";
                font-style: italic;
                font-weight: 700;
                src: local("Lato Bold Italic"), local("Lato-BoldItalic"), url(https://fonts.gstatic.com/s/lato/v11/HkF_qI1x_noxlxhrhMQYELO3LdcAZYWl9Si6vvxL-qU.woff) format("woff");
            }
        }

        /* CLIENT-SPECIFIC STYLES */
        body,
        table,
        td,
        a {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }

        table,
        td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }

        img {
            -ms-interpolation-mode: bicubic;
        }

        /* RESET STYLES */
        img {
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }

        table {
            border-collapse: collapse !important;
        }

        body {
            height: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }

        /* iOS BLUE LINKS */
        a[x-apple-data-detectors] {
            color: inherit !important;
            text-decoration: none !important;
            font-size: inherit !important;
            font-family: inherit !important;
            font-weight: inherit !important;
            line-height: inherit !important;
        }

        /* MOBILE STYLES */
        @media screen and (max-width:600px) {
            h1 {
                font-size: 32px !important;
                line-height: 32px !important;
            }
        }

        /* ANDROID CENTER FIX */
        div[style*="margin: 16px 0;"] {
            margin: 0 !important;
        }
    </style>
</head>
<body style="background-color: #f4f4f4; margin: 0 !important; padding: 0 !important;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <!-- LOGO -->
        <tr>
            <td bgcolor="#FFA73B" align="center">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px;">
                    <tr>
                        <td align="center" valign="top" style="padding: 40px 10px 40px 10px;"> </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td bgcolor="#FFA73B" align="center" style="padding: 0px 10px 0px 10px;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px;">
                    <tr>
                        <td bgcolor="#ffffff" align="center" valign="top" style="padding: 40px 20px 20px 20px; border-radius: 4px 4px 0px 0px; color: #111111; font-family: "Lato", Helvetica, Arial, sans-serif; font-size: 48px; font-weight: 400; letter-spacing: 4px; line-height: 48px;">
                            <h1 style="font-size: 48px; font-weight: 400; margin: 2;">Witaj '.$nick.'!</h1>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td bgcolor="#f4f4f4" align="center" style="padding: 0px 10px 0px 10px;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px;">
                    <tr>
                        <td bgcolor="#ffffff" align="left" style="padding: 20px 30px 40px 30px; color: #666666; font-family: "Lato", Helvetica, Arial, sans-serif; font-size: 18px; font-weight: 400; line-height: 25px;">
                            <p style="margin: 0;">Dziękujemy za rejestrację na stronie Cramber.PL! Aby potwiedzić swój adres email, a co za tym idzie aktywować swoje konto, kliknij guzik poniżej!</p>
                        </td>
                    </tr>
                    <tr>
                        <td bgcolor="#ffffff" align="left">
                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td bgcolor="#ffffff" align="center" style="padding: 20px 30px 60px 30px;">
                                        <table border="0" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td align="center" style="border-radius: 3px;" bgcolor="#FFA73B"><a href="https://cramber.pl/emailconfirm/'.$token.'" target="_blank" style="font-size: 20px; font-family: Helvetica, Arial, sans-serif; color: #ffffff; text-decoration: none; color: #ffffff; text-decoration: none; padding: 15px 25px; border-radius: 2px; border: 1px solid #FFA73B; display: inline-block;">Aktywuj konto</a></td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr> <!-- COPY -->
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
    ';
    if ($mail->send()) {
      try {
        $this->stmt = $this->pdo->prepare(
          "INSERT INTO `users` (`nick`, `email`, `password`, `date`, `token`) VALUES (?, ?, ?, ?, ?)"
        );
        $this->stmt->execute([
          $nick, $email, password_hash($pass, PASSWORD_DEFAULT), $timestamp, $token
        ]);
        $this->lastID = $this->pdo->lastInsertId();
      } catch (Exception $ex) {
        $this->error = $ex;
        return 0;
      }
      return 1;
    }
    else {
      $this->error = "Błąd podczas wysyłania maila $mail->ErrorInfo";
      return 0;
    }
  }
  function verify ($hash) {
    $user = $this->getByTOKEN($hash);
    if ($user === false) {
      $this->error = "Niepoprawny token.";
      return false;
    }
    if ($user['status']==1) {
      $this->error = "Konto już zostalo aktywowane.";
      return false;
    }
    if ($user['status']==2) {
      $this->error = "Konto jest zbanowane.";
      return false;
    }
    try {
      $this->stmt = $this->pdo->prepare("UPDATE `users` SET `status`=1 WHERE `id`=?");
      $this->stmt->execute([$user['id']]);
      $this->lastID = $this->pdo->lastInsertId();
    } catch (Exception $ex) {
      $this->error = $ex;
      return false;
    }
    return true;
  }
  function login ($nick, $password) {
    if (isset($_SESSION['user']['id'])) { return true; }
    $user = $this->getByNick($nick);
    if (!is_array($user)) {
      $this->error = "Nie ma takiego konta.";
      return false;
    }
    if ($user['status']==0) {
      $this->error = "Konto oczekuje na weryfikacje maila.";
      return false;
    }
    if ($user['status']==2) {
      $this->error = "Konto zbanowane.";
      return false;
    }
    if (password_verify($password, $user['password'])) {
      $_SESSION['user'] = [];
      $_SESSION['user']['id'] = $user['id'];
      return true;
    }
    $this->error = "Podano błędne hasło.";
    return false;
  }
}
define('DB_HOST', '-');
define('DB_NAME', '-');
define('DB_USER', '-');
define('DB_PASSWORD', '-');
$UHANDLER = new UHANDLER();