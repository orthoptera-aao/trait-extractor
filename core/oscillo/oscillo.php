<?php

function oscillo_info() {
  return(
    array(
      "oscillo" => array(
        "dependencies" => array()
      )
    )
  );
}

function oscillo_init() {
  $init = array(
    "R" => array(
      "type" => "cmd",
      "required" => "required",
      "missing text" => "Oscillo requires R.",
      "version flag" => "--version"
    ),
    "seewave" => array( 
      "type" => "Rpackage",
      "required" => "required",
      "missing text" => "Oscillo requires the R seewave package.",
      "version flag" => "--quiet -e 'packageVersion(\"seewave\")'",
      "version line" => 1
    )
  );
  return ($init);
}

function oscillo_analyse($recording) {
print_r($recording);
return array();
}