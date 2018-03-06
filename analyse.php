<?php
/*
This is the main file for running the analysis.

The file calls the core module which provides the running environemnt, and manages calls
to functions from other modules so they can be integrated into the analysis run.

*/


include("core/core.php");

$mode = "analayse"; //Set to "test" for test mode. Make this a command line switch.

//Core init needs to happen early on.
$init = core_init();


/*
Load config/modules.info which is a list of GitHuib repositories to load modules from.
Checks that all module dependecies are satisified.
Get the master branch from each before continuing.
*/
core_load_modules();

/*
The functions return information of relevence to the analysis run, e.g. verions of
software used.
*/
$init = array_merge($init, core_hook("init"));

/*
The prepare phase downloads metadata files that are required for the functioning of
each module.

Modules may load this data into $GLOBALS[module_name][data]
*/
core_prepare();
core_hook("prepare");

/*
If running in test mode do the tests and exit. Each module may define files for testing,
and tests to run on those files.
*/
if ($mode == "test") {
  //Load data need for tests
  //Do tests (transcode, analyse)
  exit();
}

$verification_notes = array();
$comparison_notes = array();

foreach ($GLOBALS["core"]["recordings"]["id"] as $recording_id) {
  /*
  Each recording is processed sequentially to avoid filling the drive with audio files.

  Each task (e.g. transcode, analyse, save) should check whether the work actually needs
  to be performed, generally by comparison to the metadata downloaded in core_prepare or
  hook_prepare.

  The core_download() function should be used to avoid downloading a single file multiple
  times.

  By default all outputs are saved to the bioacoustica-analysis repository.

  Each module should return an array of the form:

  array(
    "name" => module_name,
    "version" => identifier of the GitHub Commit,
    "files" => array(
               ),
  )

  The name and version are used as name/version/ in file paths for core output storage,
  ensuring that each version of an algorithm has it's data stored separately.

  The files array is a list of files from the transcode or analyse function that should
  be saved.
  */

  /*
  The transcode phase is used primarily for transcoding audio between formats, e.g. to flac
  to reduce filesize if transferring to a external service.

  It may also be used for other transformations before analysis.
  */

  $transcodes = array(
    core_transcode,
    core_hook("transcode", array("recording_id" => $recording_id))
  );

  /*
  The analysis phase does the bulk of the work.
  */
  $analyses = core_hook("analyse", array("recording_id" => $recording_id));

  /*
  The verify phase checks the analysis results against rules that should be true for all
  recordings, these functions may reuse some tests internally.
  */
  $verification_notes[] = core_hook("verify",
  									array("recording_id" => $recording_id,
  									      "analyses" => $analyses)
  								   );

  /*
  The compare phase can be used to compare the results of analyses with other data, e.g.
  literature traits, and raise a message when they seem incompatible.
  */
  $comparison_notes[] = core_hook("compare",
                                  array("recording_id" => $recording_id,
                                        "analyses" => $analyses)
                                 );

  /*
  The save phase records the outputs of transcode and analyse to the core data repository
  and to other locations specified by other modules.
  */
  core_save($transcodes, $analyses);
  core_hook("save", array("recording_id" => $recording_id,
                          "transcodes" => $transcodes,
                          "analyses" => $analyses)
           );

  /*
  The clean phase removes all files downloaded or created during the analysis process to
  keep the server clean.
  */
  core_clean($transcodes, $analyses);
}
