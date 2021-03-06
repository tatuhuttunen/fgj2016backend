<?php
header('Access-Control-Allow-Origin: *');

$dsn = 'mysql:dbname=SupraDB;host=localhost';
$user = '';
$password = '';

$db = new PDO($dsn, $user, $password, array(PDO::ATTR_PERSISTENT => true));

function getParamGET($param)
{
  if(isset($_GET[$param]))
  {
    return $_GET[$param];
  }
  return FALSE;
}

function getParamPOST($param)
{
  if(isset($_POST[$param]))
  {
    return $_POST[$param];
  }
  return FALSE;
}

function createGame($db)
{
  $sessionId = getParamPOST('sessionId');
  $sql = $db->prepare("
    INSERT INTO fgj16_session(id)
    VALUES(:sessionId)
  ");
  $sql->execute(array(
    "sessionId" => $sessionId
  ));
  return $sessionId;
}

function joinGame($db)
{
  $sessionId = getParamPOST('sessionId');
  $sessions = array();
  $statement = $db->prepare("
    SELECT
      id,
      occupied,
      created_at
    FROM fgj16_session
    WHERE id = :sessionId
    ORDER BY created_at DESC
    LIMIT 1
  ");
  $statement->bindValue(':sessionId', $sessionId, PDO::PARAM_STR);
  $statement->execute();
  $gameSession = $statement->fetchAll(PDO::FETCH_ASSOC)[0];
  if (empty($gameSession) || $gameSession['occupied'] !== '0')
  {
      return 'error';
  }
  $sql = $db->prepare("
    UPDATE fgj16_session
    SET occupied = 1
    WHERE id = ?
  ");

  $sql->execute(array($gameSession['id']));
  return $gameSession['id'];

}

function addEvent($db)
{
  $data = serialize(getParamPOST('data'));
  $sessionId = getParamPOST('sessionId');
  $playerId = getParamPOST('playerId');
  if($data && $sessionId)
  {
    $sql = $db->prepare("
      INSERT INTO fgj16_event (session_id, data, player_id)
      VALUES (:sessionId, :data, :playerId)
    ");

    $sql->bindValue(":data", $data);
    $sql->bindValue(":sessionId", $sessionId);
    $sql->bindValue(":playerId", $playerId);
    $sql->execute();
    return "success";
  }
  return "error inserting event";
}

function getEvents($db)
{
  $events = array();
  $sessionId = getParamGET('sessionId');
  $playerId = getParamGET('playerId');
  $since = getParamGET('since') ? getParamGET('since') : date('Y-m-d H:i:s', strtotime('-5 minutes'));
  if($sessionId)
  {
    $events = array();
    $statement = $db->prepare("
      SELECT id, data, created_at, player_id
      FROM fgj16_event
      WHERE session_id = ?
    ");
    $statement->execute(array($sessionId));
    while($row = $statement->fetch(PDO::FETCH_ASSOC))
    {
      $array = array();
      $array['data'] = unserialize($row['data']);
      $array['id'] = $row['id'];
      $array['created_at'] = $row['created_at'];
      $array['playerId'] = $row['player_id'];
      array_push($events, $array);
    }
    return json_encode($events);
  }
  return "error fetching events";
}

function getOccupied($db)
{
  $sessionId = getParamGET('sessionId');
  $statement = $db->prepare("
    SELECT
      id,
      occupied,
      created_at
    FROM fgj16_session
    WHERE id = :sessionId
    ORDER BY created_at DESC
    LIMIT 1
  ");
  $statement->bindValue(':sessionId', $sessionId, PDO::PARAM_STR);
  $statement->execute();
  $gameSession = $statement->fetchAll(PDO::FETCH_ASSOC)[0];
  if (!empty($gameSession))
  {
      return $gameSession['occupied'] !== '0' ? 'occupied' : 'unoccupied';
  }
  return "not found";
}

$value = "An error has occurred";
$action = getParamGET('action');

if ($action)
{
  switch ($action)
    {
      case "creategame":
        $value = createGame($db);
        break;
      case "joingame":
        $value = joinGame($db);
        break;
      case "addevent":
        $value = addEvent($db);
        break;
      case "getevents":
        $value = getEvents($db);
        break;
      case "getoccupied":
        $value = getOccupied($db);
        break;
      default:
        $value = "unknown command";
    }
}

echo($value);
?>
