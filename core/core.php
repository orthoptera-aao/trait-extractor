<?php
//globals: need to add $init_data_saved_to_dir = array();

function core_log($state="init", $module = NULL, $message = NULL) {
  if ($state == "init") {
    _core_log_init();
  } else if ($state == "done") {
    _core_log_close();
  } else {
    _core_log_write($state, $module, $message);
  }
}

function _core_log_init() {
  global $system;
  $timestamp = time();
  $system["core"]["logfile"] = fopen("runs/".$timestamp.".csv", 'a');
  $system["core"]["logfile name"] = $timestamp.".csv";
  core_log("info", "core", "Logfile started.");
}

function _core_log_write($state, $module, $message) {
  global $system;
  $data = array(
    date("r"),
    $state,
    $module,
    $message
  );
  fputcsv($system["core"]["logfile"], $data);
  if ($state == "fatal") {
    _core_log_close();
  }
}

function _core_log_close() {
  global $system;
  _core_log_write("info", "core", "Closing logfile.");
  fclose($system["core"]["logfile"]);
  exec("s3cmd put runs/".$system["core"]["logfile name"]." s3://bioacoustica-analysis/runs/", $output, $return_value);
}

function core_load_modules() {
  global $system;
  $module_info_path = NULL;
  if (file_exists("config/modules.info")) {
    $module_info_path = "config/modules.info";
    core_log("info", "core", "Using module info file: config/modules.info");
  } else if (file_exists("config/modules.info.default")) {
    $module_info_path = "config/modules.info.default";
    core_log("info", "core", "Using default values for config/modules.info");
  } else {
    core_log("info", "core", "config/modules.info does not exist, and the default file has been removed. Only core features will run.");
  }
  include($module_info_path);

  foreach($modules_info as $repo => $modules) {
    if ($repo != "core") {
      $git_hash = core_pull_github($repo);
      $dir = "modules/"._core_get_github_folder($repo);
    }  else {
      $dir = "core";
      exec("git rev-parse HEAD", $output, $return_value);
      $git_hash = $output[0];
    }
    foreach ($modules as $module) {
      if (!is_dir("$dir/$module")) {
        core_log("fatal", $module, "$dir/$module does not exist but is requested.");
      }
      if (file_exists("$dir/$module/$module.php")) {
        include("$dir/$module/$module.php");
      } else {
        core_log("fatal", $module, "$module module does not have a $module.php file.");
      }
      $system["modules"][$module] = array("git_hash" => $git_hash);
      core_log("info", "core", "Loaded module $module (version: $git_hash).");
    }
  }

  $module_info = core_hook("info");

  foreach ($module_info as $key => $value) {
    foreach ($value as $module => $data) {
      if (isset($data["dependencies"])) {
        foreach ($data["dependencies"] as $dependency) {
          if (!in_array($dependency, $system["modules"])) {
            core_log("fatal", "core", "'$module' requires the '$dependency' module but it is not included.");
          }
        }
      }
    }
  }
}

function _core_get_github_folder($repo) {
  $git_name = explode("/", $repo);
  $git_name = end($git_name);
  $dir = substr($git_name, 0, strlen($git_name) - 4);
  return($dir);
}

function core_pull_github($repos) {
  global $system;
  if ($system["core"]["cmd"]["git"] != TRUE) {
    core_log("fatal", "core", "You have requested loading external modules from GitHub, but Git is not installed.");
  }
  if (!is_array($repos)) {
    $repos = array($repos);
  }
  foreach ($repos as $repo) {
    unset($output);
    unset($return_value);
    $dir = _core_get_github_folder($repo);
    if (!is_dir("modules/$dir")) {
      exec("cd modules; git clone --recurse-submodules $repo; cd ..", $output, $return_value);
    }
    
    unset($output);
    unset($return_value);
    exec("cd modules/$dir; git pull --recurse-submodules; git checkout master  &> /dev/null; cd ../..", $output, $return_value);
    
    unset($output);
    unset($return_value);
    exec("cd modules/$dir; git rev-parse HEAD; cd ../..", $output, $return_value);
    return($output[0]); //Return hash of git commit.
  }
}

function core_hook($hook, $data = NULL) {
  global $system;
  $returns = array();
  foreach ($system["modules"] as $module => $module_data) {
    if (function_exists($module."_".$hook)) {
      $returns = array_merge($returns, call_user_func($module."_".$hook, $data));
    }
  }
  return($returns);
}

