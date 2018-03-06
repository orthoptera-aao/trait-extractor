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
    )
  );
  return($init);
}

function bioacoustica_prepare() {
  _bioacoustica_prepare_recordings();
  _bioacoustica_prepare_analyses();
  return(array());
}

function bioacoustica_transcode($data) {
  $return = array();
  if (!in_array($data["id"].".wav", $GLOBALS["bioacoustica"]["wave"])) {
    $extension = _bioacoustica_get_extension($data["file"]);
    if ($extension == "wav") {
      exec("wget --quiet ".$data["file"]." -O core/bioacoustica/transcode/".$data["id"].".wav", $output, $return_value);
      if ($return_value == 0) {
        $return = array(
          $data["id"] => array(
            "file name" => $data["id"].".wav",
            "local path" => "core/bioacoustica/transcode/",
            "save path" => "wav/"
          )
        );
      } else {
        echo "NOTICE: Could not download file for recording ".$data["id"]."\n";
      }
    } else {
      //Transcode
    }
  }
  return($return); //Needs to be files 
}

function bioacoustica_clean() {
  exec("rm core/bioacoustica/transcode/*.wav", $output, $return_value);
  return(array());
}

function _bioacoustica_get_extension($path) {
  $pos = strrpos($path, ".");
  return(strtolower(substr($path, $pos + 1)));
}

function _bioacoustica_prepare_analyses() {
  exec("s3cmd ls s3://bioacoustica-analysis/wav/", $output, $return_value);
  if ($return_value == 0) {
    if (count($output) == 0) {
      $GLOBALS["bioacoustica"]["wave"] = array();
    } else {
      foreach ($output as $line) {
        $start = strrpos($line, "/");
        $GLOBALS["bioacoustica"]["wave"][] = substr($line, $start + 1);
      }
    }
  }
}

function _bioacoustica_prepare_recordings() {
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
    fgetcsv($fh_recordings); //Discard headers row.
    while (($data = fgetcsv($fh_recordings)) !== FALSE) {
      $GLOBALS["core"]["recordings"][] = array_combine($keys, array_merge($data, array("bioacoustica")));
    }
  } else {
    echo "Could not download BioAcoustica recording metdata.\nExiting\n.";
    exit;
  }
}