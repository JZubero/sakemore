<?php

namespace SilverStripe\Sakemore\Commands;

use DataExtension;
use Silverstripe\Sakemore\Helpers\SakeMoreHelper;

class Snapshot extends DataExtension {
    const CMD_SNAPSHOT = 'snapshot';

    /**
     * Tell sake more about this command.
     */
    public function commands(&$list) {
        $list[self::CMD_SNAPSHOT] = array($this, 'handleSnapshot');
    }

    /**
     * Gives sake a brief for the help section.
     */
    public function help_brief(&$details) {
        $details[self::CMD_SNAPSHOT] = 'Generates a snapshot of the current state. A snapshot is a backup of the database and assets in an archive.';
    }

    /**
     * Gives sake a list of the parameters used.
     */
    public function help_parameters(&$details) {
        // Get the list of commands.
        $commands = $this->availableCommands();

        $details[self::CMD_SNAPSHOT] = array(
            'Type - A choice of: ' . implode(', ', array_keys($commands)),
        );
    }

    /**
     * Gives sake a list of examples of how to use this command.
     */
    public function help_examples(&$examples) {
        $examples[self::CMD_SNAPSHOT] = array();

        $commands = $this->availableCommands();
        foreach ($commands as $command => $specifics) {
            $examples[self::CMD_SNAPSHOT][] = sprintf(
                '%s %s - %s',
                self::CMD_SNAPSHOT, $command, $specifics['description']
            );
        }
    }

    /**
     * Gets a list of the snapshot commands available.
     */
    protected function availableCommands() {
        return array(
            'save' => array(
                'description' => 'Saves a snapshot to the temp directory of your system. Use the "--db" flag to save only the database.'
            ),
            'load' => array(
                'description' => 'Loads a snapshot into the local instance. The "src" parameter specifies the .sspak file to load. CAUTION: It overwrites the database and all the assets.'
            )
        );
    }

    /**
     * Checks if a certain command flag is present, e.g. "--db" or "--assets".
     * @param $flag
     *
     * @return bool
     */
    private function hasFlag($flag) {
        $args = array_filter($_GET['args'], function($arg) {
            return substr($arg, 0, 2) === '--';
        });

        return in_array("--{$flag}", $args);
    }

    /**
     * Checks some system conditions and prepares the snapshot commands.
     * @param null $type Can be "save" or "load"
     *
     * @return mixed|string
     */
    public function handleSnapshot($type = null) {

        // Check for UNIX
        if($this->isWIN()) {
            return 'The "snapshot" command is only available for Unix-based OS.';
        }

        // Check if "sspak" is available
        if(!$this->command_exist('sspak')) {
            return '"sspak" is not available. Check "https://github.com/silverstripe/sspak#installation" for an installation guide.';
        }

        $commands = $this->availableCommands();

        // Validate the input
        if (!$type) {
            return 'What do you want to do? Choose either "save" or "load". See "sake more help" for more details';
        }

        if (!array_key_exists($type, $commands)) {
            return sprintf('Unexpected parameter "%s". See "sake more help" for more details', $type);
        }

        // Obtain CLI command and run
        $cmd = $this->generateCliCommand($type);
        SakeMoreHelper::runCLI($cmd['command']);

        return $cmd['info'];
    }

    /**
     * Checks for Windows OS.
     * Taken from: https://stackoverflow.com/a/5879078
     *
     * @return bool
     */
    private function isWIN() {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    /**
     * Checks if the given (Shell) command exists.
     * Taken from: https://stackoverflow.com/a/12425039
     *
     * @param $cmd
     *
     * @return bool
     */
    private function command_exist($cmd) {
        $return = shell_exec(sprintf("which %s", escapeshellarg($cmd)));
        return !empty($return);
    }

    /**
     * Generates the actual CLI commands.
     * @param $type
     *
     * @return array Fields "command" and "info".
     */
    private function generateCliCommand($type) {
        $rootFolder = \Director::baseFolder();
        $command = array();

        // Build "save" command
        if ($type === 'save') {
            $command[] = 'sspak save';

            // Prepare file name and save location
            $folderName = array_reverse(explode(DIRECTORY_SEPARATOR, $rootFolder))[0];
            $projectName = $GLOBALS['project'];
            $snapshotName = implode('_', array(
                $folderName,
                $projectName,
                date('Y-m-d-H-i-s')
            ));
            $saveLocation = implode(DIRECTORY_SEPARATOR, array(
                sys_get_temp_dir(),
                $snapshotName
            ));
            if($this->hasFlag('db')) {
                $command[] = '--db';
            }

            $command[] = "{$rootFolder} {$saveLocation}.sspak";

            $info = "The snapshot was saved to \"{$saveLocation}.sspak\".";
        } // Build the "load" command
        elseif ($type === 'load') {

            // Check for source
            if(!isset($_GET['src'])) {
                die("You need to specify a .sspak file to load as \"src\" parameter (absolute path)." . PHP_EOL);
            }

            // Remove current assets folder
            $command[] = 'rm -rf assets;';

            // Build sspak load command
            $command[] = 'sspak';
            $src = $_GET['src'];
            $command[] = "load {$src} {$rootFolder};";

            // Clear caches
            $command[] = "sake more clear all";
            $info = "The snapshot \"" . array_reverse(explode(DIRECTORY_SEPARATOR, $src))[0] . "\" was loaded and the caches had been cleared.";
        }

        return array(
            'command' => implode(' ', $command),
            'info'    => $info
        );
    }
}