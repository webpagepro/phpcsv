<?php
ini_set('display_errors', 1); 
ini_set('display_startup_errors', 1); 
error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';

use League\Csv\Reader;
use League\Csv\Writer;
use PragmaRX\Countries\Package\Countries;

include_once 'inc/func.php';

// Settings
$machineName = 'ifcs20';
$resultsDir = 'results/' . $machineName . '/';
$csvFile = 'src/ifcs20.csv';
$numOfAuthorColumns = 10;

// load the CSV document from a file path
$csv = Reader::createFromPath($csvFile, 'r');
$csv->setHeaderOffset(0);

$header = $csv->getHeader(); // returns the CSV header record
$records = $csv->getRecords(); // returns all the CSV records as an Iterator object




// Write Results dir
if(is_dir($resultsDir)) {
  echo 'Results dir exists...' . PHP_EOL;
} else {
  mkdir($resultsDir);
}

/**
 * Contacts
 */
// @TODO: unknown@mail.com
$contacts = [];
//$countries = new Countries();

foreach($records as $offset => $record) {
  for($i = 1; $i <= $numOfAuthorColumns; $i++) {
    if($record['Email' . $i] != '') {
      $contacts[] = array(
        'id' => $record['Paper ID'] . $i,
        'title' => $record['First Name' . $i] . ' ' . $record['Last Name' . $i],
        'first' => $record['First Name' . $i],
        'last' => $record['Last Name' . $i],
        'email' => $record['Email' . $i],
        'photo' => '',
        'position' => '',
        'affiliation' => $record['Contact Organization'],
        'country code' => '',
        'bio' => '',
        'paperid' => $record['Paper ID'],
        'type' => 'author',
      );
    }
  }

  // Chairs
  for($i = 1; $i <= 2; $i++) {
    if($record['Session Chair Last' . $i] != '') {
      $contacts[] = array(
        'id' => $record['Paper ID'] . '00' . $i,
        'title' => $record['Session Chair First' . $i] . ' ' . $record['Session Chair Last' . $i],
        'first' => $record['Session Chair First' . $i],
        'last' => $record['Session Chair Last' . $i],
        'email' => '',
        'photo' => '',
        'position' => '',
        'affiliation' => $record['Session Chair Organization' . $i],
        'country code' => '',
        'bio' => '',
        'paperid' => $record['Paper ID'],
        'type' => 'chair'
      );
    }
  }
}

// Fix unknown@mail.com email addresses
foreach($contacts as $contactKey => $contact) {
  if($contact['email'] == 'unknown@mail.com') {
    $uniqueContactPattern = _clean($contact['first'] . $contact['last'] . $contact['affiliation']);
//    echo $uniqueContactPattern . PHP_EOL;
    foreach($contacts as $contactCheck) {
      $uniqueContactPatternCheck = _clean($contactCheck['first'] . $contactCheck['last'] . $contactCheck['affiliation']);
//      echo $uniqueContactPatternCheck . PHP_EOL;
      if($uniqueContactPattern == $uniqueContactPatternCheck && $contactCheck['email'] != 'unknown@mail.com' && $contactCheck['email'] != '') {
        $contacts[$contactKey]['email'] = $contactCheck['email'];
      }
    }
  }
}

// Write Full Contacts List
$resultsFile = $resultsDir . 'contactsFull.csv';
fopen($resultsFile, 'w');
$csvWriter = Writer::createFromPath($resultsFile, 'w'); // file
$csvWriter->insertOne(['id','presid','first','last','email','photo','position','affiliation','country code','bio','paperid','type']); // header
$csvWriter->insertAll($contacts); // rows

// Reduce to Unique Contacts
$uniqueArray = [];
$patterns = [];
$disposedContacts = [];

