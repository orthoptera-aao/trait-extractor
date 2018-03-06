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
      $git_name = end(explode("/", $repo));
      $dir = "modules/".substr($git_name, 0, strlen($git_name) - 4);
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
    unset($path);
    unset($version);
    exec("which ".$data["cmd"], $path, $return_value);
    if ($return_value == 0) {
      $output = array();
      exec($data['cmd']." ".$data['version flag'], $version, $return_value);
      if ($return_value == 0) {
        if (isset($data["version line"])) {
          $version = $version[$data["version line"]];
        } else {
          $version = $version[0];
        }
        $return[] = array(
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
  }
  return($return);
}

function core_init() {
  $init = array(
    "php" => array(
      "cmd" => "php",
      "required" => "required",
      "missing text" => "You should not get to this point in execution.",
      "version flag" => "-v"
    ),
    "git" => array(
      "cmd" => "git",
      "required" => "optional",
      "missing text" => "Git is not installed. You will not be able to use non-core modules.\n",
      "version flag" => "--version"
    )
  );
  return core_init_check($init);
}

function core_prepare() {
  //Download metadata files

  //Loads the recordings metadata into memory as $GLOBALS["core"]["recordings"]
}

function core_transcode() {
  //Convert non-wave formats to wave audio for easier analysis.

  //return files, etc
}

function core_save($transcodes, $analyses) {
  //Put all outputs onto BioAcoustica storage.

  //If current dir has not had $init saved to it, save data and add dir to $init_data_saved_to_dir = array();
}

function core_clean($transcodes, $analyses) {
  //Delete things
}
