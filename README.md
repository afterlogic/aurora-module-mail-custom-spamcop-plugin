# Aurora MailCustomSpamCopPlugin module

# Development
This repository has a pre-commit hook. To make it work you need to configure git to use the particular hooks folder.

`git config --local core.hooksPath .githooks/`

# License
This module is licensed under AGPLv3 license if free version of the product is used or Afterlogic Software License if commercial version of the product was purchased.

# Getting started
SpamCop is a module that can be used to filter spam messages. It adds a sieve rule that invokes a script that checks if the message is spam. The filtering is based on checking if the recipient exists in user's contact list or if there are any other recipients. To make SpamCop filter work, you need to disable the global sieve rule that moves messages to the Spam folder. When a user enables SpamCop in their settings, a per-user sieve rule is added.

You also need to enable the vnd.dovecot.execute module in the sieve configuration. For more information, please follow the link: [https://doc.dovecot.org/configuration_manual/sieve/plugins/extprograms/]

Once the sieve is configured, place the `/Scripts/filter-spamcop.php` script in the `sieve_execute_bin_dir`. And fill up the database settings within the script.