//foreach($contacts as $contactKey => $contact) {
//  $uniqueChairContactPattern = $contact['first'] . $contact['last'] . $contact['affiliation'];
//  if(!in_array($uniqueChairContactPattern, $disposed)) {
//    $disposed[$contacts[$contactKey]['id']] = array(
//      'id' => $contact['id']
//    );
//    $contactsUnique[] = $contact;
//  } else {
//    // add to disposed list
//    $disposed[][] = $contact['id'];
//  }
//}

$uniqueContacts = array_filter($contacts, function($contact) use (&$patterns, &$disposedContacts) {
  $uniqueChairContactPattern = _clean($contact['first'] . $contact['last'] . $contact['affiliation']);
  if (in_array($uniqueChairContactPattern, array_values($patterns))) {
    // @TODO: Add multiple paper ids together
    // print $contact['email'] . ' skipped' . PHP_EOL;
    // Anytime a duplicate email is found, add it to the removed ids array
    $disposedContacts[$uniqueChairContactPattern]['removed_ids'][] = $contact['id'];
    return false;
  } else {
    // print $contact['email'] . ' added to array' . PHP_EOL;
    $disposedContacts[$uniqueChairContactPattern] = ['unique_id' => $contact['id']];
    $patterns[] = $uniqueChairContactPattern;
    return true;
  }
});

// Write disposed contacts
file_put_contents('results/' . $machineName . '/disposedContacts.json', json_encode($disposedContacts, JSON_PRETTY_PRINT));

// Write Reduced Contacts
$resultsFile = $resultsDir . 'contacts.csv';
fopen($resultsFile, 'w');
$csvWriter = Writer::createFromPath($resultsFile); // file
$csvWriter->insertOne(['id','title','first','last','email','photo','position','affiliation','country code','bio','paperid','type']); // header
$csvWriter->insertAll($uniqueContacts); // rows

/**
 * Tracks, Types, Rooms
 */
$tracks = [];
$types = [];
$rooms = [];

foreach($records as $record) {
  // Tracks
  if(!in_array($record['Track ID'] * 10, array_column($tracks, 'id'))) {
    // Tracks get + 10 added to them to avoid decimals (hard eye roll)
    $tracks[] = array(
      'id' => $record['Track ID'] * 10,
      'name' => $record['Track Name']
    );
  }
  
  // Types
  if(!in_array($record['Decision Type'], array_column($types, 'name'))) {
    $types[] = array(
      'id' => $record['Paper ID'],
      'name' => $record['Decision Type']
    );
  }
}
// Write Tracks
$resultsFile = $resultsDir . 'tracks.csv';
fopen($resultsFile, 'w');
$csvWriter = Writer::createFromPath($resultsFile, 'w');
$csvWriter->insertOne(['id','name']); // header
$csvWriter->insertAll($tracks); // rows

// Write Types
$resultsFile = $resultsDir . 'types.csv';
fopen($resultsFile, 'w');
$csvWriter = Writer::createFromPath($resultsFile, 'w');
$csvWriter->insertOne(['id','name']); // header
$csvWriter->insertAll($types); // rows

/**
 * Papers, Presentations
 */
$papers = [];
$presentations = [];

foreach($records as $record) {
  // Accepted Only
  if ($record['Decision'] == 'Accept') {
    // Papers
    $papers[] = [
      'id' => $record['Paper ID'],
      'title' => $record['Paper Title'],
      'local file' => '',
      'remote file' => '',
      'link' => '',
      'author ids' => '',
    ];

    // Presentations
    $presentations[] = [
      'id' => $record['Paper ID'],
      'title' => $record['Paper Title'],
      'code' => '',
      'date' => '',
      'time range from' => '',
      'time range to' => '',
      'abstract' => $record['Abstract'],
      'description' => '',
      'speaker ids' => '',
      'chair ids' => '',
      'video embed' => '',
      'paper ids' => $record['Paper ID'],
      'topic ids' => '',
      'type id' => _idLookup($record['Decision Type'], $types, 'name'),
      'track id' => '',
      'room id' => '',
      'roles' => 'Attendee',
      'poccontactid' => _idLookup($record['Contact Email'], $contacts, 'email'),
    ];
  }
}
// Add Contacts to Presentations
// NOTE: This should come from Submission Webform
//foreach($presentations as $presKey => $presentation) {
//  foreach($contacts as $contactKey => $contact) {
//    if($presentation['id'] == $contact['paper ids']) {
//      $presentations[$presKey]['speaker ids'] = $presentations[$presKey]['speaker ids'] . $contact['id'] . '||';
//    }
//  }
//}

