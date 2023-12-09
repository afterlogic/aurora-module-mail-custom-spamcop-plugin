#!/bin/php
<?php
if (PHP_SAPI !== 'cli') {
    exit;
}

$DIR = __DIR__ . '/';

include 'config.php';

$DATE = date("Y-m-d");
$LOG_FILE = '/opt/afterlogic/var/log/seive-script-log-' . $DATE . '.log';

$colors = (object)['no' => "\033[0m",'red' => "\033[1;31m",'green' => "\033[1;32m",'yellow' => "\033[1;33m",'bg_red' => "\033[1;41m",'bg_green' => "\033[1;42m",'bg_yellow' => "\033[1;43m"];

$bDebug = false;

if ($bDebug) {
    $SENDER = '';
    $RECIPIENT = '';
    $sMessage = file_get_contents($DIR . '');
    $aAccountParams = array(
        'LowerBoundary' => 3,
        'UpperBoundary' => 4.5,
        'AllowDomainList' => array(),
    );
} else {
    // HOME, USER, SENDER, RECIPIENT, ORIG_RECIPIENT
    $SENDER = getenv('SENDER');
    $RECIPIENT = getenv('RECIPIENT');
    $sMessage = file_get_contents('php://stdin');
    $aAccountParams = \json_decode(\base64_decode($argv[1]), true);
}

// Define a log function
$logger = function ($label = '', ...$args) use ($colors, $bDebug, $LOG_FILE) {
    // return
    if ($bDebug) {
        printf("%s %s\n", $label, $colors->red . implode(" ", $args) . $colors->no);
    }
    $TIME = date("h:i:s") . "." . substr((string)microtime(), 2, 3);
    $text = '[' . $TIME . '] ' . $label . ' ' . implode(" ", $args) . "\n";
    file_put_contents($LOG_FILE, $text, FILE_APPEND);
};

$bExitStatus = true;

/* === Define the patterns and variables === */
$sEmailPattern = "\b[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,6}\b";
$sLinePattern = "^\s*(?:To:|Cc:).+" . $sEmailPattern;
$sMessageIdPattern = "^(?:\s*message-id).+$";
$sSpamScorePattern = "^(?:\s*x-spam-score):\s*(.+)$";

// Get the lines with emails
preg_match_all("/$sLinePattern/im", $sMessage, $sEmailLines);
// Get the message id line
preg_match("/$sMessageIdPattern/im", $sMessage, $sMessageIdLine);
// Get spam score
preg_match_all("/$sSpamScorePattern/im", $sMessage, $sSpamScoreMatch);

/* === Output debug info ==== */
$logger("=== Process incomming message ===");
$logger("Recipient:", $RECIPIENT);
$logger("Message id:", $sMessageIdLine[0]);

/* === getting account setting ==== */
if (!($USER && $PASS && $DATABASE)) {
    $logger("", "The script is not configured properly!");
    exit(0);
}
$mysqli = new mysqli("127.0.0.1", $USER, $PASS, $DATABASE);

if ($mysqli->connect_errno) {
    $logger("Failed to connect to MySQL:", $mysqli->connect_error);
    exit(0);
}

// $sAccountParamsSQL = "SELECT a.Properties FROM mail_accounts AS a
// LEFT JOIN core_users AS u ON u.Id = a.IdUser
// WHERE u.PublicId = '$RECIPIENT'";

// if ($bDebug) {
//     $logger("Account Params SQL: \n", $sAccountParamsSQL);
// }

// $oAccountParamsResult = $mysqli->query($sAccountParamsSQL);
// $oAccountParamsResult = $oAccountParamsResult->fetch_assoc();
// $oAccountParamsAll = isset($oAccountParamsResult['Properties']) ? \json_decode($oAccountParamsResult['Properties']) : [];
// $aAccountParams = array();
// foreach($oAccountParamsAll as $key => $value) {
//     $newKey = str_replace('MailCustomSpamCopPlugin::', '', $key);
//     if ($newKey !== $key) {
//         $aAccountParams[$newKey] = $value;
//     }
// }

if (!$aAccountParams
    || !isset($aAccountParams['LowerBoundary'])
    || !isset($aAccountParams['UpperBoundary'])
    || !isset($aAccountParams['AllowDomainList'])
) {
    $logger("No params found for mail account:", $RECIPIENT);
    exit(0);
}

/* === Checking if user's address is specified as recipient  ==== */
$bRecipientExists = false;
$sRecipientsSQL = "'$SENDER'";
// Loop through the lines with emails
foreach ($sEmailLines[0] as $sEmailLine) {
    $logger("Found header:", $sEmailLine);
    // Extract the emails from the line
    preg_match_all("/$sEmailPattern/i", $sEmailLine, $sEmails);

    // Loop through the emails
    foreach ($sEmails[0] as $sEmail) {
        $logger("       email: ", $sEmail);
        $sRecipientsSQL .= ",'$sEmail'";
        if ($sEmail === $RECIPIENT) {
            $bRecipientExists = true;
        }
    }
    $logger();
}

