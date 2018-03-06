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
    "tuneR" => array( //change to seewave
      "type" => "Rpackage",
      "required" => "required",
      "missing text" => "Oscillo requires the R seewave package.",
      "version flag" => "--quiet -e 'packageVersion(\"seewave\")'",
      "version line" => 1
    )
  );
  return core_init_check($init);
}