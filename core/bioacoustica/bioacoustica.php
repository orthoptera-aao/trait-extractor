<?php

function bioacoustica_init() {
  $init = array(
    "R" => array(
      "type" => "cmd",
      "required" => "required",
      "missing text" => "BioAcoustica requires R.",
      "version flag" => "--version"
    ),
    "s3cmd" => array(
      "type" => "cmd",
      "required" => "required",
      "missing text" => "BioAcoustica requires s3cmd to access data.",
      "version flag" => "--version"
    )
  );
  return($init);
}

function bioacoustica_prepare() {
  exec("s3cmd get --force s3://bioacoustica-analysis/R/recordings.txt core/bioacoustica/prepare/recordings.txt", $output, $return_value);
  if ($return_value == 0) {
    $keys = array(
      "id",
      "taxon",
      "file",
      "author",
      "uploaded",
      "size",
      "byte size",
      "MIME",
      "source"
    );
    $fh_recordings = fopen("core/bioacoustica/prepare/recordings.txt", 'r');
    while (($data = fgetcsv($fh_recordings)) !== FALSE) {
      $GLOBALS["core"]["recordings"][] = array_combine($keys, array_merge($data, array("bioacoustica")));
    }
  } else {
    echo "Could not download BioAcoustica recording metdata.\nExiting\n.";
    exit;
  }
  print_r($GLOBALS["core"]["recordings"]);exit;
}