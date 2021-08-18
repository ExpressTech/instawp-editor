<?php
require_once dirname(__FILE__) . "/../classes/_ExtraProcesses.php";
require_once dirname(__FILE__) . "/../classes/Settings.php";
require_once dirname(__FILE__) . "/../classes/System.php";
use ICEcoder\ExtraProcesses;
$settingsClass = new \ICEcoder\Settings();
$systemClass = new \ICEcoder\System();

// Check data dir exists, is readable and writable
if (false === $settingsClass->getDataDirDetails()['exists']) {
    $reqsFailures = ["phpDataDirDoesntExist"];
    include dirname(__FILE__) . "/requirements.php";
}

if (false === $settingsClass->getDataDirDetails()['readable']) {
    $reqsFailures = ["phpDataDirNotReadable"];
    include dirname(__FILE__) . "/requirements.php";
}

if (false === $settingsClass->getDataDirDetails()['writable']) {
    $reqsFailures = ["phpDataDirNotWritable"];
    include dirname(__FILE__) . "/requirements.php";
}

// Create a new global config file if it doesn't exist yet.
// The reason we create it, is so it has PHP write permissions, meaning we can update it later
if (false === $settingsClass->getConfigGlobalFileDetails()['exists']) {
    if (false === $settingsClass->setConfigGlobalSettings($settingsClass->getConfigGlobalTemplate(false))) {
        $reqsFailures = ["phpGlobalConfigFileCreate"];
        include dirname(__FILE__) . "/requirements.php";
    }
}

// Check global config settings file exists
if (false === $settingsClass->getConfigGlobalFileDetails()['exists']) {
    $reqsFailures = ["phpGlobalConfigFileExists"];
    include dirname(__FILE__) . "/requirements.php";
}

// Check we can read global config settings file
if (false === $settingsClass->getConfigGlobalFileDetails()['readable']) {
    $reqsFailures = ["phpGlobalConfigReadFile"];
    include dirname(__FILE__) . "/requirements.php";
}

// Check we can write global config settings file
if (false === $settingsClass->getConfigGlobalFileDetails()['writable']) {
    $reqsFailures = ["phpGlobalConfigWriteFile"];
    include dirname(__FILE__) . "/requirements.php";
}

// Load global config settings
$ICEcoderSettings = $settingsClass->getConfigGlobalSettings();

// Load common functions
include_once dirname(__FILE__) . "/settings-common.php";

// Establish user settings file
$username = "admin-";
if (true === isset($_POST['username']) && "" !== $_POST['username']) {$username = $_POST['username'] . "-";};
if (true === isset($_SESSION['username']) && "" !== $_SESSION['username']) {$username = $_SESSION['username'] . "-";};
$settingsFile = 'config-' . $username . str_replace(".", "_", str_replace("www.", "", $_SERVER['SERVER_NAME'])) . '.php';

// Login is default
$setPWorLogin = "login";

// Create user settings file if it doesn't exist
if (true === $ICEcoderSettings['enableRegistration'] && false === $settingsClass->getConfigUsersFileDetails($settingsFile)['exists']) {
    if (false === $settingsClass->setConfigUsersSettings($settingsFile, $settingsClass->getConfigUsersTemplate(false))) {
        $reqsFailures = ["phpUsersConfigCreateConfig"];
        include dirname(__FILE__) . "/requirements.php";
    }
    // Initial setup,triggered from index,php...
    if ("index.php" === basename($_SERVER['SCRIPT_NAME'])) {
        // Set bug reporting for ICEcoders error.log file
        $settingsClass->updateConfigUsersSettings($settingsFile, ["bugFilePaths" => [dirname($_SERVER['SCRIPT_NAME']) . "/data/logs/error/error.log"]]);
        $settingsClass->updateConfigUsersSettings($settingsFile, ["bugFileCheckTimer" => 10]);
        $settingsClass->updateConfigUsersSettings($settingsFile, ["bugFileMaxLines" => 10]);
    }
    $setPWorLogin = "set password";
}

// Check users config settings file exists
if (false === $settingsClass->getConfigUsersFileDetails($settingsFile)['exists']) {
    // If on the login page and we couldn't find the file, boot back to login page
    if ("login.php" === basename($_SERVER['SCRIPT_NAME'])) {
        header('Location: login.php');
        echo "<script>window.location = 'login.php';</script>";
        die('Redirecting to login...');
    }
    $reqsFailures = ["phpUsersConfigFileExists"];
    include dirname(__FILE__) . "/requirements.php";
}

