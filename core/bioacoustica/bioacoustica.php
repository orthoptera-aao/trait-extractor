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
    ),
    "wget" => array(
      "type" => "cmd",
      "required" => "required",
      "missing text" => "BioAcoustica requires wget to access some files.",
      "version flag" => "--version"
    ),
    "magic" => array(
      "type" => "pythonmodule",
      "required" => "optional",
      "missing text" => "s3cmd is quieter when the python-magic module is installed."
    )
  );
  return($init);
}

function bioacoustica_prepare() {
  _bioacoustica_prepare_recordings();
  _bioacoustica_prepare_analyses();
  return(array());
}

function _bioacoustica_prepare_analyses() {
  global $system;
  $system["analyses"]["wav"] = array();
  core_log("info", "core", "Attempting to list wave files on analysis server.");
  exec("s3cmd ls s3://bioacoustica-analysis/wav/", $output, $return_value);
  if ($return_value == 0) {
    if (count($output) == 0) {
      //TODO: Error checking
    } else {
      foreach ($output as $line) {
        $start = strrpos($line, "/");
        $system["analyses"]["wav"][] = substr($line, $start + 1);
      }
    }
  }
  core_log("info", "bioacoustica", count($system["analyses"]["wav"])." wave files found.");
}

function bioacoustica_transcode($data) {
  global $system;
  $return = array();
  if (!in_array($data["id"].".wav", $system["analyses"]["wav"])) {
  exit;print_r(count($system["analyses"]["wav"]));exit;
    core_log("info", "bioacoustica", "BioAcoustica file ".$data["id"]." needs to be uploaded to analysis server.");
    $extension = _bioacoustica_get_extension($data["file"]);
    if ($extension == "wav") {
      exec("wget --quiet ".$data["file"]." -O scratch/wav/".$data["id"].".wav", $output, $return_value);
      if ($return_value == 0) {
        $return = array(
          $data["id"] => array(
            "file name" => $data["id"].".wav",
            "local path" => "scratch/wav/",
            "save path" => "wav/"
          )
        );
      } else {
        core_log("warning", "bioacoustica", "Could not download file for BioAcosutica recording ".$data["id"].".");
      }
    } else {
      core_log("info", "bioacoustica", "BioAcoustica file ".$data["id"]." needs to be transcoded and uploaded to analysis server.");
      //Transcode
    }
  }
  return($return); //Needs to be files 
}

function bioacoustica_clean() {
  exec("rm scratch/wav/*.wav", $output, $return_value);
  return(array());
}

function _bioacoustica_get_extension($path) {
  $pos = strrpos($path, ".");
  return(strtolower(substr($path, $pos + 1)));
}

function _bioacoustica_prepare_recordings() {
  global $system;
  core_log("info", "bioacosutica", "Attempting to download s3://bioacoustica-analysis/R/recordings.txt core/bioacoustica/prepare/recordings.txt");
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
    core_log("info", "bioacoustica", "Downlaoded recording metadata to core/bioacoustica/prepare/recordings.txt");
    fgetcsv($fh_recordings); //Discard headers row.
    while (($data = fgetcsv($fh_recordings)) !== FALSE) {
      $system["core"]["recordings"][] = array_combine($keys, array_merge($data, array("bioacoustica")));
    }
    core_log("info", "bioacoustica", "Loaded BioAcoustica recordings into recordings array.");
  } else {
    core_log("fatal", "bioacoustica", "Could not download BioAcoustica recording metdata.");
  }
}