<?php
//globals: need to add $init_data_saved_to_dir = array();

function core_load_modules() {
  $module_info_path = NULL;
  if (file_exists("config/modules.info")) {
    $module_info_path = "config/modules.info";
  } else if (file_exists("config/modules.info.default")) {
    $module_info_path = "config/modules.info.default";
    echo "Using default values for config/modules.info\n";
  } else {
    echo "config/modules.info does not exist, and the default file has been removed.\n";
    echo "Only core features will run.\n";
  }
  include($module_info_path);

  foreach($modules_info as $repo => $modules) {
    if ($repo != "core") {
      core_pull_github($repo);
      $dir = "modules/"._core_get_github_folder($repo);
    }  else {
      $dir = "core";
    }
      foreach ($modules as $module) {
        if (!is_dir("$dir/$module")) {
          echo "$dir/$module does not exist but is requested.\nExiting.\n";
          exit;
        }
        if (file_exists("$dir/$module/$module.php")) {
          include("$dir/$module/$module.php");
        } else {
          echo "$module module does not have a $module.php file.\nExiting.\n";
          exit;
        }
        $GLOBALS["modules"][] = $module;
      }
  }

  $module_info = core_hook("info");

  foreach ($module_info as $key => $value) {
    foreach ($value as $module => $data) {
      if (isset($data["dependencies"])) {
        foreach ($data["dependencies"] as $dependency) {
          if (!in_array($dependency, $GLOBALS["modules"])) {
            echo "'$module' requires the '$dependency' module but it is not included.\nExiting\n";
            exit;
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
  if ($GLOBALS["core"]["cmd"]["git"] != TRUE) {
    echo "You have requested loading external modules from GitHub, but Git is not installed.\nExiting.\n";
    exit;
  }
  if (!is_array($repos)) {
    $repos = array($repos);
  }
  foreach ($repos as $repo) {
    unset($output);
    unset($return_value);
    $dir = _core_get_github_folder($repo);
    if (!is_dir("modules/$dir")) {
      exec("cd modules; git clone $repo; cd ..", $output, $return_value);
    }
    exec("cd modules/$dir; git pull; git checkout master; cd ../..", $output, $return_value);
  }
}

function core_hook($hook, $data = NULL) {
  $returns = array();
  foreach ($GLOBALS["modules"] as $module) {
    if (!function_exists($module."_".$hook)) {
      continue;
    }
    $returns = array_merge($returns, call_user_func($module."_".$hook, $data));
  }
  return($returns);
}

function core_download($file_with_path) {
  //Check if file has been downloaded, if not download it

  return($path_to_file);
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
        echo "Undefined depency type: $cmd_name.\n";
        exit;
    }
  }
  return($return);
}

function _core_init_check_pythonmodule($module, $data) {
  $return = array();
  exec("python -c \"import $module \" &> /dev/null", $output, $return_value);
  if ($return_value == 0) {
  
  } else {
    echo $data["missing text"]."\n";
    $GLOBALS["core"]["cmd"][$module] = FALSE;
    if ($data["required"] == "required") {
      echo "Exiting.\n";
      exit;
    }
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
        $GLOBALS["core"]["cmd"][$package] = TRUE;
      }
    } else {
      echo $data["missing text"]."\n";
      $GLOBALS["core"]["cmd"][$package] = FALSE;
      if ($data["required"] == "required") {
        echo "Exiting.\n";
        exit;
      }
    }
  return($return);
} 

function _core_init_check_cmd($cmd_name, $data) {
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
        $GLOBALS["core"]["cmd"][$cmd_name] = TRUE;
      }
    } else {
      echo $data["missing text"]."\n";
      $GLOBALS["core"]["cmd"][$cmd_name] = FALSE;
      if ($data["required"] == "required") {
        echo "Exiting.\n";
        exit;
      }
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
  $GLOBALS["core"]["recordings"] = array();
}

function core_transcode() {
  //Convert non-wave formats to wave audio for easier analysis.

  //return files, etc
  return(array());
}

function core_save($files) {
  foreach ($files as $id => $data) {
    exec("s3cmd put --force ".$data["local path"].$data["file name"]." s3://bioacoustica-analysis/".$data["save path"], $output, $return_value);
  }
}

function core_clean($files) {
  foreach ($files as $id => $data) {
    exec("rm ".$data["local path"].$data["file name"], $output, $return_value);
  }
}
