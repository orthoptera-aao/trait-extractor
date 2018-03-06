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
  exec("s3cmd get s3://bioacoustica-analysis/R/recordings.txt core/bioacoustica/prepare/recordings.txt", $output, $return_value);
  
}