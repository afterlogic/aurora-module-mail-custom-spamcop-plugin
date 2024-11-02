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
        'AllowDomainList' => array(''),
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

$getMessageSpamScore = function ($sMessage) use ($logger) {
    // Get spam score
    preg_match_all("/^(?:\s*x-spam-score):\s*(.+)$/im", $sMessage, $sSpamScoreMatch);

    $iSpamScore = 0;
    
    if (isset($sSpamScoreMatch[1])) {
        $iSpamScore = $sSpamScoreMatch[1][0];

        // detect the value type of Spam Score
        $iSpamScore = strpos($iSpamScore, '.') ? (float) $iSpamScore : (int) $iSpamScore;
    
        // if spam score is above 10 most likely its a float number with missing dot
        // les't correct this
        if (abs($iSpamScore) >= 10) {
            $logger("Spam Score will be corrected:", $iSpamScore);
            $iSpamScore = round($iSpamScore / 10, 1);
        }
    }

    return $iSpamScore;
};

$bExitStatus = true;

/* === Define the patterns and variables === */
$sEmailPattern = "\b[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,6}\b";
$sLinePattern = "^\s*(?:To:|Cc:).+" . $sEmailPattern;
$sMessageIdPattern = "^(?:\s*message-id).+$";

// Get the lines with emails
preg_match_all("/$sLinePattern/im", $sMessage, $sEmailLines);
// Get the message id line
preg_match("/$sMessageIdPattern/im", $sMessage, $sMessageIdLine);

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

/* === Getting spam scores and boundary ==== */
$iLowerBoundary = $aAccountParams['LowerBoundary'] ? (float) $aAccountParams['LowerBoundary'] : 3;
$iUpperBoundary = $aAccountParams['UpperBoundary'] ? (float) $aAccountParams['UpperBoundary'] : 5;

$iSpamScore = $getMessageSpamScore($sMessage);

$logger("Spam Score:", $iSpamScore);
$logger("Boundary:", '"' . $iLowerBoundary . '"-"' . $iUpperBoundary . '"');
$logger();

if ($bRecipientExists) { //IF To-recipient IN (own mail-adresses or aliases)

    if ($iSpamScore > $iUpperBoundary)  {// IF mail-spam-score > UPPER_LIMIT
        // MARK_AS_SPAM
        $bExitStatus = false;
    } else {
        // DELIVER_MAIL
    }
} else {
    // do we at least know sender or recipient?

    $logger("Contacts for checking:", $sRecipientsSQL);
    // Define the SQL query for contacts
    $sContactsSQL = "" .
    "SELECT COUNT(*) AS count FROM " . $PREFIX . "contacts_cards AS c
    WHERE (c.PersonalEmail IN ($sRecipientsSQL) OR c.BusinessEmail IN ($sRecipientsSQL) OR c.OtherEmail IN ($sRecipientsSQL)) 
    AND c.AddressBookId IN (SELECT id FROM " . $PREFIX . "adav_addressbooks WHERE principaluri = 'principals/" . $RECIPIENT . "')";

    if ($bDebug) {
        $logger("Contacts SQL: \n", $sContactsSQL);
    }

    // Execute the query and get the contacts count
    $oContactsCountResult = $mysqli->query($sContactsSQL);
    $iContactsCount = (int) $oContactsCountResult->fetch_assoc()['count'];

    $logger("Contacts found:", $iContactsCount);
    $logger();

    if ($iContactsCount > 0) { // IF sender IN (known adresses) OR To-recipient IN (known adresses) {
        
        if ($iSpamScore > $iUpperBoundary) { //IF mail-spam-score > UPPER_LIMIT
            // MARK_AS_SPAM
            $bExitStatus = false;
        } else {
            // DELIVER_MAIL
        }
    } else {
        preg_match("/(?<=@)[A-Za-z0-9.-]+\.[A-Za-z]{2,6}$/", $SENDER, $sSenderDomain);

        $logger("Domain for checking:", $sSenderDomain[0]);

        $iDomainsCount = 0;
        foreach ($aAccountParams['AllowDomainList'] as $sDomain) {
            if ($sSenderDomain[0] === $sDomain || end(explode(".", $sSenderDomain[0])) === $sDomain) {
                $iDomainsCount++;
            }
        }
        $logger("Domains found:", $iDomainsCount);
        $logger();

        if ($iDomainsCount > 0) { // IF sender_domain IN (allowed domains)

            if ($iSpamScore > $iUpperBoundary) { // IF mail-spam-score > UPPER_LIMIT
                // MARK_AS_SPAM
                $bExitStatus = false;
            } else {
                // DELIVER_MAIL
            }
        } else {
            // if the spam score is still so good we could allow it, default should be 1
		    // but setting it to -5 will disable the last check and we burn the mail anyways

            if ($iSpamScore < $iLowerBoundary) { // IF mail-spam-score < LOWER_LIMIT
                // DELIVER_MAIL
            } else {
                // MARK_AS_SPAM
                $bExitStatus = false;
            }
        }
    }
}
if (!$bExitStatus) {
    $logger("", "Message blocked!");
} else {
    /* === Passing the message because nothing above bloked it === */
    $logger("Message passed!");
}

$logger("=== END Process incomming message ===");
$logger();

exit($bExitStatus ? 0 : 1);
