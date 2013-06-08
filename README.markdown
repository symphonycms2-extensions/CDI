# Continuous Database Integration

* Version: 1.1.2
* Author: Remie Bolte <http://github.com/remie>
* Build Date: 2012-05-31
* Requirements: Symphony 2.3

Special thanks go out to Nick Dunn <http://github.com/nickdunn/> and Richard Warrender <http://github.com/rwarrender>. 
Part of the code comes from their DB_Sync <https://github.com/nickdunn/db_sync> extension.
Future patches to their extension will be closely monitored and implemented to increase the stability of this extension.

Huib Keemink <http://github.com/creativedutchmen/> helped out by adding token authentication to the update process (issue #32).

## Installation

1. Download the CDI extension and upload the 'cdi' folder to the 'extensions' folder of all your Symphony instances on all your environments.
2. Enable the extension by selecting "Continuous Database Integration" in the list and choose Enable from the with-selected menu, then click Apply.

## Warning

The queries are stored in a folder named `cdi` in your `/manifest` folder. This is unsecured, and therefore I strongly advise that you 
alter you .htaccess file to prevent your webserver from exposing these files.

## Disclaimer

Although it is designed to assist you with the entire release chain of development, test, acceptance and production, I cannot guarantee that it is stable. 
Be sure that you have executed the database updates on all environments before going to production. To avoid the risk of database integrity issues, be sure
to create a backup of your data in production, and only run the upgrade in maintenance mode. This allows you to revert any damage that was caused by this extension.

## Roadmap

The release milestones are listed on GitHub: <https://github.com/remie/CDI/issues/milestones>

A list of all open issues can be found here: <https://github.com/remie/CDI/issues>

## Version History

### 1.1.2

# Issue #37: Update DB Sync version number

# Issue #38: Dump DB version supported

### 1.1.1

* Issue #39: Lowercase CDI

### 1.1.0

* Issue #32: Implement authentication for CDI update on slave

* Issue #31: Symphony 2.3 compatibility

* Issue #21: Implement error handling for AJAX requests

* Issue #4: Improve error logging - part 2 "Let them know"

* Issue #3: Improve error logging - part 1 "The black holes"

### 1.0.1

* Issue #23: Incompatibility with anti_brute_force extension

* Issue #5: Switching CDI modes should stick with defaults

* Issue #19: Add option to disable back-end navigation items for slave instances

### 1.0.0
* Add warning to preferences screen for updating class.mysql.php

* Implement loading indicators for AJAX requests

* Move InstanceMode to leftColumn after Clear action

* Move Restore to footer after Clear action

* Move export to footer after Clear action

* Add download button for backups

* Add download button for CDI / DBSync file

* Symphony.WEBSITE is deprecated

### 0.4.0
* Added automatic backup of current database before executing CDI queries using https://github.com/nils-werner/dump_db

* Added automatic restore of database backup upon query execution errors (including switch to maintenance mode) 

* Added support for manual backup & restore of current database using https://github.com/nils-werner/dump_db

* UI tweaks and Ajaxification of the preferences implementation

### 0.3.0
* Support the same features as the Database Synchroniser extension: a single db_sync.sql file that logs all queries for manual propagating changes to other instances.

* Aggregate all queries and save them to a single file (using JSON or XML serialization)
  WARNING: this feature breaks backwards compatibility with version 0.2.0

### 0.2.0
* Add rollback support in case of SQL execution errors

* Add status information of the CDI log on the preferences screen

* Add a "clear CDI log" button from the preferences screen

### 0.1.0
* initial release of this extension, waiting impatiently for your feedback!