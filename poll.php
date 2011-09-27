<?php
// Poll option definitions
$options[1] = 'jQuery';
$options[2] = 'Ext JS';
$options[3] = 'Dojo';
$options[4] = 'Prototype';
$options[5] = 'YUI';
$options[6] = 'mootools';

// Column definitions
define('OPT_ID', 0);
define('OPT_TITLE', 1);
define('OPT_VOTES', 2);

define('HTML_FILE', 'index.html');

// Initialize the DB
require_once('flatfile.php');
$db = new Flatfile();
$db->datadir = 'data/';
define('VOTE_DB', 'votes.txt');
define('IP_DB', 'ips.txt');

if ($_GET['poll'] || $_POST['poll']) {
  poll_submit();
}
else if ($_GET['vote'] || $_POST['vote']) {
  poll_ajax();
}
else {
  poll_default();
}


function poll_ajax() {
  global $db;
  global $options;

  $id = $_GET['vote'] || $_POST['vote'];
  
  $ip_result = $db->selectUnique(IP_DB, 0, $_SERVER['REMOTE_ADDR']);
  
  if (empty($ip_result)) {
    $ip[0] = $_SERVER['REMOTE_ADDR'];
    $db->insert(IP_DB, $ip);
    
    if ($id != 'none') {
      $row = $db->selectUnique(VOTE_DB, OPT_ID, $id);
      if (!empty($row)) {
        $new_votes = $row[OPT_VOTES]+1;
    
        $db->updateSetWhere(VOTE_DB, array(OPT_VOTES => $new_votes), new SimpleWhereClause(OPT_ID, '=', $id));
      }
      else if ($options[$id]) {
        $new_row[OPT_ID] = $id;
        $new_row[OPT_TITLE] = $options[$id];
        $new_row[OPT_VOTES] = 1;
        $db->insert(VOTE_DB, $new_row);
      }
    }
  }
  
  $rows = $db->selectWhere(VOTE_DB, new SimpleWhereClause(OPT_ID, "!=", 0), -1, new OrderBy(OPT_VOTES, DESCENDING, INTEGER_COMPARISON));
  print json_encode($rows);
}


function poll_submit() {
  global $db;
  global $options;
  
  $id = $_GET['poll'] || $_POST['poll'];
  $id = str_replace("opt", '', $id);
  
  $ip_result = $db->selectUnique(IP_DB, 0, $_SERVER['REMOTE_ADDR']);
  
  if (!isset($_COOKIE['vote_id']) && empty($ip_result)) {
    $row = $db->selectUnique(VOTE_DB, OPT_ID, $id);
    if (!empty($row)) {
      $ip[0] = $_SERVER['REMOTE_ADDR'];
      $db->insert(IP_DB, $ip);
      
      setcookie("vote_id", $id, time()+31556926); // cookie expires in one year
      
      $new_votes = $row[OPT_VOTES]+1;
      $db->updateSetWhere(VOTE_DB, array(OPT_VOTES => $new_votes), new SimpleWhereClause(OPT_ID, '=', $id));
      
      poll_return_results($id);
    }
    else if ($options[$id]) {
      $ip[0] = $_SERVER['REMOTE_ADDR'];
      $db->insert(IP_DB, $ip);
      
      setcookie("vote_id", $id, time()+31556926); // cookie expires in one year
      
      $new_row[OPT_ID] = $id;
      $new_row[OPT_TITLE] = $options[$id];
      $new_row[OPT_VOTES] = 1;
      $db->insert(VOTE_DB, $new_row);
      
      poll_return_results($id);
    }
  }
  else {
    poll_return_results($id);
  }
}


function poll_default() {
  global $db;
  
  $ip_result = $db->selectUnique(IP_DB, 0, $_SERVER['REMOTE_ADDR']);
  
  if (!isset($_COOKIE['vote_id']) && empty($ip_result)) {
    print file_get_contents(HTML_FILE);
  }  
  else {
    poll_return_results($_COOKIE['vote_id']);
  }
}


function poll_return_results($id = NULL) {
    global $db;
  
    $html = file_get_contents(HTML_FILE);
    $results_html = "<div id='poll-container'><div id='poll-results'><h3>Poll Results</h3>\n<dl class='graph'>\n";
    
    $rows = $db->selectWhere(VOTE_DB,
      new SimpleWhereClause(OPT_ID, "!=", 0), -1,
      new OrderBy(OPT_VOTES, DESCENDING, INTEGER_COMPARISON));

    foreach ($rows as $row) {
      $total_votes = $row[OPT_VOTES]+$total_votes;
    }
    
    foreach ($rows as $row) {
      $percent = round(($row[OPT_VOTES]/$total_votes)*100);
      if (!$row[OPT_ID] == $id) {
        $results_html .= "<dt class='bar-title'>". $row[OPT_TITLE] ."</dt><dd class='bar-container'><div id='bar". $row[OPT_ID] ."'style='width:$percent%;'>&nbsp;</div><strong>$percent%</strong></dd>\n";
      }
      else {
        $results_html .= "<dt class='bar-title'>". $row[OPT_TITLE] ."</dt><dd class='bar-container'><div id='bar". $row[OPT_ID] ."' style='width:$percent%;background-color:#0066cc;'>&nbsp;</div><strong>$percent%</strong></dd>\n";
      }
    }
    
    $results_html .= "</dl><p>Total Votes: ". $total_votes ."</p></div></div>\n";
    
    $results_regex = '/<div id="poll-container">(.*?)<\/div>/s';
    $return_html = preg_replace($results_regex, $results_html, $html);
    print $return_html;
}

if (!function_exists('json_encode')) { // json_encode was added in 5.2.0, this is a replacement function if the PHP version is old
  function json_encode($a=false) {
    if (is_null($a)) return 'null';
    if ($a === false) return 'false';
    if ($a === true) return 'true';
    if (is_scalar($a)) {
      if (is_float($a)) {
        // Always use "." for floats.
        return floatval(str_replace(",", ".", strval($a)));
      }

      if (is_string($a)) {
        static $jsonReplaces = array(array("\\", "/", "\n", "\t", "\r", "\b", "\f", '"'), array('\\\\', '\\/', '\\n', '\\t', '\\r', '\\b', '\\f', '\"'));
        return '"' . str_replace($jsonReplaces[0], $jsonReplaces[1], $a) . '"';
      }
      else
        return $a;
    }
    $isList = true;
    for ($i = 0, reset($a); $i < count($a); $i++, next($a)) {
      if (key($a) !== $i) {
        $isList = false;
        break;
      }
    }
    $result = array();
    if ($isList) {
      foreach ($a as $v) $result[] = json_encode($v);
      return '[' . join(',', $result) . ']';
    }
    else {
      foreach ($a as $k => $v) $result[] = json_encode($k).':'.json_encode($v);
      return '{' . join(',', $result) . '}';
    }
  }
}
