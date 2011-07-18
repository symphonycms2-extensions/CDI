# Continuous Database Integration

* Version: 0.1.0
* Author: Remie Bolte <http://github.com/remie>
* Build Date: 2011-07-17
* Requirements: Symphony 2.2.1, requires a small modification to class.mysql.php (see below)

Special thanks go out to Nick Dunn <http://github.com/nickdunn/> and Richard Warrender <http://github.com/rwarrender>. 
Part of the code comes from their DB_Sync <https://github.com/nickdunn/db_sync> extension. 
Future patches to their extension will be closely monitored and implemented to increase the stability of this extension.

## Installation

1. Download the CDI extension and upload the 'cdi' folder to the 'extensions' folder of all your Symphony instances on all your environments.
2. Enable the extension by selecting "Continuous Database Integration" in the list and choose Enable from the with-selected menu, then click Apply.
3. Modify the `query()` function in `symphony/lib/toolkit/class.mysql.php` adding the lines between `// Start database logger` and `// End database logger` from the `class.mysql.php.txt` file included with this extension. Place these before the start of the Profile performance logging so it doesn't interfere with performance monitoring and regular query execution.

## Warning

Since this extension requires a core file modification, changes you make to the MySQL class will be lost when you upgrade Symphony. 
Remember to add in the logging call back into `class.mysql.php` if you update Symphony!

The queries are stored in a folder named `cdi` in your `/manifest` folder. This is unsecured, and therefore I strongly advise that you 
alter you .htaccess file to prevent your webserver from exposing these files.

## Disclaimer

Although it is designed to assist you with the entire release chain of development, test, acceptance and production, I cannot guarantee that it is stable. 
Be sure that you have executed the database updates on all environments before going to production. To avoid the risk of database integrity issues, be sure
to create a backup of your data in production, and only run the upgrade in maintenance mode. This allows you to revert any damage that was caused by this extension.

## Version History

### 0.1.0
* initial release of this extension, waiting impatiently for your feedback!