$logger("Recipient exists in headers:", $bRecipientExists ? 'yes' : 'no');
$logger();

/* === Recipient is not specified correctly, then we need to check the contacts and sender's domain === */
if (!$bRecipientExists) {
    /* === Getting spam scores and boundary ==== */
    $iLowerBoundary = $aAccountParams['LowerBoundary'] ? (float) $aAccountParams['LowerBoundary'] : 3;
    $iUpperBoundary = $aAccountParams['UpperBoundary'] ? (float) $aAccountParams['UpperBoundary'] : 5;
    $iSpamScore = isset($sSpamScoreMatch[1]) ? $sSpamScoreMatch[1][0] : 0;

    // detect the value type of Spam Score
    $iSpamScore = strpos($iSpamScore, '.') ? (float) $iSpamScore : (int) $iSpamScore;

    // if spam score is above 10 most likely its a float number with missing dot
    // les't correct this
    if (abs($iSpamScore) >= 10) {
        $logger("Spam Score will be corrected:", $iSpamScore);
        $iSpamScore = round($iSpamScore / 10, 1);
    }

    $logger("Spam Score:", $iSpamScore);
    $logger("Boundary:", '"' . $iLowerBoundary . '"-"' . $iUpperBoundary . '"');
    $logger();

    /* === Entering the case if spam score is above the upper boundary === */
    if ($iSpamScore > $iUpperBoundary) {
        $bExitStatus = false;
        $logger("", "Message blocked!");

        /* === Spam score is inbetween lower and upper boundary === */
    } elseif ($iSpamScore >= $iLowerBoundary && $iSpamScore <= $iUpperBoundary) {

        $logger("Contacts for checking:", $sRecipientsSQL);
        // Define the SQL query for contacts
        $sContactsSQL = "" .
"SELECT COUNT(*) AS count FROM contacts AS c
LEFT JOIN core_users AS u ON u.Id = c.IdUser
WHERE (c.PersonalEmail IN ($sRecipientsSQL) OR c.BusinessEmail IN ($sRecipientsSQL) OR c.OtherEmail IN ($sRecipientsSQL)) 
AND u.PublicId = '$RECIPIENT'";

        if ($bDebug) {
            $logger("Contacts SQL: \n", $sContactsSQL);
        }

        // Execute the query and get the contacts count
        $oContactsCountResult = $mysqli->query($sContactsSQL);
        $iContactsCount = (int) $oContactsCountResult->fetch_assoc()['count'];

        $logger("Contacts found:", $iContactsCount);
        $logger();

        if ($iContactsCount === 0) {
            // Get the sender domain
            preg_match("/(?<=@)[A-Za-z0-9.-]+\.[A-Za-z]{2,6}$/", $SENDER, $sSenderDomain);

            $logger("Domain for checking:", $sSenderDomain[0]);
            // Define the SQL query for domains
            //             $sDomainsSQL = "" .
            // "SELECT * FROM mail_accounts AS d
            // LEFT JOIN core_users AS u ON u.Id = d.IdUser
            // WHERE u.PublicId = '$RECIPIENT'
            // AND JSON_CONTAINS(d.Properties, '\"$sSenderDomain[0]\"', '$.MailCustomSpamCopPlugin::AllowDomainList')
            // OR JSON_CONTAINS(d.Properties, '" . end(explode(".", $sSenderDomain[0])) . "', '$.MailCustomSpamCopPlugin::AllowDomainList')";

            //             if ($bDebug) {
            //                 $logger("Domains SQL: \n", $sDomainsSQL);
            //             }

            // Execute the query and get the count
            // $oDomainsCountResult = $mysqli->query($sDomainsSQL);
            // $iDomainsCount = (int) $oDomainsCountResult->fetch_assoc()['count'];

            $iDomainsCount = 0;
            foreach ($aAccountParams['AllowDomainList'] as $sDomain) {
                if ($sSenderDomain[0] === $sDomain || end(explode(".", $sSenderDomain[0])) === $sDomain) {
                    $iDomainsCount++;
                }
            }
            $logger("Domains found:", $iDomainsCount);
            $logger();

            if ($iDomainsCount === 0) {
                $bExitStatus = false;
                $logger("", "Message blocked!");
            }
        }
    }
}

/* === Passing the message because nothing above bloked it === */
$logger("Message passed!");

$logger("=== END Process incomming message ===");
$logger();

exit($bExitStatus ? 0 : 1);