// Write Presentations
$resultsFile = $resultsDir . 'presentations.csv';
fopen($resultsFile, 'w');
$csvWriter = Writer::createFromPath($resultsFile, 'w');
$csvWriter->insertOne(['id','title','code','date','time range from','time range to','abstract','description','speaker ids','chair ids','video embed','paper ids','topic ids','type id','track id','room id','roles','poccontactid','speaker ids new']); // header
$csvWriter->insertAll($presentations); // rows

// Add Contacts to Papers
foreach($papers as $paperKey => $paper) {
  foreach($contacts as $contactKey => $contact) {
    if($paper['id'] == $contact['paperid']) {
      if($papers[$paperKey]['author ids'] == '') {
        $papers[$paperKey]['author ids'] = $contact['id'];
      } else {
        $papers[$paperKey]['author ids'] = $papers[$paperKey]['author ids'] . '||' . $contact['id'];
      }
    }
  }
}

// Write Papers
$resultsFile = $resultsDir . 'papers.csv';
fopen($resultsFile, 'w');
$csvWriter = Writer::createFromPath($resultsFile, 'w');
$csvWriter->insertOne(['id', 'title','local file','remote file','link','author ids']); // header
$csvWriter->insertAll($papers); // rows

/**
 * Sessions
 */
$sessions = [];
foreach($records as $key => $record) {
  // Accepted Only
  if($record['Decision'] == 'Accept') {
    // Sessions
    if(!in_array($record['Session Name'], array_column($sessions, 'title'))) {
      $sessions[$record['Session Name']] = array(
        'id' => $record['Paper ID'],
        'title' => $record['Session Name'],
        'code' => $record['Session Code'],
        'description' => '',
        'length' => '',
        'pres ids' => $record['Paper ID'],
        'chair ids' =>
          ($record['Session Chair Last1'] != '' ? _idLookup($record['Session Chair Last1'], $contacts, 'last') : '') .
          ($record['Session Chair Last2'] != '' ? '||' . _idLookup($record['Session Chair Last2'], $contacts, 'last') : ''),
        'organizer ids' => '',
        'date' => date('Y-m-d', strtotime($record['Session Start Time'])),
        'time range from' => date('n/j/Y G:i:s', strtotime($record['Session Start Time'])),
        'time range to' => date('n/j/Y G:i:s', strtotime($record['Session End Time'])),
        'type' => $record['Session Type'],
        'track id' => $record['Track ID'] * 10,
        'room id' => '',
      );
    } else {
      $sessions[$record['Session Name']]['pres ids'] = $sessions[$record['Session Name']]['pres ids'] . '||' . $record['Paper ID'];
    }
  }
}

// Write Sessions
$resultsFile = $resultsDir . 'sessions.csv';
fopen($resultsFile, 'w');
$csvWriter = Writer::createFromPath($resultsFile,'w');
$csvWriter->insertOne(['id','title','code','description','length','pres ids','chairs','organizer ids','date','time range from','time range to','type','track id','room id']); // header
$csvWriter->insertAll($sessions); // rows

/**
 * Submission System
 */

// Write Submission Links
$resultsFile = $resultsDir . 'submission-links.csv';
fopen($resultsFile, 'w');
$csvWriter = Writer::createFromPath($resultsFile,'w');
$csvWriter->insertOne(['id','title','code']); // header
$csvWriter->insertAll($sessions); // rows