function core_download($file_with_path) {
  if (file_exists("scratch/".$file_with_path)) {
    return $file_with_path;
  } else {
    core_log("info", "core", "No scratch match for scratch/$file_with_path - attempting download.");
    exec("s3cmd get s3://bioacoustica-analysis/$file_with_path scratch/$file_with_path", $output, $return_value);
    if ($return_value == 0) {
      return $file_with_path;
    }
    core_log("warning", "core", "core_download: scratch/$file_with_path is unavailable.");
    return NULL;
  }
}

function core_init_check($inits) {
  $return = array();
  foreach ($inits as $cmd_name => $data) {
    switch ($data["type"]) {
      case "cmd":
        $return[] = _core_init_check_cmd($cmd_name, $data);
        break;
      case "Rpackage" :
        $return[] = _core_init_check_Rpackage($cmd_name, $data);
        break;
      case "pythonmodule":
        $return[] = _core_init_check_pythonmodule($cmd_name, $data);
        break;
      default:
        core_log("fatal", "core", "Undefined depency type: $cmd_name.");
    }
  }
  return($return);
}

function _core_init_check_pythonmodule($module, $data) {
  $return = array();
  exec("python -c \"import $module \" &> /dev/null", $output, $return_value);
  if ($return_value == 0) {
  
  } else {
    $system["core"]["cmd"][$module] = FALSE;
    $status = "warning";
    if ($data["required"] == "required") {
      $status = "fatal";
    }
    core_log($status, $module, $data["missing text"]);
  }
}


function _core_init_check_Rpackage($package, $data) {
  $return = array();
  exec("Rscript core/scripts/package_install_check.R ".$package, $output, $return_value);
  if ($return_value == 0) {
      $output = array();
      exec("R --quiet -e 'packageVersion(\"$package\")'", $version, $return_value);
      
      if ($return_value == 0) {
        $version = substr($version[1], 7, strlen($version[1]) - 10);
        $return = array(
          "cmd_name" => $package,
          "version" => $version
        );
        $system["core"]["cmd"][$package] = TRUE;
      }
    } else {
      $system["core"]["cmd"][$package] = FALSE;
      $status = "warning";
      if ($data["required"] == "required") {
        $status = "fatal";
      }
      core_log($status, $package, $data["missing text"]);
    }
  return($return);
} 

function _core_init_check_cmd($cmd_name, $data) {
  global $system;
  $return = array();
    exec("which ".$cmd_name, $path, $return_value);
    if ($return_value == 0) {
      $output = array();
      exec($cmd_name." ".$data['version flag'], $version, $return_value);
      if ($return_value == 0) {
        if (isset($data["version line"])) {
          $version = $version[$data["version line"]];
        } else {
          $version = $version[0];
        }
        $return = array(
          "cmd_name" => $cmd_name,
          "path" => $path[0],
          "version" => $version
        );
        $system["core"]["cmd"][$cmd_name] = TRUE;
      }
    } else {
      $system["core"]["cmd"][$cmd_name] = FALSE;
      $status = "warning";
      if ($data["required"] == "required") {
        $status = "fatal";
      }
      core_log($status, $cmd_name, $data["missing text"]);
    }
    return($return);
}

function core_init() {
  $init = array(
    "php" => array(
      "type" => "cmd",
      "required" => "required",
      "missing text" => "You should not get to this point in execution.",
      "version flag" => "-v"
    ),
    "git" => array(
      "type" => "cmd",
      "required" => "optional",
      "missing text" => "Git is not installed. You will not be able to use non-core modules.\n",
      "version flag" => "--version"
    )
  );
  return core_init_check($init);
}

function core_prepare() {
  global $system;
  $system["core"]["recordings"] = array();
  core_log("info", "core", "Created recordings array.");
}

function core_transcode() {
  //Convert non-wave formats to wave audio for easier analysis.

  //return files, etc
  return(array());
}

function core_save($files) {
  foreach ($files as $id => $data) {
    if ($data["save path"] != NULL) {
      exec("s3cmd put --force ".$data["local path"].$data["file name"]." s3://bioacoustica-analysis/".$data["save path"], $output, $return_value);
      if ($return_value == 0) {
        core_log("info", "core", "Uploaded s3://bioacoustica-analysis/".$data["save path"].$data["file name"]." to analysis server.");
      }
    }
  }
}

function core_clean($files) {
  foreach ($files as $id => $data) {
    if (file_exists($data["local path"])) {
      exec("rm ".$data["local path"].$data["file name"], $output, $return_value);
    }
  }
}