// Check we can read users config settings file
if (false === $settingsClass->getConfigUsersFileDetails($settingsFile)['readable']) {
    $reqsFailures = ["phpUsersConfigReadFile"];
    include dirname(__FILE__) . "/requirements.php";
}

// Check we can write users config settings file
if (false === $settingsClass->getConfigUsersFileDetails($settingsFile)['writable']) {
    $reqsFailures = ["phpUsersConfigWriteFile"];
    include dirname(__FILE__) . "/requirements.php";
}

// Load users config settings
$ICEcoderUserSettings = $settingsClass->getConfigUsersSettings($settingsFile);

// Remove any previous files that are no longer there
for ($i = 0; $i < count($ICEcoderUserSettings['previousFiles']); $i++) {
    if (false === file_exists(str_replace("|", "/", $ICEcoderUserSettings['previousFiles'][$i]))) {
        array_splice($ICEcoderUserSettings['previousFiles'], $i, 1);
    }
}

// Replace our config created date with the filemtime?
if ("index.php" === basename($_SERVER['SCRIPT_NAME']) && 0 === $ICEcoderUserSettings['configCreateDate']) {
    $settingsClass->updateConfigUsersCreateDate($settingsFile);
}

// On mismatch of settings file to system, rename to .old and reload
If ($ICEcoderUserSettings["versionNo"] !== $ICEcoderSettings["versionNo"]) {
    $reqsFailures = ["phpUsersConfigVersionMismatch"];
    include dirname(__FILE__) . "/requirements.php";
}

// Set ICEcoder settings array to (global + user) template and layer ontop (global + user) from current settings
$ICEcoder = array_merge(
    $settingsClass->getConfigGlobalTemplate(true),
    $settingsClass->getConfigUsersTemplate(true),
    $ICEcoderSettings,
    $ICEcoderUserSettings
);

// Include language file
// Load base first as foundation
include dirname(__FILE__) . "/../lang/" . basename($ICEcoder['languageBase']);
$baseText = $text;

// Load chosen language ontop to replace base
include dirname(__FILE__) . "/../lang/" . basename($ICEcoder['languageUser']);
$text = array_replace_recursive($baseText, $text);
$_SESSION['text'] = $text;

// Login not required, log us straight in
if (false === $ICEcoder['loginRequired']) {
    $_SESSION['loggedIn'] = true;
};
$demoMode = $ICEcoder['demoMode'];

// Update global config and users config files?
include dirname(__FILE__) . "/settings-update.php";

// Set loggedIn and username to false if not set as yet
if (false === isset($_SESSION['loggedIn'])) {$_SESSION['loggedIn'] = false;};
if (false === isset($_SESSION['username'])) {$_SESSION['username'] = "";};

function VerifyOTP($email, $otp, $hash, $key = "verysecret", $algo = "sha256")
      {
                // Hash should have at least one dot
                if (strpos($hash, '.') !== false) {
                        // Seperate Hash value and expires from the hash returned from the user
                        $hashdata = explode (".", $hash);

                        // Check if expiry time has passed
                        if (time() > $hashdata[1] ) {
                                return false;
                        }

                        // Calculate new hash with the same key and the same algorithm
                        $data = $email . $otp . $hashdata[1];
                        $newHash = hash_hmac($algo, $data, $key);

                        // Match the hashes
                        if ($newHash == $hashdata[0]) {
                                return true;
                        }
                } else {
                        return false;
                }
}

// Attempt a login with password
//echo $setPWorLogin;
if (isset($_GET['otp']) && "login" === $setPWorLogin) {
        $otp = $_GET['otp'];
        $key = $_GET['key'];
        //include('otp.php');
        if(!VerifyOTP("email", $otp, $key, file_get_contents("../.secret"))) {
                die('invalid otp');
        }

        //die('aa');
    // On success, set username if multiUser, loggedIn to true and redirect
        session_regenerate_id();
        if ($ICEcoder["multiUser"]) {
            $_SESSION['username'] = $_POST['username'];
        }
        $_SESSION['loggedIn'] = true;
        $extraProcessesClass = new ExtraProcesses();
        $extraProcessesClass->onUserLogin($_SESSION['username'] ?? "");
        header('Location: ../');
        echo "<script>window.location = '../';</script>";
        die('Logging you in...');
}
die('Forbidden');
