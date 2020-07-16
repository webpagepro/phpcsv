<?php
/**
 * Functions
 */
// Lookup
function _idLookup($string, $array, $column) {
  // return id
  foreach($array as $value) {
    if(in_array($string, array_column($array, $column))) {
      if($string == $value[$column]) {
        return $value['id'];
      }
    }
  }
}

// Replace IDs
function _replaceRemovedIds($index, $contactId, $removed) {
  // email: [uniqueid, removedids]
  foreach ($removed as $key => $item) {
    if (isset($item['removed_ids'])) {
      foreach ($item['removed_ids'] as $id) {
        if ($contactId == $id) {
          return strval($item['unique_id']);
        }
      }
    }
  }
}

// Clean String
function _clean($string) {
  $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.

  return preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.
}

// Author Name Lookup
function _contactLookup($id, $array, $column) {
  $key = array_search($id, array_column($array,'id'));
  if(isset($array[$key][$column])) {
    return $array[$key][$column];
  } else {
    echo $id . ' not found' . PHP_EOL;
    return '';
  }
}