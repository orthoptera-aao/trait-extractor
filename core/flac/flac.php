<?php

function flac_init() {
  $init = array(
    "s3cmd" => array(
      "type" => "cmd",
      "required" => "required",
      "missing text" => "BioAcoustica requires s3cmd to access data.",
      "version flag" => "--version"
    ),
    "wget" => array(
      "type" => "cmd",
      "required" => "required",
      "missing text" => "BioAcoustica requires wget to access some files.",
      "version flag" => "--version"
    ),
    "ffmpeg" => array(
      "type" => "cmd",
      "required" => "required",
      "missing text" => "BioAcoustica requires ffmpeg for transcoding files.",
      "version flag" => "-version"
    )
  );
  return($init);
}

function flac_prepare() {
  global $system;
  core_log("info", "flac", "Attempting to list flac files on analysis server.");
  exec("s3cmd ls s3://bioacoustica-analysis/flac/", $output, $return_value);
  if ($return_value == 0) {
    if (count($output) == 0) {
      $system["analyses"]["flac"] = array();
    } else {
      foreach ($output as $line) {
        $start = strrpos($line, "/");
        $system["analyses"]["flac"][] = substr($line, $start + 1);
      }
    }
  core_log("info", "flac", count($system["analyses"]["flac"])." flac files found.");
  }
  return(array());
}

function flac_transcode($data) {
  global $system;
  $return = array();
  if (!in_array($data["id"].".flac", $system["analyses"]["flac"])) {
    core_log("info", "flac", "File ".$data["id"]." needs flac version.");
    $file = core_download("wav/".$data["id"].".wav");
    if ($file == NULL) {
      core_log("warning", "flac", "File was not available, skipping conversion.");
      return($return);
    } else {
      $return["wave"] = array(
        "file name" => $data["id"].".wav",
        "local path" => "scratch/wav/",
        "save path" => NULL
        );
    }
    exec("ffmpeg -i scratch/wav/".$data["id"].".wav scratch/flac/.".$data["id"].".flac -y", $output, $return_value);
    if ($return_value == 0) {
      $return ["flac"]= array(
        "file name" =>$data["id"].".flac",
        "local path" => "scratch/flac/",
        "save path" => "flac/"
      );
    } else {
      core_log("warning", "flac", "flac file was not created: ".serialize($output));
    }
  }

  return($return); //Needs to be files 
}